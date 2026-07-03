<?php

namespace ShopExtensions;

use SilverShop\Model\Order;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Extension;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Service\ServiceResponse;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Extension for the omnipay Payment model.
 *
 * Two responsibilities:
 *  1. Assign the order's invoice number as soon as a Manual ("Rechnung"/invoice) payment is
 *     authorized or captured (see {@see self::assignManualInvoiceNumber()}).
 *  2. Reconcile DUPLICATE payments on capture: when a payment settles on an order that is already
 *     fully covered by another payment, flag it (and optionally auto-refund). This is the real
 *     safety net against SEPA double payments — a customer can legitimately switch SEPA→card, and
 *     if both end up settling we catch and reverse the surplus (see {@see self::reconcileDuplicatePayment()}).
 *
 * Why the invoice number is assigned here and not in Order::onStatusChange(): the checkout calls
 * OrderProcessor::placeOrder() (Cart → Unpaid) BEFORE OrderProcessor::makePayment() creates the
 * Payment. At the Cart→Unpaid transition no Payment exists yet, so a gateway check there can never
 * see "Manual". The payment lifecycle hook fires once the Manual payment is actually authorized.
 *
 * @property \SilverStripe\Omnipay\Model\Payment $owner
 */
class PaymentExtension extends Extension
{
    /**
     * When true, a payment detected as a duplicate (order already fully covered by another payment)
     * is refunded automatically via omnipay. Default false: only flag the order + alert an admin,
     * because moving money automatically warrants an explicit opt-in per project.
     *
     * @config
     * @var bool
     */
    private static bool $auto_refund_duplicate = false;

    /**
     * Recipient for the "duplicate payment detected" alert. Empty → falls back to the SiteConfig
     * AdminNotificationMail, then the system admin email.
     *
     * @config
     * @var string
     */
    private static string $duplicate_alert_email = '';

    public function onAuthorized(ServiceResponse $response): void
    {
        $this->assignManualInvoiceNumber();
    }

    public function onCaptured(ServiceResponse $response): void
    {
        $this->assignManualInvoiceNumber();
        $this->reconcileDuplicatePayment();
    }

    /**
     * For Manual (invoice) payments only: give the owning order its invoice number.
     */
    protected function assignManualInvoiceNumber(): void
    {
        $payment = $this->getOwner();
        if ($payment->Gateway !== 'Manual') {
            return;
        }

        // Reload the order fresh from the DB to avoid acting on stale in-memory data.
        $order = Order::get()->byID($payment->OrderID);
        if (!$order || !$order->exists() || !$order->hasMethod('ensureInvoiceNumber')) {
            return;
        }

        // Safety: never number an order that is still a shopping cart. In the normal flow the
        // order is already placed (Unpaid) when a payment authorizes, but this guards against
        // any path that might authorize a payment while the order is still in "Cart".
        if ($order->Status === 'Cart') {
            return;
        }

        $order->ensureInvoiceNumber();
    }

    /**
     * Detect and handle a duplicate payment: this (just-captured, non-manual) payment is surplus if
     * the order total is ALREADY fully covered by the OTHER captured/authorized non-manual payments.
     * That is the SEPA-double-payment case: e.g. the customer, seeing the order still Unpaid, paid
     * again by card; the card settled the order, then the SEPA is collected days later on top.
     *
     * Independent of hook ordering and of the order's Paid flag: it inspects the sibling payments
     * directly. On a hit it flags the order (HasDuplicatePayment) and alerts an admin; if
     * auto_refund_duplicate is on and the gateway allows it, it also refunds this payment.
     */
    protected function reconcileDuplicatePayment(): void
    {
        $payment = $this->getOwner();

        if ($payment->Status !== 'Captured' || GatewayInfo::isManual($payment->Gateway)) {
            return;
        }

        $order = Order::get()->byID($payment->OrderID);
        if (!$order || !$order->exists()) {
            return;
        }

        // Opt-in feature (same master switch as the canPay gate / waiting screen).
        if (!$order->config()->get('manage_pending_payments')) {
            return;
        }

        // Sum of the OTHER (non-manual) captured/authorized payments on this order.
        $coveredByOthers = 0.0;
        foreach ($order->Payments() as $sibling) {
            if ($sibling->ID == $payment->ID || GatewayInfo::isManual($sibling->Gateway)) {
                continue;
            }
            if ($sibling->Status === 'Captured' || $sibling->Status === 'Authorized') {
                $coveredByOthers += (float) $sibling->Amount;
            }
        }

        // Not a duplicate: this payment is (at least partly) needed to cover the order.
        if ($coveredByOthers + 0.001 < (float) $order->GrandTotal()) {
            return;
        }

        // Duplicate: flag the order for a refund review (idempotent).
        if (!$order->HasDuplicatePayment) {
            $order->HasDuplicatePayment = true;
            $order->write();
        }

        $this->alertDuplicatePayment($order, $payment);

        if ((bool) $payment->config()->get('auto_refund_duplicate')) {
            $this->refundDuplicatePayment($payment);
        }
    }

    /**
     * Email an admin that a duplicate payment landed on an order, so it can be refunded.
     */
    protected function alertDuplicatePayment(Order $order, Payment $payment): void
    {
        $siteConfig = SiteConfig::current_site_config();
        $to = (string) $payment->config()->get('duplicate_alert_email')
            ?: ($siteConfig->AdminNotificationMail ?: Email::config()->admin_email);
        if (!$to) {
            return;
        }

        $from = $siteConfig->AdminEmail ?: Email::config()->admin_email;
        $body = sprintf(
            '<p>Für Bestellung <strong>%s</strong> (ID %d) wurde eine <strong>zusätzliche Zahlung</strong> '
                . 'verbucht, obwohl die Bestellung bereits durch eine andere Zahlung gedeckt war.</p>'
                . '<p>Betroffene Zahlung: Gateway %s, Betrag %s, Payment-ID %d, Referenz %s.</p>'
                . '<p>Bitte eine Rückerstattung prüfen.</p>',
            htmlspecialchars((string) $order->Reference),
            (int) $order->ID,
            htmlspecialchars((string) $payment->Gateway),
            htmlspecialchars((string) $payment->dbObject('Money')->Nice()),
            (int) $payment->ID,
            htmlspecialchars((string) $payment->TransactionReference)
        );

        try {
            Email::create()
                ->setTo($to)
                ->setFrom($from, $siteConfig->AdminName ?: null)
                ->setSubject(sprintf('Mögliche Doppelzahlung – Bestellung %s', $order->Reference))
                ->setBody($body)
                ->setData(['BaseURL' => Director::absoluteBaseURL()])
                ->send();
        } catch (\Throwable $e) {
            // Never let alerting break the payment capture flow.
        }
    }

    /**
     * Opt-in: refund the surplus payment via omnipay, if the gateway/payment allows it.
     */
    protected function refundDuplicatePayment(Payment $payment): void
    {
        if (!$payment->canRefund()) {
            return;
        }
        try {
            $service = ServiceFactory::create()->getService($payment, ServiceFactory::INTENT_REFUND);
            $service->initiate();
        } catch (\Throwable $e) {
            // Fall back to the manual review the flag + alert already triggered.
        }
    }
}
