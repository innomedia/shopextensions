<?php

namespace ShopExtensions;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;

/**
 * Extension on {@see \SilverShop\Page\Product} that adds German-market tax handling and
 * shipping-requirement logic to products.
 *
 * It introduces a per-product tax rate (used when CustomTaxModifier's per_product_tax is
 * enabled), scaffolds a tax-rate dropdown in the CMS, and provides the requiresShipping()
 * flag that the checkout uses to decide whether a shipping address is needed (e.g. courses
 * and digital goods do not require shipping).
 *
 * Registered against SilverShop\Page\Product in _config/shopextensions.yml.
 *
 * @property \SilverShop\Page\Product $owner
 */
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
     * Add Tax field to products for per-product tax rates.
     *
     * The Decimal's third parameter (19) is the DB column default, so a freshly created column /
     * any row inserted outside the ORM already carries the regular German VAT rate instead of 0.
     */
    private static $db = [
        'Tax' => 'Decimal(5,2,19)'
    ];

    /**
     * Set the default tax rate on a newly created product.
     *
     * NOTE: this MUST be onAfterPopulateDefaults, not populateDefaults() — DataObject only invokes
     * the `defaults` config array and the `onAfterPopulateDefaults` extension hook; an extension
     * method literally named populateDefaults() is never called, so a new product would otherwise
     * default to 0 % and the CMS dropdown would pre-select "0 % (steuerfrei)".
     */
    public function onAfterPopulateDefaults()
    {
        $this->owner->Tax = Config::inst()->get(ProductExtension::class, 'default_tax_rate');
    }

    /**
     * Add Tax field to CMS
     *
     * Adds a "Steuersatz" (tax rate) dropdown to the Pricing tab when per-product tax is
     * enabled, otherwise removes the auto-scaffolded Tax field entirely.
     *
     * @param FieldList $fields The product's CMS fields.
     */
    public function updateCMSFields(FieldList $fields)
    {
        // Only add Tax field if per_product_tax is enabled
        $taxModifierClass = 'ShopExtensions\\CustomTaxModifier';
        if (class_exists($taxModifierClass)) {
            $perProductTax = $taxModifierClass::config()->get('per_product_tax');
            
            if ($perProductTax) {
                $availableRates = Config::inst()->get(ProductExtension::class, 'available_tax_rates');

                // Build the dropdown source as [rate => label] so the OPTION VALUE is the
                // actual tax rate. available_tax_rates is configured as a list of
                // {rate, label} maps on purpose: an associative array keyed by integer
                // rates gets reindexed to 0,1,2 by SilverStripe's config merge, which would
                // make the dropdown store the option index instead of the rate.
                $source = $this->buildTaxRateSource($availableRates);

                $taxField = DropdownField::create('Tax', 'Steuersatz')
                    ->setSource($source)
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
     * Build the [rate => label] source for the tax-rate dropdown from the configured
     * available_tax_rates.
     *
     * Supports both the current list-of-maps format ([{rate, label}, ...]) and, for
     * backwards compatibility with older projects, a plain associative array
     * (rate => label). The returned keys are the tax rates themselves, so the CMS
     * dropdown stores the rate — not the option index.
     *
     * @param  array|null $availableRates The raw available_tax_rates config value.
     * @return array<int|string, string> Map of tax rate => human-readable label.
     */
    protected function buildTaxRateSource($availableRates)
    {
        $source = [];

        if (!is_array($availableRates)) {
            return $source;
        }

        foreach ($availableRates as $key => $value) {
            if (is_array($value)) {
                // List-of-maps format: [{rate, label}, ...]
                if (isset($value['rate'])) {
                    $source[$value['rate']] = $value['label'] ?? ($value['rate'] . '%');
                }
            } else {
                // Legacy associative format: rate => label
                $source[$key] = $value;
            }
        }

        return $source;
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
