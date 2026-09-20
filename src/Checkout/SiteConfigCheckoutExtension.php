<?php

namespace ShopExtensions\Checkout;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\ORM\DB;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Checkout settings on SiteConfig (tab Shop → Kasse), opt-in, see _config/checkout.yml.example:
 *
 * - Legal pages for the terms checkbox: CheckoutWithdrawalPage (Widerrufsbelehrung) and
 *   CheckoutPrivacyPage (Datenschutzerklärung). The terms page itself is SilverShop's TermsPage. The
 *   label with links is built by CheckoutPageControllerExtension when `terms_label_with_legal_links`
 *   is on.
 * - One-time migration `migrate_send_receipt_email` (default off): SiteConfig.SendReceiptEmail is the
 *   master switch for invoice PDFs in all order mails, but it is 0 on SiteConfig records that existed
 *   before the field was added. When enabled, dev/build switches it on ONCE per record (marker
 *   CheckoutDefaultsVersion), so an editor who switches it off later keeps that choice.
 *
 * Column names match Ghee\Shop\Extensions\CheckoutSiteConfigExtension (no data migration; remove the
 * project copy in the same step).
 *
 * @property SiteConfig $owner
 * @property int $CheckoutDefaultsVersion
 */
class SiteConfigCheckoutExtension extends Extension
{
    private const DEFAULTS_VERSION = 1;

    private static array $db = [
        'CheckoutDefaultsVersion' => 'Int',
    ];

    private static array $has_one = [
        'CheckoutWithdrawalPage' => SiteTree::class,
        'CheckoutPrivacyPage' => SiteTree::class,
    ];

    /**
     * @config
     */
    private static bool $migrate_send_receipt_email = false;

    public function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName(['CheckoutDefaultsVersion', 'CheckoutWithdrawalPageID', 'CheckoutPrivacyPageID']);

        $tab = $fields->fieldByName('Root.Shop.ShopTabs') ? 'Root.Shop.ShopTabs.Checkout' : 'Root.Shop.Checkout';
        $fields->findOrMakeTab($tab)->setTitle(_t(self::class . '.Tab', 'Kasse'));
        $fields->addFieldsToTab($tab, [
            TreeDropdownField::create('CheckoutWithdrawalPageID', _t(self::class . '.Withdrawal', 'Seite Widerrufsbelehrung'), SiteTree::class)
                ->setDescription(_t(self::class . '.WithdrawalHint', 'Wird neben den AGB im Bestell-Häkchen verlinkt. Die AGB-Seite wählen Sie im Reiter „Allgemein".')),
            TreeDropdownField::create('CheckoutPrivacyPageID', _t(self::class . '.Privacy', 'Seite Datenschutzerklärung'), SiteTree::class)
                ->setDescription(_t(self::class . '.PrivacyHint', 'Wird im Bestell-Häkchen verlinkt.')),
        ]);
    }

    public function onRequireDefaultRecords(): void
    {
        if (!$this->getOwner()->config()->get('migrate_send_receipt_email')) {
            return;
        }
        if (!$this->getOwner()->hasField('SendReceiptEmail')) {
            return;
        }

        foreach (SiteConfig::get() as $siteConfig) {
            if ((int) $siteConfig->CheckoutDefaultsVersion >= self::DEFAULTS_VERSION) {
                continue;
            }
            $siteConfig->SendReceiptEmail = true;
            $siteConfig->CheckoutDefaultsVersion = self::DEFAULTS_VERSION;
            $siteConfig->write();

            DB::alteration_message('SiteConfig: invoice attachment in order mails switched on (SendReceiptEmail = 1).', 'changed');
        }
    }
}
