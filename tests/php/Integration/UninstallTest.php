<?php
/**
 * Tests for uninstall.php.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration;

use CheckoutFieldKit\Tests\TestCase;

/**
 * Uninstalling removes the plugin's settings and never touches orders.
 */
class UninstallTest extends TestCase {

	public function test_deletes_the_plugin_options() {
		update_option( 'cfk_field_set_classic', array( 'version' => 1 ) );
		update_option( 'cfk_field_set_block', array( 'version' => 1 ) );
		update_option( 'cfk_settings', array( 'version' => 1 ) );

		$this->run_uninstall();

		$this->assertFalse( get_option( 'cfk_field_set_classic' ) );
		$this->assertFalse( get_option( 'cfk_field_set_block' ) );
		$this->assertFalse( get_option( 'cfk_settings' ) );
	}

	public function test_keeps_order_data_and_other_options() {
		update_option( 'cfk_unrelated_but_similar', 'stay' );
		$order = $this->create_order( array( 'meta' => array( 'cfk_test_field' => 'kept' ) ) );

		$this->run_uninstall();

		$this->assertSame( 'stay', get_option( 'cfk_unrelated_but_similar' ) );
		$this->assertSame( 'kept', wc_get_order( $order->get_id() )->get_meta( 'cfk_test_field' ) );
	}

	/**
	 * Runs uninstall.php the way WordPress does.
	 */
	private function run_uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'checkout-fieldkit/checkout-fieldkit.php' );
		}

		include dirname( CFK_TESTS_PLUGIN_FILE ) . '/uninstall.php';
	}
}
