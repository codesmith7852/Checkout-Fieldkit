<?php
/**
 * Tests for the PSR-4 autoloader.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Unit;

use CheckoutFieldKit\Autoloader;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Maps namespaced class names to files under a base directory.
 */
class AutoloaderTest extends TestCase {

	/**
	 * Autoloaders registered by a test, removed again afterwards.
	 *
	 * @var callable[]
	 */
	private $registered = array();

	public function tear_down() {
		foreach ( $this->registered as $loader ) {
			spl_autoload_unregister( $loader );
		}
		parent::tear_down();
	}

	public function test_loads_a_class_from_the_matching_subdirectory() {
		$this->registered[] = Autoloader::register( 'CfkAutoloadFixture\\', dirname( __DIR__ ) . '/Fixtures/autoload' );

		$this->assertTrue( class_exists( 'CfkAutoloadFixture\\Sub\\Example' ) );
	}

	public function test_ignores_classes_outside_its_namespace() {
		$loader = new Autoloader( 'CfkAutoloadFixture\\', dirname( __DIR__ ) . '/Fixtures/autoload' );

		$this->assertFalse( $loader->load( 'SomethingElse\\Sub\\Example' ) );
	}

	public function test_returns_false_when_the_file_does_not_exist() {
		$loader = new Autoloader( 'CfkAutoloadFixture\\', dirname( __DIR__ ) . '/Fixtures/autoload' );

		$this->assertFalse( $loader->load( 'CfkAutoloadFixture\\Missing' ) );
	}

	public function test_the_plugin_namespace_is_autoloaded() {
		$this->assertTrue( class_exists( 'CheckoutFieldKit\\Plugin' ) );
	}
}
