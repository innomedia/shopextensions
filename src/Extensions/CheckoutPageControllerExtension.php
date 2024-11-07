<?php

namespace ShopExtensions;

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
        $form->setTemplate('ExtendedCheckoutForm');
        $fields = $form->Fields();
        $fields->insertBefore('ReadTermsAndConditions', CheckboxField::create('Newsletter', 'Ich möchte den Newsletter abbonnieren und einen Coupon geschenkt bekommen'));
    }
}
