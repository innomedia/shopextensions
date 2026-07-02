<?php

use SilverShop\Model\Order;
use SilverStripe\Dev\Debug;
use SilverStripe\Core\Extension;
use TractorCow\Fluent\Model\Locale;
use SilverStripe\Core\Config\Config;
use SilverShop\ORM\FieldType\ShopCurrency;


/**
 * Extension providing a currency formatter for ShopCurrency values.
 *
 * NOTE: This class is legacy and currently unregistered/unused (see the bonus task
 * list about removing it entirely). The previously hard-coded *1.16 Corona-era VAT
 * factor has been removed — TaxedNice() now formats the plain value.
 *
 * @property \SilverShop\ORM\FieldType\ShopCurrency $owner
 */
class CustomShopCurrency extends Extension {
    /**
     * Format the currency value as a symbol-prefixed string.
     * Negative values are wrapped in parentheses.
     *
     * @return string The formatted currency value.
     */
    public function TaxedNice()
    {
        $val = $this->owner->config()->currency_symbol . number_format(abs($this->owner->value), 2);
        if ($this->owner->value < 0) {
            return "($val)";
        }

        return $val;
    }
}