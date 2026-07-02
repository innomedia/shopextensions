<?php

namespace ShopExtensions;

use SilverShop\Model\Order;
use SilverStripe\Core\Extension;
use SilverStripe\Omnipay\Service\ServiceResponse;

/**
 * Extension for the omnipay Payment model.
 *
 * Assigns the order's invoice number as soon as a Manual ("Rechnung"/invoice) payment is
 * authorized or captured.
 *
 * Why here and not in Order::onStatusChange(): the checkout calls OrderProcessor::placeOrder()
 * (Cart → Unpaid) BEFORE OrderProcessor::makePayment() creates the Payment. At the Cart→Unpaid
 * transition no Payment exists yet, so a gateway check there can never see "Manual" and the
 * invoice number is never assigned. The payment lifecycle hook fires once the Manual payment is
 * actually authorized, at which point the order is placed and the gateway is known.
 *
 * Non-Manual gateways (Mollie, cards) keep getting their number on "Paid" via Order::onPaid(),
 * so they don't consume a number while still unpaid (avoids gaps in the invoice range).
 *
 * @property \SilverStripe\Omnipay\Model\Payment $owner
 */
class PaymentExtension extends Extension
{
    public function onAuthorized(ServiceResponse $response): void
    {
        $this->assignManualInvoiceNumber();
    }

    public function onCaptured(ServiceResponse $response): void
    {
        $this->assignManualInvoiceNumber();
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
}
