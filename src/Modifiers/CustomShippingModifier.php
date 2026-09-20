<?php
namespace ShopExtensions;

use SilverShop\Model\Modifiers\OrderModifier;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;

/**
 * Order shipping-cost modifier (legacy).
 *
 * @deprecated Legacy flat-rate/weight-tier calculator from an early project. It is NOT
 * registered by this module and should not be used for new shops: it decides by the billing
 * country stored in the session by AjaxCartController (not the shipping address), knows only
 * "domestic" vs. "everything else", and has no VAT handling of its own. Write a project-specific
 * modifier instead. It is kept so existing projects that list it in Order.modifiers keep working.
 *
 * Calculation (all values configurable, defaults are the original hard-coded ones):
 *  - Domestic orders ({@see self::$domestic_country}, or no country in the session) pay
 *    {@see self::$domestic_rate} and ship free once the subtotal exceeds
 *    {@see self::$free_shipping_threshold}.
 *  - Other countries always pay by total weight: the first tier of
 *    {@see self::$international_weight_tiers} whose [min, max) range contains the weight, else
 *    {@see self::$international_base_rate}; plus per-product ShippingInternational surcharges.
 */
class CustomShippingModifier extends OrderModifier
{
    private static $singular_name = "Zzgl. Versandkosten incl. 19% MwSt.";

    private static $plural_name = "Zzgl. Versandkosten incl. 19% MwSt.";

    /**
     * ISO code of the domestic country.
     * @config
     */
    private static string $domestic_country = 'DE';

    /**
     * Flat domestic shipping rate.
     * @config
     */
    private static float $domestic_rate = 4.39;

    /**
     * Domestic orders with a subtotal ABOVE this amount ship free. null disables free shipping.
     * @config
     */
    private static ?float $free_shipping_threshold = 29;

    /**
     * Rate for non-domestic orders whose weight matches no tier.
     * @config
     */
    private static float $international_base_rate = 6.39;

    /**
     * Weight tiers for non-domestic orders: list of {min, max, rate}, weight in kg, min inclusive,
     * max exclusive. The first match wins.
     * @config
     */
    private static array $international_weight_tiers = [
        ['min' => 0.5, 'max' => 2, 'rate' => 10],
        ['min' => 2, 'max' => 5, 'rate' => 27],
        ['min' => 5, 'max' => 10, 'rate' => 35],
        ['min' => 10, 'max' => 20, 'rate' => 50],
        ['min' => 20, 'max' => 31, 'rate' => 31.5],
    ];

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
     * @param  int|float $incoming The running order amount.
     * @return int|float The amount including shipping where applicable.
     */
    public function value($incoming): int|float
    {
        if ($this->isInternational() || !$this->qualifiesForFreeShipping()) {
            return $incoming + $this->individualShipping();
        }

        return $incoming;
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
     * The shipping amount displayed in the order summary table: 0 when shipping is waived,
     * otherwise the calculated shipping cost.
     *
     * @return int|float The displayed shipping value.
     */
    public function TableValue()
    {
        $value = $this->individualShipping();

        if ($this->isInternational()) {
            return $value;
        }

        // A rate of exactly 1 was a legacy "free shipping" sentinel.
        if ($value == 1 || $this->qualifiesForFreeShipping()) {
            return 0;
        }

        return $value;
    }

    /**
     * Raw shipping cost for the current order before any free-shipping waiver.
     *
     * @return int|float
     */
    public function individualShipping()
    {
        if (!$this->isInternational()) {
            return (float) $this->config()->get('domestic_rate');
        }

        $order = $this->Order();
        $totalweight = 0;
        $additionalshippingfees = 0;
        if ($order && $orderItems = $order->Items()) {
            foreach ($orderItems as $orderItem) {
                if ($product = $orderItem->Product()) {
                    if ($product->Weight > 0) {
                        $totalweight += $product->Weight * $orderItem->Quantity;
                    }
                    if ($product->ShippingInternational > 0) {
                        $additionalshippingfees += $product->ShippingInternational * $orderItem->Quantity;
                    }
                }
            }
        }

        foreach ((array) $this->config()->get('international_weight_tiers') as $tier) {
            if ($totalweight >= (float) $tier['min'] && $totalweight < (float) $tier['max']) {
                return (float) $tier['rate'] + $additionalshippingfees;
            }
        }

        return (float) $this->config()->get('international_base_rate') + $additionalshippingfees;
    }

    /**
     * Whether the billing country selected in the session is not the domestic country.
     */
    protected function isInternational(): bool
    {
        $country = $this->sessionCountry();

        return $country && $country !== $this->config()->get('domestic_country');
    }

    protected function qualifiesForFreeShipping(): bool
    {
        $threshold = $this->config()->get('free_shipping_threshold');
        $order = $this->Order();

        return $threshold !== null && $order && $order->SubTotal() > (float) $threshold;
    }

    protected function sessionCountry(): ?string
    {
        // Controller::curr() returns null outside a request (CLI/queued job) in SilverStripe 6;
        // has_curr() no longer exists.
        $controller = Controller::curr();
        $request = $controller
            ? $controller->getRequest()
            : Injector::inst()->get(HTTPRequest::class);

        if (!$request || !$request->hasSession()) {
            return null;
        }

        return $request->getSession()->get('cartbillingcountry') ?: null;
    }
}
