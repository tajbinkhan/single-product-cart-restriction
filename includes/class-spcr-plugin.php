<?php
/**
 * Main plugin bootstrap class.
 *
 * @package SingleProductCartRestriction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class SPCR_Plugin {
	/**
	 * Admin settings service.
	 *
	 * @var SPCR_Admin|null
	 */
	private $admin;

	/**
	 * Cart restriction service.
	 *
	 * @var SPCR_Cart_Restrictions|null
	 */
	private $cart_restrictions;

	/**
	 * Registers plugin hooks.
	 */
	public function run(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'boot' ), 20 );
		add_action( 'admin_notices', array( $this, 'woocommerce_dependency_notice' ) );

		add_filter( 'plugin_action_links_' . SPCR_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Loads text domain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'single-product-cart-restriction', false, dirname( SPCR_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Boots services after dependencies are loaded.
	 */
	public function boot(): void {
		if ( ! $this->is_woocommerce_active() ) {
			return;
		}

		if ( is_admin() ) {
			$this->admin = new SPCR_Admin();
		}

		$this->cart_restrictions = new SPCR_Cart_Restrictions();
	}

	/**
	 * Whether WooCommerce is active.
	 */
	public function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}

	/**
	 * Displays dependency notice when WooCommerce is missing.
	 */
	public function woocommerce_dependency_notice(): void {
		if ( $this->is_woocommerce_active() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Single Product Cart Restriction requires WooCommerce to be active.', 'single-product-cart-restriction' );
		echo '</p></div>';
	}

	/**
	 * Adds plugin action links.
	 *
	 * @param string[] $links Existing links.
	 *
	 * @return string[]
	 */
	public function plugin_action_links( array $links ): array {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=products&section=spcr' );

		array_unshift(
			$links,
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'single-product-cart-restriction' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		$defaults = spcr_get_default_settings();

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key, false ) ) {
				add_option( $key, $value, '', false );
			}
		}
	}

	/**
	 * Deactivation hook.
	 */
	public static function deactivate(): void {
		// Intentionally left blank.
	}

	/**
	 * Uninstall hook.
	 */
	public static function uninstall(): void {
		$defaults = spcr_get_default_settings();

		foreach ( array_keys( $defaults ) as $option_name ) {
			delete_option( $option_name );
		}
	}
}
