<?php

namespace ShopExtensions\Payment;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Omnipay\Service\ServiceResponse;

/**
 * A payment start the provider REJECTS stays an error, also with asynchronous notifications.
 * Registered on {@see \SilverStripe\Omnipay\Service\PurchaseService} in _config/shopextensions.yml
 * (bugfix, on by default).
 *
 * With `GatewayInfo.<gateway>.use_async_notification: true` (needed for SEPA / bank transfer),
 * silverstripe-omnipay never flags a purchase response as an error – PaymentService::
 * wrapOmnipayResponse() sets SERVICE_PENDING instead. A request Mollie refuses (HTTP 422: invalid
 * webhook URL, amount below the method minimum, method not enabled, …) then puts the payment on
 * "PendingPurchase" and sends the customer to the SUCCESS link: the order looks placed, but no
 * payment exists at the provider and no webhook will ever come.
 *
 * For Mollie a created payment always answers with a redirect to its checkout page – bank transfer
 * and SEPA included. "Neither successful nor a redirect" at the START of a purchase is therefore a
 * rejection. Only then, and only for the gateways in `gateways`, the response is turned back into
 * an error: omnipay writes a PurchaseError message, the payment stays "Created", and the checkout
 * handles it like any failed payment (see CheckoutPageControllerExtension::processPaymentResponse()).
 *
 * @property \SilverStripe\Omnipay\Service\PurchaseService $owner
 */
class AsyncPurchaseErrorExtension extends Extension
{
    /**
     * Gateways whose successful purchase start is always a redirect.
     *
     * @config
     */
    private static array $gateways = ['Mollie'];

    public function updateServiceResponse(ServiceResponse $response): void
    {
        if ($response->isNotification() || !$response->isAwaitingNotification() || $response->isRedirect()) {
            return;
        }

        $payment = $response->getPayment();

        // Purchase start only: complete() runs on a payment that is already PendingPurchase.
        if (!$payment || $payment->Status !== 'Created'
            || !in_array($payment->Gateway, (array) Config::inst()->get(static::class, 'gateways'), true)
        ) {
            return;
        }

        $omnipayResponse = $response->getOmnipayResponse();
        if (!$omnipayResponse || $omnipayResponse->isSuccessful()) {
            return;
        }

        $response->removeFlag(ServiceResponse::SERVICE_PENDING);
        $response->addFlag(ServiceResponse::SERVICE_ERROR);
        $response->setTargetUrl($payment->FailureUrl);
    }
}
