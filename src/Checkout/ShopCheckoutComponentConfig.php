<?php

namespace ShopExtensions\Checkout;

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
use ShopExtensions\Model\PaymentOption;
use SilverStripe\Omnipay\GatewayInfo;
use SilverStripe\Security\Security;

/**
 * Checkout component configuration shipped with this module.
 *
 * Mirrors the default SilverShop checkout component set (customer details, billing
 * address, membership, payment, notes and terms) but only adds the shipping address
 * component when the order actually requires shipping. This keeps the checkout short for
 * digital goods and courses, which do not need a delivery address.
 *
 * Deliberately module-generic: it must not know about any single project. Projects that
 * need extra checkout fields subclass this config, implement
 * {@see self::addProjectComponents()} and point
 * {@see \ShopExtensions\CheckoutPageControllerExtension::$checkout_component_config_class}
 * at their subclass.
 */
class ShopCheckoutComponentConfig extends CheckoutComponentConfig
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
     * @param Order $order      The order being checked out.
     * @param bool  $namespaced Whether field names are namespaced per component.
     */
    public function __construct(Order $order, bool $namespaced = true)
    {
        parent::__construct($order, $namespaced);

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
        //
        // Read via config() (i.e. static::class) so a project subclass may carry its own flag,
        // while a value set on this class is still inherited by every subclass.
        $useTiles = $this->config()->get('use_payment_tiles')
            && class_exists(PaymentOption::class)
            && PaymentOption::get()->filter('Enabled', 1)->exists();

        if ($useTiles) {
            $this->addComponent(PaymentMethodComponent::create());
        } elseif (count(GatewayInfo::getSupportedGateways()) > 1) {
            $this->addComponent(Payment::create());
        }

        $this->addComponent(Notes::create());

        $this->addProjectComponents();

        $this->addComponent(Terms::create());
    }

    /**
     * Extension point for project-specific checkout components.
     *
     * Called after Notes and before Terms, so subclasses get the ordering right without
     * having to fight CheckoutComponentConfig::addComponent()'s $insertBefore (which cannot
     * match through the CheckoutComponentNamespaced proxy).
     */
    protected function addProjectComponents(): void
    {
    }
}
