<?php

namespace ShopExtensions;

use SilverStripe\Core\Extension;
use SilverShop\Model\Variation\Variation;
use SilverStripe\Core\Convert;

class ProductControllerExtension extends Extension{
    private static $allowed_actions = [
        'selectvariation' => true
    ];


    /* Used for Refreshing Variant Prices via AJAX */
    public function selectvariation(){
        $request = $this->owner->getRequest();
        if($request->param('ID')){
            $variation = Variation::get()->byID(Convert::raw2sql($request->param('ID')));
            //€ $sellingPrice<% if $BaseWeight %><span class="small">($BaseWeight)</span><% end_if %>
            return '€'.number_format($variation->Price,2,',','.');
        }
    }
}