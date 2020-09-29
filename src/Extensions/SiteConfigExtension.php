<?php

namespace ShopExtensions;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TreeDropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\FontAwesome\FontAwesomeField;

class SiteConfigExtension extends DataExtension
{
    private static $db = [
        'CostHint' => 'Text',
        'HintPayment' => 'HTMLText',
        'HintAfterPayment' => 'HTMLText',
        'ReceiptHeader' => 'HTMLText',
        'ReceiptFooter' => 'HTMLText'
    ];

    private static $has_one = [
        'ReceiptLogo' => Image::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab('Root.Produkte', TextareaField::create('CostHint', 'Hinweis bei Preis'));

        $fields->addFieldToTab('Root.Bestellabschluss', TextField::create('ReceiptHeader', 'Adresszeile Absender'));
        $fields->addFieldToTab('Root.Bestellabschluss', HTMLEditorField::create('ReceiptFooter', 'Fußzeile'));
        $fields->addFieldToTab('Root.Bestellabschluss', UploadField::create('ReceiptLogo', 'Logo'));

        $fields->addFieldToTab('Root.Bestellabschluss', HTMLEditorField::create('HintPayment', 'Hinweis Payment'));
        $fields->addFieldToTab('Root.Bestellabschluss', HTMLEditorField::create('HintAfterPayment', 'Hinweis nach Bestellabschluss'));

        return $fields;
    }
}