<?php
/**
 * Tests for loading the plugin only when its requirements are met.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration;

use CheckoutFieldKit\Loader;
use CheckoutFieldKit\Plugin;
use CheckoutFieldKit\Requirements;
use CheckoutFieldKit\RequirementsNotice;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Without WooCommerce the plugin shows a notice and loads nothing else.
 */
class LoaderTest extends TestCase {

	public function test_boots_the_plugin_when_requirements_are_met() {
		$plugin = new Plugin();

		$loaded = Loader::load( new Requirements( '11.3.0' ), $plugin );

		$this->assertTrue( $loaded );
		$this->assertTrue( $plugin->is_booted() );
		$this->assertNull( $this->registered_notice() );
	}

	public function test_does_not_boot_and_shows_a_notice_when_woocommerce_is_missing() {
		$plugin = new Plugin();

		$loaded = Loader::load( new Requirements( null ), $plugin );

		$this->assertFalse( $loaded );
		$this->assertFalse( $plugin->is_booted() );

		$this->log_in_as( 'administrator' );
		$notice = $this->registered_notice();
		$this->assertNotNull( $notice, 'A requirements notice is hooked to admin_notices.' );
		$html = get_echo( array( $notice, 'render' ) );
		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'Checkout FieldKit needs WooCommerce', $html );
	}

	public function test_the_notice_names_both_versions_when_woocommerce_is_too_old() {
		Loader::load( new Requirements( '8.5.0' ), new Plugin() );

		$this->log_in_as( 'administrator' );
		$html = get_echo( array( $this->registered_notice(), 'render' ) );
		$this->assertStringContainsString( '9.0', $html );
		$this->assertStringContainsString( '8.5.0', $html );
	}

	public function test_the_notice_is_hidden_from_users_who_cannot_manage_plugins() {
		Loader::load( new Requirements( null ), new Plugin() );

		$this->log_in_as( 'editor' );
		$html = get_echo( array( $this->registered_notice(), 'render' ) );

		$this->assertSame( '', $html );
	}

	/**
	 * The RequirementsNotice hooked to admin_notices, if any.
	 *
	 * @return RequirementsNotice|null
	 */
	private function registered_notice() {
		global $wp_filter;

		if ( empty( $wp_filter['admin_notices'] ) ) {
			return null;
		}

		foreach ( $wp_filter['admin_notices']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof RequirementsNotice ) {
					return $callback['function'][0];
				}
			}
		}

		return null;
	}
}
