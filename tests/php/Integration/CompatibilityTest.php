<?php
/**
 * Tests for the WooCommerce feature compatibility declarations.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration;

use Automattic\WooCommerce\Internal\Features\FeaturesController;
use CheckoutFieldKit\Tests\TestCase;

/**
 * The plugin tells WooCommerce it works with HPOS and the Block checkout.
 */
class CompatibilityTest extends TestCase {

	/**
	 * @dataProvider data_features
	 *
	 * @param string $feature WooCommerce feature ID.
	 */
	public function test_declares_compatibility_with( $feature ) {
		$plugins = wc_get_container()->get( FeaturesController::class )->get_compatible_plugins_for_feature( $feature );

		$this->assertContains( plugin_basename( CFK_TESTS_PLUGIN_FILE ), $plugins['compatible'] );
	}

	public function data_features() {
		return array(
			'High-Performance Order Storage' => array( 'custom_order_tables' ),
			'Block checkout'                 => array( 'cart_checkout_blocks' ),
		);
	}
}
