<?php

namespace ShopExtensions;

use Psr\Log\LoggerInterface;
use SilverShop\Checkout\OrderEmailNotifier;
use SilverShop\Checkout\OrderProcessor;
use ShopExtensions\Model\PaymentOption;
use SilverShop\Model\Order;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\Omnipay\Service\ServiceFactory;
use SilverStripe\Omnipay\Service\ServiceResponse;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Extension for the omnipay Payment model.
 *
 * Responsibilities:
 *  1. Assign the order's invoice number as soon as a Manual ("Rechnung"/invoice) payment is
 *     authorized or captured (see {@see self::assignManualInvoiceNumber()}), and optionally send
 *     the confirmation mails for it (see {@see self::sendManualPaymentConfirmation()}).
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

    /**
     * Customer-facing name of the payment method, for invoices and mails.
     *
     * With payment tiles the shop names its methods itself (PaymentOption.Title, e.g. "Vorkasse /
     * Überweisung"), while GatewayTitle only knows the gateway ("Manual" → a generic translation).
     * Uses the tile the order was placed with when it belongs to this payment's gateway, otherwise
     * the only enabled tile for the gateway, otherwise GatewayTitle.
     */
    public function PaymentMethodTitle(): string
    {
        $payment = $this->getOwner();

        if (class_exists(PaymentOption::class)) {
            $order = $payment->OrderID ? Order::get()->byID($payment->OrderID) : null;
            if ($order && $order->hasMethod('UsedPaymentOption')) {
                $option = $order->UsedPaymentOption();
                if ($option && $option->exists() && $option->PaymentGateway === $payment->Gateway && $option->Title) {
                    return (string) $option->Title;
                }
            }

            $options = PaymentOption::get()->filter(['PaymentGateway' => $payment->Gateway, 'Enabled' => 1]);
            if ($options->count() === 1 && $options->first()->Title) {
                return (string) $options->first()->Title;
            }
        }

        return (string) $payment->getGatewayTitle();
    }

    public function onAuthorized(ServiceResponse $response): void
    {
        $this->assignManualInvoiceNumber();
        $this->sendManualPaymentConfirmation();
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
     * Opt-in ({@see CustomOrderEmailNotifier::$send_confirmation_for_manual_payment}): send the order
     * confirmation to the customer and the new-order notification to the operator as soon as an
     * order is placed with a Manual gateway (Vorkasse / bank transfer / invoice).
     *
     * Without it such an order hears nothing until an admin sets it to Paid, because this module
     * sends its mails from Order::onStatusChange(→ Paid) and projects keep
     * OrderProcessor.send_confirmation off so card/PayPal orders don't get a confirmation AND a
     * receipt. The customer then never sees the bank details and the operator is not told that a
     * transfer is due.
     *
     * Runs after silvershop's PaymentExtension::onAuthorized() (order placed, Cart → Unpaid) and after
     * the invoice number was assigned, so the confirmation can already carry it. Skipped when
     * silvershop itself is configured to send the mail (no duplicates), when the order is not Unpaid
     * or already has a receipt, and when an earlier Manual payment of the same order was authorized
     * (paying again from the account must not resend the confirmation).
     */
    protected function sendManualPaymentConfirmation(): void
    {
        if (!CustomOrderEmailNotifier::config()->get('send_confirmation_for_manual_payment')) {
            return;
        }

        $payment = $this->getOwner();
        if (!$payment->OrderID || !GatewayInfo::isManual($payment->Gateway)) {
            return;
        }

        $order = Order::get()->byID($payment->OrderID);
        if (!$order || $order->Status !== 'Unpaid' || $order->ReceiptSent) {
            return;
        }

        foreach ($order->Payments()->exclude('ID', $payment->ID) as $sibling) {
            if (GatewayInfo::isManual($sibling->Gateway)
                && in_array($sibling->Status, ['Authorized', 'Captured'], true)
            ) {
                return;
            }
        }

        $processorConfig = OrderProcessor::config();
        $notifier = OrderEmailNotifier::create($order);

        if (!$processorConfig->get('send_confirmation')) {
            $notifier->sendConfirmation();
        }
        if (!$processorConfig->get('send_admin_notification')) {
            $notifier->sendAdminNotification();
        }
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

        try {
            // Body from a template (ShopExtensions/Email/DuplicatePaymentEmail, overridable in the
            // theme) instead of a sprintf() string, so projects get their mail layout and the
            // operator sees all payments of the order, not just the surplus one.
            Email::create()
                ->setTo($to)
                ->setFrom($from, (string) ($siteConfig->AdminName ?: ''))
                ->setSubject(_t(
                    'ShopExtensions\\Email.DuplicatePaymentSubject',
                    'Mögliche Doppelzahlung – Bestellung {ref}',
                    '',
                    ['ref' => $order->Reference]
                ))
                ->setHTMLTemplate('ShopExtensions/Email/DuplicatePaymentEmail')
                ->setData([
                    'Order' => $order,
                    'Payment' => $payment,
                    'SiteConfig' => $siteConfig,
                    'BaseURL' => Director::absoluteBaseURL(),
                ])
                ->send();
        } catch (\Throwable $e) {
            // Never let alerting break the payment capture flow, but leave a trace: a silently
            // swallowed error here once meant the alert never went out at all.
            Injector::inst()->get(LoggerInterface::class)->error(
                'Duplicate payment alert for order ' . $order->Reference . ' failed: ' . $e->getMessage()
            );
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
