<?php

namespace ShopExtensions;

use SilverStripe\Core\Extension;

/**
 * Optional tax-breakdown engine for the German market, ported from the ss_oap_web
 * project's OrderTaxExtension and hardened for SilverShop ^6.
 *
 * It computes a per-tax-rate breakdown of an order ({@see self::getTaxInformation()}
 * returns `rate => ['gross', 'net', 'tax']`) that correctly attributes:
 *  - item and order-level coupon reductions (order coupons distributed by revenue ratio),
 *  - gift cards (deliberately NOT treated as a revenue reduction — they are a means of
 *    payment, so they never lower the taxable turnover),
 *  - shipping cost (distributed across the non-zero tax rates by revenue ratio).
 *
 * This extension is OPT-IN: it is not registered in the main shopextensions.yml. A project
 * enables it via _config/receiptexport.yml.example (Order.extensions). It adds only methods,
 * no DB fields, so enabling/disabling it needs no schema change.
 *
 * B2B/B2C awareness: the calculation mode is taken from
 * {@see \ShopExtensions\CustomTaxModifier::$tax_mode} (single source of truth):
 *  - "inclusive" (B2C): item totals are gross, tax is extracted.
 *  - "exclusive" (B2B/net): item totals are net, tax is added on top.
 *
 * All access to the silvershop/discounts models is guarded with class_exists(), so the base
 * module does not hard-depend on that package.
 *
 * @property \SilverShop\Model\Order $owner
 */
class OrderTaxExtension extends Extension
{
    /**
     * Frozen per-rate breakdown (JSON) captured at placement. Once set on a placed order, both the
     * display and the DATEV/PDF export read this instead of recalculating — so a later change of
     * tax_mode / rates / coupon logic can never retroactively alter a placed order's booked tax.
     */
    private static $db = [
        'FrozenTaxInformation' => 'Text',
    ];

    /**
     * Tax rate (percent) under which shipping is booked when an order contains only
     * 0%-taxed items. Configurable so non-19% jurisdictions can override it.
     *
     * @config
     * @var int
     */
    private static $high_tax_rate = 19;

    /**
     * Resolve the effective high tax rate from config.
     *
     * @return int
     */
    protected function highTaxRate(): int
    {
        // On SS6 an Extension's private statics are merged into the owner's config, so this
        // resolves ShopExtensions\OrderTaxExtension.high_tax_rate via the Order.
        return (int) $this->owner->config()->get('high_tax_rate') ?: 19;
    }

    /**
     * The current tax calculation mode ('inclusive' = B2C gross, 'exclusive' = B2B net).
     * Taken from CustomTaxModifier so display and export stay consistent.
     *
     * @return string
     */
    protected function taxMode(): string
    {
        if (class_exists(CustomTaxModifier::class)) {
            return (string) CustomTaxModifier::config()->get('tax_mode') ?: 'inclusive';
        }
        return 'inclusive';
    }

    /**
     * Resolve the product behind an order item, tolerating the versioned fallback.
     *
     * @param \SilverShop\Model\OrderItem $orderItem
     * @return \SilverShop\Page\Product|\SilverStripe\ORM\DataObject|null
     */
    protected function productFor($orderItem)
    {
        $product = $orderItem->Product();
        if (!$product || !$product->exists()) {
            // Versioned fallback: fetch the (possibly unpublished) product record.
            $product = $orderItem->Product(true);
        }
        return $product;
    }

    /**
     * Distinct product tax rates present in the order.
     *
     * Special case: if the only rate is 0% and shipping is included, the high tax rate is
     * appended so shipping on an otherwise tax-free order is still booked with VAT.
     *
     * @param bool $includeShipping
     * @return array<int|float>
     */
    public function getTaxRates($includeShipping = true)
    {
        $taxRates = [];
        foreach ($this->owner->Items() as $orderItem) {
            $product = $this->productFor($orderItem);
            if (!$product) {
                continue;
            }
            $taxRate = $product->Tax;
            if (!in_array($taxRate, $taxRates)) {
                $taxRates[] = $taxRate;
            }
        }

        if (count($taxRates) == 1 && $taxRates[0] == 0 && $includeShipping) {
            $taxRates[] = $this->highTaxRate();
        }

        return $taxRates;
    }

    /**
     * Highest tax rate present in the order.
     *
     * @param bool $includeShipping
     * @return int|float
     */
    public function getHighestTaxRate($includeShipping = true)
    {
        $highestTaxRate = 0;
        foreach ($this->getTaxRates($includeShipping) as $taxRate) {
            if ($taxRate > $highestTaxRate) {
                $highestTaxRate = $taxRate;
            }
        }
        return $highestTaxRate;
    }

    /**
     * Sum of item totals carrying the given tax rate.
     *
     * @param int|float|null $taxrate
     * @param bool $checkifapplicable When true, only items the coupon applies to are counted.
     * @return float
     */
    public function getSubTotalAmountForTaxrate($taxrate = null, $checkifapplicable = false)
    {
        $amount = 0;
        foreach ($this->owner->Items() as $orderItem) {
            $product = $this->productFor($orderItem);
            if (!$product || $product->Tax != $taxrate) {
                continue;
            }
            if ($checkifapplicable && !$this->checkOrderItemAgainstCoupon($orderItem)) {
                continue;
            }
            $amount += $orderItem->Total();
        }
        return $amount;
    }

    /**
     * Per-rate map of item subtotals.
     *
     * @param bool $checkifapplicable
     * @return array<int|float, float>
     */
    public function getSubTotalsByTaxrate($checkifapplicable = false)
    {
        $subTotals = [];
        foreach ($this->getTaxRates() as $taxRate) {
            $subTotals[$taxRate] = $this->getSubTotalAmountForTaxrate($taxRate, $checkifapplicable);
        }
        return $subTotals;
    }

    /**
     * Share of the order revenue that falls under a given tax rate — the distribution key
     * used to spread order-level coupons and shipping across rates.
     *
     * @param int|float|null $taxrate
     * @param bool $checkifapplicable Restrict the total to coupon-applicable items.
     * @param bool $ignorezerotaxrate Exclude 0%-taxed items from the denominator (shipping split).
     * @return float amount(rate) / total, or 0 when the total is 0.
     */
    public function getSubtotalRatioForTaxrate($taxrate = null, $checkifapplicable = false, $ignorezerotaxrate = false)
    {
        $amount = 0;
        $total = 0;

        foreach ($this->owner->Items() as $orderItem) {
            $product = $this->productFor($orderItem);
            if (!$product) {
                continue;
            }
            $applicable = !$checkifapplicable || $this->checkOrderItemAgainstCoupon($orderItem);

            if ($product->Tax == $taxrate && $applicable) {
                $amount += $orderItem->Total();
            }
            if ($applicable && !($ignorezerotaxrate && $product->Tax == 0)) {
                $total += $orderItem->Total();
            }
        }

        if ($total == 0) {
            return 0;
        }
        return $amount / $total;
    }

    /**
     * Coupon-driven revenue reductions per tax rate.
     *
     * Item coupons (getFor() == 'Items'): read the per-item DiscountAmount from the
     * many_many extra field and attribute it to the item's tax rate. Order/cart coupons:
     * take the total order discount and distribute it across rates by revenue ratio.
     *
     * Gift cards are excluded (they carry a GiftVoucherID) — they are payment, not a
     * reduction of taxable turnover.
     *
     * @return array<int|float, float>
     */
    public function getReductionsByTaxrates()
    {
        $order = $this->owner;
        $deductions = [];
        // Initialise every known rate to 0 to avoid undefined-key warnings downstream.
        foreach ($this->getTaxRates() as $rate) {
            $deductions[$rate] = 0;
        }

        if (!$this->hasCoupon()) {
            return $deductions;
        }

        $coupon = $this->getCoupon();
        $orderDiscountClass = 'SilverShop\\Discounts\\Model\\OrderDiscount';
        $orderCouponClass = 'SilverShop\\Discounts\\Model\\OrderCoupon';

        if ($coupon && method_exists($coupon, 'getFor') && $coupon->getFor() == 'Items') {
            foreach ($order->Items() as $item) {
                $product = $this->productFor($item);
                if (!$product || !method_exists($item, 'Discounts')) {
                    continue;
                }
                foreach ($item->Discounts() as $discount) {
                    $isOrderDiscount = ($discount->ClassName == $orderDiscountClass);
                    $isPlainCoupon = ($discount->ClassName == $orderCouponClass && !$discount->GiftVoucherID);
                    if (($isOrderDiscount || $isPlainCoupon) && $discount->Active) {
                        $deductions[$product->Tax] = ($deductions[$product->Tax] ?? 0) + $discount->DiscountAmount;
                    }
                }
            }
        } else {
            $modifier = $this->getDiscountModifier();
            $orderDiscountAmount = $modifier ? $modifier->getDiscount() : 0;
            foreach ($this->getTaxRates() as $taxrate) {
                $deductions[$taxrate] = $this->getSubtotalRatioForTaxrate($taxrate, true) * $orderDiscountAmount;
            }
        }

        return $deductions;
    }

    /**
     * Shipping cost distributed across the non-zero tax rates by revenue ratio.
     * Special case: an order with only a 0% rate books the whole shipping under the high rate.
     *
     * @return array<int|float, float>
     */
    public function getShippingAmountByTaxrate()
    {
        $shipping = $this->hasShippingCost() ? $this->getShippingCost() : 0;

        $taxrates = $this->getTaxRates(false);
        if (count($taxrates) == 1 && $taxrates[0] == 0) {
            return [$this->highTaxRate() => $shipping];
        }

        $taxratios = [];
        foreach ($taxrates as $taxrate) {
            if ($taxrate != 0) {
                $taxratios[$taxrate] = $this->getSubtotalRatioForTaxrate($taxrate, false, true) * $shipping;
            }
        }
        return $taxratios;
    }

    /**
     * The core breakdown: per tax rate, the gross / net / tax amounts.
     *
     * base = subtotal[rate] - reductions[rate] + shipping[rate].
     *  - inclusive (B2C): base is gross → net = gross / (1 + rate/100), tax = gross - net.
     *  - exclusive (B2B): base is net   → tax = net * rate/100, gross = net + tax.
     *
     * Values are returned as German-formatted strings (comma decimal, no thousands sep),
     * matching the CSV/PDF export expectations. Use floatval(str_replace(',', '.', …)) to
     * compute with them.
     *
     * For placed orders the frozen breakdown (captured at placement) is returned unchanged, so the
     * booked tax never moves; carts (and un-frozen legacy orders) are computed live.
     *
     * @return array<int|float, array{gross:string, net:string, tax:string}>
     */
    public function getTaxInformation()
    {
        if ($this->isTaxFrozen()) {
            $frozen = json_decode((string) $this->owner->getField('FrozenTaxInformation'), true);
            if (is_array($frozen) && $frozen) {
                return $frozen;
            }
        }
        return $this->computeTaxInformation();
    }

    /**
     * Whether this order's tax breakdown is frozen (placed order with a stored breakdown).
     */
    protected function isTaxFrozen(): bool
    {
        $order = $this->owner;
        return $order && $order->exists() && !$order->IsCart()
            && (string) $order->getField('FrozenTaxInformation') !== '';
    }

    /**
     * Freeze the current (live) per-rate breakdown onto the order. Called at placement (and by the
     * backfill task) so the export/display read stable figures afterwards. Does not write the order
     * itself — the caller persists it (placement writes the order right after).
     */
    public function freezeTaxInformation(): void
    {
        $this->owner->FrozenTaxInformation = json_encode($this->computeTaxInformation());
    }

    /**
     * The live per-rate breakdown (gross/net/tax), computed from the current order state.
     *
     * @return array<int|float, array{gross:string, net:string, tax:string}>
     */
    public function computeTaxInformation()
    {
        $amounts = $this->getSubTotalsByTaxrate();
        $reductions = $this->getReductionsByTaxrates();
        $shipping = $this->getShippingAmountByTaxrate();
        $inclusive = ($this->taxMode() !== 'exclusive');

        $result = [];
        foreach ($this->getTaxRates() as $taxrate) {
            $base = ($amounts[$taxrate] ?? 0) - ($reductions[$taxrate] ?? 0) + ($shipping[$taxrate] ?? 0);

            if ($inclusive) {
                $grossAmount = $base;
                $netAmount = $grossAmount / ((100 + $taxrate) / 100);
                $taxAmount = $grossAmount - $netAmount;
            } else {
                $netAmount = $base;
                $taxAmount = $netAmount * ($taxrate / 100);
                $grossAmount = $netAmount + $taxAmount;
            }

            $result[$taxrate] = [
                'gross' => number_format(round($grossAmount, 2), 2, ',', ''),
                'net' => number_format(round($netAmount, 2), 2, ',', ''),
                'tax' => number_format(round($taxAmount, 2), 2, ',', ''),
            ];
        }
        return $result;
    }

    /**
     * Whether an order item is covered by the active coupon.
     *
     * silvershop/discounts ^2 (SS4/5) exposed Discount::match($item); SS6/dev-main does not.
     * When match() exists we honour it (port compatibility); otherwise a cart/order coupon is
     * assumed to apply to every item (the sensible default for whole-order coupons).
     *
     * @param \SilverShop\Model\OrderItem $orderItem
     * @return bool
     */
    public function checkOrderItemAgainstCoupon($orderItem)
    {
        $coupon = $this->getCoupon();
        if ($coupon && method_exists($coupon, 'match')) {
            return (bool) $coupon->match($orderItem, $coupon);
        }
        return true;
    }

    /**
     * Whether the order carries a (non-gift-card) discount coupon.
     *
     * @return bool
     */
    public function hasCoupon()
    {
        foreach ($this->owner->Discounts() as $discount) {
            if (!$discount->GiftVoucherID) {
                return true;
            }
        }
        return false;
    }

    /**
     * The first non-gift-card discount, or false.
     *
     * @return \SilverStripe\ORM\DataObject|false
     */
    public function getCoupon()
    {
        foreach ($this->owner->Discounts() as $discount) {
            if (!$discount->GiftVoucherID) {
                return $discount;
            }
        }
        return false;
    }

    /**
     * Whether the order carries a gift card (a discount with a GiftVoucherID).
     *
     * @return bool
     */
    public function hasGiftCard()
    {
        foreach ($this->owner->Discounts() as $discount) {
            if ($discount->GiftVoucherID) {
                return true;
            }
        }
        return false;
    }

    /**
     * The gift-card discount, or false.
     *
     * @return \SilverStripe\ORM\DataObject|false
     */
    public function getGiftCard()
    {
        foreach ($this->owner->Discounts() as $discount) {
            if ($discount->GiftVoucherID) {
                return $discount;
            }
        }
        return false;
    }

    /**
     * The order's discount modifier (class name contains "Discount"), or false.
     *
     * @return \SilverShop\Model\Modifiers\OrderModifier|false
     */
    public function getDiscountModifier()
    {
        foreach ($this->owner->Modifiers() as $modifier) {
            if (stristr($modifier->ClassName, 'Discount')) {
                return $modifier;
            }
        }
        return false;
    }

    /**
     * Whether the order has a shipping modifier with a positive amount.
     *
     * @return bool
     */
    public function hasShippingCost()
    {
        foreach ($this->owner->Modifiers() as $modifier) {
            if (stristr($modifier->ClassName, 'Shipping') && $modifier->Amount > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * The shipping cost, or 0.
     *
     * @return float
     */
    public function getShippingCost()
    {
        foreach ($this->owner->Modifiers() as $modifier) {
            if (stristr($modifier->ClassName, 'Shipping') && $modifier->Amount > 0) {
                return $modifier->Amount;
            }
        }
        return 0;
    }
}
