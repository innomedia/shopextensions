<?php

namespace ShopExtensions\Address;

use SilverShop\Cart\ShoppingCart;
use SilverShop\Model\Address;
use SilverShop\Model\Order;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;

/**
 * Address rules for German-speaking shops (opt-in, see _config/streetaddress.yml.example):
 *
 * 1. Street and house number are SEPARATE fields (`StreetName`, `HouseNumber`), as carriers (DHL/UPS
 *    label APIs) want them. The core single line `Address` is kept in sync in onBeforeWrite
 *    ("Hauptstraße 12"), so templates, invoices, payment data and shipping modules keep working. The
 *    column is called StreetName because Address::setStreet() is an alias setter for `Address` – a
 *    db field "Street" would never be written.
 * 2. Required fields come from `required_address_fields` (default Country, StreetName, HouseNumber,
 *    PostalCode, City); `drop_fields` (default State, Phone) are removed from the form.
 * 3. Postcodes are validated per country (`postcode_patterns`, default DE 5 digits, AT 4 digits), in
 *    the checkout validator and as a model safety net (every write path, also CMS/imports).
 * 4. Country dropdown: `default_country` preselected and sorted first.
 * 5. Empty first/last name are taken from the current cart (`fill_names_from_cart`, only in a web
 *    request – safe in CLI/queued jobs).
 * 6. Addresses that only have the single line (address book from before) are split for the form.
 *
 * Messages: _t keys ShopExtensions\Address\StreetAddressExtension.Postcode_DE / Postcode_AT, other
 * countries PostcodeInvalid.
 *
 * Replaces Ghee\Shop\Extensions\ShopAddressExtension (same column names, no data migration). Don't
 * register it together with another extension that implements updateRequiredFields.
 *
 * @property Address $owner
 * @property string $StreetName
 * @property string $HouseNumber
 */
class StreetAddressExtension extends Extension
{
    private static array $db = [
        'StreetName' => 'Varchar(200)',
        'HouseNumber' => 'Varchar(30)',
    ];

    private static array $required_address_fields = [
        'Country',
        'StreetName',
        'HouseNumber',
        'PostalCode',
        'City',
    ];

    private static array $drop_fields = ['State', 'Phone'];

    private static string $default_country = 'DE';

    /**
     * ISO country code => regular expression without delimiters.
     */
    private static array $postcode_patterns = [
        'DE' => '^[0-9]{5}$',
        'AT' => '^[0-9]{4}$',
    ];

    private static bool $fill_names_from_cart = true;

    public static function postcodePattern(?string $country): ?string
    {
        $patterns = (array) Address::config()->get('postcode_patterns');

        return $patterns[strtoupper((string) $country)] ?? null;
    }

    public static function isValidPostcode(?string $country, ?string $postcode): bool
    {
        $pattern = self::postcodePattern($country);

        return $pattern === null || preg_match('/' . $pattern . '/', trim((string) $postcode)) === 1;
    }

    public static function postcodeMessage(?string $country): string
    {
        $code = strtoupper((string) $country);

        return match ($code) {
            'DE' => _t(self::class . '.Postcode_DE', 'Bitte geben Sie eine gültige Postleitzahl mit 5 Ziffern an.'),
            'AT' => _t(self::class . '.Postcode_AT', 'Bitte geben Sie eine gültige Postleitzahl mit 4 Ziffern an.'),
            default => _t(self::class . '.PostcodeInvalid', 'Bitte geben Sie eine gültige Postleitzahl an.'),
        };
    }

    /**
     * Whether the extension is registered on the Address model.
     */
    public static function isActive(): bool
    {
        return Address::has_extension(self::class);
    }

    public function updateCountryField(DropdownField $field): void
    {
        $source = (array) $field->getSource();
        $default = (string) $this->getOwner()->config()->get('default_country');

        if ($default !== '' && isset($source[$default])) {
            $field->setSource([$default => $source[$default]] + $source);
        }

        $field->setEmptyString('');
        $field->setHasEmptyDefault(false);

        if (!$field->getValue() && $default !== '' && array_key_exists($default, $source)) {
            $field->setValue($default);
        }
    }

    public function updateFormFields(FieldList $fields): void
    {
        $owner = $this->getOwner();

        // shopextensions' AddressExtension inserts Company a second time → keep exactly one.
        while ($fields->dataFieldByName('Company')) {
            $fields->removeByName('Company', true);
        }

        $fields->removeByName(array_merge((array) $owner->config()->get('drop_fields'), ['Address']), true);

        $street = TextField::create('StreetName', _t(self::class . '.StreetName', 'Straße'))->setMaxLength(200);
        $number = TextField::create('HouseNumber', _t(self::class . '.HouseNumber', 'Hausnummer'))->setMaxLength(30);
        $company = TextField::create('Company', _t(self::class . '.Company', 'Firma'))->setMaxLength(100);

        if ($fields->dataFieldByName('AddressLine2')) {
            $fields->insertBefore('AddressLine2', $street);
            $fields->insertBefore('AddressLine2', $number);
        } else {
            $fields->push($street);
            $fields->push($number);
        }
        $fields->insertBefore('StreetName', $company);

        $titles = [
            'AddressLine2' => _t(self::class . '.AddressLine2', 'Adresszusatz'),
            'City' => _t(self::class . '.City', 'Ort'),
            'PostalCode' => _t(self::class . '.PostalCode', 'PLZ'),
            'FirstName' => _t(self::class . '.FirstName', 'Vorname'),
            'Surname' => _t(self::class . '.Surname', 'Nachname'),
            'Country' => _t(self::class . '.Country', 'Land'),
        ];
        foreach ($titles as $name => $title) {
            if ($field = $fields->dataFieldByName($name)) {
                $field->setTitle($title);
                if (in_array($name, ['AddressLine2', 'City'], true)) {
                    $field->setDescription(null);
                }
            }
        }

        if (!$owner->StreetName && $owner->Address) {
            [$streetName, $houseNumber] = self::splitStreetLine((string) $owner->Address);
            $street->setValue($streetName);
            $number->setValue($houseNumber);
        }
    }

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName('Address');
        $before = $fields->dataFieldByName('AddressLine2') ? 'AddressLine2' : null;
        $fields->addFieldToTab('Root.Main', TextField::create('StreetName', _t(self::class . '.StreetName', 'Straße')), $before);
        $fields->addFieldToTab('Root.Main', TextField::create('HouseNumber', _t(self::class . '.HouseNumber', 'Hausnummer')), $before);
    }

    public function updateRequiredFields(array &$fields): void
    {
        $required = array_values((array) $this->getOwner()->config()->get('required_address_fields'));
        $fields = array_combine($required, $required) ?: [];
    }

    public function updateFieldLabels(&$labels): void
    {
        $labels['StreetName'] = _t(self::class . '.StreetName', 'Straße');
        $labels['HouseNumber'] = _t(self::class . '.HouseNumber', 'Hausnummer');
        $labels['PostalCode'] = _t(self::class . '.PostalCode', 'PLZ');
        $labels['City'] = _t(self::class . '.City', 'Ort');
        $labels['Country'] = _t(self::class . '.Country', 'Land');
        $labels['AddressLine2'] = _t(self::class . '.AddressLine2', 'Adresszusatz');
        $labels['Company'] = _t(self::class . '.Company', 'Firma');
        $labels['FirstName'] = _t(self::class . '.FirstName', 'Vorname');
        $labels['Surname'] = _t(self::class . '.Surname', 'Nachname');
    }

    /**
     * Model-level safety net; the checkout validator reports this earlier with field messages.
     */
    public function updateValidate(ValidationResult $result): void
    {
        $owner = $this->getOwner();
        if ($owner->PostalCode && !self::isValidPostcode($owner->Country, $owner->PostalCode)) {
            $result->addFieldError('PostalCode', self::postcodeMessage($owner->Country));
        }
    }

    protected function onBeforeWrite(): void
    {
        $address = $this->getOwner();

        foreach (['StreetName', 'HouseNumber', 'PostalCode', 'City', 'AddressLine2', 'Company', 'FirstName', 'Surname'] as $field) {
            if (is_string($address->$field)) {
                $address->$field = trim(preg_replace('/\s+/u', ' ', $address->$field) ?? $address->$field);
            }
        }

        if ($address->StreetName) {
            // setField(): Address::setStreet() & co. are alias setters – write the column directly.
            $address->setField('Address', trim($address->StreetName . ' ' . $address->HouseNumber));
        } elseif ($address->Address && !$address->HouseNumber) {
            [$streetName, $houseNumber] = self::splitStreetLine((string) $address->Address);
            $address->setField('StreetName', $streetName);
            $address->setField('HouseNumber', $houseNumber);
        }

        if ($address->FirstName || $address->Surname || !$address->config()->get('fill_names_from_cart')) {
            return;
        }

        // Only inside a web request: ShoppingCart::curr() needs the session.
        if (!Controller::curr()) {
            return;
        }
        try {
            $order = ShoppingCart::curr();
        } catch (\Throwable) {
            return;
        }
        if ($order instanceof Order) {
            $address->FirstName = $order->FirstName;
            $address->Surname = $order->Surname;
        }
    }

    /**
     * "Hauptstraße 12a" → ["Hauptstraße", "12a"]. Without a recognisable number the whole line is the
     * street.
     *
     * @return array{0:string,1:string}
     */
    public static function splitStreetLine(string $line): array
    {
        $line = trim($line);
        if (preg_match('/^(.*?\S)\s+(\d+\s?[a-zA-Z]?(?:\s*[-\/]\s*[\w .]+)*)$/u', $line, $m)) {
            return [trim($m[1], ' ,'), trim($m[2])];
        }

        return [$line, ''];
    }
}
