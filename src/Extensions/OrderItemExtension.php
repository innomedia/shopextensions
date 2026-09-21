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
     * The article number to print on documents (invoice, cancellation invoice, delivery slip).
     *
     * Takes it from the thing actually bought, i.e. this line's buyable: with product variations
     * that is the variation and its own SKU ("GHEE-ROSA-500"), not the parent product's number
     * ("GHEE"). The PDF templates used to read $Product(true).InternalItemID and so printed the
     * product number for every variation, while the HTML receipt already showed the variation SKU.
     *
     * Order of preference: the buyable's ArticleNumber (a field some projects add), its
     * InternalItemID, then the same two on the parent product. Empty string when nothing is set.
     * The buyable is the versioned one (as bought), so a later SKU change cannot alter documents of
     * orders that are already placed.
     *
     * @return string
     */
    public function ArticleNumber(): string
    {
        $candidates = [];
        if ($this->owner->hasMethod('Buyable') && ($buyable = $this->owner->Buyable())) {
            $candidates[] = $buyable;
        }
        if ($this->owner->hasMethod('Product') && ($product = $this->owner->Product(true))) {
            $candidates[] = $product;
        }

        foreach ($candidates as $candidate) {
            foreach (['ArticleNumber', 'InternalItemID'] as $field) {
                $value = $candidate->hasField($field) ? trim((string) $candidate->getField($field)) : '';
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
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
