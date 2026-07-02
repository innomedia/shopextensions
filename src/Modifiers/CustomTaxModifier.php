<?php
namespace ShopExtensions;

use SilverShop\Model\Modifiers\Tax\Base;

/**
 * Order tax modifier for the German market.
 *
 * Two calculation modes via {@see self::$tax_mode}:
 *  - "inclusive": prices already contain the tax (gross). The tax is extracted for display and
 *    does not change the order total (this modifier contributes 0 to the running total).
 *  - "exclusive": prices are net. The tax is added on top of the subtotal.
 *
 * FREEZING (idiomatic): the live calculation lives in {@see self::value()}; SilverShop's base
 * OrderModifier::modify() then freezes the total contribution into $this->Amount (recomputed only
 * while the order is a cart, read from the DB once placed). The per-RATE breakdown shown on the
 * invoice is frozen per line onto the OrderItems (TaxRate/TaxAmount, see
 * {@see \ShopExtensions\OrderExtension::freezeItemTaxes()}), and the tax mode onto the order
 * (Order.TaxMode). For placed orders the display therefore reads the frozen per-line values and
 * never recalculates — a later change of tax_mode/rates cannot alter a placed order.
 *
 * Rates are resolved per product (product Tax field) when {@see self::$per_product_tax} is on,
 * otherwise a single {@see self::$default_tax_rate}. Multiple rates in one order are grouped and
 * reported separately (e.g. 7% and 19%).
 */
class CustomTaxModifier extends Base
{
    private static $table_name = 'SilverShop_CustomTaxModifier';

    /**
     * Tax calculation mode: 'inclusive' or 'exclusive'
     * @config @var string
     */
    private static $tax_mode = 'inclusive';

    /**
     * Enable per-product tax rates. If false, all products use default_tax_rate.
     * @config @var bool
     */
    private static $per_product_tax = true;

    /**
     * Default/static tax rate (percentage). Used when per_product_tax is off, or as fallback.
     * @config @var float
     */
    private static $default_tax_rate = 19;

    /**
     * Tax rates per country.
     * @config @var array
     */
    private static $country_rates = [];

    /**
     * When true AND the optional {@see \ShopExtensions\OrderTaxExtension} engine is registered on
     * the order, the LIVE calculation (cart display + the amount charged + what gets frozen onto
     * the items at placement) is taken from the engine's getTaxInformation(), which additionally
     * attributes coupon/gift-card reductions and shipping across the tax rates. Default false.
     * @config @var bool
     */
    private static $use_tax_engine_for_display = false;

    // ---------------------------------------------------------------------------------------------
    // Live engine bridge
    // ---------------------------------------------------------------------------------------------

    /**
     * The engine's per-rate breakdown when the bridge is enabled and available, else null.
     *
     * @return array<int|float|string, array{gross:string, net:string, tax:string}>|null
     */
    protected function engineTaxInfo()
    {
        if (!$this->config()->get('use_tax_engine_for_display')) {
            return null;
        }
        $order = $this->Order();
        if ($order && $order->hasMethod('getTaxInformation')) {
            return $order->getTaxInformation();
        }
        return null;
    }

    /**
     * Whether the owning order is placed (no longer a cart). Placed orders display the tax exactly
     * as it was frozen at placement — never recalculated from the current config.
     */
    protected function isFrozen(): bool
    {
        $order = $this->Order();
        return $order && $order->exists() && !$order->IsCart();
    }

    // ---------------------------------------------------------------------------------------------
    // Modifier contract
    // ---------------------------------------------------------------------------------------------

    /**
     * Uses a custom table value/title rendering (see CustomTableValue()/TableTitle()).
     */
    public function Custom()
    {
        return true;
    }

    /**
     * Live contribution of this modifier to the running order total: in "exclusive" mode the tax is
     * added on top (net → gross); in "inclusive" mode the tax is already in the prices, so it adds
     * nothing. SilverShop's base OrderModifier::modify() calls this while the order is a cart and
     * freezes the result into $this->Amount, reading the stored Amount once the order is placed.
     *
     * @param int|float $incoming running order total
     * @return int|float
     */
    public function value($incoming): int|float
    {
        if ($this->config()->get('tax_mode') !== 'exclusive') {
            return 0;
        }
        return $this->liveTotalTax();
    }

    /**
     * Whether the tax is separately billed. Always false; behaviour is driven by tax_mode.
     */
    public function taxBilled()
    {
        return false;
    }

    /**
     * Whether this modifier is shown in the order summary table.
     */
    public function ShowInTable(): bool
    {
        return true;
    }

    // ---------------------------------------------------------------------------------------------
    // Display (frozen for placed orders, live for carts)
    // ---------------------------------------------------------------------------------------------

    /**
     * One label line per distinct rate: "zzgl. MwSt. zu <rate>%" (exclusive) or
     * "Enthaltene MwSt. zu <rate>%" (inclusive). For placed orders the frozen Order.TaxMode is used
     * so the label stays correct even if the config changed after placement.
     */
    public function TableTitle()
    {
        $taxMode = ($this->isFrozen() && $this->Order()->TaxMode)
            ? $this->Order()->TaxMode
            : $this->config()->get('tax_mode');
        $label = ($taxMode === 'exclusive') ? 'zzgl. MwSt. zu' : 'Enthaltene MwSt. zu';

        $html = "";
        foreach ($this->getTaxRates() as $rate) {
            $html .= $label . " " . $this->formatRate($rate) . "%<br/>";
        }
        return substr($html, 0, max(0, strlen($html) - 5));
    }

    /**
     * One value line per distinct rate with its tax total in euros.
     */
    public function CustomTableValue()
    {
        $html = "";
        foreach ($this->getTaxRates() as $rate) {
            $html .= $this->getProductTaxTotalByTaxRate($rate) . '€' . "<br/>";
        }
        return substr($html, 0, max(0, strlen($html) - 5));
    }

    /**
     * Combined tax across all distinct rates.
     *
     * @return float
     */
    public function TotalTaxAmount()
    {
        $total = 0;
        foreach ($this->getTaxRates() as $rate) {
            $total += floatval(str_replace(',', '.', $this->getProductTaxTotalByTaxRate($rate)));
        }
        return $total;
    }

    // ---------------------------------------------------------------------------------------------
    // Rate resolution + per-rate totals
    // ---------------------------------------------------------------------------------------------

    /**
     * Distinct tax rates to display: frozen per-line rates for placed orders, otherwise the live
     * rates (engine keys when the bridge is on, else the products' rates).
     *
     * @return array
     */
    private function getTaxRates()
    {
        if ($this->isFrozen()) {
            return $this->frozenRates();
        }
        if (($info = $this->engineTaxInfo()) !== null) {
            return array_keys($info);
        }
        return $this->liveRates();
    }

    /**
     * The per-rate tax total (comma-decimal string): frozen sum of the line TaxAmounts for placed
     * orders, otherwise the live engine value (bridge on) or the simple item-based calculation.
     *
     * @param float|int|string $taxrate
     * @return string
     */
    private function getProductTaxTotalByTaxRate($taxrate)
    {
        if ($this->isFrozen()) {
            $sum = 0.0;
            foreach ($this->Order()->Items() as $item) {
                if ((float) $item->TaxRate === (float) $taxrate) {
                    $sum += (float) $item->TaxAmount;
                }
            }
            return number_format(round($sum, 2), 2, ",", "");
        }

        if (($info = $this->engineTaxInfo()) !== null) {
            foreach ($info as $rate => $data) {
                if ((float) $rate === (float) $taxrate) {
                    return $data['tax'];
                }
            }
            return number_format(0, 2, ",", "");
        }

        return $this->simpleRateTax($taxrate);
    }

    /**
     * The tax rate for a product (product Tax field when per_product_tax is on, else the default).
     * Public so the placement freeze ({@see \ShopExtensions\OrderExtension::freezeItemTaxes()}) can
     * group items by rate consistently with the display.
     *
     * @param \SilverShop\Page\Product|null $product
     * @return float
     */
    public function rateForProduct($product)
    {
        $defaultRate = (float) $this->config()->get('default_tax_rate');
        if (!$this->config()->get('per_product_tax')) {
            return $defaultRate;
        }
        if ($product && $product->Tax !== null && $product->Tax > 0) {
            return (float) $product->Tax;
        }
        return $defaultRate;
    }

    /**
     * Live per-rate tax breakdown (rate-string => tax float), engine-aware. Computed from the
     * current line totals regardless of the order's cart/placed status — used both by value() and
     * by the placement freeze to allocate tax onto the items.
     *
     * @return array<string, float>
     */
    public function getLiveRateBreakdown(): array
    {
        $out = [];
        if (($info = $this->engineTaxInfo()) !== null) {
            foreach ($info as $rate => $data) {
                $out[(string) $rate] = floatval(str_replace(',', '.', $data['tax']));
            }
            return $out;
        }
        foreach ($this->liveRates() as $rate) {
            $out[(string) $rate] = floatval(str_replace(',', '.', $this->simpleRateTax($rate)));
        }
        return $out;
    }

    /**
     * Sum of the live per-rate tax (used by value() for the exclusive-mode total contribution).
     */
    protected function liveTotalTax(): float
    {
        $total = 0.0;
        foreach ($this->getLiveRateBreakdown() as $tax) {
            $total += (float) $tax;
        }
        return $total;
    }

    /**
     * The distinct product rates in the order (live, status-agnostic).
     *
     * @return array<int, float>
     */
    protected function liveRates(): array
    {
        if (!$this->config()->get('per_product_tax')) {
            return [(float) $this->config()->get('default_tax_rate')];
        }
        $rates = [];
        foreach ($this->Order()->Items() as $item) {
            $rate = $this->rateForProduct($item->Product());
            if (!in_array($rate, $rates, true)) {
                $rates[] = $rate;
            }
        }
        return $rates ?: [(float) $this->config()->get('default_tax_rate')];
    }

    /**
     * Distinct frozen per-line rates for a placed order.
     *
     * @return array<int, float>
     */
    protected function frozenRates(): array
    {
        $rates = [];
        foreach ($this->Order()->Items() as $item) {
            $rate = (float) $item->TaxRate;
            if (!in_array($rate, $rates, true)) {
                $rates[] = $rate;
            }
        }
        return $rates ?: [(float) $this->config()->get('default_tax_rate')];
    }

    /**
     * Simple item-based tax for one rate (comma-decimal string), status-agnostic:
     *  - exclusive: tax = sum(item totals at rate) * rate/100
     *  - inclusive: tax = total - total / (1 + rate/100)   (0 for a 0% rate)
     *
     * @param float|int|string $taxrate
     * @return string
     */
    protected function simpleRateTax($taxrate)
    {
        $priceTotal = 0.0;
        foreach ($this->Order()->Items() as $item) {
            if ($this->rateForProduct($item->Product()) == $taxrate) {
                $priceTotal += (float) $item->Total();
            }
        }

        if ($this->config()->get('tax_mode') === 'exclusive') {
            $taxAmount = $priceTotal * ($taxrate / 100);
        } else {
            $taxAmount = $taxrate > 0 ? $priceTotal - ($priceTotal / ($taxrate / 100 + 1)) : 0.0;
        }

        return number_format(round($taxAmount, 2), 2, ",", "");
    }

    /**
     * Render a rate without trailing ".00" (19.00 → "19", 7.5 → "7.5").
     *
     * @param float|int|string $rate
     * @return string
     */
    protected function formatRate($rate): string
    {
        $f = (float) $rate;
        return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.');
    }
}
