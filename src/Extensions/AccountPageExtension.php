<?php

namespace ShopExtensions;

use SilverShop\Model\Order;
use SilverStripe\Dev\Debug;
use SilverStripe\i18n\i18n;
use SilverStripe\Core\Extension;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Convert;
use SilverStripe\Security\Security;

class AccountPageControllerExtension extends Extension{
    private static $allowed_actions = array(
        'receipt' => true,
        'infos' => true,
    );

    public function receipt(HTTPRequest $request){
        $Order = Order::get()->byID($request->param('ID'));

        $name = 'Rechnung'.$Order->Reference;
        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.$name.'.pdf"');
        header('Content-Transfer-Encoding: binary');
        echo $Order->PDFReceipt("binary");
    }

    public function infos(){
        $request = $this->owner->getRequest();
        if($request->param('ID')){
            $order = Order::get()->byID(Convert::raw2sql($request->param('ID')));
            if(count($order) == 1 && $order->canView(Security::getCurrentUser())){
                return $this->owner->customise([
                    'Title' => $this->owner->Title,
                    'Order' => $order
                ])->renderWith(['AccountPage_infos', 'Page']);
            } else {
                $this->owner->httpError(403);
            }
        }
        $this->owner->httpError(404);
    }
}