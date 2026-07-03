<?php

namespace ShopExtensions\Forms;

use ShopExtensions\Model\PaymentOption;
use SilverShop\Checkout\OrderProcessor;
use SilverShop\Forms\OrderActionsForm as BaseOrderActionsForm;
use SilverShop\Model\Order;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Omnipay\GatewayFieldsFactory;

/**
 * Injector replacement for {@see \SilverShop\Forms\OrderActionsForm}, paired with
 * {@see \ShopExtensions\OrderActionsFormExtension}.
 *
 * The extension renders the payment tiles (whose submitted value is a PaymentOption ID). This
 * subclass overrides {@see self::dopayment()} to resolve that ID back to a gateway: it persists the
 * chosen tile on the order (Order.UsedPaymentOptionID, so ExtendedPurchaseService forwards the
 * Mollie sub-method) and starts the payment on the option's gateway — skipping Mollie's own method
 * selection screen, exactly like the checkout.
 *
 * When no tile can be resolved (feature off, or the value is already a plain gateway), it defers to
 * the vendor implementation, so nothing changes for non-tile setups.
 *
 * Wired in via the Injector (see paymenttiles.yml.example). OrderActionsForm is created through
 * OrderActionsForm::create() in OrderManipulationExtension, so the Injector swap takes effect.
 */
class OrderActionsForm extends BaseOrderActionsForm
{
    /**
     * @param array $data
     * @param \SilverStripe\Forms\Form $form
     */
    public function dopayment(array $data, $form): HTTPResponse
    {
        // Idempotency guard: if a payment is already in flight (fresh), refuse to start a second
        // one — this catches the race where the form was rendered before the pending payment
        // registered (e.g. the customer returned under load and clicked "pay" again). Reload the
        // order fresh from the DB so we don't act on stale in-memory state. See
        // docs/SEPA_DOUBLE_PAYMENT_PLAN.md.
        $fresh = ($this->order instanceof Order && $this->order->isInDB())
            ? Order::get()->byID($this->order->ID)
            : null;
        if ($fresh && $fresh->hasMethod('hasFreshPendingPayment') && $fresh->hasFreshPendingPayment()) {
            $form->sessionMessage(
                _t(
                    self::class . '.PaymentInProgress',
                    'Ihre Zahlung wird bereits verarbeitet. Bitte warten Sie einen Moment und laden Sie die Seite neu.'
                ),
                'warning'
            );
            return $this->controller->redirectBack();
        }

        $data = $form->getData();
        $optionID = empty($data['PaymentMethod']) ? null : $data['PaymentMethod'];
        $option = $optionID ? PaymentOption::get()->byID($optionID) : null;

        // No tile resolved → the value is a plain gateway (or the feature is off): vendor behaviour.
        if (!$option || !$option->exists()) {
            return parent::dopayment($data, $form);
        }

        if (self::config()->allow_paying
            && $this->order instanceof Order
            && $this->order->canPay()
        ) {
            // Persist the chosen tile so ExtendedPurchaseService forwards the Mollie sub-method.
            $this->order->UsedPaymentOptionID = $option->ID;
            if ($this->order->isInDB()) {
                $this->order->write();
            }

            $processor = OrderProcessor::create($this->order);
            $fieldFactory = GatewayFieldsFactory::create(null);
            $response = $processor->makePayment(
                $option->PaymentGateway,
                $fieldFactory->normalizeFormData($data),
                $processor->getReturnUrl()
            );
            if ($response && !$response->isError()) {
                return $response->redirectOrRespond();
            }

            $form->sessionMessage($processor->getError(), 'bad');
            return $this->controller->redirectBack();
        }

        $form->sessionMessage(
            _t(BaseOrderActionsForm::class . '.CouldNotProcessPayment', 'Payment could not be processed.'),
            'bad'
        );
        return $this->controller->redirectBack();
    }
}