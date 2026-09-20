<?php

namespace ShopExtensions\Checkout\Component;

use ShopExtensions\Security\RequestThrottle;
use SilverShop\Checkout\Checkout;
use SilverShop\Checkout\Component\Membership;
use SilverShop\Extension\MemberExtension;
use SilverShop\Model\Order;
use SilverShop\ShopTools;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\PasswordField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Optional customer account in the checkout: a "create account" checkbox and ONE password field
 * (opt-in via ShopCheckoutComponentConfig.component_classes.membership, see
 * _config/checkout.yml.example).
 *
 * - The e-mail comes from CustomerDetails (the core component renders a second e-mail field).
 * - Without the tick a password (e.g. filled by a password manager) is ignored → guest order.
 * - CheckoutConfig.membership_required (e.g. switched on per request for subscription carts) makes the
 *   account mandatory: the password is then required without the tick.
 * - "Account exists" answers are rate limited per IP (RequestThrottle bucket `checkout-account`), so
 *   the checkout can't be used to probe e-mail addresses.
 *
 * Field names stay those of SilverShop's Membership component:
 * SilverShop-Checkout-Component-Membership_CreateAccount / _Password.
 */
class OptionalMembership extends Membership
{
    public const CREATE_FIELD = 'CreateAccount';

    /**
     * Throttle bucket for the account-exists check ('' = no throttling).
     *
     * @config
     */
    private static string $throttle_bucket = 'checkout-account';

    public function __construct($confirmed = false, $validator = null)
    {
        parent::__construct($confirmed, $validator);
    }

    public function name(): string
    {
        return ShopTools::sanitiseClassName(Membership::class);
    }

    public function getFormFields(Order $order): FieldList
    {
        $fields = FieldList::create();
        if (Security::getCurrentUser()) {
            return $fields;
        }

        $fields->push(CheckboxField::create(self::CREATE_FIELD, _t(self::class . '.CreateAccount', 'Kundenkonto anlegen')));
        $fields->push(
            PasswordField::create('Password', _t(self::class . '.Password', 'Passwort'))
                ->setAttribute('autocomplete', 'new-password')
        );

        return $fields;
    }

    public function getRequiredFields(Order $order): array
    {
        return [];
    }

    /**
     * The tick, or CheckoutConfig.membership_required.
     */
    public static function wantsAccount(array $data): bool
    {
        return Checkout::membership_required() || !empty($data[self::CREATE_FIELD]);
    }

    public function validateData(Order $order, array $data): bool
    {
        if (Security::getCurrentUser() || !self::wantsAccount($data)) {
            return true;
        }

        $result = ValidationResult::create();

        $bucket = (string) \SilverStripe\Core\Config\Config::inst()->get(self::class, 'throttle_bucket');
        $controller = Controller::curr();
        $request = $controller ? $controller->getRequest() : null;
        if ($bucket !== '' && $request && !RequestThrottle::attempt($request, $bucket)) {
            $result->addError(
                _t(self::class . '.TooMany', 'Zu viele Versuche, ein Konto anzulegen. Bitte bestellen Sie ohne Konto oder versuchen Sie es in ein paar Minuten erneut.'),
                ValidationResult::TYPE_ERROR
            );
            throw ValidationException::create($result);
        }

        $password = (string) ($data['Password'] ?? '');
        $idField = Member::config()->get('unique_identifier_field');
        $identifier = (string) ($data[$idField] ?? '');

        if ($identifier !== '' && MemberExtension::get_by_identifier($identifier) instanceof Member) {
            $result->addError(
                _t(self::class . '.MemberExists', 'Mit dieser E-Mail-Adresse gibt es bereits ein Konto. Bitte melden Sie sich an oder bestellen Sie ohne Konto.'),
                ValidationResult::TYPE_ERROR,
                $idField
            );
        }

        if ($password === '') {
            $result->addError(
                _t(self::class . '.PasswordRequired', 'Bitte wählen Sie ein Passwort für Ihr Konto.'),
                ValidationResult::TYPE_ERROR,
                'Password'
            );
        } elseif (!$this->passwordValidator->validate($password, Member::create())->isValid()) {
            $result->addError(
                _t(self::class . '.PasswordWeak', 'Dieses Passwort ist zu unsicher.'),
                ValidationResult::TYPE_ERROR,
                'Password'
            );
        }

        if (!$result->isValid()) {
            throw ValidationException::create($result);
        }

        return true;
    }

    public function setData(Order $order, array $data): Order
    {
        if (Security::getCurrentUser() || !self::wantsAccount($data) || empty($data['Password'])) {
            return $order;
        }

        unset($data[self::CREATE_FIELD]);

        return parent::setData($order, $data);
    }
}
