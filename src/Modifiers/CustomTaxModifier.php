<?php
namespace ShopExtensions;

use SilverShop\Model\Order;
use SilverStripe\Dev\Debug;
use SilverShop\Model\Modifiers\Tax\Base;

class CustomTaxModifier extends Base
{

    private static $table_name = 'SilverShop_CustomTaxModifier';

    /**
     * Tax rates per country
     *
     * @config
     * @var    array
     */
    private static $country_rates = [];

    public function Custom()
    {
        return true;
    }

    public function value($incoming): int|float
    {
        return $incoming;
    }
    public function modify($subtotal, $forcecalculation = false)
    {
        $order = $this->Order();
        $value = ($order->IsCart() || $forcecalculation) ? $this->value($subtotal) : $this->Amount;

        $value = round($value, Order::config()->rounding_precision);
        $this->Amount = $value;
        return $subtotal;
    }


    public function taxBilled(){
        return false;
    }

    public function TableTitle()
    {
        $html = "";
        foreach($this->getTaxRates() as $tax)
        {
            $html .= "Enthaltene MwSt. zu ".$tax."%<br/>";
        }
        $html = substr($html,0,strlen($html) - 5);
        return $html;
    }

    public function CustomTableValue()
    {
        $html = "";
        foreach($this->getTaxRates() as $tax)
        {
            $html .= '€'.$this->getProductTaxTotalByTaxRate($tax)."<br/>";
        }
        $html = substr($html,0,strlen($html) - 5);
        return $html;
    }

    public function TotalTaxAmount(){
        $total = 0;
        foreach($this->getTaxRates() as $tax)
        {
            //debug::dump($this->getProductTaxTotalByTaxRate($tax));
            $total += floatval(str_replace(',', '.', $this->getProductTaxTotalByTaxRate($tax)));
        }
        return $total;
    }

    public function ShowInTable(): bool
    {
        return true;
    }

    private function getTaxRates()
    {
        $order = $this->Order();
        $tax = [];
        foreach($order->Items() as $item)
        {
            if(!in_array($item->Product()->Tax,$tax))
            {
                array_push($tax,$item->Product()->Tax);
            }
        }

        return $tax;
    }
    private function getProductTaxTotalByTaxRate($taxrate)
    {
        $order = $this->Order();
        $PriceTotal = 0;
        foreach($order->Items() as $item)
        {
            if($item->Product()->Tax == $taxrate)
            {
                $PriceTotal += $item->Total() ;
            }
        }

        $netprice = $PriceTotal - ($PriceTotal / ($taxrate / 100 + 1));


        return number_format(round($netprice, 2),2,",","");
    }


    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        // While the order is still in "Cart" status, persist country code to DB
        if ($this->OrderID && $this->Order()->IsCart()) {
        }
    }
}
