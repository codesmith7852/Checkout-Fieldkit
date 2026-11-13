<?php
/**
 * Tests for the WooCommerce requirement check.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Unit;

use CheckoutFieldKit\Requirements;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Decides whether the plugin may load, and explains why not.
 */
class RequirementsTest extends TestCase {

	public function test_not_met_when_woocommerce_is_missing() {
		$requirements = new Requirements( null );

		$this->assertFalse( $requirements->are_met() );
		$this->assertStringContainsString( 'needs WooCommerce', $requirements->problem() );
		$this->assertStringContainsString( 'activate', $requirements->problem() );
	}

	/**
	 * @dataProvider data_too_old
	 *
	 * @param string $version An installed WooCommerce version below the minimum.
	 */
	public function test_not_met_when_woocommerce_is_too_old( $version ) {
		$requirements = new Requirements( $version );

		$this->assertFalse( $requirements->are_met() );
		$this->assertStringContainsString( '9.0', $requirements->problem() );
		$this->assertStringContainsString( $version, $requirements->problem() );
	}

	public function data_too_old() {
		return array(
			'8.9.3' => array( '8.9.3' ),
			'8.0'   => array( '8.0' ),
		);
	}

	/**
	 * @dataProvider data_new_enough
	 *
	 * @param string $version An installed WooCommerce version at or above the minimum.
	 */
	public function test_met_when_woocommerce_is_new_enough( $version ) {
		$requirements = new Requirements( $version );

		$this->assertTrue( $requirements->are_met() );
		$this->assertSame( '', $requirements->problem() );
	}

	public function data_new_enough() {
		return array(
			'9.0'    => array( '9.0' ),
			'9.0.0'  => array( '9.0.0' ),
			'11.3.0' => array( '11.3.0' ),
		);
	}

	public function test_reads_the_running_woocommerce_version() {
		$this->assertTrue( Requirements::from_environment()->are_met() );
	}

	public function test_minimum_matches_the_plugin_header() {
		$header = get_file_data( CFK_TESTS_PLUGIN_FILE, array( 'wc' => 'WC requires at least' ) );

		$this->assertSame( $header['wc'], Requirements::MINIMUM_WOOCOMMERCE_VERSION );
	}
}
