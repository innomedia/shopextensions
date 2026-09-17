<?php
use SilverShop\Cart\ShoppingCart;
use SilverShop\Page\CartPage;
use SilverShop\Page\CheckoutPage;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Model\ArrayData;

/**
 * Standalone controller backing the AJAX mini-cart (route: ajaxcart//$Action/$ID/$Name).
 *
 * Stores the selected billing country in the session and renders the AjaxCart template for the
 * current shopping cart, so the front-end can refresh the mini-cart (including country-dependent
 * display) via AJAX: GET ajaxcart/updateCart?country=DE (ajaxcart/ alone does the same).
 *
 * Previously the cart was echoed from init() followed by die(), and init() switched
 * RequestHandler.require_allowed_actions off globally for the rest of the request. Both are gone:
 * the actions are regular allow-listed actions returning an HTTPResponse, so the middleware
 * (session save, headers) runs as usual.
 */
class AjaxCartController extends Controller
{
    private static $allowed_actions = [
        'updateCart',
    ];

    /**
     * ajaxcart/ without an action behaves like ajaxcart/updateCart.
     */
    public function index(HTTPRequest $request): HTTPResponse
    {
        return $this->updateCart($request);
    }

    /**
     * Store the billing country in the session and render the mini-cart.
     *
     * A "country" request parameter holding an ISO 3166-1 alpha-2 code is saved to the session,
     * an empty or invalid one leaves the session untouched, and no parameter at all clears it.
     * Always renders the panel (the template shows an empty state when the cart is empty) so the
     * front-end gets a valid data-cart-count on every call.
     */
    public function updateCart(HTTPRequest $request): HTTPResponse
    {
        $session = $request->getSession();
        $country = $request->requestVar('country');
        if ($country !== null) {
            $country = strtoupper(trim((string) $country));
            if (preg_match('/^[A-Z]{2}$/', $country)) {
                $session->set('cartbillingcountry', $country);
            }
        } else {
            $session->clear('cartbillingcountry');
        }

        $order = ShoppingCart::curr();
        $hasItems = $order && $order->Items() && $order->Items()->exists();

        $html = $this->customise(ArrayData::create([
            'Cart' => $hasItems ? $order : false,
        ]))->renderWith('AjaxCart');

        return HTTPResponse::create((string) $html)
            ->addHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Cart page link, so the mini-cart buttons resolve when the panel is
     * rendered from this standalone controller (which lacks ViewableCartExtension).
     */
    public function getCartLink()
    {
        return CartPage::find_link();
    }

    /**
     * Checkout page link, see {@see self::getCartLink()}.
     */
    public function getCheckoutLink()
    {
        return CheckoutPage::find_link();
    }
}
