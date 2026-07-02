<?php

use SilverShop\Discounts\Model\Modifiers\OrderDiscountModifier;


/**
 * Thin subclass of silvershop's OrderDiscountModifier that localises the display
 * name to the German "Rabatt" / "Rabatte" (singular / plural). No behaviour is
 * changed beyond the labels.
 *
 * Pre-existing quirks (left untouched intentionally):
 *  - The file is named "DicountModifier.php" (missing an "s") while the class is
 *    "DiscountModifier".
 *  - Unlike the sibling modifiers, this class declares no namespace (global namespace).
 */
class DiscountModifier extends OrderDiscountModifier
{
    private static $singular_name = "Rabatt";
    private static $plural_name = "Rabatte";
}
