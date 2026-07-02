<?php

namespace ShopExtensions;

use SilverStripe\Core\Extension;


/**
 * Extension for the SilverShop OrderItem model.
 *
 * Adds the per-line VAT that is frozen at placement (TaxRate + TaxAmount), mirroring how SilverShop
 * freezes an item's price in CalculatedTotal: while the order is a cart the tax is recalculated
 * live by {@see \ShopExtensions\CustomTaxModifier}; when the order is placed the tax charged for
 * each line is written here and never recalculated again. That way a later change of tax_mode /
 * rates can never retroactively alter a placed order's tax.
 *
 * The amounts are the item's share of its rate's total tax (engine-aware: when the tax engine
 * distributes coupon/shipping across rates, each item gets its proportional share), so summing
 * TaxAmount per TaxRate reproduces exactly the tax that was charged and exported.
 *
 * @property \SilverShop\Model\OrderItem $owner
 * @property float $TaxRate
 * @property float $TaxAmount
 */
class OrderItemExtension extends Extension{

    private static $db = [
        'TaxRate' => 'Decimal(5,2)',
        'TaxAmount' => 'Currency',
    ];

    /**
     * Return the live Product associated with this order item.
     *
     * @return \SilverShop\Page\Product|null The product, or null when the
     *                                       order item has no ProductID.
     */
    public function PreparedProduct(){
        if($this->owner->ProductID){
            return \SilverShop\Page\Product::get()->byID($this->owner->ProductID);
        } else {
            return null;
        }
    }

    /**
     * The item's unit price as a negated, display-ready currency string, for the cancellation
     * invoice (Stornorechnung). See {@see \ShopExtensions\OrderExtension::formatNegativeCurrency()}.
     *
     * @return string
     */
    public function NegUnitPrice(): string
    {
        return \ShopExtensions\OrderExtension::formatNegativeCurrency($this->owner->UnitPrice());
    }

    /**
     * The item's line total as a negated, display-ready currency string, for the cancellation
     * invoice (Stornorechnung).
     *
     * @return string
     */
    public function NegTotal(): string
    {
        return \ShopExtensions\OrderExtension::formatNegativeCurrency($this->owner->Total());
    }
}
