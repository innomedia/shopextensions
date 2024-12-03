<?php

namespace ShopExtensions;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\Debug;
use SilverStripe\Control\Director;
use SilverStripe\Security\Security;
use SilverShop\Cart\ShoppingCart;

class ShoppingCartControllerExtension extends Extension{
    public function updateAddResponse($request, $response, $product, $quantity){

        if(Director::is_ajax()){
            $html = $this->owner->renderWith('AjaxCart')->forTemplate();
            $return = json_encode([
                'type' => 'multiple',
                'target' => $request->postVar('target'),
                'tooltip' => _t('Order.ADDEDTOCART', 'Produkt in den Warenkorb gelegt'),
                'values' => [
                    '.header__ajaxcart' => $html,
                    '.header__itemsincart' => ShoppingCart::curr()->Items()->Count()
                    ]
            ]);
            echo $return;
            return;
        }
    }

    public function updateRemoveAllResponse($request, $response, $product){
        $this->owner->cart = ShoppingCart::singleton();

        if(Director::is_ajax()){
            $html = $this->owner->renderWith('AjaxCart')->forTemplate();
            $return = json_encode([
                'type' => 'multiple',
                'target' => $request->postVar('target'),
                'values' => [
                    '.header__ajaxcart' => $html,
                    '.header__itemsincart' => ShoppingCart::curr()->Items()->Count()
                    ]
            ]);
            echo $return;
            return;
        }
    }
}
