<?php

namespace ShopExtensions\Checkout\Component;

use ShopExtensions\Address\StreetAddressExtension;
use SilverShop\Checkout\Component\BillingAddress;
use SilverShop\Model\Order;
use SilverShop\ShopTools;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;

/**
 * Billing address with a "same as shipping address" checkbox (opt-in via
 * ShopCheckoutComponentConfig.component_classes.billing_address, see _config/checkout.yml.example).
 *
 * Ticked: no billing field is required and the order's billing address becomes the shipping address.
 * Unticked: the billing address fields are required – validated here, because a form's required-field
 * list is static and can't depend on another field's value. Decided on the server, works without JS.
 * Orders without shipping always use the entered billing address as before.
 *
 * Field names stay those of SilverShop's BillingAddress component (name() override), so templates and
 * validators address them predictably: SilverShop-Checkout-Component-BillingAddress_SameAsShipping.
 */
class BillingSameAsShippingAddress extends BillingAddress
{
    public const SAME_FIELD = 'SameAsShipping';

    /**
     * Address fields to blank in getData() while the checkbox is ticked (the hidden billing block must
     * not be prefilled with the shipping address).
     */
    private const PREFILL_FIELDS = ['FirstName', 'Surname', 'Company', 'Address', 'StreetName', 'HouseNumber', 'AddressLine2', 'PostalCode', 'City', 'State', 'Phone'];

    public function name(): string
    {
        return ShopTools::sanitiseClassName(BillingAddress::class);
    }

    public function getFormFields(Order $order): FieldList
    {
        $fields = parent::getFormFields($order);

        if ($order->requiresShipping()) {
            $fields->unshift(
                CheckboxField::create(self::SAME_FIELD, _t(self::class . '.SameAsShipping', 'Rechnungsadresse wie Lieferadresse'))
            );
        }

        return $fields;
    }

    public function getRequiredFields(Order $order): array
    {
        return $order->requiresShipping() ? [] : parent::getRequiredFields($order);
    }

    /**
     * Address fields that must be filled when the billing address differs.
     */
    public function getConditionalRequiredFields(Order $order): array
    {
        return array_values(array_unique(array_merge(['FirstName', 'Surname'], parent::getRequiredFields($order))));
    }

    public static function isSameAsShipping(array $data): bool
    {
        return !empty($data[self::SAME_FIELD]);
    }

    public function validateData(Order $order, array $data): bool
    {
        if (self::isSameAsShipping($data) || !$order->requiresShipping()) {
            return true;
        }

        $result = ValidationResult::create();

        foreach ($this->getConditionalRequiredFields($order) as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                $result->addError(_t(self::class . '.Required', 'Bitte füllen Sie dieses Feld aus.'), ValidationResult::TYPE_ERROR, $field);
            }
        }

        if (StreetAddressExtension::isActive()
            && !empty($data['PostalCode'])
            && !StreetAddressExtension::isValidPostcode($data['Country'] ?? null, $data['PostalCode'])
        ) {
            $result->addError(StreetAddressExtension::postcodeMessage($data['Country'] ?? null), ValidationResult::TYPE_ERROR, 'PostalCode');
        }

        if (!$result->isValid()) {
            throw ValidationException::create($result);
        }

        return true;
    }

    public function getData(Order $order): array
    {
        $data = parent::getData($order);
        $data[self::SAME_FIELD] = !$order->BillingAddressID
            || !$order->ShippingAddressID
            || (int) $order->BillingAddressID === (int) $order->ShippingAddressID;

        if ($data[self::SAME_FIELD]) {
            foreach (self::PREFILL_FIELDS as $key) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    public function setData(Order $order, array $data): Order
    {
        if ((self::isSameAsShipping($data) || !$order->requiresShipping()) && $order->ShippingAddressID) {
            $order->BillingAddressID = $order->ShippingAddressID;
            $order->write();

            return $order;
        }

        unset($data[self::SAME_FIELD]);

        // A billing address that is still the shipping address record must not be edited in place.
        if ((int) $order->BillingAddressID === (int) $order->ShippingAddressID) {
            $order->BillingAddressID = 0;
        }

        return parent::setData($order, $data);
    }
}
