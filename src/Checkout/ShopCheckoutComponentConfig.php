<?php

namespace ShopExtensions\Checkout;

use SilverShop\Checkout\CheckoutComponentConfig;
use SilverShop\Checkout\Component\CheckoutComponent;
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
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
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
 * Deliberately module-generic: it must not know about any single project. Projects add extra fields
 * with a subclass that implements {@see self::addProjectComponents()} (pointing
 * {@see \ShopExtensions\CheckoutPageControllerExtension::$checkout_component_config_class} at it), and
 * REPLACE single components through the config map {@see self::$component_classes} — no subclass
 * needed for that.
 */
class ShopCheckoutComponentConfig extends CheckoutComponentConfig
{
    /**
     * SilverShop defaults, used when a project configured a class that doesn't exist.
     */
    private const DEFAULT_COMPONENT_CLASSES = [
        'customer_details' => CustomerDetails::class,
        'shipping_address' => ShippingAddress::class,
        'billing_address' => BillingAddress::class,
        'membership' => Membership::class,
        'notes' => Notes::class,
        'terms' => Terms::class,
    ];

    // SilverShop's CheckoutComponentConfig only uses Injectable, but the constructor below reads
    // its flags via $this->config() — without this trait every checkout request is fatal.
    use Configurable;

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
     * Which class is used for each checkout component. Projects swap single components here instead
     * of subclassing this config and rebuilding the whole list (the parent list can't be reordered
     * through the CheckoutComponentNamespaced proxy). A MAP, so YAML merges by key:
     *
     *   ShopExtensions\Checkout\ShopCheckoutComponentConfig:
     *     component_classes:
     *       billing_address: 'ShopExtensions\Checkout\Component\BillingSameAsShippingAddress'
     *       membership: 'ShopExtensions\Checkout\Component\OptionalMembership'
     *
     * The payment component is NOT in the map: it is chosen by use_payment_tiles.
     *
     * @config
     * @var array
     */
    private static $component_classes = [
        'customer_details' => CustomerDetails::class,
        'shipping_address' => ShippingAddress::class,
        'billing_address' => BillingAddress::class,
        'membership' => Membership::class,
        'notes' => Notes::class,
        'terms' => Terms::class,
    ];

    /**
     * Offer logged-in customers to keep the entered shipping/billing address in their address book.
     *
     * @config
     * @var bool
     */
    private static $shipping_add_to_address_book = false;

    private static $billing_add_to_address_book = false;

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

        $this->addComponent($this->makeComponent('customer_details'));

        // Only add shipping address if order requires shipping
        if ($order->requiresShipping()) {
            $shipping = $this->makeComponent('shipping_address');
            if ($shipping instanceof ShippingAddress && $this->config()->get('shipping_add_to_address_book')) {
                $shipping->setAddToAddressBook(true);
            }
            $this->addComponent($shipping);
        }

        $billing = $this->makeComponent('billing_address');
        if ($billing instanceof BillingAddress && $this->config()->get('billing_add_to_address_book')) {
            $billing->setAddToAddressBook(true);
        }
        $this->addComponent($billing);

        if (Checkout::member_creation_enabled() && !Security::getCurrentUser()) {
            $this->addComponent($this->makeComponent('membership'));
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

        $this->addComponent($this->makeComponent('notes'));

        $this->addProjectComponents();

        $this->addComponent($this->makeComponent('terms'));
    }

    /**
     * Builds the component registered under $key in {@see self::$component_classes}, falling back to
     * the SilverShop default when a project configured an unknown class.
     */
    protected function makeComponent(string $key): CheckoutComponent
    {
        $classes = (array) $this->config()->get('component_classes');
        $class = (string) ($classes[$key] ?? '');

        if ($class === '' || !class_exists($class)) {
            $class = (string) (self::DEFAULT_COMPONENT_CLASSES[$key] ?? '');
        }

        return Injector::inst()->create($class);
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
