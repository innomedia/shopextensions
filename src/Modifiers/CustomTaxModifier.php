<?php
namespace ShopExtensions;

use SilverShop\Model\Order;
use SilverStripe\Dev\Debug;
use SilverShop\Model\Modifiers\Tax\Base;

class CustomTaxModifier extends Base
{

    private static $table_name = 'SilverShop_CustomTaxModifier';

    /**
     * Tax calculation mode: 'inclusive' or 'exclusive'
     *
     * @config
     * @var string
     */
    private static $tax_mode = 'inclusive';

    /**
     * Enable per-product tax rates
     * If false, all products use the default_tax_rate
     *
     * @config
     * @var bool
     */
    private static $per_product_tax = true;

    /**
     * Default/static tax rate (percentage)
     * Used when per_product_tax is false, or as fallback
     *
     * @config
     * @var float
     */
    private static $default_tax_rate = 19;

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
        
        // Calculate total tax amount
        $taxAmount = 0;
        foreach($this->getTaxRates() as $tax) {
            $taxAmount += floatval(str_replace(',', '.', $this->getProductTaxTotalByTaxRate($tax)));
        }
        
        $this->Amount = round($taxAmount, Order::config()->rounding_precision);
        
        // If tax mode is exclusive, add tax to subtotal
        if ($this->config()->get('tax_mode') === 'exclusive') {
            return $subtotal + $this->Amount;
        }
        // If inclusive, subtotal already contains tax
        return $subtotal;
    }


    public function taxBilled(){
        return false;
    }

    public function TableTitle()
    {
        $taxMode = $this->config()->get('tax_mode');
        $label = ($taxMode === 'exclusive') ? 'zzgl. MwSt. zu' : 'Enthaltene MwSt. zu';
        
        $html = "";
        foreach($this->getTaxRates() as $tax)
        {
            $html .= $label." ".$tax."%<br/>";
        }
        $html = substr($html,0,strlen($html) - 5);
        return $html;
    }

    public function CustomTableValue()
    {
        $html = "";
        foreach($this->getTaxRates() as $tax)
        {
            $html .= $this->getProductTaxTotalByTaxRate($tax).'€'."<br/>";
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
        $perProductTax = $this->config()->get('per_product_tax');
        
        // If per_product_tax is disabled, return static rate for all products
        if (!$perProductTax) {
            return [$this->config()->get('default_tax_rate')];
        }
        
        // Collect unique tax rates from products
        $tax = [];
        foreach($order->Items() as $item)
        {
            $taxRate = $this->getProductTaxRate($item->Product());
            if (!in_array($taxRate, $tax))
            {
                array_push($tax, $taxRate);
            }
        }
        
        if(count($tax) == 0)
        {
            $tax = [$this->config()->get('default_tax_rate')];
        }

        return $tax;
    }
    
    /**
     * Get the tax rate for a specific product
     * 
     * @param Product $product
     * @return float
     */
    private function getProductTaxRate($product)
    {
        $perProductTax = $this->config()->get('per_product_tax');
        $defaultRate = $this->config()->get('default_tax_rate');
        
        // If per_product_tax is disabled, always return default rate
        if (!$perProductTax) {
            return $defaultRate;
        }
        
        // Use product-specific rate if set, otherwise default
        if ($product->Tax !== null && $product->Tax > 0) {
            return $product->Tax;
        }
        
        return $defaultRate;
    }
    private function getProductTaxTotalByTaxRate($taxrate)
    {
        $order = $this->Order();
        $PriceTotal = 0;
        
        foreach($order->Items() as $item)
        {
            $productTaxRate = $this->getProductTaxRate($item->Product());
            if($productTaxRate == $taxrate)
            {
                $PriceTotal += $item->Total();
            }
        }
        
        $taxMode = $this->config()->get('tax_mode');
        
        if ($taxMode === 'exclusive') {
            // Tax is added on top: tax = price * (rate / 100)
            $taxAmount = $PriceTotal * ($taxrate / 100);
        } else {
            // Tax is included: extract tax from gross price
            $taxAmount = $PriceTotal - ($PriceTotal / ($taxrate / 100 + 1));
        }

        return number_format(round($taxAmount, 2),2,",","");
    }


    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        // While the order is still in "Cart" status, persist country code to DB
        if ($this->OrderID && $this->Order()->IsCart()) {
        }
    }
}
