<?php
/**
 * Tests for the main plugin file and the Plugin bootstrap class.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration;

use CheckoutFieldKit\Plugin;
use CheckoutFieldKit\Service;
use CheckoutFieldKit\Tests\TestCase;

/**
 * The plugin header, version and service registration.
 */
class PluginTest extends TestCase {

	public function test_header_declares_the_agreed_requirements() {
		$data = get_file_data(
			CFK_TESTS_PLUGIN_FILE,
			array(
				'name'         => 'Plugin Name',
				'author'       => 'Author',
				'plugin_uri'   => 'Plugin URI',
				'license'      => 'License',
				'requires_wp'  => 'Requires at least',
				'requires_php' => 'Requires PHP',
				'requires_wc'  => 'WC requires at least',
				'plugins'      => 'Requires Plugins',
				'text_domain'  => 'Text Domain',
				'version'      => 'Version',
			)
		);

		$this->assertSame( 'Checkout FieldKit for WooCommerce', $data['name'] );
		$this->assertSame( 'codesmith7852', $data['author'] );
		$this->assertSame( 'https://github.com/codesmith7852/Checkout-Fieldkit', $data['plugin_uri'] );
		$this->assertSame( 'GPL-2.0-or-later', $data['license'] );
		$this->assertSame( '6.6', $data['requires_wp'] );
		$this->assertSame( '7.4', $data['requires_php'] );
		$this->assertSame( '9.0', $data['requires_wc'] );
		$this->assertSame( 'woocommerce', $data['plugins'] );
		$this->assertSame( 'checkout-fieldkit', $data['text_domain'] );
		$this->assertSame( \CheckoutFieldKit\VERSION, $data['version'] );
	}

	public function test_the_plugin_is_booted_when_woocommerce_is_active() {
		$this->assertTrue( Plugin::instance()->is_booted() );
	}

	public function test_boot_registers_the_hooks_of_every_added_service() {
		$service = $this->make_service();
		$plugin  = new Plugin();
		$plugin->add( $service );

		$plugin->boot();

		$this->assertSame( 1, $service->registered );
	}

	public function test_boot_runs_only_once() {
		$service = $this->make_service();
		$plugin  = new Plugin();
		$plugin->add( $service );

		$plugin->boot();
		$plugin->boot();

		$this->assertSame( 1, $service->registered );
	}

	public function test_a_service_added_after_boot_is_registered_straight_away() {
		$plugin = new Plugin();
		$plugin->boot();
		$service = $this->make_service();

		$plugin->add( $service );

		$this->assertSame( 1, $service->registered );
	}

	public function test_a_service_can_be_looked_up_by_class() {
		$plugin  = new Plugin();
		$service = $this->make_service();
		$plugin->add( $service );

		$this->assertSame( $service, $plugin->get( get_class( $service ) ) );
		$this->assertNull( $plugin->get( 'CheckoutFieldKit\\NoSuchService' ) );
	}

	/**
	 * A service that counts how often its hooks were registered.
	 *
	 * @return Service
	 */
	private function make_service() {
		return new class() implements Service {
			/**
			 * Times register_hooks() ran.
			 *
			 * @var int
			 */
			public $registered = 0;

			public function register_hooks(): void {
				++$this->registered;
			}
		};
	}
}
