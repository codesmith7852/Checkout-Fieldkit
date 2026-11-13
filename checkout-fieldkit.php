<?php
/**
 * Plugin Name:          Checkout FieldKit for WooCommerce
 * Plugin URI:           https://github.com/codesmith7852/Checkout-Fieldkit
 * Description:          Edit, hide, reorder and add WooCommerce checkout fields on both the Classic and the Block checkout.
 * Version:              0.1.0
 * Requires at least:    6.6
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               codesmith7852
 * Author URI:           https://github.com/codesmith7852
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          checkout-fieldkit
 * WC requires at least: 9.0
 * WC tested up to:      11.3
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.1.0';
const PLUGIN_FILE = __FILE__;

require_once __DIR__ . '/includes/Autoloader.php';
Autoloader::register( __NAMESPACE__ . '\\', __DIR__ . '/includes' );

add_action( 'plugins_loaded', array( Loader::class, 'on_plugins_loaded' ) );
