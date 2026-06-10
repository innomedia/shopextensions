<?php

namespace ShopExtensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\NumericField;

class ProductExtension extends Extension
{
    /**
     * Default: products require shipping (physical goods)
     * Override this in config or via extension for specific product types
     */
    private static $requires_shipping = true;

    /**
     * Default tax rate for new products
     * 
     * @config
     * @var float
     */
    private static $default_tax_rate = 19;

    /**
     * Add Tax field to products for per-product tax rates
     */
    private static $db = [
        'Tax' => 'Decimal(5,2)'
    ];

    /**
     * Set default tax rate when creating new product
     */
    public function populateDefaults()
    {
        $this->owner->Tax = $this->owner->config()->get('default_tax_rate');
    }

    /**
     * Add Tax field to CMS
     */
    public function updateCMSFields(FieldList $fields)
    {
        // Only add Tax field if per_product_tax is enabled
        $fields->removeByName("Tax");
        $taxModifierClass = 'ShopExtensions\\CustomTaxModifier';
        if (class_exists($taxModifierClass)) {
            $perProductTax = $taxModifierClass::config()->get('per_product_tax');
            
            if ($perProductTax) {
                $taxField = NumericField::create('Tax', 'Steuersatz (%)')
                    ->setDescription('Mehrwertsteuersatz für dieses Produkt (z.B. 19 für 19%)')
                    ->setScale(2);
                
                // Add to Main tab, after Price field if it exists
                if ($fields->dataFieldByName('BasePrice')) {
                    $fields->insertAfter('BasePrice', $taxField);
                } else {
                    $fields->addFieldToTab('Root.Pricing', $taxField);
                }
            }
        }
    }

    /**
     * Check if this product requires a shipping address
     * Can be overridden via config or extension
     * 
     * @return bool
     */
    public function requiresShipping()
    {
        return $this->owner->config()->get('requires_shipping');
    }
}
