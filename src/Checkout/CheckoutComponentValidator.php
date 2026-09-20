<?php

namespace ShopExtensions\Checkout;

use ShopExtensions\Address\StreetAddressExtension;
use ShopExtensions\Checkout\Component\BillingSameAsShippingAddress;
use ShopExtensions\Checkout\Component\OptionalMembership;
use ShopExtensions\Model\PaymentOption;
use SilverShop\Checkout\CheckoutComponentConfig;
use SilverShop\Forms\CheckoutComponentValidator as SilverShopCheckoutComponentValidator;
use SilverShop\Model\Address;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\Omnipay\GatewayInfo;

/**
 * Checkout validator that checks every field BEFORE anything is saved, with the message at the field
 * (opt-in via Injector, see _config/checkout.yml.example):
 *
 *   SilverStripe\Core\Injector\Injector:
 *     SilverShop\Forms\CheckoutComponentValidator:
 *       class: ShopExtensions\Checkout\CheckoutComponentValidator
 *
 * Fixes in silvershop/core 6:
 * - The core constructor never initialises RequiredFieldsValidator's $required, so required fields are
 *   not checked; address components return true from validateData(), and a missing field surfaces as
 *   the untranslatable model exception "Address Model validate function - missing required field".
 * - Component errors lose their field in CheckoutComponentConfig::validateData() (the namespaced field
 *   name ends up as the message CODE) – mapped back here, plus `code_map` for codes that don't match
 *   a form field (default: Membership e-mail → CustomerDetails e-mail).
 *
 * Checks, in this order: required fields (billing fields only when the billing address differs, see
 * {@see BillingSameAsShippingAddress}; the password only when an account is wanted, see
 * {@see OptionalMembership}), e-mail format, postcode per country (when {@see StreetAddressExtension}
 * is registered), payment tile usable and its gateway configured, project checks via the hook
 * updateCheckoutValidation(array $data, CheckoutComponentValidator $validator, bool &$valid), then
 * the components' own validateData().
 *
 * Messages: _t keys of this class ("Sie" defaults), override them in the project lang file.
 */
class CheckoutComponentValidator extends SilverShopCheckoutComponentValidator
{
    public const NS_CUSTOMER = 'SilverShop-Checkout-Component-CustomerDetails_';
    public const NS_SHIPPING = 'SilverShop-Checkout-Component-ShippingAddress_';
    public const NS_BILLING = 'SilverShop-Checkout-Component-BillingAddress_';
    public const NS_MEMBERSHIP = 'SilverShop-Checkout-Component-Membership_';
    public const NS_PAYMENT = 'SilverShop-Checkout-Component-Payment_';

    /**
     * Component message code => form field it belongs to.
     *
     * @config
     */
    private static array $code_map = [
        self::NS_MEMBERSHIP . 'Email' => self::NS_CUSTOMER . 'Email',
    ];

    /**
     * @config
     */
    private static bool $check_email = true;

    public function __construct(CheckoutComponentConfig $checkoutComponentConfig)
    {
        parent::__construct($checkoutComponentConfig);

        // SilverShop's constructor doesn't call RequiredFieldsValidator's → $required is still null.
        $required = $checkoutComponentConfig->getRequiredFields();
        $this->required = array_combine($required, $required) ?: [];
    }

    public function php($data): bool
    {
        $data = is_array($data) ? $data : [];
        $fields = $this->form->Fields();
        $billingSame = self::billingSameAsShipping($data);

        // Billing fields become required only when the billing address differs.
        if (!$billingSame && $fields->dataFieldByName(self::NS_BILLING . BillingSameAsShippingAddress::SAME_FIELD)) {
            $conditional = array_merge(['FirstName', 'Surname'], array_keys((array) Address::singleton()->getRequiredFields()));
            foreach (array_unique($conditional) as $field) {
                if ($fields->dataFieldByName(self::NS_BILLING . $field)) {
                    $this->required[self::NS_BILLING . $field] = self::NS_BILLING . $field;
                }
            }
        }

        // Password only required when an account is wanted.
        if ($fields->dataFieldByName(self::NS_MEMBERSHIP . OptionalMembership::CREATE_FIELD)
            && !empty($data[self::NS_MEMBERSHIP . OptionalMembership::CREATE_FIELD])
            && $fields->dataFieldByName(self::NS_MEMBERSHIP . 'Password')
        ) {
            $this->required[self::NS_MEMBERSHIP . 'Password'] = self::NS_MEMBERSHIP . 'Password';
        }

        $valid = RequiredFieldsValidator::php($data);

        $valid = $this->checkEmail($data) && $valid;
        $valid = $this->checkPostcode($data, self::NS_SHIPPING) && $valid;
        if (!$billingSame) {
            $valid = $this->checkPostcode($data, self::NS_BILLING) && $valid;
        }
        $valid = $this->checkPaymentOption($data) && $valid;

        $this->extend('updateCheckoutValidation', $data, $this, $valid);

        $formMessages = [];
        try {
            $this->config->validateData($data);
        } catch (ValidationException $exception) {
            $codeMap = (array) static::config()->get('code_map');
            foreach ($exception->getResult()->getMessages() as $code => $message) {
                $field = $message['fieldName'] ?? null;
                if (!$field && is_string($code)) {
                    $field = $codeMap[$code] ?? $code;
                }

                if ($field && $fields->dataFieldByName($field)) {
                    if (!$this->fieldHasError($field)) {
                        $this->validationError($field, $message['message'], 'bad');
                    }
                } elseif (!in_array($message['message'], array_column((array) $this->getErrors(), 'message'), true)) {
                    $formMessages[$message['message']] = $message['message'];
                }
                $valid = false;
            }
        }

        if (!$valid) {
            $summary = _t(self::class . '.Summary', 'Bitte prüfen Sie die markierten Angaben.');
            if ($formMessages) {
                $summary .= ' ' . implode(' ', $formMessages);
            }
            $this->form->sessionMessage($summary, 'bad');
        }

        return $valid;
    }

    /**
     * Whether the posted data says "billing address = shipping address".
     */
    public static function billingSameAsShipping(array $data): bool
    {
        return !empty($data[self::NS_BILLING . BillingSameAsShippingAddress::SAME_FIELD]);
    }

    protected function checkEmail(array $data): bool
    {
        $name = self::NS_CUSTOMER . 'Email';
        $value = trim((string) ($data[$name] ?? ''));

        if (!static::config()->get('check_email') || $value === '' || $this->fieldHasError($name) || filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        $this->validationError($name, _t(self::class . '.InvalidEmail', 'Bitte geben Sie eine gültige E-Mail-Adresse an, z. B. name@beispiel.de.'), 'validation');

        return false;
    }

    protected function checkPostcode(array $data, string $namespace): bool
    {
        if (!StreetAddressExtension::isActive()) {
            return true;
        }

        $name = $namespace . 'PostalCode';
        $value = trim((string) ($data[$name] ?? ''));
        if ($value === '' || $this->fieldHasError($name) || !$this->form->Fields()->dataFieldByName($name)) {
            return true;
        }

        $country = (string) ($data[$namespace . 'Country'] ?? '');
        if (StreetAddressExtension::isValidPostcode($country, $value)) {
            return true;
        }

        $this->validationError($name, StreetAddressExtension::postcodeMessage($country), 'validation');

        return false;
    }

    /**
     * A payment option whose gateway isn't configured must fail HERE, not in setData():
     * Checkout::setPaymentMethod() silently returns false and clears the session key.
     */
    protected function checkPaymentOption(array $data): bool
    {
        $name = self::NS_PAYMENT . 'PaymentMethod';
        $field = $this->form->Fields()->dataFieldByName($name);
        $value = $data[$name] ?? null;

        if (!$field || $value === null || $value === '' || $this->fieldHasError($name)) {
            return true;
        }

        $supported = GatewayInfo::getSupportedGateways(false);

        if (class_exists(PaymentOption::class) && ctype_digit((string) $value)) {
            $option = PaymentOption::get()->byID((int) $value);
            $ok = $option && $option->canUse() && isset($supported[$option->PaymentGateway]);
        } else {
            $ok = isset($supported[(string) $value]);
        }

        if ($ok) {
            return true;
        }

        $this->validationError($name, _t(self::class . '.PaymentNotAvailable', 'Die gewählte Zahlart ist nicht verfügbar.'), 'validation');

        return false;
    }
}
