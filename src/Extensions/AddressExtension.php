<?php

namespace ShopExtensions;

use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;

class AddressExtension extends DataExtension{
    public function updateCountryField(DropdownField $field){
        $source = $field->getSource();
        foreach ($source as $key => $value) {
            $source[$key] = \Locale::getDisplayRegion('-'.$key, 'de_DE');
        }

        $field->setSource($source)->setEmptyString('Land auswählen');

        $controller = \SilverStripe\Control\Controller::curr();
        $session = $controller->getRequest()->getSession();
        if($session->get('cartbillingcountry')){
            $field->setValue($session->get('cartbillingcountry'));
        }
    }

    public function updateFormFields(\SilverStripe\Forms\FieldList $fields){
        $fields->insertBefore('Address', TextField::create('Company', $this->owner->fieldLabel('Company')));
        $fields->insertBefore('Address', TextField::create('FirstName', $this->owner->fieldLabel('FirstName')));
        $fields->insertBefore('Address', TextField::create('Surname', $this->owner->fieldLabel('Surname')));
    }
}