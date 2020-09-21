<?php
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Control\Session;
use SilverShop\Cart\ShoppingCart;
use SilverStripe\View\ArrayData;
use SilverStripe\Control\Controller;
use SilverStripe\View\SSViewer;
use SilverStripe\Dev\Debug;

class AjaxCartController extends Controller{
    private static $allowed_functions = array(
        'updateCart' => true
    );


    public function init(){
        parent::init();

        $this->updateCart();

        Config::inst()->update(RequestHandler::class, 'require_allowed_actions', false);
    }


    public function updateCart(){
        //SSViewer::set_themes(["heimatschwarzwald"]);

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
        if (!$order || !$order->Items() || !$order->Items()->exists()) {
            return false;
        }

        $return = $this->customise(new ArrayData(array(
            'Cart' => $order,
        )))->renderWith('AjaxCart');

        echo $return;
        //debug::dump($session->get('cartbillingcountry'));
        die;
    }
}