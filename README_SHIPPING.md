# Conditional Shipping Address in Checkout

This module provides conditional shipping address fields in the checkout process based on whether products require physical shipping.

## How It Works

- **Default**: All products require shipping (physical goods)
- **Checkout**: Shipping address fields only show if at least one item in the cart requires shipping
- **Configuration**: Set `requires_shipping: false` for digital products, services, or courses

## Configuration Examples

### Via YML Config

Set for all instances of a product class:

```yaml
# _config/shopextensions.yml
Course:
  requires_shipping: false

DigitalDownload:
  requires_shipping: false

SilverShop\Page\Product:
  requires_shipping: true  # Default for physical products
```

### Via Extension

For more complex logic, create a product extension:

```php
<?php

namespace App\Extensions;

use SilverStripe\Core\Extension;

class MyProductExtension extends Extension
{
    public function requiresShipping()
    {
        // Custom logic
        if ($this->owner->ProductType === 'Digital') {
            return false;
        }
        
        // Check product category
        if ($this->owner->Categories()->filter('URLSegment', 'downloads')->exists()) {
            return false;
        }
        
        // Default to parent behavior
        return $this->owner->config()->get('requires_shipping');
    }
}
```

Then register it:

```yaml
SilverShop\Page\Product:
  extensions:
    - App\Extensions\MyProductExtension
```

## Mixed Cart Behavior

If a cart contains both digital and physical products:
- Shipping address **will be shown** (because at least one item needs it)
- This ensures physical goods can be delivered

## Implementation Details

### Files

- `ProductExtension.php` - Adds `requiresShipping()` method to products
- `OrderExtension.php` - Adds `requiresShipping()` method to orders (checks all items)
- `CustomCheckoutComponentConfig.php` - Conditionally includes ShippingAddress component

### Default Values

- Products: `requires_shipping: true` (physical goods by default)
- Orders: Returns `true` if cart is empty (safe default during initial checkout)
