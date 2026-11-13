<?php
/**
 * Declares which WooCommerce features the plugin supports.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Tells WooCommerce the plugin works with High-Performance Order Storage and
 * the Block checkout, so WooCommerce does not warn about it.
 */
final class Compatibility implements Service {

	/**
	 * WooCommerce feature IDs the plugin is compatible with.
	 */
	public const FEATURES = array( 'custom_order_tables', 'cart_checkout_blocks' );

	/**
	 * Path of the main plugin file.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Path of the main plugin file.
	 */
	public function __construct( string $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	/**
	 * WooCommerce collects declarations while it initialises.
	 */
	public function register_hooks(): void {
		add_action( 'before_woocommerce_init', array( $this, 'declare_features' ) );
	}

	/**
	 * Declares compatibility with each feature.
	 */
	public function declare_features(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		foreach ( self::FEATURES as $feature ) {
			FeaturesUtil::declare_compatibility( $feature, $this->plugin_file, true );
		}
	}
}
