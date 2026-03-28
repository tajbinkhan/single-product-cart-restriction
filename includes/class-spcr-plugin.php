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
	 * WooCommerce plugin file path.
	 */
	private const WOOCOMMERCE_PLUGIN_FILE = 'woocommerce/woocommerce.php';

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
		return self::is_woocommerce_dependency_active();
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
		echo esc_html__( 'Single Product Cart Restriction requires WooCommerce to be installed and active.', 'single-product-cart-restriction' );
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
		if ( ! self::is_woocommerce_dependency_active() ) {
			wp_die(
				esc_html__( 'Single Product Cart Restriction cannot be activated because WooCommerce is not active. Please install and activate WooCommerce first.', 'single-product-cart-restriction' ),
				esc_html__( 'Plugin dependency check', 'single-product-cart-restriction' ),
				array( 'back_link' => true )
			);
		}

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

	/**
	 * Checks whether WooCommerce plugin dependency is active.
	 */
	private static function is_woocommerce_dependency_active(): bool {
		if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( self::WOOCOMMERCE_PLUGIN_FILE ) ) {
			return true;
		}

		return is_multisite() && function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( self::WOOCOMMERCE_PLUGIN_FILE );
	}
}
