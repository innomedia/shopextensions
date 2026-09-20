<?php

namespace ShopExtensions;

use ShopExtensions\Checkout\ShopCheckoutComponentConfig;
use ShopExtensions\Security\RequestThrottle;
use SilverShop\Cart\ShoppingCart;
use SilverShop\Forms\PaymentForm;
use SilverShop\Model\Order;
use SilverShop\ShopUserInfo;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Omnipay\Model\Message\GatewayErrorMessage;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\SecurityToken;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Extension on {@see \SilverShop\Page\CheckoutPageController} that rebuilds the checkout order form
 * and adds what silvershop/core is missing for a one-page checkout.
 *
 * Always on:
 *  - the form is built from the configured checkout component config (by default
 *    {@see ShopCheckoutComponentConfig}, which drops the shipping address component for orders that
 *    need no shipping), rendered with the "ExtendedCheckoutForm" template, CompositeFields flattened
 *    into one FieldList, success link set (fixes zero-total order redirects);
 *  - `disable_cache` (bugfix, switchable): the checkout is session bound and must never be cached;
 *  - `$CheckoutPaymentNotice`: friendly notice after a failed or cancelled payment. omnipay 6 keeps
 *    the kind of message in PaymentMessage.Type and dropped the GatewayErrorMessage subclasses, so
 *    SilverShop's own PaymentErrorMessage() never finds one. Returns data; the template decides how
 *    it looks (`payment_notice: false` switches it off);
 *  - `$CheckoutErrorSummary`: every field that carries a validation message as {ID, Title, Message},
 *    for an error list at the top of the form (accessibility).
 *
 * Opt-in (see _config/checkout.yml.example):
 *  - `restore_unchecked_checkboxes`: core reloads submitted data with MERGE_IGNORE_FALSEISH, so an
 *    UNticked checkbox comes back ticked (its default) after a failed submit;
 *  - `hide_raw_gateway_messages`: a form message without any field message comes from the order
 *    processor or the provider (Mollie answers with ~900 characters of JSON) – replaced by a
 *    readable sentence;
 *  - `success_link_to_confirmation`: always land on <checkout>/order/<ID> (Order::Link() sends
 *    logged-in customers into the account, without the thank-you text and the bank details);
 *  - `terms_label_with_legal_links`: terms checkbox label with links to terms/withdrawal/privacy
 *    (needs {@see \ShopExtensions\Checkout\SiteConfigCheckoutExtension});
 *  - `enable_summary_endpoint`: POST <checkout>/summary with a country, recalculates the cart and
 *    answers {html, totalNice, isEmpty, country} as JSON – for a checkout that updates the shipping
 *    costs when the country changes. CSRF protected and rate limited.
 *
 * Hooks: updateSummaryCountryAllowed(&$allowed, $country, $order), onSummaryCountry($country, $order),
 * updateSummaryResponseData(&$data, $order).
 *
 * Registered against SilverShop\Page\CheckoutPageController in _config/shopextensions.yml.
 *
 * @property \SilverShop\Page\CheckoutPageController $owner
 */
class CheckoutPageControllerExtension extends Extension
{
    private static $allowed_actions = [
        'summary',
    ];

    /**
     * Checkout component config used to build the order form.
     *
     * Projects that need extra checkout fields subclass {@see ShopCheckoutComponentConfig}
     * (overriding `addProjectComponents()`) and point this config at their own class, so no
     * project-specific component ever has to live inside this module. To REPLACE a single component,
     * use ShopCheckoutComponentConfig.component_classes instead of a subclass.
     *
     * @config
     * @var string
     */
    private static $checkout_component_config_class = ShopCheckoutComponentConfig::class;

    /**
     * Never cache the checkout (bugfix; the page is session bound).
     *
     * @config
     * @var bool
     */
    private static $disable_cache = true;

    /**
     * Offer $CheckoutPaymentNotice after a failed/cancelled payment.
     *
     * @config
     * @var bool
     */
    private static $payment_notice = true;

    /**
     * Replace a bare provider/processor form message with a generic sentence.
     *
     * @config
     * @var bool
     */
    private static $hide_raw_gateway_messages = false;

    /**
     * Restore unticked checkboxes after a failed submit (core MERGE_IGNORE_FALSEISH bug).
     *
     * @config
     * @var bool
     */
    private static $restore_unchecked_checkboxes = false;

    /**
     * Always redirect to <checkout>/order/<ID> after a successful order.
     *
     * @config
     * @var bool
     */
    private static $success_link_to_confirmation = false;

    /**
     * Build the terms checkbox label with links to the legal pages.
     *
     * @config
     * @var bool
     */
    private static $terms_label_with_legal_links = false;

    /**
     * Offer POST <checkout>/summary (see class docs).
     *
     * @config
     * @var bool
     */
    private static $enable_summary_endpoint = false;

    /**
     * Template the summary endpoint renders the order with.
     *
     * @config
     * @var string
     */
    private static $summary_template = 'ShopExtensions/Checkout/CheckoutSummary';

    /**
     * RequestThrottle bucket of the summary endpoint ('' = no limit).
     *
     * @config
     * @var string
     */
    private static $summary_throttle_bucket = 'checkout-summary';

    public const NS_TERMS = 'SilverShop-Checkout-Component-Terms_';

    /**
     * The order form as it was last built, for the lazily computed error summary: a form's session
     * state is cleared once it has been rendered, the field messages stay on the field objects.
     */
    private ?PaymentForm $checkoutForm = null;

    private ?ArrayList $errorSummary = null;

    protected function onAfterInit(): void
    {
        if ($this->owner->config()->get('disable_cache')) {
            HTTPCacheControlMiddleware::singleton()->disableCache(true);
        }
    }

    /**
     * Replaces the default checkout order form with a customised version.
     *
     * Builds a new PaymentForm from the custom component config, keeps the "Submit Payment"
     * action when the config provides on-site payment data, sets the success/return link,
     * applies the ExtendedCheckoutForm template, flattens any CompositeFields, and hands the
     * result back through the by-reference $form argument.
     *
     * @param PaymentForm $form The order form to replace, passed by reference.
     */
    public function updateOrderForm(PaymentForm &$form)
    {
        // Replace the form with the configured checkout component config
        $configClass = Config::inst()->get(static::class, 'checkout_component_config_class')
            ?: ShopCheckoutComponentConfig::class;
        $cart = ShoppingCart::curr();
        $config = Injector::inst()->create($configClass, $cart);
        $newForm = PaymentForm::create($this->owner, 'OrderForm', $config);

        // Check if we need to change the button label for onsite payment
        if ($config->hasComponentWithPaymentData()) {
            $newForm->setActions(
                FieldList::create(
                    FormAction::create('checkoutSubmit', _t('SilverShop\Page\CheckoutPage.SubmitPayment', 'Submit Payment'))
                )
            );
        }

        // Set success link to order details page (fixes zero-total order redirects)
        $newForm->setSuccessLink($newForm->getOrderProcessor()->getReturnUrl());

        // …or always to the confirmation page (thank you, bank details, next steps).
        if ($this->owner->config()->get('success_link_to_confirmation') && $cart instanceof Order && $cart->ID) {
            $newForm->setSuccessLink(Controller::join_links($this->owner->Link(), 'order', $cart->ID));
        }

        $newForm->Cart = $this->owner->Cart();

        // Apply the template flattening logic
        $newForm->setTemplate('ExtendedCheckoutForm');
        $updatedFields = new FieldList();
        $fields = $newForm->Fields();
        foreach ($fields as $field) {
            switch (get_class($field)) {
                case "SilverStripe\Forms\CompositeField":
                    foreach ($field->getChildren() as $childfield) {
                        $updatedFields->push($childfield);
                    }
                    break;
                default:
                    $updatedFields->push($field);
                    break;
            }
        }
        $newForm->setFields($updatedFields);

        if ($this->owner->config()->get('terms_label_with_legal_links')) {
            $this->applyTermsLabel($newForm);
        }

        if ($this->owner->config()->get('restore_unchecked_checkboxes')) {
            $this->restoreUncheckedCheckboxes($newForm);
        }

        if ($this->owner->config()->get('hide_raw_gateway_messages')) {
            $this->hideRawFormMessage($newForm);
        }

        // Remember the form and drop a stale summary: the summary reads the FIELDS, and other
        // extensions (project labels, holder templates) still run after this one.
        $this->checkoutForm = $newForm;
        $this->errorSummary = null;

        // Replace the form by reference
        $form = $newForm;
    }

    /**
     * Friendly notice when the customer is back from a payment that failed or was cancelled. Provider
     * messages (Mollie: raw JSON) are never shown to the customer.
     */
    public function CheckoutPaymentNotice(): ?ArrayData
    {
        if (!$this->owner->config()->get('payment_notice')) {
            return null;
        }

        $order = ShoppingCart::curr();
        if (!$order instanceof Order || !$order->isInDB()) {
            return null;
        }

        /** @var Payment|null $payment */
        $payment = $order->Payments()->sort(['Created' => 'DESC', 'ID' => 'DESC'])->first();
        if (!$payment || in_array($payment->Status, ['Captured', 'Authorized', 'PendingPurchase', 'PendingAuthorization'], true)) {
            return null;
        }

        $hasError = $payment->Status === 'Void';
        foreach ($payment->Messages() as $message) {
            if ($message instanceof GatewayErrorMessage || str_ends_with((string) $message->Type, 'Error')) {
                $hasError = true;
                break;
            }
        }

        if (!$hasError) {
            return null;
        }

        return ArrayData::create([
            'Title' => _t(self::class . '.PaymentFailedTitle', 'Die Zahlung wurde nicht abgeschlossen.'),
            'Text' => _t(
                self::class . '.PaymentFailedText',
                'Es wurde nichts abgebucht. Ihre Angaben und Ihr Warenkorb sind noch da – wählen Sie eine Zahlart und versuchen Sie es noch einmal.'
            ),
        ]);
    }

    /**
     * Fields with a validation message, for the error list at the top of the form:
     * {ID, Title, Message}. Title prefers the attribute data-summary-label (a short label for the
     * list, e.g. "AGB" instead of the whole checkbox sentence).
     */
    public function CheckoutErrorSummary(): ArrayList
    {
        if ($this->errorSummary instanceof ArrayList) {
            return $this->errorSummary;
        }

        if (!$this->checkoutForm instanceof PaymentForm) {
            // Builds the form – and fills $this->checkoutForm through updateOrderForm().
            $this->owner->OrderForm();
        }

        $summary = ArrayList::create();
        if ($this->checkoutForm instanceof PaymentForm) {
            foreach ($this->checkoutForm->Fields()->dataFields() as $field) {
                if (!$field->getMessage()) {
                    continue;
                }
                $summary->push(ArrayData::create([
                    'ID' => $field->ID(),
                    'Title' => $field->getAttribute('data-summary-label') ?: strip_tags((string) $field->Title()),
                    'Message' => strip_tags((string) $field->getMessage()),
                ]));
            }
        }

        return $this->errorSummary = $summary;
    }

    /**
     * POST <checkout>/summary with `Country` (ISO-2): stores the country for the shipping
     * calculation, recalculates the cart and answers with the rendered summary.
     *
     * Answers: 404 (endpoint off), 405 (not POST), 429 (rate limit), 400 (CSRF), 422 (country not
     * deliverable), otherwise 200 with {isEmpty, html, totalNice, country}.
     */
    public function summary(HTTPRequest $request): HTTPResponse
    {
        HTTPCacheControlMiddleware::singleton()->disableCache(true);

        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'application/json; charset=utf-8');

        if (!$this->owner->config()->get('enable_summary_endpoint')) {
            return $response->setStatusCode(404)->setBody($this->jsonMessage('Not found.'));
        }

        if (!$request->isPOST()) {
            return $response->setStatusCode(405)->addHeader('Allow', 'POST')
                ->setBody($this->jsonMessage(_t(self::class . '.PostOnly', 'Nur POST erlaubt.')));
        }

        $bucket = (string) $this->owner->config()->get('summary_throttle_bucket');
        if ($bucket !== '' && !RequestThrottle::attempt($request, $bucket)) {
            return $response->setStatusCode(429)
                ->addHeader('Retry-After', (string) RequestThrottle::retryAfterSeconds($request, $bucket))
                ->setBody($this->jsonMessage(_t(self::class . '.TooMany', 'Zu viele Anfragen in kurzer Zeit. Bitte versuchen Sie es gleich noch einmal.')));
        }

        if (!SecurityToken::inst()->checkRequest($request)) {
            return $response->setStatusCode(400)
                ->setBody($this->jsonMessage(_t(self::class . '.SessionExpired', 'Ihre Sitzung ist abgelaufen. Bitte laden Sie die Seite neu.')));
        }

        $order = ShoppingCart::curr();
        $country = strtoupper(trim((string) $request->postVar('Country')));

        if ($country !== '') {
            $allowedCountries = SiteConfig::current_site_config()->getCountriesList();
            $allowed = (bool) preg_match('/^[A-Z]{2}$/', $country) && isset($allowedCountries[$country]);
            // Projects add their own rule here, e.g. "no shipping rate for that country".
            $this->owner->extend('updateSummaryCountryAllowed', $allowed, $country, $order);

            if (!$allowed) {
                return $response->setStatusCode(422)
                    ->setBody($this->jsonMessage(_t(self::class . '.CountryNotAllowed', 'In dieses Land liefern wir leider nicht.')));
            }

            ShopUserInfo::singleton()->setLocation(
                array_merge(ShopUserInfo::singleton()->getLocation(), ['Country' => $country])
            );

            // An address saved by an earlier (failed) submit wins in the shipping calculation – keep it
            // in line with the dropdown. Never touch an address-book record of a member.
            if ($order instanceof Order && $order->ShippingAddressID) {
                $address = $order->getShippingAddress();
                if ($address && $address->exists() && !$address->MemberID && $address->Country !== $country) {
                    $address->Country = $country;
                    $address->write();
                }
            }

            $this->owner->extend('onSummaryCountry', $country, $order);
        }

        if (!$order instanceof Order || !$order->Items()->exists()) {
            return $response->setBody((string) json_encode(['isEmpty' => true, 'html' => '', 'totalNice' => '']));
        }

        $order->calculate();

        $data = [
            'isEmpty' => false,
            'html' => (string) $order->renderWith((string) $this->owner->config()->get('summary_template')),
            'totalNice' => $order->hasMethod('TotalNice')
                ? (string) $order->TotalNice()
                : (string) DBField::create_field('Currency', $order->Total())->Nice(),
            'country' => $country,
        ];
        $this->owner->extend('updateSummaryResponseData', $data, $order);

        return $response->setBody((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Terms checkbox label with links to the terms page (SilverShop TermsPage), the withdrawal policy
     * and the privacy policy ({@see \ShopExtensions\Checkout\SiteConfigCheckoutExtension}). Pages that
     * are not set stay plain text.
     */
    public function TermsLabel(): DBHTMLText
    {
        $siteConfig = SiteConfig::current_site_config();
        $link = static function ($page, string $title): string {
            if ($page && $page->exists()) {
                return sprintf(
                    '<a href="%s" target="_blank" rel="noopener">%s</a>',
                    Convert::raw2att($page->Link()),
                    Convert::raw2xml($title)
                );
            }

            return Convert::raw2xml($title);
        };

        $html = _t(
            self::class . '.TermsLabel',
            'Ich habe die {terms} und die {withdrawal} gelesen und akzeptiere sie. Hinweise zur Verarbeitung meiner Daten stehen in der {privacy}.',
            [
                'terms' => $link($siteConfig->TermsPage(), _t(self::class . '.Terms', 'AGB')),
                'withdrawal' => $link(
                    $siteConfig->hasMethod('CheckoutWithdrawalPage') ? $siteConfig->CheckoutWithdrawalPage() : null,
                    _t(self::class . '.Withdrawal', 'Widerrufsbelehrung')
                ),
                'privacy' => $link(
                    $siteConfig->hasMethod('CheckoutPrivacyPage') ? $siteConfig->CheckoutPrivacyPage() : null,
                    _t(self::class . '.Privacy', 'Datenschutzerklärung')
                ),
            ]
        );

        return DBField::create_field('HTMLText', $html);
    }

    protected function applyTermsLabel(PaymentForm $form): void
    {
        if ($terms = $form->Fields()->dataFieldByName(self::NS_TERMS . 'ReadTermsAndConditions')) {
            $terms->setTitle($this->TermsLabel());
        }
    }

    /**
     * CheckoutForm reloads the submitted data with MERGE_IGNORE_FALSEISH – an unticked checkbox would
     * come back ticked (its default). Restore the real state from the session data.
     */
    protected function restoreUncheckedCheckboxes(PaymentForm $form): void
    {
        $request = $this->owner->getRequest();
        $session = $request ? $request->getSession() : null;
        $data = $session ? $session->get(sprintf('FormInfo.%s.data', $form->FormName())) : null;

        if (!is_array($data)) {
            return;
        }

        foreach ($form->Fields()->dataFields() as $field) {
            if ($field instanceof CheckboxField && !array_key_exists($field->getName(), $data)) {
                $field->setValue(false);
            }
        }
    }

    /**
     * A form message without any field message comes from the order processor or the payment provider
     * (Mollie answers with raw JSON). Replace it with a sentence the customer can act on; the payment
     * notice explains the payment case on its own.
     */
    protected function hideRawFormMessage(PaymentForm $form): void
    {
        if (!$form->getMessage()) {
            return;
        }

        foreach ($form->Fields()->dataFields() as $field) {
            if ($field->getMessage()) {
                return;
            }
        }

        $form->clearMessage();
        if (!$this->CheckoutPaymentNotice()) {
            $form->setMessage(
                _t(
                    self::class . '.ProcessingError',
                    'Ihre Bestellung konnte nicht abgeschlossen werden. Bitte versuchen Sie es noch einmal oder wählen Sie eine andere Zahlart.'
                ),
                'bad'
            );
        }
    }

    private function jsonMessage(string $message): string
    {
        return (string) json_encode(['message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
