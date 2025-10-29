<?php
/**
 * Utils class
 *
 * Utility functions for the Mighty Kids Dynamic Pricing plugin.
 *
 * @package MightyKids\DynamicPricing
 * @since 1.0.0
 */

namespace MightyKids\DynamicPricing;

defined( 'ABSPATH' ) || exit;

/**
 * Class Utils
 */
class Utils {

	/**
	 * Get the JS file URL based on the SCRIPT_DEBUG constant.
	 *
	 * @param string $filename The filename to get the URL for.
	 * @return string
	 */
	public static function get_js_file_url( string $filename ): string {
		$file_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		return MKDYNAMIC_ASSETS_URL . '/js/' . $filename . $file_suffix . '.js';
	}

	/**
	 * Get the JS file path based on the SCRIPT_DEBUG constant.
	 *
	 * @param string $filename The filename to get the path for.
	 * @return string
	 */
	public static function get_js_file_path( string $filename ): string {
		$file_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		return MKDYNAMIC_DIR_PATH . '/assets/js/' . $filename . $file_suffix . '.js';
	}

	/**
	 * Get the CSS file URL.
	 *
	 * @param string $filename The filename to get the URL for.
	 * @return string
	 */
	public static function get_css_file_url( string $filename ): string {
		return MKDYNAMIC_ASSETS_URL . '/css/' . $filename . '.css';
	}

	/**
	 * Get the CSS file path.
	 *
	 * @param string $filename The filename to get the path for.
	 * @return string
	 */
	public static function get_css_file_path( string $filename ): string {
		return MKDYNAMIC_DIR_PATH . '/assets/css/' . $filename . '.css';
	}

	/**
	 * Get an asset version from a file path, falling back to the plugin version.
	 *
	 * @param string $file_path Absolute path to the asset file.
	 * @return int|string The file modification time or the plugin version.
	 */
	public static function get_asset_version_by_path( string $file_path ): int|string {
		if ( is_string( $file_path ) && '' !== $file_path && file_exists( $file_path ) ) {
			$mtime = filemtime( $file_path );
			if ( false !== $mtime ) {
				return $mtime;
			}
		}

		return MKDYNAMIC_VERSION;
	}

	/**
	 * Get the version for a JS asset by file name.
	 *
	 * @param string $filename The JS filename without extension and without suffix.
	 * @return int|string The file modification time or the plugin version.
	 */
	public static function get_js_file_version( string $filename ): int|string {
		return self::get_asset_version_by_path( self::get_js_file_path( $filename ) );
	}

	/**
	 * Get the version for a CSS asset by file name.
	 *
	 * @param string $filename The CSS filename without extension.
	 * @return int|string The file modification time or the plugin version.
	 */
	public static function get_css_file_version( string $filename ): int|string {
		return self::get_asset_version_by_path( self::get_css_file_path( $filename ) );
	}

	/**
	 * Check if the product is a subscription product.
	 *
	 * @param \WC_Product $product The product to check.
	 * @return bool True if the product is a subscription product, false otherwise.
	 */
	public static function is_subscription_product( \WC_Product $product ): bool {
		if ( ! class_exists( 'WC_Subscriptions_Product' ) || ! method_exists( 'WC_Subscriptions_Product', 'is_subscription' ) ) {
			return false;
		}

		return \WC_Subscriptions_Product::is_subscription( $product );
	}

	/**
	 * Calculate the discounted price.
	 *
	 * @param float $price               The original price.
	 * @param float $discount_percentage The discount percentage.
	 * @return float The discounted price.
	 */
	public static function calculate_discounted_price( float $price, float $discount_percentage ): float {
		return $price * ( 1 - $discount_percentage / 100 );
	}

	/**
	 * Format the quantity range for display.
	 *
	 * @param int        $min The minimum quantity.
	 * @param int|string $max The maximum quantity (or empty string for unlimited).
	 * @return string The formatted quantity range.
	 */
	public static function format_quantity_range( int $min, int|string $max ): string {
		if ( empty( $max ) || $min === $max ) {
			return $min . ( empty( $max ) ? ' +' : '' );
		}

		return $min . ' - ' . $max;
	}

	/**
	 * Check if a plugin is active based on its slug.
	 *
	 * @param string $plugin_slug The slug of the plugin to check.
	 * @return bool True if the plugin is active, false otherwise.
	 */
	public static function is_plugin_active( string $plugin_slug ): bool {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		return in_array( $plugin_slug . '/' . $plugin_slug . '.php', $active_plugins, true );
	}

	/**
	 * Check if Mighty Kids plugin is active.
	 *
	 * @return bool
	 */
	public static function is_mighty_kids_active(): bool {
		return self::is_plugin_active( 'mighty-kids-plugin' );
	}

	/**
	 * Check if WooCommerce All Products for Subscriptions is active.
	 *
	 * @return bool
	 */
	public static function is_wcs_att_active(): bool {
		return class_exists( 'WCS_ATT' );
	}
}
