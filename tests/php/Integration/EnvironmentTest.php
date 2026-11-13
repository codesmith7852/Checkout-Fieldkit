<?php
/**
 * Proves the test harness loads WordPress, WooCommerce and this plugin.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration;

use CheckoutFieldKit\Tests\TestCase;

/**
 * The environment every other test relies on.
 */
class EnvironmentTest extends TestCase {

	public function test_wordpress_is_loaded() {
		$this->assertTrue( function_exists( 'add_action' ) );
		$this->assertTrue( did_action( 'init' ) > 0, 'WordPress has run init.' );
	}

	public function test_woocommerce_is_loaded_and_installed() {
		$this->assertTrue( class_exists( 'WooCommerce' ), 'WooCommerce is loaded.' );
		$this->assertTrue( did_action( 'woocommerce_init' ) > 0, 'WooCommerce has initialised.' );
		$this->assertSame( WC_VERSION, get_option( 'woocommerce_version' ), 'WooCommerce is installed in the test database.' );

		$this->log_in_as( 'shop_manager' );
		$this->assertTrue( current_user_can( 'manage_woocommerce' ), 'WooCommerce roles and capabilities exist.' );
	}
}
