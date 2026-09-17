<?php

namespace ShopExtensions;

use SilverStripe\Core\Extension;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverShop\Cart\ShoppingCart;

/**
 * Extension for the SilverShop ShoppingCartController.
 *
 * Provides AJAX responses for cart mutations. When an add or remove-all
 * request is made via AJAX, it returns a JSON payload used by the front-end
 * to update the mini-cart (header dropdown) markup and the cart item count.
 *
 * The payload is handed back through the by-reference $response argument of the core hooks.
 * It used to be echoed, after which the controller still returned its own "1"/"" body, so the
 * client received invalid JSON and the output bypassed the middleware.
 *
 * @property \SilverShop\Cart\ShoppingCartController $owner
 */
class ShoppingCartControllerExtension extends Extension
{
    /**
     * Return a JSON response after a product is added to the cart via AJAX.
     *
     * @param \SilverStripe\Control\HTTPRequest $request The current request.
     * @param HTTPResponse|null $response Set to the JSON response for AJAX requests.
     * @param \SilverShop\Model\Buyable|null $product The product that was added.
     * @param int $quantity The quantity that was added.
     */
    public function updateAddResponse($request, &$response, $product = null, $quantity = 1)
    {
        if (!Director::is_ajax() || $response instanceof HTTPResponse) {
            return;
        }

        $response = $this->jsonResponse([
            'type' => 'multiple',
            'target' => $request->postVar('target'),
            'tooltip' => _t('Order.ADDEDTOCART', 'Produkt in den Warenkorb gelegt'),
            'values' => $this->cartValues(),
        ]);
    }

    /**
     * Return a JSON response after all of a product's items are removed via AJAX.
     *
     * @param \SilverStripe\Control\HTTPRequest $request The current request.
     * @param HTTPResponse|null $response Set to the JSON response for AJAX requests.
     * @param \SilverShop\Model\Buyable|null $product The product that was removed.
     */
    public function updateRemoveAllResponse($request, &$response, $product = null)
    {
        if (!Director::is_ajax() || $response instanceof HTTPResponse) {
            return;
        }

        $response = $this->jsonResponse([
            'type' => 'multiple',
            'target' => $request->postVar('target'),
            'values' => $this->cartValues(),
        ]);
    }

    /**
     * Mini-cart markup and item count for the header.
     */
    protected function cartValues(): array
    {
        $cart = ShoppingCart::curr();

        return [
            '.header__ajaxcart' => $this->owner->renderWith('AjaxCart')->forTemplate(),
            '.header__itemsincart' => $cart ? $cart->Items()->Count() : 0,
        ];
    }

    protected function jsonResponse(array $data): HTTPResponse
    {
        return HTTPResponse::create(json_encode($data))
            ->addHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
