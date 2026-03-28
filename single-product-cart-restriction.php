<?php
/**
 * Plugin Name: Single Product Cart Restriction
 * Plugin URI: https://www.webphics.com/
 * Description: Restrict WooCommerce carts to a single product line item with block or replace behavior.
 * Version: 1.0.0
 * Author: webphics
 * Author URI: https://www.webphics.com/
 * Text Domain: single-product-cart-restriction
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SingleProductCartRestriction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPCR_VERSION', '1.0.0' );
define( 'SPCR_PLUGIN_FILE', __FILE__ );
define( 'SPCR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPCR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SPCR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
require_once SPCR_PLUGIN_PATH . 'includes/functions.php';
require_once SPCR_PLUGIN_PATH . 'includes/class-spcr-plugin.php';
require_once SPCR_PLUGIN_PATH . 'includes/class-spcr-admin.php';
require_once SPCR_PLUGIN_PATH . 'includes/class-spcr-cart-restrictions.php';

register_activation_hook( SPCR_PLUGIN_FILE, array( 'SPCR_Plugin', 'activate' ) );
register_deactivation_hook( SPCR_PLUGIN_FILE, array( 'SPCR_Plugin', 'deactivate' ) );
register_uninstall_hook( SPCR_PLUGIN_FILE, array( 'SPCR_Plugin', 'uninstall' ) );

/**
 * Returns plugin bootstrap instance.
 *
 * @return SPCR_Plugin
 */
function spcr(): SPCR_Plugin {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new SPCR_Plugin();
	}

	return $instance;
}

spcr()->run();
