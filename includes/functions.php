<?php
/**
 * Shared helper functions.
 *
 * @package SingleProductCartRestriction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns plugin default option values.
 *
 * @return array<string, mixed>
 */
function spcr_get_default_settings(): array {
	return array(
		'spcr_enabled'                   => 'no',
		'spcr_mode'                      => 'block',
		'spcr_bypass_cart'               => 'no',
		'spcr_hide_action_messages'      => 'no',
		'spcr_hide_cart_quantity'        => 'no',
		'spcr_force_quantity_one'        => 'no',
		'spcr_custom_notice'             => '',
		'spcr_excluded_products'         => array(),
		'spcr_excluded_categories'       => array(),
		'spcr_restrict_only_categories'  => array(),
		'spcr_bypass_admin_shop_manager' => 'no',
		'spcr_debug_mode'                => 'no',
	);
}

/**
 * Returns a plugin option value with a known default fallback.
 *
 * @param string $key Option key.
 *
 * @return mixed
 */
function spcr_get_option( string $key ) {
	$defaults = spcr_get_default_settings();
	$default  = $defaults[ $key ] ?? '';
	$value    = get_option( $key, $default );

	if ( is_array( $default ) ) {
		return is_array( $value ) ? $value : $default;
	}

	if ( is_string( $default ) ) {
		return is_string( $value ) ? $value : $default;
	}

	return $value;
}

/**
 * Normalizes yes/no option values to bool.
 *
 * @param string $key Option key.
 */
function spcr_is_yes_option( string $key ): bool {
	return 'yes' === spcr_get_option( $key );
}

/**
 * Sanitizes incoming IDs into a unique positive integer list.
 *
 * @param mixed $raw_ids Raw ID input.
 *
 * @return int[]
 */
function spcr_normalize_ids( $raw_ids ): array {
	$ids = array_filter(
		array_map( 'absint', (array) $raw_ids )
	);

	return array_values( array_unique( $ids ) );
}

/**
 * Checks whether the current request targets our WooCommerce settings section.
 */
function spcr_is_settings_screen(): bool {
	if ( ! is_admin() ) {
		return false;
	}

	$tab     = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
	$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
	$page    = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

	return 'wc-settings' === $page && 'products' === $tab && 'spcr' === $section;
}
