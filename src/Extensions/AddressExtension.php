<?php

namespace ShopExtensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;

/**
 * Extension for the SilverShop Address model.
 *
 * Customises the address form: localises the country dropdown to German
 * region names, preselects the country stored in the session cart, and adds
 * Company / FirstName / Surname fields ahead of the address fields.
 *
 * @property \SilverShop\Model\Address $owner
 */
class AddressExtension extends Extension{
    /**
     * Localise the country dropdown and preselect the session cart country.
     *
     * Replaces each country option label with its German display name and
     * sets a German empty-string prompt. If a billing country is stored in
     * the session cart, it is preselected.
     *
     * @param DropdownField $field The country dropdown field to modify.
     * @return void
     */
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

    /**
     * Insert Company, FirstName and Surname fields before the Address field.
     *
     * @param \SilverStripe\Forms\FieldList $fields The address form fields.
     * @return void
     */
    public function updateFormFields(\SilverStripe\Forms\FieldList $fields){
        $fields->insertBefore('Address', TextField::create('Company', $this->owner->fieldLabel('Company')));
        $fields->insertBefore('Address', TextField::create('FirstName', $this->owner->fieldLabel('FirstName')));
        $fields->insertBefore('Address', TextField::create('Surname', $this->owner->fieldLabel('Surname')));
    }
}