<?php

namespace ShopExtensions\Forms;

use SilverShop\Forms\ShopQuantityField as SilverShopQuantityField;

/**
 * Bugfix for silvershop/core 6.0.x: ShopQuantityField::forTemplate() is typed `string` but returns
 * the DBHTMLText from renderWith(), so every cart page with at least one item fails with a TypeError.
 *
 * Wired via Injector in _config/shopextensions.yml (on by default); OrderItem::QuantityField() uses
 * ::create() and gets this class. DropdownShopQuantityField inherits the same bug and is NOT fixed
 * here – don't use it. Remove this class and the Injector entry once silvershop/core casts itself.
 *
 * Replaces the project copies Tietge\Books\Form\ShopQuantityField, CustomShopQuantityField
 * (vogelwerk) and Ghee\Shop\Form\ShopQuantityField (remove them and their Injector entry).
 */
class ShopQuantityField extends SilverShopQuantityField
{
    public function forTemplate(): string
    {
        return (string) $this->renderWith($this->template);
    }
}
