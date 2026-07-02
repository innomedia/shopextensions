<?php

namespace ShopExtensions\Checkout;

use SilverShop\Checkout\Checkout;
use SilverShop\Checkout\Component\Payment;
use SilverShop\Model\Order;
use SilverShop\ShopTools;
use ShopExtensions\Model\PaymentOption;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\FieldList;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Model\List\ArrayList;

/**
 * Checkout component that replaces SilverShop's plain gateway radio list with configurable
 * payment tiles ({@see PaymentOption}). Each tile is a real radio in a single OptionsetField
 * named "PaymentMethod" — so the selection travels with the normal form submit, works without
 * JavaScript, and needs no AJAX side-channel or hidden second list (the approach ss_oap_web used).
 *
 * On submit it sets the SilverShop gateway from the chosen option's PaymentGateway and persists
 * the option itself on the order (Order.UsedPaymentOptionID). The sub-method code is later
 * forwarded to the provider by {@see \ShopExtensions\ExtendedPurchaseService}.
 *
 * Only wired in when the feature flag is set (see CustomCheckoutComponentConfig).
 */
class PaymentMethodComponent extends Payment
{
    /**
     * Use the SAME namespaced field prefix as the standard SilverShop Payment component, so the
     * checkout template (and its JS), which references the payment field by that exact name,
     * keeps working when this component is swapped in. The field therefore stays
     * "SilverShop-Checkout-Component-Payment_PaymentMethod" instead of taking this class's name.
     * Data round-trips correctly because getData/setData namespacing all go through name().
     *
     * @return string
     */
    public function name(): string
    {
        return ShopTools::sanitiseClassName(Payment::class);
    }

    /**
     * Active, usable payment options whose gateway is actually supported by the site.
     *
     * @return ArrayList
     */
    protected function availableOptions(): ArrayList
    {
        $supported = GatewayInfo::getSupportedGateways();
        $list = ArrayList::create();
        foreach (PaymentOption::get()->filter('Enabled', 1)->sort('Sort ASC') as $option) {
            if ($option->PaymentGateway && isset($supported[$option->PaymentGateway]) && $option->canUse()) {
                $list->push($option);
            }
        }
        return $list;
    }

    /**
     * @param Order $order
     * @return FieldList
     */
    public function getFormFields(Order $order): FieldList
    {
        $options = $this->availableOptions();
        $source = [];
        foreach ($options as $option) {
            $source[$option->ID] = $option->Title;
        }

        $selected = $order->UsedPaymentOptionID ?: key($source);

        $field = PaymentTileField::create('PaymentMethod', _t(__CLASS__ . '.Title', 'Zahlart'), $source, $selected)
            ->setPaymentOptions($options)
            ->setTemplate('ShopExtensions/Checkout/PaymentTileField');

        return FieldList::create($field);
    }

    /**
     * @param Order $order
     * @return array
     */
    public function getRequiredFields(Order $order): array
    {
        return ['PaymentMethod'];
    }

    /**
     * @param Order $order
     * @param array $data
     * @return bool
     */
    public function validateData(Order $order, array $data): bool
    {
        $result = ValidationResult::create();

        if (empty($data['PaymentMethod'])) {
            $result->addError(_t(__CLASS__ . '.NoPaymentMethod', 'Bitte wählen Sie eine Zahlart.'), 'PaymentMethod');
            throw ValidationException::create($result);
        }

        $option = PaymentOption::get()->byID($data['PaymentMethod']);
        $supported = GatewayInfo::getSupportedGateways();

        if (!$option || !$option->canUse() || !isset($supported[$option->PaymentGateway])) {
            $result->addError(_t(__CLASS__ . '.InvalidPaymentMethod', 'Die gewählte Zahlart ist nicht verfügbar.'), 'PaymentMethod');
            throw ValidationException::create($result);
        }

        return true;
    }

    /**
     * @param Order $order
     * @param array $data
     * @return Order
     */
    public function setData(Order $order, array $data): Order
    {
        if (empty($data['PaymentMethod'])) {
            return $order;
        }

        $option = PaymentOption::get()->byID($data['PaymentMethod']);
        if (!$option) {
            return $order;
        }

        // Set the SilverShop gateway so downstream payment creation works unchanged...
        Checkout::get($order)->setPaymentMethod($option->PaymentGateway);
        // ...and persist the concrete option (incl. sub-method) on the order.
        $order->UsedPaymentOptionID = $option->ID;
        if ($order->isInDB()) {
            $order->write();
        }

        return $order;
    }

    /**
     * @param Order $order
     * @return array
     */
    public function getData(Order $order): array
    {
        return ['PaymentMethod' => $order->UsedPaymentOptionID];
    }
}
