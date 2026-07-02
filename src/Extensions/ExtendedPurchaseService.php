<?php

namespace ShopExtensions;

use SilverShop\Model\Order;
use SilverStripe\Control\Director;
use SilverStripe\Core\Extension;
use SilverStripe\Omnipay\Service\PaymentService;
use SilverStripe\SiteConfig\SiteConfig;
use TractorCow\Fluent\Model\Locale;


/**
 * Extension on {@see \SilverStripe\Omnipay\Service\PurchaseService} that enriches the
 * gateway request data before a payment is initiated.
 *
 * It injects several fields that the German-market gateways (Mollie, PayPal) require or
 * benefit from: a human-readable order description, a service/terms URL, the storefront
 * language, and an optional custom payment method chosen by the customer. It also strips
 * the reserved "PaymentMethod" key so repayments do not clash with Omnipay's own params.
 *
 * Registered against SilverStripe\Omnipay\Service\PurchaseService in
 * _config/shopextensions.yml.
 *
 * @property \SilverStripe\Omnipay\Service\PurchaseService $owner
 */
class ExtendedPurchaseService extends Extension
{
    /**
     * Populates the outgoing gateway data with information required by the payment
     * providers before the purchase request is sent.
     *
     * Adds the order description, the terms-page (or site root) service URL, an optional
     * custom payment method from the session, and the current locale language, then
     * removes the reserved "PaymentMethod" key to keep repayments valid.
     *
     * @param array $data Gateway request data, passed by reference and mutated in place.
     */
    public function onBeforePurchase(array &$data)
    {
        $payment = $this->owner->getPayment();

        /** @var Order $order */
        $order = $payment->Order();

        // Mollie/PayPal require a description for the payment statement
        $data['description'] = $order->getDescription();
        //die($data['description']);

        // Link the customer back to the terms page (falls back to the site root)
        $termsPage = SiteConfig::current_site_config()->TermsPage();

        $serviceURL = $termsPage->exists()
            ? $termsPage->AbsoluteLink()
            : Director::absoluteURL('/');

        $data['serviceUrl'] = $serviceURL;

        // Pass the two-letter language code so the gateway shows the localised checkout
        if(class_exists('TractorCow\Fluent\Model\Locale')){
            if(Locale::getCurrentLocale()->Locale){
                $data['language'] = substr(Locale::getCurrentLocale()->Locale,0,2);

            }
        }

        // Fix reserved params in repayment
        unset($data['PaymentMethod']);

        // Forward the customer-selected sub-method (e.g. a specific Mollie method) so the
        // provider can skip its own selection screen. Read from the order relation persisted by
        // PaymentMethodComponent — NOT from a session side-channel as before. The correct
        // omnipay-mollie key is 'paymentMethod' (→ Mollie 'method'); the old code set the
        // wrong key ('paymentType') from $_SESSION, so nothing was ever forwarded.
        if ($order->hasMethod('UsedPaymentOption')) {
            $option = $order->UsedPaymentOption();
            if ($option && $option->exists() && $option->PaymentMethod) {
                $data['paymentMethod'] = $option->PaymentMethod;
            }
        }
    }
}
