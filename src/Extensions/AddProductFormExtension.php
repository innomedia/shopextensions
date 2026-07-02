<?php

namespace ShopExtensions;

use SilverStripe\Core\Extension;

/**
 * Styles the frontend "add to cart" form (and, by inheritance, the VariationForm)
 * for the Tailwind theme. The silvershop forms are built in PHP, so the only place
 * to attach the theme's button/field classes is through the updateAddProductForm
 * extension hook fired in {@see \SilverShop\Forms\AddProductForm::__construct()}.
 *
 * Registered against SilverShop\Forms\AddProductForm in _config/shopextensions.yml.
 * VariationForm extends AddProductForm and calls the parent constructor, so this
 * hook covers both without needing a second registration.
 *
 * @property \SilverShop\Forms\AddProductForm $owner
 */
class AddProductFormExtension extends Extension
{
    /**
     * Add Tailwind classes to the product form, its quantity field and its
     * add-to-cart action so the frontend form matches the rest of the theme.
     *
     * @return void
     */
    public function updateAddProductForm()
    {
        $form = $this->owner;
        $form->addExtraClass('product-buy-form flex flex-wrap items-end gap-4');

        $quantity = $form->Fields()->dataFieldByName('Quantity');
        if ($quantity) {
            $quantity->addExtraClass('w-24');
            $quantity->setAttribute('min', '1');
        }

        foreach ($form->Actions() as $action) {
            $action->addExtraClass('btn-base btn-accent');
        }
    }
}
