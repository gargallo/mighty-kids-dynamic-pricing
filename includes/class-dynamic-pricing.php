<?php
/**
 * Dynamic Pricing Display
 *
 * Handles real-time price updates on product pages when subscription options
 * or quantity changes. Integrates with WooCommerce All Products for Subscriptions
 * and bulk discount systems.
 *
 * @package MightyKids\DynamicPricing
 * @since 1.0.0
 */

namespace MightyKids\DynamicPricing;

use MightyKids\DynamicPricing\Settings;
use MightyKids\DynamicPricing\Utils;
use WC_Product;
use WC_Product_Variable;

defined( 'ABSPATH' ) || exit;

/**
 * Class Dynamic_Pricing
 *
 * Manages dynamic price display for products with subscriptions and bulk discounts.
 */
class Dynamic_Pricing {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Only run on product pages.
		if ( ! is_admin() ) {
			add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'enqueue_and_render' ), 5 );
		}

		// Clear cache when product is updated.
		add_action( 'woocommerce_update_product', array( $this, 'clear_schemes_cache_on_update' ), 10, 1 );
		add_action( 'woocommerce_new_product', array( $this, 'clear_schemes_cache_on_update' ), 10, 1 );
	}

	/**
	 * Clear schemes cache when product is updated.
	 *
	 * @param int $product_id The product ID.
	 */
	public function clear_schemes_cache_on_update( int $product_id ): void {
		self::clear_schemes_cache( $product_id );
	}
	
	/**
	 * Enqueue scripts and render price container.
	 */
	public function enqueue_and_render(): void {
		$this->enqueue_scripts();
		$this->render_price_container();
	}

	/**
	 * Enqueue scripts for dynamic price display.
	 */
	public function enqueue_scripts(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'mk-dynamic-pricing',
			Utils::get_css_file_url( 'dynamic-pricing' ),
			array(),
			MKDYNAMIC_VERSION
		);

		// Enqueue JavaScript.
		wp_enqueue_script(
			'mk-dynamic-pricing',
			Utils::get_js_file_url( 'dynamic-pricing' ),
			array( 'jquery', 'wc-add-to-cart-variation' ),
			MKDYNAMIC_VERSION,
			true
		);

		// Localize script with product data.
		wp_localize_script(
			'mk-dynamic-pricing',
			'mkDynamicPricing',
			array(
				'productId'            => $product->get_id(),
				'basePrice'            => $this->get_base_price( $product ),
				'regularPrice'         => $this->get_regular_price( $product ),
				'discountTiers'        => $this->get_discount_tiers(),
				'subscriptionDiscount' => $this->get_subscription_discount(),
				'subscriptionSchemes'  => $this->get_subscription_schemes( $product ),
				'currencyFormat'       => $this->get_currency_format(),
				'isVariable'           => $product instanceof WC_Product_Variable,
				'bulkDiscountsEnabled' => $this->is_bulk_discounts_enabled(),
				'debug'                => defined( 'WP_DEBUG' ) && WP_DEBUG,
			)
		);
	}

	/**
	 * Render the price container for dynamic updates.
	 */
	public function render_price_container(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		// Check if bulk discounts are enabled globally.
		$bulk_discounts_enabled = $this->is_bulk_discounts_enabled();
		
		// Check if product has subscription schemes.
		$has_subscriptions = ! empty( $this->get_subscription_schemes( $product ) );

		// Only show if:
		// 1. Bulk discounts are enabled AND product qualifies, OR
		// 2. Product has subscriptions (independent of bulk discounts)
		if ( ! $has_subscriptions ) {
			// No subscriptions, so bulk discounts must be enabled
			if ( ! $bulk_discounts_enabled ) {
				return;
			}
			
			// Bulk discounts enabled, check if product qualifies
			if ( ! $this->product_qualifies_for_bulk_discounts( $product ) ) {
				return;
			}
		} elseif ( $bulk_discounts_enabled ) {
			// Has subscriptions AND bulk enabled, check product qualification
			if ( ! $this->product_qualifies_for_bulk_discounts( $product ) ) {
				return;
			}
		}
		// If has subscriptions but bulk disabled, still show (for subscription toggle)

		?>
		<div class="mk-dynamic-price-wrapper" style="margin-bottom: 1rem;">
			<div class="mk-dynamic-price-label" style="font-weight: 600; margin-bottom: 0.5rem;">
				<?php esc_html_e( 'Your Price:', 'mighty-kids-dynamic-pricing' ); ?>
			</div>
			<div class="mk-dynamic-price-amount" style="font-size: 1.5rem; font-weight: 700; color: #333;">
				<!-- Price will be updated by JavaScript -->
			</div>
			<div class="mk-dynamic-price-savings" style="color: #4CAF50; font-size: 0.9rem; margin-top: 0.25rem;">
				<!-- Savings info will be updated by JavaScript -->
			</div>
		</div>
		<?php
	}

	/**
	 * Get base price for a product.
	 *
	 * @param WC_Product $product The product.
	 * @return float
	 */
	private function get_base_price( WC_Product $product ): float {
		if ( $product instanceof WC_Product_Variable ) {
			// For variable products, return 0 - will be set when variation is selected.
			return 0.0;
		}

		return (float) $product->get_price();
	}

	/**
	 * Get regular price for a product.
	 *
	 * @param WC_Product $product The product.
	 * @return float
	 */
	private function get_regular_price( WC_Product $product ): float {
		if ( $product instanceof WC_Product_Variable ) {
			return 0.0;
		}

		return (float) $product->get_regular_price();
	}

	/**
	 * Get discount tiers configuration.
	 *
	 * @return array
	 */
	private function get_discount_tiers(): array {
		// Return empty array if bulk discounts are disabled
		if ( ! $this->is_bulk_discounts_enabled() ) {
			return array();
		}

		// Try to get from Mighty Kids plugin first
		if ( class_exists( '\Progressus\MightyKids\Discounts\Bulk_Discount_Manager' ) ) {
			$tiers = \Progressus\MightyKids\Discounts\Bulk_Discount_Manager::get_discount_tiers();
		} else {
			// Fallback to plugin's own configuration
			$tiers = Settings::get_discount_tiers();
		}

		// Format tiers for JavaScript.
		$formatted_tiers = array();
		foreach ( $tiers as $tier ) {
			$formatted_tiers[] = array(
				'min'      => (int) $tier['min'],
				'max'      => ! empty( $tier['max'] ) ? (int) $tier['max'] : 9999,
				'discount' => (float) $tier['discount'],
			);
		}

		return $formatted_tiers;
	}

	/**
	 * Get subscription discount percentage.
	 *
	 * @return float
	 */
	private function get_subscription_discount(): float {
		// Check if WooCommerce Subscriptions and APFS are active.
		if ( ! class_exists( 'WCS_ATT' ) ) {
			return 0.0;
		}

		// Get discount from settings
		$discount = Settings::get_subscription_discount();

		return (float) $discount;
	}

	/**
	 * Get all subscription schemes with their individual discounts.
	 * Uses transient caching to reduce database queries.
	 *
	 * @param WC_Product $product The product to get schemes for.
	 * @return array
	 */
	private function get_subscription_schemes( $product ): array {
		$schemes = array();

		// Check if WCS_ATT is active.
		if ( ! class_exists( '\WCS_ATT_Product_Schemes' ) ) {
			return $schemes;
		}

		// Try to get from cache first.
		$cache_key = 'mkdynamic_sub_schemes_' . $product->get_id();
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// Get subscription schemes for this product.
		$product_schemes = \WCS_ATT_Product_Schemes::get_subscription_schemes( $product );

		if ( empty( $product_schemes ) ) {
			// Cache empty result for 5 minutes to avoid repeated queries.
			set_transient( $cache_key, $schemes, 5 * MINUTE_IN_SECONDS );
			return $schemes;
		}

		// Extract scheme data with discounts.
		foreach ( $product_schemes as $key => $scheme ) {
			$schemes[ $key ] = array(
				'key'      => $key,
				'discount' => isset( $scheme['subscription_discount'] ) ? (float) $scheme['subscription_discount'] : 0.0,
			);
		}

		// Cache for 1 hour.
		set_transient( $cache_key, $schemes, HOUR_IN_SECONDS );

		return $schemes;
	}

	/**
	 * Clear subscription schemes cache for a product.
	 *
	 * @param int $product_id The product ID.
	 */
	public static function clear_schemes_cache( int $product_id ): void {
		$cache_key = 'mkdynamic_sub_schemes_' . $product_id;
		delete_transient( $cache_key );
	}

	/**
	 * Get currency format settings.
	 *
	 * @return array
	 */
	private function get_currency_format(): array {
		return array(
			'symbol'           => get_woocommerce_currency_symbol(),
			'decimal_sep'      => wc_get_price_decimal_separator(),
			'thousand_sep'     => wc_get_price_thousand_separator(),
			'decimals'         => wc_get_price_decimals(),
			'price_format'     => get_woocommerce_price_format(),
			'currency_pos'     => get_option( 'woocommerce_currency_pos' ),
		);
	}

	/**
	 * Check if bulk discounts are enabled.
	 *
	 * @return bool
	 */
	private function is_bulk_discounts_enabled(): bool {
		// Try to get from Mighty Kids plugin first
		if ( class_exists( '\Progressus\MightyKids\Admin\Admin_Settings' ) ) {
			return \Progressus\MightyKids\Admin\Admin_Settings::is_bulk_discount_enabled();
		}

		// Fallback to plugin's own setting
		return Settings::is_bulk_discounts_enabled();
	}

	/**
	 * Check if product qualifies for bulk discounts.
	 *
	 * @param WC_Product $product The product to check.
	 * @return bool
	 */
	private function product_qualifies_for_bulk_discounts( WC_Product $product ): bool {
		// Try to get from Mighty Kids plugin first
		if ( class_exists( '\Progressus\MightyKids\Discounts\Bulk_Discount_Manager' ) ) {
			return \Progressus\MightyKids\Discounts\Bulk_Discount_Manager::product_qualifies_for_bulk_discounts( $product );
		}

		// Fallback: all products qualify by default
		return true;
	}
}
