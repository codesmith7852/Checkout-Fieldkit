<?php
/**
 * Tests for uninstall.php.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Uninstalling removes the plugin's settings and never touches orders.
 */
class UninstallTest extends TestCase {

	/**
	 * The options the plugin stores (spec §3).
	 */
	const OPTIONS = array(
		'checkout_fieldkit_field_set_classic',
		'checkout_fieldkit_field_set_block',
		'checkout_fieldkit_settings',
	);

	public function test_deletes_the_plugin_options() {
		$this->add_plugin_options();

		$this->run_uninstall();

		foreach ( self::OPTIONS as $option ) {
			$this->assertFalse( get_option( $option ), $option );
		}
	}

	public function test_deletes_the_options_the_plugin_stores_in() {
		$domain = new Domain();

		$this->assertSame(
			self::OPTIONS,
			array(
				$domain->field_sets( 'classic' )->option_name(),
				$domain->field_sets( 'block' )->option_name(),
				$domain->settings()->option_name(),
			)
		);
	}

	public function test_keeps_order_data_and_other_options() {
		update_option( 'checkout_fieldkit_unrelated_but_similar', 'stay' );
		$order = $this->create_order( array( 'meta' => array( 'checkout_fieldkit_test_field' => 'kept' ) ) );

		$this->run_uninstall();

		$this->assertSame( 'stay', get_option( 'checkout_fieldkit_unrelated_but_similar' ) );
		$this->assertSame( 'kept', wc_get_order( $order->get_id() )->get_meta( 'checkout_fieldkit_test_field' ) );
	}

	/**
	 * @group ms-required
	 */
	public function test_deletes_the_options_of_every_site_in_a_network() {
		$this->skipWithoutMultisite();

		$site_ids = array( get_current_blog_id(), self::factory()->blog->create(), self::factory()->blog->create() );
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			$this->add_plugin_options();
			update_option( 'checkout_fieldkit_unrelated_but_similar', 'stay' );
			restore_current_blog();
		}

		$this->run_uninstall();

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			foreach ( self::OPTIONS as $option ) {
				$this->assertFalse( get_option( $option ), "$option on site $site_id" );
			}
			$this->assertSame( 'stay', get_option( 'checkout_fieldkit_unrelated_but_similar' ), "site $site_id" );
			restore_current_blog();
		}
	}

	/**
	 * Stores every plugin option on the current site.
	 */
	private function add_plugin_options() {
		foreach ( self::OPTIONS as $option ) {
			update_option( $option, array( 'version' => 1 ) );
		}
	}

	/**
	 * Runs uninstall.php the way WordPress does.
	 */
	private function run_uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'checkout-fieldkit/checkout-fieldkit.php' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Set by WordPress when uninstalling.
		}

		include dirname( CFK_TESTS_PLUGIN_FILE ) . '/uninstall.php';
	}
}
