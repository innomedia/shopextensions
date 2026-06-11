<?php

namespace ShopExtensions;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;

class ProductExtension extends Extension
{
    /**
     * Default: products require shipping (physical goods)
     * Override this in config or via extension for specific product types
     */
    private static $requires_shipping = true;

    /**
     * Default tax rate for new products
     * Define in YAML config
     * 
     * @config
     * @var float
     */
    private static $default_tax_rate = 19;

    /**
     * Available tax rates for dropdown selection
     * Must be defined in YAML config
     * 
     * @config
     * @var array
     */
    private static $available_tax_rates = [];

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
        $this->owner->Tax = Config::inst()->get(ProductExtension::class, 'default_tax_rate');
    }

    /**
     * Add Tax field to CMS
     */
    public function updateCMSFields(FieldList $fields)
    {
        // Only add Tax field if per_product_tax is enabled
        $taxModifierClass = 'ShopExtensions\\CustomTaxModifier';
        if (class_exists($taxModifierClass)) {
            $perProductTax = $taxModifierClass::config()->get('per_product_tax');
            
            if ($perProductTax) {
                $availableRates = Config::inst()->get(ProductExtension::class, 'available_tax_rates');
                $defaultRate = Config::inst()->get(ProductExtension::class, 'default_tax_rate');
                
                // Use existing value or default
                $currentValue = $this->owner->Tax ?: $defaultRate;
                
                $taxField = DropdownField::create('Tax', 'Steuersatz')
                    ->setSource($availableRates)
                    ->setValue($currentValue)
                    ->setDescription('Mehrwertsteuersatz für dieses Produkt');
                
                // Replace the auto-scaffolded field with our dropdown
                $fields->removeByName('Tax');
                $fields->addFieldToTab('Root.Pricing', $taxField);
            } else {
                // If per_product_tax is disabled, remove the field entirely
                $fields->removeByName('Tax');
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
