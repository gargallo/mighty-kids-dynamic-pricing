# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a WordPress plugin for WooCommerce that provides dynamic pricing displays with subscription and bulk discount support. The plugin integrates with WooCommerce All Products for Subscriptions and can optionally integrate with a companion Mighty Kids plugin.

**Key Dependencies:**
- WordPress 6.6+
- PHP 8.2+
- WooCommerce (required)
- WooCommerce All Products for Subscriptions (optional, for subscription features)
- Mighty Kids plugin (optional, for shared settings integration)

## Local Development Environment

This plugin is located within a Local by Flywheel development environment:

**Site Path:** `/Users/gargallo/Local Sites/mightykids/`
**Plugin Path:** `/Users/gargallo/Local Sites/mightykids/app/public/wp-content/plugins/mighty-kids-dynamic-pricing/`

### Using WP-CLI

Access WP-CLI through Local by Flywheel:
```bash
# Open shell in Local app, or use:
wp --path="/Users/gargallo/Local Sites/mightykids/app/public" [command]
```

Common WP-CLI commands:
```bash
# Plugin management
wp plugin list
wp plugin activate mighty-kids-dynamic-pricing
wp plugin deactivate mighty-kids-dynamic-pricing

# Clear caches (important after code changes)
wp cache flush
wp transient delete --all

# Database operations
wp db query "SELECT * FROM wp_options WHERE option_name LIKE 'mkdynamic%'"

# Check WooCommerce products
wp wc product list
```

### Accessing Logs

Local by Flywheel logs are accessible through:
- Local app: Site → Logs tab
- File system: `/Users/gargallo/Local Sites/mightykids/logs/`
- PHP errors: Check `wp-content/debug.log` (if `WP_DEBUG_LOG` is enabled)

## Architecture

### Plugin Structure

The plugin uses a singleton pattern for initialization and follows WordPress/WooCommerce coding standards:

**Main Entry Point:** [mighty-kids-dynamic-pricing.php](mighty-kids-dynamic-pricing.php)
- Defines constants (MKDYNAMIC_VERSION, MKDYNAMIC_DIR_PATH, etc.)
- Instantiates `Mighty_Kids_Dynamic_Pricing` singleton
- Handles plugin activation/deactivation hooks

**Core Classes:**
- **Dynamic_Pricing** ([includes/class-dynamic-pricing.php](includes/class-dynamic-pricing.php)): Main functionality for price calculations and frontend rendering
- **Settings** ([includes/class-settings.php](includes/class-settings.php)): Manages plugin options with validation
- **Admin_Settings** ([admin/class-admin-settings.php](admin/class-admin-settings.php)): WordPress admin settings interface
- **Utils** ([includes/class-utils.php](includes/class-utils.php)): Helper functions for asset management and plugin checks

### Integration Points

**WooCommerce Hooks:**
- `woocommerce_before_add_to_cart_button` (priority 5): Renders dynamic price container
- `woocommerce_update_product`: Clears subscription schemes cache
- `woocommerce_new_product`: Clears subscription schemes cache

**Mighty Kids Plugin Integration:**
The plugin checks for the companion Mighty Kids plugin and prefers its settings when available:
- Bulk discount settings: `\Progressus\MightyKids\Admin\Admin_Settings::is_bulk_discount_enabled()`
- Discount tiers: `\Progressus\MightyKids\Discounts\Bulk_Discount_Manager::get_discount_tiers()`
- Product qualification: `\Progressus\MightyKids\Discounts\Bulk_Discount_Manager::product_qualifies_for_bulk_discounts()`

### Caching Strategy

Subscription schemes are cached using WordPress transients to reduce database queries:
- Cache key: `mkdynamic_sub_schemes_{product_id}`
- TTL: 1 hour for products with schemes, 5 minutes for products without
- Cleared on product update/creation via `clear_schemes_cache_on_update()`

### Frontend Assets

JavaScript handles real-time price updates based on quantity and subscription selection:
- Main script: [assets/js/dynamic-pricing.js](assets/js/dynamic-pricing.js)
- Minified version: [assets/js/dynamic-pricing.min.js](assets/js/dynamic-pricing.min.js)
- Uses `SCRIPT_DEBUG` constant to determine which version to load
- Localized data includes: product ID, base price, discount tiers, subscription schemes, currency format

## Common Development Tasks

### Testing Dynamic Pricing Display

1. Ensure WooCommerce is active
2. Create/edit a WooCommerce product
3. Navigate to the product page on the frontend
4. The dynamic price display appears only when:
   - Bulk discounts are enabled AND product qualifies, OR
   - Product has subscription schemes

### Debugging

Enable WordPress debugging in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true); // Uses non-minified JS
```

JavaScript debugging data is available in browser console when `WP_DEBUG` is enabled (check `mkDynamicPricing.debug`).

### Clearing Caches After Changes

```bash
# Clear WordPress transients
wp transient delete --all

# Or programmatically clear specific product cache:
wp eval "MightyKids\DynamicPricing\Dynamic_Pricing::clear_schemes_cache(123);" # Replace 123 with product ID

# Clear WooCommerce caches
wp cache flush
```

### Modifying Discount Behavior

**Via Filters:**
```php
// Change default subscription discount
add_filter('mkdynamic_subscription_discount_percentage', function($discount) {
    return 15.0; // Change from default 10%
});

// Modify discount tiers
add_filter('mkdynamic_discount_tiers', function($tiers) {
    return [
        ['min' => 2, 'max' => 2, 'discount' => 5],
        ['min' => 3, 'max' => 4, 'discount' => 10],
        ['min' => 5, 'max' => '', 'discount' => 15],
    ];
});
```

### Working with Settings

Settings are stored as WordPress options with the prefix `mkdynamic_`:
- `mkdynamic_bulk_discounts_enabled`: '0' or '1'
- `mkdynamic_subscription_discount`: Percentage as string (e.g., '10')
- `mkdynamic_discount_tiers`: Serialized array of tier configurations

Access via Settings class:
```php
Settings::is_bulk_discounts_enabled(); // bool
Settings::get_subscription_discount(); // float
Settings::get_discount_tiers(); // array
```

## Code Style Notes

- Uses PHP 8.2+ features (typed properties, union types)
- Follows WordPress Coding Standards
- All classes are namespaced under `MightyKids\DynamicPricing`
- Text domain: `mighty-kids-dynamic-pricing`
- Plugin constants use `MKDYNAMIC_` prefix
- All output is properly escaped (`esc_html_e`, `esc_attr`, etc.)
- Uses WordPress nonce verification for settings (handled by Settings API)

## Admin Interface

Settings page location: **WooCommerce → Dynamic Pricing**

When the Mighty Kids plugin is active, a notice appears indicating that the main plugin's settings take precedence.
