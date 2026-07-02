<?php

namespace ShopExtensions;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\Debug;
use SilverStripe\Control\Director;
use SilverStripe\Security\Security;
use SilverShop\Cart\ShoppingCart;

/**
 * Extension for the SilverShop ShoppingCartController.
 *
 * Provides AJAX responses for cart mutations. When an add or remove-all
 * request is made via AJAX, it returns a JSON payload used by the front-end
 * to update the mini-cart (header dropdown) markup and the cart item count.
 *
 * @property \SilverShop\Cart\ShoppingCartController $owner
 */
class ShoppingCartControllerExtension extends Extension{
    /**
     * Return a JSON response after a product is added to the cart via AJAX.
     *
     * Re-renders the mini-cart with the AjaxCart template and echoes a JSON
     * payload containing the updated mini-cart HTML, the current item count
     * and an "added to cart" tooltip. Does nothing for non-AJAX requests.
     *
     * @param \SilverStripe\Control\HTTPRequest $request The current request.
     * @param \SilverStripe\Control\HTTPResponse $response The default response.
     * @param \SilverShop\Page\Product $product The product that was added.
     * @param int $quantity The quantity that was added.
     * @return void JSON is echoed directly for AJAX requests.
     */
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

    /**
     * Return a JSON response after all of a product's items are removed via AJAX.
     *
     * Re-renders the mini-cart with the AjaxCart template and echoes a JSON
     * payload containing the updated mini-cart HTML and the current item
     * count. Does nothing for non-AJAX requests.
     *
     * @param \SilverStripe\Control\HTTPRequest $request The current request.
     * @param \SilverStripe\Control\HTTPResponse $response The default response.
     * @param \SilverShop\Page\Product $product The product that was removed.
     * @return void JSON is echoed directly for AJAX requests.
     */
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
