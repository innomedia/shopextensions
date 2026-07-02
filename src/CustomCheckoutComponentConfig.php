<?php

namespace ShopExtensions;

use SilverShop\Checkout\CheckoutComponentConfig;
use SilverShop\Checkout\Component\BillingAddress;
use SilverShop\Checkout\Component\CustomerDetails;
use SilverShop\Checkout\Component\Membership;
use SilverShop\Checkout\Component\Notes;
use SilverShop\Checkout\Component\Payment;
use SilverShop\Checkout\Component\ShippingAddress;
use SilverShop\Checkout\Component\Terms;
use SilverShop\Checkout\Checkout;
use SilverShop\Model\Order;
use ShopExtensions\Checkout\PaymentMethodComponent;
use ShopExtensions\Model\PaymentOption;
use SilverStripe\Core\Config\Config;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Security\Security;

/**
 * Custom checkout component configuration for the SilverShop checkout.
 *
 * Mirrors the default SilverShop checkout component set (customer details, billing
 * address, membership, payment, notes and terms) but only adds the shipping address
 * component when the order actually requires shipping. This keeps the checkout short for
 * digital goods and courses, which do not need a delivery address.
 *
 * Wired in as the injector implementation of
 * {@see \SilverShop\Checkout\CheckoutComponentConfig} in _config/shopextensions.yml.
 */
class CustomCheckoutComponentConfig extends CheckoutComponentConfig
{
    /**
     * When true, the checkout offers the configurable payment tiles
     * ({@see \ShopExtensions\Checkout\PaymentMethodComponent}) instead of SilverShop's plain
     * gateway radio list. Default false, so existing projects behave exactly as before until
     * they opt in (see _config/paymenttiles.yml.example).
     *
     * @config
     * @var bool
     */
    private static $use_payment_tiles = false;

    /**
     * Assembles the checkout components for the given order.
     *
     * The shipping address component is omitted when the order does not require shipping,
     * the membership component is only offered to guests when member creation is enabled,
     * and the payment component is only shown when more than one gateway is available.
     *
     * @param Order $order The order being checked out.
     */
    public function __construct(Order $order)
    {
        parent::__construct($order);
        
        $this->addComponent(CustomerDetails::create());
        
        // Only add shipping address if order requires shipping
        if ($order->requiresShipping()) {
            $this->addComponent(ShippingAddress::create());
        }
        
        $this->addComponent(BillingAddress::create());
        
        if (Checkout::member_creation_enabled() && !Security::getCurrentUser()) {
            $this->addComponent(Membership::create());
        }

        // Payment tiles (opt-in) replace the plain gateway radio list. They are shown whenever
        // at least one usable PaymentOption exists; otherwise we fall back to the default
        // behaviour so the customer can always pay.
        $useTiles = Config::inst()->get(self::class, 'use_payment_tiles')
            && class_exists(PaymentOption::class)
            && PaymentOption::get()->filter('Enabled', 1)->exists();

        if ($useTiles) {
            $this->addComponent(PaymentMethodComponent::create());
        } elseif (count(GatewayInfo::getSupportedGateways()) > 1) {
            $this->addComponent(Payment::create());
        }

        $this->addComponent(Notes::create());
        $this->addComponent(Terms::create());
    }
}
