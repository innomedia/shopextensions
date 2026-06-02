<?php

namespace ShopExtensions;

use SilverStripe\Core\Extension;


class OrderItemExtension extends Extension{
    public function PreparedProduct(){
        if($this->owner->ProductID){
            return \SilverShop\Page\Product::get()->byID($this->owner->ProductID);
        } else {
            return null;
        }
    }
}