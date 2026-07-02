<?php
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Control\Session;
use SilverShop\Cart\ShoppingCart;
use SilverShop\Page\CartPage;
use SilverShop\Page\CheckoutPage;
use SilverStripe\Model\ArrayData;
use SilverStripe\Control\Controller;
use SilverStripe\View\SSViewer;
use SilverStripe\Dev\Debug;

/**
 * Standalone controller backing the AJAX mini-cart.
 *
 * Stores the selected billing country in the session and renders the
 * AjaxCart template for the current shopping cart, so the front-end can
 * refresh the mini-cart (including country-dependent display) via AJAX.
 */
class AjaxCartController extends Controller{
    private static $allowed_functions = array(
        'updateCart' => true
    );


    /**
     * Initialise the controller and immediately render the cart.
     *
     * Calls updateCart() during init and relaxes the allowed-actions
     * requirement so the cart can be rendered directly.
     *
     * @return void
     */
    public function init(){
        parent::init();

        $this->updateCart();

        Config::inst()->update(RequestHandler::class, 'require_allowed_actions', false);
    }


    /**
     * Store the billing country in the session and render the mini-cart.
     *
     * When a "country" request parameter is present it is saved to the
     * session (or cleared when absent), then the current cart is rendered
     * with the AjaxCart template and echoed. Always renders the panel (the
     * template shows an empty state when the cart is empty) so the front-end
     * gets a valid data-cart-count on every call. Terminates via die().
     *
     * @return void Ends the request after echoing the rendered panel.
     */
    public function updateCart(){
        $session = $this->getRequest()->getSession();
        if(isset($_REQUEST['country'])){
            if($_REQUEST['country'] != ''){
                $session->set('cartbillingcountry', $_REQUEST['country']);
                $session->save($this->getRequest());
            }
        } else {
            $session->clear('cartbillingcountry');
        }

        $order = ShoppingCart::curr();
        $hasItems = $order && $order->Items() && $order->Items()->exists();

        $return = $this->customise(new ArrayData(array(
            'Cart' => $hasItems ? $order : false,
        )))->renderWith('AjaxCart');

        echo $return;
        die;
    }

    /**
     * Cart page link, so the mini-cart buttons resolve when the panel is
     * rendered from this standalone controller (which lacks ViewableCartExtension).
     */
    public function getCartLink(){
        return CartPage::find_link();
    }

    /**
     * Checkout page link, see {@see self::getCartLink()}.
     */
    public function getCheckoutLink(){
        return CheckoutPage::find_link();
    }
}