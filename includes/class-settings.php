<?php
/**
 * Settings class
 *
 * Handles plugin settings and configuration.
 *
 * @package MightyKids\DynamicPricing
 * @since 1.0.0
 */

namespace MightyKids\DynamicPricing;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 */
class Settings {

	/**
	 * Option name for enabling bulk discounts.
	 *
	 * @var string
	 */
	public const OPTION_BULK_DISCOUNT_ENABLED = 'mkdynamic_bulk_discounts_enabled';

	/**
	 * Option name for subscription discount percentage.
	 *
	 * @var string
	 */
	public const OPTION_SUBSCRIPTION_DISCOUNT = 'mkdynamic_subscription_discount';

	/**
	 * Option name for discount tiers.
	 *
	 * @var string
	 */
	public const OPTION_DISCOUNT_TIERS = 'mkdynamic_discount_tiers';

	/**
	 * Get a setting value.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $fallback    Fallback value.
	 * @return mixed
	 */
	public static function get_setting( string $option_name, $fallback = null ) {
		return get_option( $option_name, $fallback );
	}

	/**
	 * Check if bulk discounts are enabled.
	 *
	 * @return bool
	 */
	public static function is_bulk_discounts_enabled(): bool {
		$value = self::get_setting( self::OPTION_BULK_DISCOUNT_ENABLED, '1' );
		
		if ( is_bool( $value ) ) {
			return $value;
		}
		
		if ( is_int( $value ) ) {
			return 1 === $value;
		}
		
		$value = is_string( $value ) ? strtolower( $value ) : $value;
		return in_array( $value, array( '1', 'yes', 'true' ), true );
	}

	/**
	 * Get subscription discount percentage.
	 *
	 * @return float
	 */
	public static function get_subscription_discount(): float {
		$discount = self::get_setting( self::OPTION_SUBSCRIPTION_DISCOUNT, '10' );
		
		// Apply filter for customization.
		$discount = apply_filters( 'mkdynamic_subscription_discount_percentage', $discount );
		
		return (float) $discount;
	}

	/**
	 * Get discount tiers configuration.
	 * Always tries to get from Mighty Kids plugin first.
	 *
	 * @return array
	 */
	public static function get_discount_tiers(): array {
		// Try to get from Mighty Kids plugin first
		if ( class_exists( '\Progressus\MightyKids\Discounts\Bulk_Discount_Manager' ) ) {
			return \Progressus\MightyKids\Discounts\Bulk_Discount_Manager::get_discount_tiers();
		}

		// Fallback to plugin's own configuration only if Mighty Kids is not available
		$tiers = self::get_setting( self::OPTION_DISCOUNT_TIERS, false );
		
		// If no custom tiers are set, use defaults.
		if ( false === $tiers || ! is_array( $tiers ) || empty( $tiers ) ) {
			$tiers = self::get_default_discount_tiers();
		}
		
		// Apply filter for customization.
		$tiers = apply_filters( 'mkdynamic_discount_tiers', $tiers );
		
		return $tiers;
	}

	/**
	 * Get default discount tiers.
	 *
	 * @return array
	 */
	public static function get_default_discount_tiers(): array {
		return array(
			array(
				'min'      => 2,
				'max'      => 2,
				'discount' => 5,
			),
			array(
				'min'      => 3,
				'max'      => 4,
				'discount' => 10,
			),
			array(
				'min'      => 5,
				'max'      => '',
				'discount' => 15,
			),
		);
	}

	/**
	 * Update discount tiers.
	 *
	 * @param array $tiers Discount tiers configuration.
	 * @return bool
	 */
	public static function update_discount_tiers( array $tiers ): bool {
		// Validate tiers structure.
		$validated_tiers = self::validate_discount_tiers( $tiers );
		
		if ( false === $validated_tiers ) {
			return false;
		}
		
		return update_option( self::OPTION_DISCOUNT_TIERS, $validated_tiers );
	}

	/**
	 * Validate discount tiers structure.
	 *
	 * @param array $tiers Discount tiers to validate.
	 * @return array|false Validated tiers or false if invalid.
	 */
	private static function validate_discount_tiers( array $tiers ): array|false {
		$validated = array();
		
		foreach ( $tiers as $tier ) {
			if ( ! is_array( $tier ) ) {
				continue;
			}
			
			// Check required fields.
			if ( ! isset( $tier['min'] ) || ! isset( $tier['discount'] ) ) {
				continue;
			}
			
			$min = (int) $tier['min'];
			$max = isset( $tier['max'] ) && '' !== $tier['max'] ? (int) $tier['max'] : '';
			$discount = (float) $tier['discount'];
			
			// Validate values.
			if ( $min < 1 || $discount < 0 || $discount > 100 ) {
				continue;
			}
			
			if ( '' !== $max && $max < $min ) {
				continue;
			}
			
			$validated[] = array(
				'min'      => $min,
				'max'      => $max,
				'discount' => $discount,
			);
		}
		
		return empty( $validated ) ? false : $validated;
	}
}
