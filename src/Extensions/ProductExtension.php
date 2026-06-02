<?php

namespace ShopExtensions;

use SilverStripe\Core\Extension;

class ProductExtension extends Extension
{
    /**
     * Default: products require shipping (physical goods)
     * Override this in config or via extension for specific product types
     */
    private static $requires_shipping = true;

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
