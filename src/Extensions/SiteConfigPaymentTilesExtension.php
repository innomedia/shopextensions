<?php

namespace ShopExtensions;

use ShopExtensions\Model\PaymentOption;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

/**
 * Adds a "Zahlarten" GridField to the SiteConfig "Shop" tab for managing the checkout payment
 * tiles ({@see PaymentOption}) — gateway, optional sub-method code, title, icon and sort order.
 *
 * OPT-IN: registered only via _config/paymenttiles.yml.example and additionally gated behind the
 * {@see self::$enabled} flag, so the tab is only shown where the feature is actually used.
 *
 * @property \SilverStripe\SiteConfig\SiteConfig $owner
 */
class SiteConfigPaymentTilesExtension extends Extension
{
    /**
     * Whether the "Zahlarten" management tab is shown.
     *
     * @config
     * @var bool
     */
    private static $enabled = false;

    private static $has_many = [
        'PaymentOptions' => PaymentOption::class,
    ];

    /**
     * @param FieldList $fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        // Read this extension's own config (not the owner's) to avoid colliding with the
        // identically-named `enabled` flag on SiteConfigExportExtension.
        if (!Config::inst()->get(static::class, 'enabled')) {
            return $fields;
        }

        // Add a sub-tab inside silvershop's existing Root.Shop.ShopTabs TabSet. Must run AFTER
        // silvershop's ShopConfigExtension (ordering set via `After: silvershop/config#shopconfig`
        // in paymenttiles.yml), else findOrMakeTab auto-creates the path and duplicates "Shop".
        $tab = 'Root.Shop.ShopTabs.PaymentTiles';
        $fields->findOrMakeTab($tab)->setTitle('Zahlarten');
        $fields->addFieldToTab($tab, HeaderField::create('PaymentTilesHeader', 'Zahlarten im Checkout', 3));
        $fields->addFieldToTab($tab, LiteralField::create(
            'PaymentTilesHint',
            '<p class="message notice">Diese Kacheln werden dem Kunden im Checkout zur Auswahl angezeigt '
            . '(nur wenn <code>use_payment_tiles</code> aktiv ist). Der „Methoden-Code" (z. B. Mollie '
            . '<code>ideal</code>, <code>creditcard</code>) wird an den Zahlungsanbieter durchgereicht, '
            . 'damit dessen eigener Auswahlbildschirm übersprungen wird.</p>'
        ));

        $config = GridFieldConfig_RecordEditor::create();
        $config->addComponent(GridFieldOrderableRows::create('Sort'));

        $grid = GridField::create('PaymentOptions', 'Zahlarten', $this->owner->PaymentOptions(), $config);
        $fields->addFieldToTab($tab, $grid);

        return $fields;
    }
}
