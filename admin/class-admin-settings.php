<?php
/**
 * Admin Settings class
 *
 * Creates admin settings page for the Mighty Kids Dynamic Pricing plugin.
 *
 * @package MightyKids\DynamicPricing
 * @since 1.0.0
 */

namespace MightyKids\DynamicPricing\Admin;

use MightyKids\DynamicPricing\Settings;
use MightyKids\DynamicPricing\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Settings
 */
class Admin_Settings {

	/**
	 * Page slug for the settings page.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'mkdynamic-settings';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	public const SETTINGS_GROUP = 'mkdynamic_settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			Settings::OPTION_BULK_DISCOUNT_ENABLED,
			array(
				'type'              => 'string',
				'description'       => __( 'Enable bulk discounts functionality.', 'mighty-kids-dynamic-pricing' ),
				'sanitize_callback' => array( $this, 'sanitize_boolean_string' ),
				'show_in_rest'      => false,
				'default'           => '1',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			Settings::OPTION_SUBSCRIPTION_DISCOUNT,
			array(
				'type'              => 'string',
				'description'       => __( 'Default subscription discount percentage.', 'mighty-kids-dynamic-pricing' ),
				'sanitize_callback' => array( $this, 'sanitize_discount_percentage' ),
				'show_in_rest'      => false,
				'default'           => '10',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			Settings::OPTION_DISCOUNT_TIERS,
			array(
				'type'              => 'array',
				'description'       => __( 'Bulk discount tiers configuration.', 'mighty-kids-dynamic-pricing' ),
				'sanitize_callback' => array( $this, 'sanitize_discount_tiers' ),
				'show_in_rest'      => false,
				'default'           => Settings::get_default_discount_tiers(),
			)
		);

		// Add settings sections.
		add_settings_section(
			'mkdynamic_main_section',
			__( 'General Settings', 'mighty-kids-dynamic-pricing' ),
			array( $this, 'render_main_section_description' ),
			self::PAGE_SLUG
		);

		// Add settings fields.
		add_settings_field(
			'mkdynamic_bulk_discount_toggle',
			__( 'Enable Bulk Discounts', 'mighty-kids-dynamic-pricing' ),
			array( $this, 'render_bulk_discount_toggle_field' ),
			self::PAGE_SLUG,
			'mkdynamic_main_section'
		);

		add_settings_field(
			'mkdynamic_subscription_discount_field',
			__( 'Subscription Discount (%)', 'mighty-kids-dynamic-pricing' ),
			array( $this, 'render_subscription_discount_field' ),
			self::PAGE_SLUG,
			'mkdynamic_main_section'
		);

		add_settings_field(
			'mkdynamic_discount_tiers_field',
			__( 'Discount Tiers', 'mighty-kids-dynamic-pricing' ),
			array( $this, 'render_discount_tiers_field' ),
			self::PAGE_SLUG,
			'mkdynamic_main_section'
		);
	}

	/**
	 * Add admin menu page.
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Dynamic Pricing', 'mighty-kids-dynamic-pricing' ),
			__( 'Dynamic Pricing', 'mighty-kids-dynamic-pricing' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'woocommerce_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_add_inline_style( 'wp-admin', $this->get_admin_css() );
		wp_add_inline_script( 'jquery', $this->get_admin_js() );
	}

	/**
	 * Render main section description.
	 */
	public function render_main_section_description(): void {
		?>
		<p><?php esc_html_e( 'Configure the dynamic pricing display settings.', 'mighty-kids-dynamic-pricing' ); ?></p>
		<?php
	}

	/**
	 * Render bulk discount toggle field.
	 */
	public function render_bulk_discount_toggle_field(): void {
		$value = get_option( Settings::OPTION_BULK_DISCOUNT_ENABLED, '1' );
		$checked = '1' === $value ? 'checked' : '';
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_BULK_DISCOUNT_ENABLED ); ?>" value="1" <?php echo esc_attr( $checked ); ?> />
			<?php esc_html_e( 'Enable bulk discounts for quantity-based pricing', 'mighty-kids-dynamic-pricing' ); ?>
		</label>
		<?php
	}

	/**
	 * Render subscription discount field.
	 */
	public function render_subscription_discount_field(): void {
		$value = get_option( Settings::OPTION_SUBSCRIPTION_DISCOUNT, '10' );
		?>
		<input type="number" name="<?php echo esc_attr( Settings::OPTION_SUBSCRIPTION_DISCOUNT ); ?>" value="<?php echo esc_attr( $value ); ?>" min="0" max="100" step="0.1" />
		<p class="description"><?php esc_html_e( 'Default discount percentage for subscription products.', 'mighty-kids-dynamic-pricing' ); ?></p>
		<?php
	}

	/**
	 * Render discount tiers field.
	 */
	public function render_discount_tiers_field(): void {
		$tiers = Settings::get_discount_tiers();
		?>
		<div id="mkdynamic-discount-tiers">
			<?php foreach ( $tiers as $index => $tier ) : ?>
				<div class="mkdynamic-tier-row" data-index="<?php echo esc_attr( $index ); ?>">
					<input type="number" name="mkdynamic_tier_min[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $tier['min'] ); ?>" min="1" placeholder="<?php esc_attr_e( 'Min Qty', 'mighty-kids-dynamic-pricing' ); ?>" />
					<span><?php esc_html_e( 'to', 'mighty-kids-dynamic-pricing' ); ?></span>
					<input type="number" name="mkdynamic_tier_max[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $tier['max'] ); ?>" min="1" placeholder="<?php esc_attr_e( 'Max Qty (leave empty for unlimited)', 'mighty-kids-dynamic-pricing' ); ?>" />
					<span><?php esc_html_e( 'discount', 'mighty-kids-dynamic-pricing' ); ?></span>
					<input type="number" name="mkdynamic_tier_discount[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $tier['discount'] ); ?>" min="0" max="100" step="0.1" placeholder="<?php esc_attr_e( 'Discount %', 'mighty-kids-dynamic-pricing' ); ?>" />
					<button type="button" class="button mkdynamic-remove-tier"><?php esc_html_e( 'Remove', 'mighty-kids-dynamic-pricing' ); ?></button>
				</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="button" id="mkdynamic-add-tier"><?php esc_html_e( 'Add Tier', 'mighty-kids-dynamic-pricing' ); ?></button>
		<p class="description"><?php esc_html_e( 'Configure quantity-based discount tiers. Leave max quantity empty for unlimited.', 'mighty-kids-dynamic-pricing' ); ?></p>
		<?php
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Dynamic Pricing Settings', 'mighty-kids-dynamic-pricing' ); ?></h1>
			
			<?php if ( Utils::is_mighty_kids_active() ) : ?>
				<div class="notice notice-info">
					<p><?php esc_html_e( 'Mighty Kids plugin detected. This plugin will use the main plugin\'s settings when available.', 'mighty-kids-dynamic-pricing' ); ?></p>
				</div>
			<?php endif; ?>
			
			<form method="post" action="options.php">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save Settings', 'mighty-kids-dynamic-pricing' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize boolean string value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_boolean_string( $value ): string {
		return isset( $_POST[ Settings::OPTION_BULK_DISCOUNT_ENABLED ] ) ? '1' : '0';
	}

	/**
	 * Sanitize discount percentage.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_discount_percentage( $value ): string {
		$value = (float) $value;
		return (string) max( 0, min( 100, $value ) );
	}

	/**
	 * Sanitize discount tiers.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public function sanitize_discount_tiers( $value ): array {
		if ( ! is_array( $value ) ) {
			return Settings::get_default_discount_tiers();
		}

		$tiers = array();
		$min_values = $_POST['mkdynamic_tier_min'] ?? array();
		$max_values = $_POST['mkdynamic_tier_max'] ?? array();
		$discount_values = $_POST['mkdynamic_tier_discount'] ?? array();

		foreach ( $min_values as $index => $min ) {
			$max = $max_values[ $index ] ?? '';
			$discount = $discount_values[ $index ] ?? 0;

			$tiers[] = array(
				'min'      => (int) $min,
				'max'      => '' === $max ? '' : (int) $max,
				'discount' => (float) $discount,
			);
		}

		return Settings::validate_discount_tiers( $tiers ) ?: Settings::get_default_discount_tiers();
	}

	/**
	 * Get admin CSS.
	 *
	 * @return string
	 */
	private function get_admin_css(): string {
		return '
			.mkdynamic-tier-row {
				display: flex;
				align-items: center;
				gap: 10px;
				margin-bottom: 10px;
			}
			.mkdynamic-tier-row input[type="number"] {
				width: 80px;
			}
		';
	}

	/**
	 * Get admin JavaScript.
	 *
	 * @return string
	 */
	private function get_admin_js(): string {
		return '
			jQuery(document).ready(function($) {
				$("#mkdynamic-add-tier").on("click", function() {
					var index = $(".mkdynamic-tier-row").length;
					var newRow = $("<div class=\"mkdynamic-tier-row\" data-index=\"" + index + "\">" +
						"<input type=\"number\" name=\"mkdynamic_tier_min[" + index + "]\" min=\"1\" placeholder=\"Min Qty\" />" +
						"<span>to</span>" +
						"<input type=\"number\" name=\"mkdynamic_tier_max[" + index + "]\" min=\"1\" placeholder=\"Max Qty (leave empty for unlimited)\" />" +
						"<span>discount</span>" +
						"<input type=\"number\" name=\"mkdynamic_tier_discount[" + index + "]\" min=\"0\" max=\"100\" step=\"0.1\" placeholder=\"Discount %\" />" +
						"<button type=\"button\" class=\"button mkdynamic-remove-tier\">Remove</button>" +
						"</div>");
					$("#mkdynamic-discount-tiers").append(newRow);
				});
				
				$(document).on("click", ".mkdynamic-remove-tier", function() {
					$(this).closest(".mkdynamic-tier-row").remove();
				});
			});
		';
	}
}
