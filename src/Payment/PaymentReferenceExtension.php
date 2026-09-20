<?php

namespace ShopExtensions\Payment;

use Omnipay\Common\Message\RequestInterface;
use Omnipay\Common\Message\ResponseInterface;
use SilverStripe\Core\Extension;

/**
 * Stores the provider's transaction reference when a purchase starts and passes it back when the
 * purchase is completed. Registered on {@see \SilverStripe\Omnipay\Service\PurchaseService} in
 * _config/shopextensions.yml (bugfix, on by default).
 *
 * silverstripe-omnipay writes the reference only in markCompleted(). On the customer's return
 * (`/paymentendpoint/…/complete`) it calls completePurchase() WITHOUT a reference and expects the
 * gateway to read it from the return URL. Mollie returns without any parameter, the adapter throws
 * "The transactionReference parameter is required", and a customer who has already paid lands back
 * in the checkout (the webhook completes the payment in the background).
 *
 * Idempotent: it only fills an EMPTY reference, so gateways that return the reference themselves
 * are not affected.
 *
 * Replaces the project copies Tietge\Books\Checkout\PaymentReferenceExtension and
 * Ghee\Shop\Checkout\PaymentReferenceExtension (remove them when updating).
 *
 * @property \SilverStripe\Omnipay\Service\PurchaseService $owner
 */
class PaymentReferenceExtension extends Extension
{
    /**
     * Hook from PurchaseService::initiate(), right after the purchase request was sent.
     */
    public function onAfterSendPurchase(RequestInterface $request, ResponseInterface $response): void
    {
        $payment = $this->getOwner()->getPayment();
        $reference = $response->getTransactionReference();

        if (!$payment || !$reference || $payment->TransactionReference) {
            return;
        }

        $payment->TransactionReference = $reference;
        $payment->write();
    }

    /**
     * Hook from PurchaseService::complete(), before the status request (return AND webhook).
     */
    public function onBeforeCompletePurchase(array &$gatewayData): void
    {
        if (!empty($gatewayData['transactionReference'])) {
            return;
        }

        $payment = $this->getOwner()->getPayment();
        $reference = $payment ? (string) $payment->TransactionReference : '';

        if ($reference !== '') {
            $gatewayData['transactionReference'] = $reference;
        }
    }
}
