<?php
/**
 * Tests for the Block checkout feature check.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Unit\Environment;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Environment\BlockSupport;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Spec §5: block features need WooCommerce 8.9+; on older versions they stay
 * off and the admin says why.
 */
class BlockSupportTest extends TestCase {

	/**
	 * @dataProvider data_new_enough
	 *
	 * @param string $version WooCommerce version.
	 */
	public function test_available_from_woocommerce_8_9_with_the_fields_api( $version ) {
		$support = new BlockSupport( $version, true );

		$this->assertTrue( $support->is_available() );
		$this->assertSame( '', $support->reason() );
	}

	public function data_new_enough() {
		return array(
			'8.9.0' => array( '8.9.0' ),
			'8.9'   => array( '8.9' ),
			'11.3'  => array( '11.3.0' ),
		);
	}

	/**
	 * @dataProvider data_too_old
	 *
	 * @param string $version WooCommerce version.
	 */
	public function test_off_with_a_reason_on_older_woocommerce( $version ) {
		$support = new BlockSupport( $version, true );

		$this->assertFalse( $support->is_available() );
		$this->assertStringContainsString( '8.9', $support->reason() );
		$this->assertStringContainsString( $version, $support->reason() );
	}

	public function data_too_old() {
		return array(
			'8.8.5' => array( '8.8.5' ),
			'7.0'   => array( '7.0' ),
		);
	}

	public function test_off_with_a_reason_when_the_fields_api_is_missing() {
		$support = new BlockSupport( '11.3.0', false );

		$this->assertFalse( $support->is_available() );
		$this->assertNotSame( '', $support->reason() );
	}

	public function test_off_with_a_reason_without_woocommerce() {
		$support = new BlockSupport( null, false );

		$this->assertFalse( $support->is_available() );
		$this->assertStringContainsString( 'WooCommerce', $support->reason() );
	}

	public function test_the_running_woocommerce_supports_block_features() {
		$support = BlockSupport::from_environment();

		$this->assertTrue( $support->is_available() );
		$this->assertSame( '', $support->reason() );
	}

	public function test_the_domain_shares_one_instance() {
		$domain = new Domain();

		$this->assertInstanceOf( BlockSupport::class, $domain->block_support() );
		$this->assertSame( $domain->block_support(), $domain->block_support() );
	}
}
