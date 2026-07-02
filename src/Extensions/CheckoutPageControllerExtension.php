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

/**
 * Extension on {@see \SilverShop\Page\CheckoutPageController} that rebuilds the checkout
 * order form.
 *
 * It swaps in the {@see CustomCheckoutComponentConfig} (which drops the shipping address
 * component for orders that need no shipping), renders the form with the
 * "ExtendedCheckoutForm" template, and flattens nested CompositeFields into a single flat
 * FieldList for a simpler layout. It also sets the success link so that zero-total orders
 * redirect straight to the order details page instead of the payment step.
 *
 * Registered against SilverShop\Page\CheckoutPageController in
 * _config/shopextensions.yml.
 *
 * @property \SilverShop\Page\CheckoutPageController $owner
 */
class CheckoutPageControllerExtension extends Extension
{
    /**
     * Replaces the default checkout order form with a customised version.
     *
     * Builds a new PaymentForm from the custom component config, keeps the "Submit Payment"
     * action when the config provides on-site payment data, sets the success/return link,
     * applies the ExtendedCheckoutForm template, flattens any CompositeFields, and hands the
     * result back through the by-reference $form argument.
     *
     * @param PaymentForm $form The order form to replace, passed by reference.
     */
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
        
        // Set success link to order details page (fixes zero-total order redirects)
        $newForm->setSuccessLink($newForm->getOrderProcessor()->getReturnUrl());
        
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
