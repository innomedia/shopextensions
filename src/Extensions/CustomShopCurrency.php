<?php

use SilverShop\Model\Order;
use SilverStripe\Dev\Debug;
use SilverStripe\Core\Extension;
use TractorCow\Fluent\Model\Locale;
use SilverStripe\Core\Config\Config;
use SilverShop\ORM\FieldType\ShopCurrency;


class CustomShopCurrency extends Extension {
    public function TaxedNice()
    {
        // return "<span title=\"$this->value\">$" . number_format($this->value, 2) . '</span>';
        $val = $this->owner->config()->currency_symbol . number_format(abs($this->owner->value * 1.16), 2);
        if ($this->owner->value < 0) {
            return "($val)";
        }

        return $val;
    }
}