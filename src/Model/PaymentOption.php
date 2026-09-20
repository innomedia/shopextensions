<?php

namespace ShopExtensions\Model;

use SilverShop\Model\Order;
use SilverStripe\Assets\Image;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Security\Permission;

/**
 * A selectable payment tile shown in the checkout when the payment-tiles feature is enabled.
 *
 * Each option maps a visible tile (title + icon) onto a payment gateway and, optionally, a
 * gateway sub-method code (e.g. the Mollie "method" such as ideal, creditcard, paypal). The
 * gateway is what SilverShop's checkout uses to create the payment; the sub-method is forwarded
 * to the provider by {@see \ShopExtensions\ExtendedPurchaseService} so the provider can skip its
 * own method-selection screen.
 *
 * Managed via a GridField in the SiteConfig "Shop" tab (see SiteConfigPaymentTilesExtension).
 * OPT-IN: this class ships always, but is only used when the feature flag is set.
 *
 * @property string $PaymentGateway
 * @property string $PaymentMethod
 * @property string $Title
 * @property int    $Sort
 * @property bool   $Enabled
 * @property int    $ImageID
 * @method   Image  Image()
 */
class PaymentOption extends DataObject
{
    private static $table_name = 'ShopExtensions_PaymentOption';

    private static $db = [
        'PaymentGateway' => 'Varchar(100)',
        'PaymentMethod' => 'Varchar(100)',
        'Title' => 'Varchar(255)',
        'Sort' => 'Int',
        'Enabled' => 'Boolean(1)',
        // Whether an order using this tile gets its invoice number already at placement
        // (Cart→Unpaid), not only at Paid. Meant for "on account"/bank transfer (esp. B2B),
        // where the customer needs the invoice number to reference the payment. Instant methods
        // (card, iDEAL) stay unset → number only on Paid, so an aborted order never burns a
        // number. An option flagged here is "number-bearing" and is therefore excluded from the
        // automatic stale-Unpaid cancellation ({@see \ShopExtensions\Tasks\CancelStaleUnpaidOrdersTask}).
        'InvoiceOnPlacement' => 'Boolean(0)',
        // One sentence under the tile title in the checkout ("Sie überweisen nach der Bestellung …").
        // Rendered by the tile template and linked to the radio with aria-describedby.
        'TileDescription' => 'Varchar(160)',
    ];

    /**
     * Gateway parameters that must be configured before a tile of that gateway is offered, e.g.
     * {Mollie: [apiKey]}. Without them the customer would only fail AFTER clicking "order now".
     * Empty by default (no gateway is checked).
     *
     * @config
     * @var array
     */
    private static $required_gateway_parameters = [];

    /**
     * Known sub-methods per gateway as code => label, e.g.
     * {Mollie: {creditcard: Kreditkarte, paypal: PayPal}}. When the selected gateway has an entry,
     * the CMS shows a dropdown instead of the free-text provider code. A value that is not in the
     * list is kept as an extra option, so nothing is lost.
     *
     * @config
     * @var array
     */
    private static $gateway_methods = [];

    private static $has_one = [
        'Image' => Image::class,
        'SiteConfig' => \SilverStripe\SiteConfig\SiteConfig::class,
    ];

    private static $owns = [
        'Image',
    ];

    private static $default_sort = 'Sort ASC';

    private static $defaults = [
        'Enabled' => 1,
    ];

    private static $summary_fields = [
        'Title' => 'Titel',
        'PaymentGateway' => 'Gateway',
        'PaymentMethod' => 'Methode',
        'Enabled.Nice' => 'Aktiv',
    ];

    /**
     * @return \SilverStripe\Forms\FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['Sort', 'SiteConfigID']);

        $gateways = GatewayInfo::getSupportedGateways();

        $fields->replaceField('PaymentGateway', DropdownField::create('PaymentGateway', 'Gateway', $gateways)
            ->setEmptyString('– Gateway wählen –')
            ->setDescription('Das SilverShop-/Omnipay-Gateway, über das gezahlt wird (z. B. „Mollie", „PayPal_Express").'));

        $fields->dataFieldByName('PaymentMethod')
            ->setTitle('Methoden-Code (optional)')
            ->setDescription('Nur bei Gateways mit Untermethoden (z. B. Mollie): der Provider-Code wie „ideal", „creditcard", „paypal", „sofort". Leer lassen, wenn das Gateway keine Untermethode hat.');

        // Known sub-methods as a dropdown (config gateway_methods); unknown/empty gateway keeps the
        // free-text field. A stored code that is not in the list stays selectable.
        $methods = (array) ($this->owner->config()->get('gateway_methods')[$this->owner->PaymentGateway] ?? []);
        if ($methods) {
            $current = (string) $this->owner->PaymentMethod;
            if ($current !== '' && !isset($methods[$current])) {
                $methods[$current] = $current;
            }
            $fields->replaceField('PaymentMethod', DropdownField::create(
                'PaymentMethod',
                'Zahlungsmethode beim Anbieter',
                $methods
            )->setEmptyString('– keine (Anbieter ohne Untermethode, z. B. Vorkasse) –'));
        }

        $fields->insertBefore('Image', TextField::create('TileDescription', 'Kurzbeschreibung im Checkout')
            ->setDescription('Optional, ein Satz unter dem Titel der Kachel (max. 160 Zeichen).')
            ->setMaxLength(160));

        $fields->dataFieldByName('Title')
            ->setDescription('Anzeigename der Kachel im Checkout, z. B. „Kreditkarte" oder „PayPal".');

        if ($image = $fields->dataFieldByName('Image')) {
            $image->setTitle('Icon')->setDescription('Optionales Icon/Logo der Zahlart.');
        }

        $fields->dataFieldByName('InvoiceOnPlacement')
            ->setTitle('Rechnungsnummer schon bei Bestellung vergeben')
            ->setDescription('Für „auf Rechnung"/Überweisung (v. a. B2B): die Rechnungsnummer wird bereits '
                . 'bei der Bestellung (Platzierung) vergeben, damit der Kunde referenziert überweisen kann. '
                . 'Für Sofort-Zahlarten (Kreditkarte, iDEAL, SEPA) leer lassen – dort entsteht die Nummer erst '
                . 'bei bezahlter Bestellung, damit abgebrochene Zahlungen keine Nummer verbrauchen. '
                . 'Achtung: so markierte Zahlarten werden vom automatischen Storno liegengebliebener '
                . 'Bestellungen ausgenommen (gehören ins Mahnwesen).');

        return $fields;
    }

    /**
     * Whether this option may be offered to the customer.
     *
     * Checks the Enabled flag and the gateway credentials
     * ({@see self::$required_gateway_parameters}), then asks the extensions. The order is passed on
     * to the hook so a rule can look at the cart (e.g. only mandate-capable methods for a
     * subscription order); it is optional, because not every caller has one.
     *
     * Extension: `public function canUse(?Order $order = null): ?bool` — return false to hide, null
     * to abstain (never true, that would overrule other rules).
     */
    public function canUse(?Order $order = null): bool
    {
        if (!$this->Enabled) {
            return false;
        }
        if (!$this->gatewayConfigured()) {
            return false;
        }
        $result = $this->extend('canUse', $order);
        if (in_array(false, $result, true)) {
            return false;
        }
        return true;
    }

    /**
     * Whether every gateway parameter required for this tile's gateway is filled in
     * ({@see self::$required_gateway_parameters}).
     */
    public function gatewayConfigured(): bool
    {
        $gateway = (string) $this->PaymentGateway;
        $all = (array) $this->config()->get('required_gateway_parameters');
        $required = (array) ($all[$gateway] ?? []);
        if (!$required) {
            return true;
        }

        $parameters = (array) GatewayInfo::getParameters($gateway);
        foreach ($required as $key) {
            if (trim((string) ($parameters[$key] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    public function canView($member = null)
    {
        return true;
    }

    public function canEdit($member = null)
    {
        return Permission::check('CMS_ACCESS', 'any', $member);
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check('CMS_ACCESS', 'any', $member);
    }

    public function canDelete($member = null)
    {
        return Permission::check('CMS_ACCESS', 'any', $member);
    }
}
