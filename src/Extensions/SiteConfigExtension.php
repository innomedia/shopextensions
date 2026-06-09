<?php

namespace ShopExtensions;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;

class SiteConfigExtension extends Extension
{
    private static $db = [
        'CostHint' => 'Text',
        'HintPayment' => 'HTMLText',
        'HintAfterPayment' => 'HTMLText',
        'ReceiptHeader' => 'HTMLText',
        'ReceiptFooter' => 'HTMLText',
        'AdminNotificationMail' => 'Text',
    ];

    private static $has_one = [
        'ReceiptLogo' => Image::class,
    ];

    /**
     * Configure which fields to show in CMS
     * Set to empty array to hide all, or specify field names to show
     * Default: all fields are shown
     */
    private static array $enabled_fields = [
        'CostHint',
        'AdminNotificationMail',
        'ReceiptHeader',
        'ReceiptFooter',
        'ReceiptLogo',
        'HintPayment',
        'HintAfterPayment',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $enabledFields = $this->owner->config()->get('enabled_fields');

        if (in_array('CostHint', $enabledFields)) {
            $fields->addFieldToTab('Root.Produkte', TextareaField::create('CostHint', 'Hinweis bei Preis'));
        }

        if (in_array('AdminNotificationMail', $enabledFields)) {
            $fields->addFieldToTab('Root.Bestellabschluss', TextField::create('AdminNotificationMail', 'Admin Benachrichtigungsmail')
                ->setDescription('An diese E-Mail Adresse wird eine Benachrichtigung gesendet, sobald eine Bestellung getätigt wurde.'));
        }

        if (in_array('ReceiptHeader', $enabledFields)) {
            $fields->addFieldToTab('Root.Bestellabschluss', TextField::create('ReceiptHeader', 'Adresszeile Absender'));
        }

        if (in_array('ReceiptFooter', $enabledFields)) {
            $fields->addFieldToTab('Root.Bestellabschluss', HTMLEditorField::create('ReceiptFooter', 'Fußzeile'));
        }

        if (in_array('ReceiptLogo', $enabledFields)) {
            $fields->addFieldToTab('Root.Bestellabschluss', UploadField::create('ReceiptLogo', 'Logo'));
        }

        if (in_array('HintPayment', $enabledFields)) {
            $fields->addFieldToTab('Root.Bestellabschluss', HTMLEditorField::create('HintPayment', 'Hinweis Payment'));
        }

        if (in_array('HintAfterPayment', $enabledFields)) {
            $fields->addFieldToTab('Root.Bestellabschluss', HTMLEditorField::create('HintAfterPayment', 'Hinweis nach Bestellabschluss'));
        }

        return $fields;
    }
}