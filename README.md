# Mighty Kids Dynamic Pricing

A WordPress plugin that provides dynamic pricing display for WooCommerce products with subscription and bulk discount support.

## Features

- **Real-time price updates** on product pages
- **Subscription discount support** (integrates with WooCommerce All Products for Subscriptions)
- **Bulk discount support** (quantity-based pricing)
- **Variable product support** with variation selection
- **Caching system** for improved performance
- **Admin settings panel** for configuration
- **Integration with Mighty Kids plugin** (when available)

## Requirements

- WordPress 6.6+
- PHP 8.2+
- WooCommerce (required)
- WooCommerce All Products for Subscriptions (optional, for subscription discounts)

## Installation

1. Upload the plugin files to `/wp-content/plugins/mighty-kids-dynamic-pricing/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the settings under WooCommerce > Dynamic Pricing

## Configuration

### Basic Settings

- **Enable Bulk Discounts**: Toggle quantity-based pricing
- **Subscription Discount (%)**: Default discount for subscription products
- **Discount Tiers**: Configure quantity-based discount levels

### Integration with Mighty Kids Plugin

When the Mighty Kids plugin is active, this plugin will automatically use:

- Bulk discount settings from the main plugin
- Discount tiers configuration
- Product qualification rules

## Usage

The plugin automatically displays dynamic pricing on product pages when:

1. Bulk discounts are enabled AND the product qualifies, OR
2. The product has subscription schemes (independent of bulk discounts)

## Hooks and Filters

### Filters

- `mkdynamic_subscription_discount_percentage`: Modify the default subscription discount percentage
- `mkdynamic_discount_tiers`: Modify the discount tiers configuration

### Example

```php
// Change default subscription discount to 15%
add_filter('mkdynamic_subscription_discount_percentage', function() {
    return 15.0;
});

// Custom discount tiers
add_filter('mkdynamic_discount_tiers', function($tiers) {
    return [
        ['min' => 2, 'max' => 2, 'discount' => 5],
        ['min' => 3, 'max' => 4, 'discount' => 10],
        ['min' => 5, 'max' => '', 'discount' => 15],
    ];
});
```

## Development

### File Structure

```
mighty-kids-dynamic-pricing/
├── mighty-kids-dynamic-pricing.php    # Main plugin file
├── includes/
│   ├── class-dynamic-pricing.php      # Main functionality
│   ├── class-settings.php             # Settings management
│   └── class-utils.php                # Utility functions
├── admin/
│   └── class-admin-settings.php       # Admin interface
└── assets/
    ├── css/
    │   └── dynamic-pricing.css        # Styles
    └── js/
        ├── dynamic-pricing.js         # JavaScript
        └── dynamic-pricing.min.js     # Minified JavaScript
```

## Changelog

### 1.0.0

- Initial release
- Dynamic pricing display for subscriptions and bulk discounts
- Admin settings panel
- Integration with Mighty Kids plugin

## Support

For support and feature requests, please contact the development team.

## License

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
