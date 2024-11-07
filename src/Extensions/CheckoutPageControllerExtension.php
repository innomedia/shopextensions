<?php

namespace ShopExtensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\Debug;
use SilverStripe\Forms\Form;
use SilverShop\Forms\PaymentForm;
use SilverShop\Cart\ShoppingCart;
use SilverShop\Discounts\Form\CouponForm;
use Dynamic\CountryDropdownField\Fields\CountryDropdownField;
use SilverStripe\Forms\CheckboxField;

class CheckoutPageControllerExtension extends Extension{
    public function updateOrderForm(PaymentForm $form){
        $updatedFields = new FieldList();
        $fields = $form->Fields();
        foreach($fields as $field)
        {
            switch(get_class($field))
            {
                case "SilverStripe\Forms\CompositeField":
                    foreach($field->getChildren() as $childfield)
                    {
                        $updatedFields->push($childfield);
                    }
                    break;
                default:
                    $updatedFields->push($field);
                    break;
            }
        }
        $form->setFields($updatedFields);
    }
}
