<?php

namespace ShopExtensions;

use ShopExtensions\Checkout\PaymentMethodComponent;
use ShopExtensions\Checkout\PaymentTileField;
use SilverShop\Model\Order;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;

/**
 * Extends SilverShop's {@see \SilverShop\Forms\OrderActionsForm} (the "pay an open order" form on
 * the account page) so a customer re-paying an unpaid order sees the SAME payment tiles as in the
 * checkout — SEPA/iDEAL/card — instead of the raw gateway radio list.
 *
 * Hooked in via the form's updateForm extension point. It swaps the plain "PaymentMethod"
 * OptionsetField for our {@see PaymentTileField}, fed from the shared
 * {@see PaymentMethodComponent::availablePaymentOptions()}. The tile value is a PaymentOption ID;
 * the paired {@see \ShopExtensions\Forms\OrderActionsForm} subclass resolves it back to a gateway
 * (and persists Order.UsedPaymentOptionID so ExtendedPurchaseService forwards the Mollie sub-method).
 *
 * OPT-IN and self-guarding: if no usable PaymentOption exists (feature off / none configured), the
 * default gateway list is left untouched, so the customer can always pay.
 *
 * @property \SilverShop\Forms\OrderActionsForm $owner
 */
class OrderActionsFormExtension extends Extension
{
    /**
     * @param Order $order The order the form acts on (passed by OrderActionsForm::__construct).
     */
    public function updateForm(Order $order)
    {
        $form = $this->owner;

        // The payment field only exists when the order is payable and non-manual gateways remain.
        $existing = $form->Fields()->dataFieldByName('PaymentMethod');
        if (!$existing) {
            return;
        }

        $options = PaymentMethodComponent::availablePaymentOptions();
        if (!$options->count()) {
            // No tiles configured → keep SilverShop's default gateway radio list (fallback).
            return;
        }

        $source = [];
        foreach ($options as $option) {
            $source[$option->ID] = $option->Title;
        }
        $selected = $order->UsedPaymentOptionID ?: key($source);

        $tile = PaymentTileField::create('PaymentMethod', $existing->Title(), $source, $selected)
            ->setPaymentOptions($options)
            ->setTemplate('ShopExtensions/Checkout/PaymentTileField');

        $form->Fields()->replaceField('PaymentMethod', $tile);

        // The tile value is a PaymentOption ID, not a gateway name. SilverShop's
        // OrderActionsFormValidator would treat the numeric value as an onsite gateway and demand
        // credit-card fields. Our tiles resolve to offsite gateways (Mollie redirect), so a plain
        // required-field validator is correct and sufficient here.
        $form->setValidator(RequiredFieldsValidator::create('PaymentMethod'));
    }
}