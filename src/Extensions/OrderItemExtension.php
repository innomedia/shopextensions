<?php

namespace ShopExtensions;

use SilverStripe\ORM\DataExtension;

class OrderItemExtension extends DataExtension{
    public function PreparedProduct(){
        if($this->owner->ProductID){
            return \SilverShop\Page\Product::get()->byID($this->owner->ProductID);
        } else {
            return null;
        }
    }
}