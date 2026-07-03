<?php
namespace ShopExtensions;

use Dompdf\Dompdf;
use SilverShop\Checkout\OrderEmailNotifier;
use SilverShop\Model\Order;
use SilverShop\Page\AccountPage;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\Debug;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\FieldType\DBCurrency;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Extends the SilverShop Order with everything the German market needs on top of the
 * base module: sequential invoice numbers with a configurable number range, PDF receipt
 * and delivery-slip generation (via dompdf), invoice/billing meta fields, the payment
 * gateway order description (required by Mollie/PayPal/Stripe), and the shipping-required
 * check that drives the checkout components.
 *
 * Registered against {@see \SilverShop\Model\Order} in _config/shopextensions.yml.
 *
 * @property \SilverShop\Model\Order $owner
 * @property string $BillingName
 * @property string $VATNumber
 * @property int    $InvoiceNumber  Raw sequential number (without prefix)
 * @property string $InvoiceHTML
 * @property string $InvoicePrefix
 * @property string $InvoiceDate
 * @property string $ReferencePrefix
 */
class OrderExtension extends Extension{
    /**
     * Fallback invoice number start, used when SiteConfig.InvoiceNumberStart is empty.
     * Keeps behaviour identical for projects ported from SS4/5 without the new field set.
     */
    const DEFAULT_INVOICE_START = 200000;

    /**
     * Fallback invoice number prefix, used when SiteConfig.InvoiceNumberPrefix is empty.
     */
    const DEFAULT_INVOICE_PREFIX = 'RE';

    /**
     * Opt-in flag for the cancellation (Storno) feature. Only when true does an order moving to
     * the "AdminCancelled" status get a Storno date stamped, a downloadable cancellation invoice
     * (Stornorechnung) and a reversing line in the accounting export. Off by default so projects
     * without the accounting export are unaffected. Enabled via receiptexport.yml.example.
     *
     * @config
     * @var bool
     */
    private static $enable_storno = false;

    /**
     * Suffix appended to the invoice number to form the cancellation (Storno) document number.
     * A credit note reuses its invoice's number with this suffix (e.g. RE10003 → RE10003-S) so it
     * is unmistakably tied to the invoice it reverses — no separate number range to reconcile.
     *
     * @config
     * @var string
     */
    private static $storno_suffix = '-S';

    /**
     * Whether zero-value (0,00 €) orders get an invoice at all — invoice number, receipt PDF and
     * receipt mail. Default false: free orders (e.g. gratis courses) get NO invoice, so they never
     * consume an invoice number or punch a gap into the sequential invoice range. Set to true to
     * treat 0 € orders like any other paid order.
     *
     * @config
     * @var bool
     */
    private static $issue_invoice_for_zero_total = false;

    /**
     * Whether the delivery slip (Lieferschein) feature is available at all: the CMS download
     * button on the order and the /OrderReceipt/StreamDeliverySlip route. Opt-in (default false)
     * because many shops (pure digital/seminar catalogues) never ship anything and don't need it.
     * Enable per project via YAML (see deliveryslip.yml.example).
     *
     * @config
     * @var bool
     */
    private static $enable_delivery_slip = false;

    private static $db = [
        'BillingName' => 'Varchar',
        'VATNumber' => 'Varchar',
        'InvoiceNumber' => 'Int',
        'InvoiceHTML' => 'HTMLText',
        'InvoicePrefix' => 'Text',
        'InvoiceDate' => 'Date',
        'ReferencePrefix' => 'Text',
        // Timestamp of the cancellation. The Storno document NUMBER is derived from the invoice
        // number (see StornoNumber()), so no separate numeric Storno field is stored.
        'StornoDate' => 'Date',
        // tax_mode frozen at placement so a placed order's tax LABEL ("zzgl."/"Enthaltene MwSt.")
        // stays correct even if the config changes later (per-line amounts live on the OrderItem).
        'TaxMode' => 'Varchar(20)',
        // Set by the reconciliation in ShopExtensions\PaymentExtension when a payment captures on an
        // order that is already fully covered by another payment (e.g. customer switched SEPA→card
        // and both settled). Flags the order for a refund review in the CMS.
        'HasDuplicatePayment' => 'Boolean',
    ];

    /**
     * Persists the payment tile the customer picked in checkout (see
     * {@see \ShopExtensions\Checkout\PaymentMethodComponent}). Only populated when the
     * payment-tiles feature is enabled; harmless (an empty relation) otherwise. Replaces the
     * old session side-channel and is read by ExtendedPurchaseService to forward the Mollie
     * sub-method.
     */
    private static $has_one = [
        'UsedPaymentOption' => \ShopExtensions\Model\PaymentOption::class,
    ];

    /**
     * Adds "download receipt" and "download delivery slip" buttons to the order in the CMS,
     * linking to the access-controlled route served by
     * {@see \ShopExtensions\Controllers\OrderReceiptController} (owner member or CMS user only).
     */
    public function updateCMSFields(FieldList $fields)
    {
        $receiptLink = "/OrderReceipt/StreamReceipt/" . $this->owner->ID;
        $previewLink = "/OrderReceipt/PreviewReceipt/" . $this->owner->ID;
        $deliverySlipLink = "/OrderReceipt/StreamDeliverySlip/" . $this->owner->ID;

        // Show a small PDF preview (first page, 400px, rasterised via ImageMagick) above the
        // download button — but only once the order actually has an invoice number, so we don't
        // render a receipt for orders that don't have one yet. lazy-loaded to keep the CMS snappy.
        $receiptHtml = '';
        if ($this->owner->InvoiceNumber) {
            $receiptHtml .= "<img src='" . $previewLink . "' width='400' loading='lazy' alt='Rechnungsvorschau' "
                . "style='display:block;max-width:100%;height:auto;border:1px solid #ccc;margin-bottom:.5rem;' />";
        }
        $receiptHtml .= "<a class='btn btn-primary' href='" . $receiptLink . "'>Rechnung herunterladen</a>";

        $fields->addFieldToTab("Root.Rechnungen", LiteralField::create("DownloadReceipt", $receiptHtml));

        // Loud warning when a duplicate payment was detected (e.g. SEPA + card both settled), so a
        // CMS user can arrange a refund. Set by ShopExtensions\PaymentExtension reconciliation.
        if ($this->owner->HasDuplicatePayment) {
            $fields->addFieldToTab("Root.Main", LiteralField::create(
                "DuplicatePaymentWarning",
                "<div class='alert alert-danger' style='font-weight:600'>⚠️ Mögliche Doppelzahlung: "
                    . "diese Bestellung wurde durch mehr als eine Zahlung abgedeckt. Bitte Rückerstattung prüfen.</div>"
            ));
        }

        // Delivery slip is an opt-in feature (enable_delivery_slip) — hide the button where the
        // project doesn't ship anything and hasn't enabled it.
        if ($this->owner->config()->get('enable_delivery_slip')) {
            $fields->addFieldToTab("Root.Rechnungen", LiteralField::create("DownloadDeliverySlip", "<a class='btn btn-primary' href='" . $deliverySlipLink . "'>Lieferschein herunterladen</a>"));
        }

        // Cancellation invoice (Stornorechnung): only for admin-cancelled orders that carry an
        // invoice number, and only when the Storno feature is enabled.
        if ($this->IsStorno()) {
            $stornoLink = "/OrderReceipt/StreamStorno/" . $this->owner->ID;
            $fields->addFieldToTab("Root.Rechnungen", LiteralField::create(
                "DownloadStorno",
                "<a class='btn btn-outline-danger' href='" . $stornoLink . "'>Stornorechnung "
                    . Convert::raw2xml($this->StornoNumber()) . " herunterladen</a>"
            ));
        }
    }

    /**
     * Grace window (minutes) during which a freshly started, still-in-flight payment blocks the
     * "pay again" action, to prevent a second (duplicate) payment when the customer returns while
     * the first is still pending (e.g. under load, or SEPA). Deliberately SHORT: a stuck/abandoned
     * pending payment must not lock the order out of a legitimate retry (e.g. switching to card),
     * and SEPA stays pending for days — so the real duplicate safety net is the reconciliation in
     * {@see \ShopExtensions\PaymentExtension}, not an indefinite block. Set 0 to block for the whole
     * pending duration (not recommended). See docs/SEPA_DOUBLE_PAYMENT_PLAN.md.
     *
     * @config
     * @var int
     */
    private static int $pending_payment_grace_mins = 5;

    /**
     * Master switch for the pending-payment / duplicate-payment handling: the canPay gate that hides
     * the "pay again" form while a payment is in flight, the "payment processing" waiting screen, the
     * repay idempotency guard, and the duplicate-payment reconciliation in
     * {@see \ShopExtensions\PaymentExtension}. Opt-in (default false) so projects that don't need it
     * behave exactly as before. Enable per project via YAML (see delayedpayments.yml.example).
     *
     * @config
     * @var bool
     */
    private static bool $manage_pending_payments = false;

    /**
     * Whether the order may still be paid.
     *
     * First gate: suppress paying while a payment is genuinely in flight (see
     * {@see self::hasFreshPendingPayment()}), so the account "pay again" form and the checkout
     * re-pay both disappear during the pending window. Otherwise defers to the discount-specific
     * logic below (and, via a null return, to SilverShop's own canPay).
     *
     * @return bool|null False while a fresh payment is in flight; true/false for discounted orders;
     *                   null when neither applies (defer to core logic).
     */
    public function canPay($member = null){
        if ($this->hasFreshPendingPayment()) {
            return false;
        }

        $discounts = $this->owner->Discounts();
        $totaldiscount = 0;
        if(count($discounts) > 0 ){
            foreach ($discounts as $discount){
                $totaldiscount += $discount->Amount;
            }
        }

        if($totaldiscount > 0){
            if (!in_array($this->owner->Status, Order::config()->payable_status)) {
                return false;
            }
            if (empty($this->owner->Paid)) {
                return true;
            }
            return true;
        }
    }

    /**
     * Whether the order has a payment that is genuinely in flight AND still within the grace window
     * ({@see self::$pending_payment_grace_mins}). Used to hide the "pay again" action so the customer
     * can't start a duplicate payment while one is processing. Only the in-flight purchase states
     * count (not pending refund/void). Beyond the grace window a pending payment no longer blocks, so
     * an abandoned/stuck payment never permanently locks the order out of a retry.
     *
     * @return bool
     */
    public function hasFreshPendingPayment(): bool
    {
        // Feature is opt-in: when off, never gate — behave exactly like stock SilverShop.
        if (!$this->owner->config()->get('manage_pending_payments')) {
            return false;
        }

        if (!$this->owner->isInDB() || !$this->owner->hasMethod('HasPendingPayments') || !$this->owner->HasPendingPayments()) {
            return false;
        }

        $graceMins = (int) $this->owner->config()->get('pending_payment_grace_mins');

        $query = Payment::get()->filter([
            'OrderID' => $this->owner->ID,
            'Status' => ['PendingAuthorization', 'PendingPurchase', 'PendingCapture'],
        ]);

        // 0 or negative → block for the whole pending duration (no time window).
        if ($graceMins > 0) {
            $cutoff = date('Y-m-d H:i:s', DBDatetime::now()->getTimestamp() - $graceMins * 60);
            $query = $query->filter('Created:GreaterThan', $cutoff);
        }

        return $query->count() > 0;
    }

    /**
     * Template helper: true unless the request carries the "fs" (final step) flag.
     * Used to distinguish a placed order from the final checkout step in templates.
     *
     * @return bool
     */
    public function HasBeenPlaced(){
        if(!isset($_REQUEST['fs'])){
            return true;
        } else {
            return false;
        }
    }

    /**
     * Allow members to cancel their own order only within 24 hours of creation.
     *
     * @param \SilverStripe\Security\Member $member
     * @return bool|null False once older than 24h; null otherwise (defer to core logic).
     */
    public function canCancel($member)
    {
        // TODO check if order was placed more than 24 hrs ago and return false if true
        $created = DBDatetime::create()->setValue($this->owner->Created);
        if ((int) $created->TimeDiffIn('hours') > 24) {
            return false;
        }
    }


    /**
     * Public, idempotent entry point to assign an invoice number and persist it, used from
     * outside the order's own write cycle (e.g. the Manual/invoice payment hook in
     * {@see \ShopExtensions\PaymentExtension}). Does nothing if a number is already set, so it
     * never overwrites an existing invoice number and never creates gaps on re-runs.
     */
    public function ensureInvoiceNumber(): void
    {
        if ($this->owner->InvoiceNumber) {
            return;
        }
        if (!$this->shouldIssueInvoice()) {
            return;
        }
        $this->assignInvoiceNumber();
        $this->owner->write();
    }

    /**
     * Whether this order should get an invoice (number + receipt PDF + receipt mail).
     *
     * Orders with a value always do. Zero-value (0,00 €) orders only do when the project opts in
     * via the issue_invoice_for_zero_total config flag (default off), so free orders don't consume
     * invoice numbers or create gaps in the sequence.
     *
     * @return bool
     */
    public function shouldIssueInvoice(): bool
    {
        if ((float) $this->owner->Total() > 0.0) {
            return true;
        }
        return (bool) $this->owner->config()->get('issue_invoice_for_zero_total');
    }

    /**
     * Placement hook: freeze the per-line VAT onto the order items (and the tax mode onto the
     * order). Fired by SilverShop's OrderProcessor::placeOrder() via extend('onPlaceOrder').
     */
    public function onPlaceOrder()
    {
        $this->freezeItemTaxes();
        $this->maybeAssignInvoiceOnPlacement();
    }

    /**
     * Assign the invoice number already at placement (Cart→Unpaid) when the chosen payment tile
     * ({@see \ShopExtensions\Model\PaymentOption}) is flagged InvoiceOnPlacement — used for
     * "on account"/bank transfer (esp. B2B), where the customer needs the number to reference the
     * transfer. Only the fields are set here (no write): OrderProcessor::placeOrder() writes the
     * order right after onPlaceOrder, so the number persists. Instant methods (card/iDEAL/SEPA)
     * leave the flag unset → number still only on Paid, so aborted payments never burn a number.
     */
    protected function maybeAssignInvoiceOnPlacement(): void
    {
        if ($this->owner->InvoiceNumber) {
            return;
        }
        if (!$this->owner->hasMethod('UsedPaymentOption')) {
            return;
        }
        $option = $this->owner->UsedPaymentOption();
        if (!$option || !$option->exists() || !$option->InvoiceOnPlacement) {
            return;
        }
        if (!$this->shouldIssueInvoice()) {
            return;
        }
        $this->assignInvoiceNumber();
    }

    /**
     * Freeze the VAT that was actually charged onto each OrderItem (TaxRate + TaxAmount) and the
     * tax mode onto the order. Mirrors how SilverShop freezes an item's price into CalculatedTotal.
     *
     * The tax modifier's per-rate total (engine-aware — includes coupon/shipping distribution) is
     * allocated to the items of that rate proportionally to their line total, with the rounding
     * remainder going to the last item so the per-rate sum matches exactly what was charged/exported.
     * Idempotent enough to re-run for a data backfill (see FreezeOrderTaxTask); the caller persists
     * the order (onPlaceOrder runs just before OrderProcessor writes it; the task writes explicitly).
     */
    public function freezeItemTaxes(): void
    {
        $order = $this->owner;
        /** @var CustomTaxModifier|null $modifier */
        $modifier = $order->getModifier(CustomTaxModifier::class);
        if (!$modifier) {
            return;
        }

        // Freeze the engine's per-rate breakdown first (source of truth for the DATEV/PDF export
        // and for the per-line allocation below), when the tax engine is registered.
        if ($order->hasMethod('freezeTaxInformation')) {
            $order->freezeTaxInformation();
        }

        // Per-rate tax computed from the final line totals (engine-aware, now frozen). Rate => float.
        $rateTax = $modifier->getLiveRateBreakdown();

        // Group the items by their (versioned) product tax rate.
        $groups = [];
        foreach ($order->Items() as $item) {
            $rate = $modifier->rateForProduct($item->Product());
            $groups[(string) $rate][] = $item;
        }

        foreach ($groups as $rateKey => $items) {
            $rateNum = (float) $rateKey;
            $tax = isset($rateTax[$rateKey]) ? (float) $rateTax[$rateKey] : 0.0;

            $basis = 0.0;
            foreach ($items as $it) {
                $basis += (float) $it->Total();
            }

            $allocated = 0.0;
            $last = count($items) - 1;
            foreach ($items as $idx => $it) {
                if ($idx === $last) {
                    $share = round($tax - $allocated, 2); // remainder → no per-rate rounding drift
                } else {
                    $share = $basis > 0 ? round($tax * ((float) $it->Total()) / $basis, 2) : 0.0;
                    $allocated += $share;
                }
                $it->TaxRate = $rateNum;
                $it->TaxAmount = $share;
                $it->write();
            }
        }

        $order->TaxMode = (string) $modifier->config()->get('tax_mode');
    }

    /**
     * Assign the next invoice number to this order, if it doesn't have one yet.
     * The starting number is read from SiteConfig.InvoiceNumberStart and falls back
     * to self::DEFAULT_INVOICE_START, so ported projects behave as before.
     * Always (re)sets the invoice date to preserve the previous behaviour.
     */
    protected function assignInvoiceNumber()
    {
        if (!$this->owner->InvoiceNumber) {
            $start = (int) (SiteConfig::current_site_config()->InvoiceNumberStart ?: self::DEFAULT_INVOICE_START);
            $maxRef = Order::get()->sort('InvoiceNumber DESC')->first();

            if ($maxRef && $maxRef->InvoiceNumber) {
                $this->owner->InvoiceNumber = (int) $maxRef->InvoiceNumber + 1;
            } else {
                $this->owner->InvoiceNumber = $start;
            }
        }
        $this->owner->InvoiceDate = date('Y-m-d H:i:s');
    }

    /**
     * Stamp the cancellation (Storno) date on first cancellation. The Storno document number is
     * derived from the invoice number on the fly (see StornoNumber()), so nothing else is assigned.
     * Idempotent: keeps the original cancellation date if the order is re-saved.
     */
    protected function stampStornoDate()
    {
        if (!$this->owner->StornoDate) {
            $this->owner->StornoDate = date('Y-m-d H:i:s');
        }
    }

    /**
     * Whether this order has a cancellation invoice (Stornorechnung) available: the Storno feature
     * is enabled and a Storno document number can be formed (order is admin-cancelled and has an
     * invoice number to reverse). Drives the CMS download button and the StreamStorno route.
     *
     * @return bool
     */
    public function IsStorno(): bool
    {
        return (bool) $this->owner->config()->get('enable_storno') && $this->StornoNumber() !== false;
    }

    /**
     * The formatted cancellation (Storno) document number: the invoice number plus the configured
     * suffix (e.g. RE10003 → RE10003-S). Returns false unless the order is admin-cancelled and
     * actually has an invoice number to reverse.
     *
     * @return string|false
     */
    public function StornoNumber()
    {
        if ($this->owner->Status !== 'AdminCancelled') {
            return false;
        }
        $invoice = $this->InvoiceNumber();
        if ($invoice === false) {
            return false;
        }
        return $invoice . $this->owner->config()->get('storno_suffix');
    }

    /**
     * Per-tax-rate breakdown for the cancellation invoice, as NEGATED, display-ready strings.
     * Reuses the frozen tax engine breakdown (getTaxInformation) so a credit note reverses exactly
     * what was invoiced/booked. Each row carries the rate, a mode-aware label (contained vs. added),
     * and negated net/tax/gross currency strings.
     *
     * @return ArrayList
     */
    public function StornoTaxRows(): ArrayList
    {
        $list = ArrayList::create();
        if (!$this->owner->hasMethod('getTaxInformation')) {
            return $list;
        }

        $mode = $this->owner->TaxMode
            ?: (string) CustomTaxModifier::config()->get('tax_mode');
        $contained = ($mode === 'inclusive');

        foreach ($this->owner->getTaxInformation() as $rate => $info) {
            $rateLabel = rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.');
            $list->push(ArrayData::create([
                'Rate' => $rateLabel,
                'Label' => ($contained ? 'Enthaltene MwSt. ' : 'MwSt. ') . $rateLabel . '%',
                'Contained' => $contained,
                'Net' => self::formatNegativeCurrency(self::parseLocalNumber($info['net'] ?? 0)),
                'Tax' => self::formatNegativeCurrency(self::parseLocalNumber($info['tax'] ?? 0)),
                'Gross' => self::formatNegativeCurrency(self::parseLocalNumber($info['gross'] ?? 0)),
            ]));
        }
        return $list;
    }

    /**
     * The order sub-total as a negated, display-ready currency string (for the Stornorechnung).
     *
     * @return string
     */
    public function NegSubTotal(): string
    {
        return self::formatNegativeCurrency($this->owner->SubTotal());
    }

    /**
     * The order total as a negated, display-ready currency string (for the Stornorechnung).
     * Authoritative amount of the credit note (= -Order.Total), independent of the mode.
     *
     * @return string
     */
    public function NegTotal(): string
    {
        return self::formatNegativeCurrency($this->owner->Total());
    }

    /**
     * Format an amount as a negated currency string with a clear leading minus, e.g. "-€59.50".
     * Uses the configured currency symbol. Deliberately a minus rather than DBCurrency's accounting
     * parentheses so a cancellation invoice reads unambiguously as negative.
     *
     * @param mixed $amount Numeric amount (magnitude; the sign is forced negative unless it is 0).
     * @return string
     */
    public static function formatNegativeCurrency($amount): string
    {
        $symbol = DBCurrency::config()->get('currency_symbol') ?: '€';
        $value = abs((float) $amount);
        $sign = $value > 0 ? '-' : '';
        return $sign . $symbol . number_format($value, 2);
    }

    /**
     * Parse a locale-formatted number string (German "1.234,56" or plain "1234.56") to a float.
     * Tolerant of currency symbols/whitespace. Used to turn the tax engine's formatted breakdown
     * strings back into numbers before re-formatting them negated.
     *
     * @param mixed $value
     * @return float
     */
    public static function parseLocalNumber($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $s = preg_replace('/[^0-9,.\-]/', '', (string) $value);
        if (strpos($s, ',') !== false) {
            // German format: '.' groups thousands, ',' is the decimal separator.
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }
        return (float) $s;
    }

    /**
     * Order lifecycle hook fired once payment is fully settled. Assigns the invoice number
     * and sends the receipt email (with the invoice PDF attached, see CustomOrderEmailNotifier).
     */
    public function onPaid()
    {
        // Zero-value orders get no invoice at all (number, PDF, receipt mail) unless the project
        // opts in via issue_invoice_for_zero_total — so free orders don't consume invoice numbers.
        if (!$this->shouldIssueInvoice()) {
            return;
        }

        $this->assignInvoiceNumber();

        if (!$this->owner->ReceiptSent) {
            $notifier = CustomOrderEmailNotifier::create($this->owner)->sendReceipt();
            $this->owner->ReceiptSent = DBDatetime::now()->Rfc2822();
        }
    }

    /**
     * Order lifecycle hook fired on every status transition. Handles two cases:
     *  - Manual (invoice) gateway moving to "Unpaid": assign the invoice number up front.
     *  - Any supported gateway moving to "Paid": mark items paid, run onPaid() and notify admin.
     *
     * This covers gateways (e.g. Mollie) that confirm payment asynchronously via status
     * change rather than through the synchronous onPaid() path.
     *
     * @param string $fromStatus
     * @param string $toStatus
     */
    public function onStatusChange($fromStatus, $toStatus)
    {
        $payment = '';
        $payments = Payment::get()->filter('OrderID', $this->owner->ID)->sort('ID', 'ASC');
        if (count($payments) > 0) {
            $payment = $payments->last();
        }

        // Fallback only: at the normal Cart→Unpaid placement the Payment does not exist yet, so
        // $payment->Gateway is empty and this branch does not fire. The real assignment for Manual
        // invoice orders happens in ShopExtensions\PaymentExtension when the payment is authorized.
        // Kept for the edge case where a Manual payment already exists on a manual status change.
        if ($fromStatus == 'Cart' && $toStatus == 'Unpaid' && $payment && in_array($payment->Gateway, ['Manual'])) {
            $this->assignInvoiceNumber();
        }

        // Stamp the cancellation (Storno) date when an order is admin-cancelled, if enabled. The
        // Storno document number itself is derived from the invoice number (see StornoNumber()).
        if ($toStatus == 'AdminCancelled' && $this->owner->config()->get('enable_storno')) {
            $this->stampStornoDate();
        }

        if ($toStatus == 'Paid' && in_array($payment->Gateway, ['Stripe', 'PayPal_Express', 'PayPal_Pro', 'Mollie','Manual'])  ) {
            $this->owner->Paid = DBDatetime::now()->Rfc2822();
            foreach ($this->owner->Items() as $item) {
                $item->onPlacement();
                $item->onPayment();
                $item->write();
            }

            //all payment is settled
            $this->onPaid();

            $notifier = CustomOrderEmailNotifier::create($this->owner);
            $notifier->sendAdminNotification();
        }
    }

    /**
     * Front-end link to the member-facing receipt action on the AccountPage.
     *
     * @return string|null
     */
    public function ReceiptLink()
    {
        if ($page = AccountPage::get()->first()) {
            $base = $page->Link();
            return Controller::join_links($base, 'receipt', $this->owner->ID);
        }
    }

    /**
     * Invoice number prefix, read from SiteConfig.InvoiceNumberPrefix with a fallback to
     * self::DEFAULT_INVOICE_PREFIX so ported projects without the field behave as before.
     *
     * @return string
     */
    public function ReferencePrefix(){
        return SiteConfig::current_site_config()->InvoiceNumberPrefix ?: self::DEFAULT_INVOICE_PREFIX;
    }

    /**
     * The formatted invoice number (prefix + number), or false if none has been assigned yet.
     * Used by templates and the mailer to decide whether an invoice PDF can be produced.
     *
     * @return string|false
     */
    public function InvoiceNumber()
    {
        if ($this->owner->InvoiceNumber == 0 || $this->owner->InvoiceNumber == "" || $this->owner->InvoiceNumber == null) {
            return false;
        } else {
            return $this->ReferencePrefix().$this->owner->InvoiceNumber;
        }
    }

    /**
     * Render the order's invoice as a PDF using the Receipt.ss template and dompdf.
     *
     * @param string $type_ 'stream' to send the PDF to the browser, otherwise the raw
     *                      PDF binary string is returned (used for email attachments).
     * @return string|void PDF binary when $type_ !== 'stream'; streams and returns void otherwise.
     */
    public function PDFReceipt($type_ = 'stream')
    {
        $controller = \SilverStripe\CMS\Controllers\ContentController::create();

        if ($this->owner->canView()) {
            $siteconfig = SiteConfig::current_site_config();

            \SilverStripe\View\SSViewer::set_themes(Config::inst()->get('SilverStripe\View\SSViewer', 'themes'));
            $content = $controller->customise([
                'Order' => $this->owner,
                'BasePath' => Director::baseFolder(),
            ])->renderWith('Receipt');

            $dompdf = new Dompdf();
            $dompdf->loadHtml($content);


            // (Optional) Setup the paper size and orientation
            $dompdf->setPaper('A4', 'portrait');

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser
            if ($type_ == 'stream') {
                $dompdf->stream('Rechnung ' . $this->owner->Reference);
            } else {
                return $dompdf->output();
            }
        } else {
            return;
        }
    }

    /**
     * Render the order's delivery slip as a PDF using the DeliverySlip.ss template and dompdf.
     *
     * @param string $type_ 'stream' to send the PDF to the browser, otherwise the raw
     *                      PDF binary string is returned.
     * @return string|void PDF binary when $type_ !== 'stream'; streams and returns void otherwise.
     */
    public function PDFDeliverySlip($type_ = 'stream')
    {
        $controller = \SilverStripe\CMS\Controllers\ContentController::create();

        if ($this->owner->canView()) {
            $siteconfig = SiteConfig::current_site_config();

            \SilverStripe\View\SSViewer::set_themes(Config::inst()->get('SilverStripe\View\SSViewer', 'themes'));
            $content = $controller->customise([
                'Order' => $this->owner,
                'BasePath' => Director::baseFolder(),
            ])->renderWith('DeliverySlip');

            $dompdf = new Dompdf();
            $dompdf->loadHtml($content);


            // (Optional) Setup the paper size and orientation
            $dompdf->setPaper('A4', 'portrait');

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser
            if ($type_ == 'stream') {
                $dompdf->stream('Lieferschein ' . $this->owner->Reference);
            } else {
                return $dompdf->output();
            }
        } else {
            return;
        }
    }

    /**
     * Render the order's cancellation invoice (Stornorechnung) as a PDF using the Storno.ss
     * template and dompdf. Mirrors the invoice layout but with the Storno number ("<invoice>-S")
     * and all amounts negated (via the negated display helpers used by the template).
     *
     * @param string $type_ 'stream' to send the PDF to the browser, otherwise the raw PDF binary.
     * @return string|void PDF binary when $type_ !== 'stream'; streams and returns void otherwise.
     */
    public function PDFStorno($type_ = 'stream')
    {
        $controller = \SilverStripe\CMS\Controllers\ContentController::create();

        if ($this->owner->canView()) {
            \SilverStripe\View\SSViewer::set_themes(Config::inst()->get('SilverStripe\View\SSViewer', 'themes'));
            $content = $controller->customise([
                'Order' => $this->owner,
                'BasePath' => Director::baseFolder(),
            ])->renderWith('Storno');

            $dompdf = new Dompdf();
            $dompdf->loadHtml($content);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            if ($type_ == 'stream') {
                $dompdf->stream('Stornorechnung ' . $this->owner->Reference);
            } else {
                return $dompdf->output();
            }
        } else {
            return;
        }
    }

    /**
     * Builds the human-readable order description passed to the payment gateway.
     * This is REQUIRED by several gateways (Mollie, PayPal) — without it the purchase
     * request fails. Other extensions can amend the parts via the updateDescriptionParts hook.
     *
     * @return string e.g. "42 | Jane Doe | jane@example.com"
     */
    public function getDescription()
    {
        /** @var \SilverShop\Model\Order $order */
        $order = $this->owner;
        $parts = [
            'ID' =>$order->ID,
            'Name' => $order->getName(),
            'Email' => $order->getLatestEmail()
        ];

        $this->owner->extend('updateDescriptionParts', $parts);

        return implode(' | ', array_filter($parts));
    }


    /**
     * Check if any items in this order require shipping
     * @return bool
     */
    public function requiresShipping()
    {
        $items = $this->owner->Items();

        if (!$items || $items->count() === 0) {
            // Default to true if no items yet (during initial checkout)
            return true;
        }

        foreach ($items as $item) {
            $product = $item->Product();
            if ($product && $product->exists() && $product->requiresShipping()) {
                return true;
            }
        }

        return false;
    }
}
