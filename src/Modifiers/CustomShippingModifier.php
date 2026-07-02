<?php
namespace ShopExtensions;

use SilverStripe\Control\Session;
use SilverShop\Model\Modifiers\OrderModifier;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\Debug;

/**
 * Order shipping-cost modifier for the German market.
 *
 * Adds shipping costs based on the billing country and, for non-German orders,
 * the total order weight. Domestic (DE) orders pay a flat fee and ship free once
 * the subtotal exceeds 29 EUR; non-DE orders are always charged shipping using
 * weight tiers (see {@see self::individualShipping()}).
 *
 * NOTE: {@see self::getTableTitle()} still contains an outdated, hard-coded
 * 2020 Corona VAT special case (reduced 16% MwSt. between 2020-07-01 and
 * 2020-12-31). This is legacy code kept as-is and no longer has any effect.
 */
class CustomShippingModifier extends OrderModifier
{
    private static $singular_name = "Zzgl. Versandkosten incl. 19% MwSt.";

    private static $plural_name = "Zzgl. Versandkosten incl. 19% MwSt.";


    /**
     * Apply the shipping cost to the running order total.
     *
     * @param  int|float $subtotal         The current order subtotal.
     * @param  bool      $forcecalculation Whether to force recalculation (framework flag).
     * @return int|float The subtotal including shipping.
     */
    public function modify($subtotal, $forcecalculation = false)
    {
        $total = $this->value($subtotal);
        $this->Amount = $total;
        return $total;
    }

    /**
     * Add the shipping charge to the incoming amount.
     *
     * Non-German orders (billing country from session) are always charged the
     * weight-based shipping. German orders ship free when the subtotal is over
     * 29 EUR, otherwise the flat domestic fee is added.
     *
     * @param  int|float $incoming The running order amount.
     * @return int|float The amount including shipping where applicable.
     */
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

    /**
     * The label shown for this modifier in the order summary table.
     *
     * @return string The shipping line title.
     */
    public function getTableTitle(): string
    {
        return $this->i18n_singular_name();
    }


    /**
     * The shipping amount displayed in the order summary table.
     *
     * Returns 0 when shipping is waived (domestic subtotal over 29 EUR, or the
     * sentinel value of 1), otherwise the calculated shipping cost. Non-German
     * orders always show the weight-based cost.
     *
     * @return int|float The displayed shipping value.
     */
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

    /**
     * Compute the raw shipping cost for the current order.
     *
     * For non-German billing countries (from session) the cost is derived from
     * the total order weight plus any per-product international surcharge
     * (ShippingInternational). Weight tiers (in kg):
     *   0.5 to < 2   => 10 EUR
     *   2   to < 5   => 27 EUR
     *   5   to < 10  => 35 EUR
     *   10  to < 20  => 50 EUR
     *   20  to < 31  => 31.5 EUR
     *   below 0.5    => 6.39 EUR (base non-DE rate)
     *
     * For German orders a flat 4.39 EUR applies (the free-shipping-over-29
     * threshold is handled by the callers {@see self::value()} / {@see self::TableValue()}).
     *
     * @return int|float The shipping cost before any free-shipping waiver.
     */
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



