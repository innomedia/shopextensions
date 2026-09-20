<?php

namespace ShopExtensions\Delivery;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;

/**
 * SiteConfig fields for {@see DeliveryEstimate}: DispatchLeadDays (business days until dispatch) and
 * DeliveryDays (business days the carrier needs). Opt-in, see _config/delivery.yml.example.
 *
 * Config:
 *   cms_tab         tab the fields are added to
 *   add_cms_fields  false when the project places the two fields itself (same column names)
 *
 * @property \SilverStripe\SiteConfig\SiteConfig $owner
 * @property int $DispatchLeadDays
 * @property int $DeliveryDays
 */
class SiteConfigDeliveryExtension extends Extension
{
    private static array $db = [
        'DispatchLeadDays' => 'Int(1)',
        'DeliveryDays' => 'Int(2)',
    ];

    private static string $cms_tab = 'Root.Shop.ShopTabs.Delivery';

    private static bool $add_cms_fields = true;

    public function updateCMSFields(FieldList $fields): void
    {
        // add_cms_fields false = the project places the two fields itself (same column names). Don't
        // touch the FieldList at all then: extensions run in registration order, and removing the
        // fields here would also remove the ones the project just added.
        if (!$this->getOwner()->config()->get('add_cms_fields')) {
            return;
        }

        $fields->removeByName(['DispatchLeadDays', 'DeliveryDays']);

        $tab = (string) $this->getOwner()->config()->get('cms_tab');
        if (!$fields->fieldByName('Root.Shop.ShopTabs')) {
            $tab = str_replace('Root.Shop.ShopTabs.', 'Root.Shop.', $tab);
        }
        $fields->findOrMakeTab($tab, _t(self::class . '.Tab', 'Lieferzeit'));
        $fields->addFieldsToTab($tab, [
            NumericField::create('DispatchLeadDays', _t(self::class . '.DispatchLeadDays', 'Versand nach … Werktagen'))
                ->setDescription(_t(self::class . '.DispatchLeadDaysHint', '0 = Versand am Bestelltag, 1 = am nächsten Werktag.')),
            NumericField::create('DeliveryDays', _t(self::class . '.DeliveryDays', 'Laufzeit des Versanddienstleisters (Werktage)')),
        ]);
    }
}
