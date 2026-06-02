<?php

namespace ShopExtensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\Debug;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverShop\Forms\PaymentForm;
use SilverShop\Cart\ShoppingCart;
use SilverShop\Discounts\Form\CouponForm;
use Dynamic\CountryDropdownField\Fields\CountryDropdownField;
use SilverStripe\Forms\CheckboxField;

class CheckoutPageControllerExtension extends Extension
{
    
    public function updateOrderForm(PaymentForm &$form)
    {
        // Replace the form with our custom checkout component config
        $config = CustomCheckoutComponentConfig::create(ShoppingCart::curr());
        $newForm = PaymentForm::create($this->owner, 'OrderForm', $config);
        
        // Check if we need to change the button label for onsite payment
        if ($config->hasComponentWithPaymentData()) {
            $newForm->setActions(
                FieldList::create(
                    FormAction::create('checkoutSubmit', _t('SilverShop\Page\CheckoutPage.SubmitPayment', 'Submit Payment'))
                )
            );
        }
        
        $newForm->Cart = $this->owner->Cart();
        
        // Apply the template flattening logic
        $newForm->setTemplate('ExtendedCheckoutForm');
        $updatedFields = new FieldList();
        $fields = $newForm->Fields();
        foreach ($fields as $field) {
            switch (get_class($field)) {
                case "SilverStripe\Forms\CompositeField":
                    foreach ($field->getChildren() as $childfield) {
                        $updatedFields->push($childfield);
                    }
                    break;
                default:
                    $updatedFields->push($field);
                    break;
            }
        }
        $newForm->setFields($updatedFields);
        
        // Replace the form by reference
        $form = $newForm;
    }
}
