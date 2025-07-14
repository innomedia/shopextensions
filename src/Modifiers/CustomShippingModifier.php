<?php
namespace ShopExtensions;

use SilverStripe\Control\Session;
use SilverShop\Model\Modifiers\OrderModifier;
use SilverShop\Discounts\Model\Modifiers\OrderDiscountModifier;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\Debug;

class CustomShippingModifier extends OrderModifier
{
    private static $singular_name = "Zzgl. Versandkosten incl. 19% MwSt.";

    private static $plural_name = "Zzgl. Versandkosten incl. 19% MwSt.";


    public function modify($subtotal, $forcecalculation = false)
    {
        $total = $this->value($subtotal);
        $this->Amount = $total;
        return $total;
    }

    public function value($incoming): int|float
    {
        $shipping = $this->individualShipping();

        $order = $this->Order();
        $request = Injector::inst()->get(HTTPRequest::class);
        $session = $request->getSession();

        if($session->get('cartbillingcountry')){
            if($session->get('cartbillingcountry') != 'DE'){
                return $incoming + $shipping;
            }
        }

        if ($order->SubTotal() > 29) {
            return $incoming;
        } else {
            return $incoming + $shipping;
        }
    }

    public function getTableTitle(): string
    {
        $title = $this->i18n_singular_name();

        // Change title during period or reduced tax
        $today = strtotime($this->LastEdited);
        $startReducedTax = strtotime("2020-07-01 00:00:01");
        $endReducedTax = strtotime("2020-12-31 23:59:59");
        if($today > $startReducedTax && $today < $endReducedTax) {
            $title = 'Zzgl. Versandkosten incl. 16% MwSt.';
        }

        return $title;
    }


    public function TableValue()
    {
        $request = Injector::inst()->get(HTTPRequest::class);
        $session = $request->getSession();
        $subtotal = $this->Order()->SubTotal();

        $value = $this->individualShipping();

        if($session->get('cartbillingcountry')){
            if($session->get('cartbillingcountry') != 'DE'){
                return $value;
            }
        }

        if ($value == 1) {
            return 0;
        } else if ($subtotal > 29) {
            return 0;
        } else
            return $value;

    }

    public function individualShipping()
    {
        $request = Injector::inst()->get(HTTPRequest::class);
        $order = $this->Order();
        $session = $request->getSession();
        if($session->get('cartbillingcountry')){
            if($session->get('cartbillingcountry') != 'DE'){
                // check for weight and add cost
                $totalweight = 0;
                $additionalshippingfees = 0;
                if ($order && $orderItems = $order->Items()) {
                    foreach ($orderItems as $orderItem) {
                        if ($product = $orderItem->Product()) {
                            if($product->Weight > 0){
                                $totalweight += $product->Weight * $orderItem->Quantity;
                            }
                            if($product->ShippingInternational > 0){
                                $additionalshippingfees += $product->ShippingInternational * $orderItem->Quantity;
                            }
                        }
                    }
                }
                if($totalweight >= 0.5 && $totalweight < 2){
                    return 10 + $additionalshippingfees;
                } elseif ($totalweight >= 2 && $totalweight < 5){
                    return 27 + $additionalshippingfees;
                } elseif ($totalweight >= 5 && $totalweight < 10){
                    return 35 + $additionalshippingfees;
                } elseif ($totalweight >= 10 && $totalweight < 20){
                    return 50 + $additionalshippingfees;
                } elseif ($totalweight >= 20 && $totalweight < 31){
                    return 31.5 + $additionalshippingfees;
                }

                return 6.39 + $additionalshippingfees;
            }
        }

        $order = $this->Order();

        $totaldiscount = 0;
        foreach ($order->Discounts() as $discount){
            $totaldiscount += $discount->Amount;
        }
        $shipping = 4.39;

        /*
        $amountleft = $totaldiscount - $order->SubTotal();
        if($amountleft > $shipping){
            return 0;
        }
        */

        return $shipping;

    }

}

class DiscountModifier extends OrderDiscountModifier
{
    private static $singular_name = "Rabatt";
    private static $plural_name = "Rabatte";
}


