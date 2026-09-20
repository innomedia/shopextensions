<?php

namespace ShopExtensions\Service;

use SilverShop\Model\Order;
use SilverShop\ShopTools;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;

/**
 * Places and settles orders WITHOUT checkout, session, request or current controller – for orders
 * created by code: subscription renewals from a webhook or cron job, imports, manual re-orders.
 *
 * SilverShop's OrderProcessor::placeOrder() reads the client IP from Controller::curr() and clears the
 * session cart; neither exists in a CLI/queued job. This service mirrors placeOrder() without both:
 *
 *   $order = Order::create([... 'Status' => 'Cart', 'MemberID' => $member->ID, ...]); $order->write();
 *   // add items (write them with OrderID) and addresses, then
 *   $order->calculate();
 *   $placer = OrderPlacer::singleton();
 *   $placer->place($order);                                         // Cart → Unpaid, onPlaceOrder hooks
 *   $placer->recordPayment($order, 'Mollie', 18.60, 'EUR', 'tr_…'); // Payment already settled elsewhere
 *   $placer->markPaid($order);                                      // → Paid: invoice number + receipt mail + admin mail
 *
 * markPaid() runs the regular pipeline in {@see \ShopExtensions\OrderExtension::onStatusChange()}: Paid
 * date, items onPayment, invoice number (persistent sequence), queued receipt mail with PDF and the
 * admin notification. The last payment's gateway must be in Order.paid_status_gateways.
 *
 * Hooks on the order: onPlaceOrder (same as the checkout: tax freeze, receipt token …),
 * onBeforeProgrammaticPlacement / onAfterProgrammaticPlacement.
 */
class OrderPlacer
{
    use Injectable;

    /**
     * Cart → Unpaid (or Paid for a zero total). Throws when the order is not a cart or has no items.
     * Runs in a transaction; the caller writes nothing else in between.
     *
     * @throws ValidationException
     */
    public function place(Order $order, ?Member $member = null): Order
    {
        if (!$order->isInDB()) {
            $order->write();
        }
        if (!$order->IsCart()) {
            throw ValidationException::create('Order ' . $order->ID . ' is not a cart and can not be placed.');
        }
        if ($order->Items()->count() === 0) {
            throw ValidationException::create('Order ' . $order->ID . ' has no items.');
        }

        if ($order->Locale) {
            ShopTools::install_locale($order->Locale);
        }

        $conn = DB::get_conn();
        $transactions = $conn->supportsTransactions();
        if ($transactions) {
            $conn->transactionStart();
        }

        try {
            $order->extend('onBeforeProgrammaticPlacement');

            if ($member && !$order->MemberID) {
                $order->MemberID = $member->ID;
            }

            $order->setField('Status', $order->TotalOutstanding(false) !== 0.0 ? 'Unpaid' : 'Paid');
            if (!$order->Placed) {
                $order->setField('Placed', DBDatetime::now()->Rfc2822());
            }
            if (!$order->IPAddress) {
                $order->IPAddress = '';
            }

            foreach ($order->Items() as $item) {
                $item->onPlacement();
                $item->write();
            }
            foreach ($order->Modifiers() as $modifier) {
                $modifier->write();
            }

            $order->extend('onPlaceOrder');
            $order->write();

            $order->extend('onAfterProgrammaticPlacement');
        } catch (\Throwable $e) {
            if ($transactions) {
                $conn->transactionRollback();
            }
            throw $e;
        }

        if ($transactions) {
            $conn->transactionEnd();
        }

        return $order;
    }

    /**
     * Records a payment that was settled outside omnipay (e.g. a recurring Mollie charge reported by a
     * webhook). No gateway request is made.
     */
    public function recordPayment(
        Order $order,
        string $gateway,
        float $amount,
        string $currency,
        string $transactionReference = '',
        string $status = 'Captured'
    ): Payment {
        $payment = Payment::create();
        $payment->Gateway = $gateway;
        $payment->setAmount($amount);
        $payment->setCurrency($currency);
        $payment->Status = $status;
        $payment->TransactionReference = $transactionReference;
        $payment->OrderID = $order->ID;
        $payment->write();

        return $payment;
    }

    /**
     * Unpaid → Paid through the regular status transition (see class docs). Idempotent.
     */
    public function markPaid(Order $order): Order
    {
        if ($order->Status === 'Paid' || $order->IsPaid()) {
            return $order;
        }

        $order->extend('onPayment');
        $order->setField('Status', 'Paid');
        $order->write();

        return $order;
    }
}
