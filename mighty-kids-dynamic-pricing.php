<?php
/**
 * Plugin Name: Mighty Kids Dynamic Pricing
 * Plugin URI: https://mightykidssupplements.co.uk/
 * Description: Dynamic pricing display for Mighty Kids products with subscription and bulk discount support.
 * Author: Daniel Gargallo
 * Author URI: https://mightykidssupplements.co.uk/
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * Text Domain: mighty-kids-dynamic-pricing
 * Requires Plugins: woocommerce
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package MightyKids\DynamicPricing
 */

namespace MightyKids\DynamicPricing;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
if ( ! defined( 'MKDYNAMIC_VERSION' ) ) {
	define( 'MKDYNAMIC_VERSION', '1.0.0' );
}

if ( ! defined( 'MKDYNAMIC_FILE' ) ) {
	define( 'MKDYNAMIC_FILE', __FILE__ );
}

if ( ! defined( 'MKDYNAMIC_DIR_PATH' ) ) {
	define( 'MKDYNAMIC_DIR_PATH', untrailingslashit( plugin_dir_path( MKDYNAMIC_FILE ) ) );
}

if ( ! defined( 'MKDYNAMIC_DIR_URL' ) ) {
	define( 'MKDYNAMIC_DIR_URL', untrailingslashit( plugins_url( '/', MKDYNAMIC_FILE ) ) );
}

if ( ! defined( 'MKDYNAMIC_ASSETS_URL' ) ) {
	define( 'MKDYNAMIC_ASSETS_URL', MKDYNAMIC_DIR_URL . '/assets' );
}

/**
 * Main plugin class.
 */
class Mighty_Kids_Dynamic_Pricing {

	/**
	 * Single instance of the class.
	 *
	 * @var Mighty_Kids_Dynamic_Pricing
	 */
	private static $instance = null;

	/**
	 * Get single instance.
	 *
	 * @return Mighty_Kids_Dynamic_Pricing
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
		$this->load_dependencies();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Load plugin dependencies.
	 */
	private function load_dependencies() {
		require_once MKDYNAMIC_DIR_PATH . '/includes/class-dynamic-pricing.php';
		require_once MKDYNAMIC_DIR_PATH . '/includes/class-settings.php';
		require_once MKDYNAMIC_DIR_PATH . '/includes/class-utils.php';
		
		if ( is_admin() ) {
			require_once MKDYNAMIC_DIR_PATH . '/admin/class-admin-settings.php';
		}
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		// Check if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Load text domain.
		load_plugin_textdomain( 'mighty-kids-dynamic-pricing', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Initialize components.
		new Dynamic_Pricing();
		
		if ( is_admin() ) {
			new Admin\Admin_Settings();
		}
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		// Set default options.
		add_option( 'mkdynamic_bulk_discounts_enabled', '1' );
		add_option( 'mkdynamic_subscription_discount', '10' );
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate() {
		// Clean up if needed.
	}

	/**
	 * WooCommerce missing notice.
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Mighty Kids Dynamic Pricing requires WooCommerce to be installed and active.', 'mighty-kids-dynamic-pricing' ); ?></p>
		</div>
		<?php
	}
}

// Initialize the plugin.
Mighty_Kids_Dynamic_Pricing::instance();
