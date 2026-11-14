<?php
/**
 * Tests for the essential field lists.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Unit\Fields;

use CheckoutFieldKit\Fields\EssentialFields;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Spec §2.3 and ADR 0003: built-in fields WooCommerce depends on, each with
 * what stops working without it.
 */
class EssentialFieldsTest extends TestCase {

	public function test_classic_essential_fields() {
		$this->assertSame(
			array( 'billing_email', 'billing_country', 'billing_address_1', 'billing_city', 'billing_state', 'billing_postcode', 'shipping_country', 'shipping_address_1', 'shipping_city', 'shipping_state', 'shipping_postcode' ),
			( new EssentialFields() )->names( 'classic' )
		);
	}

	public function test_block_essential_fields() {
		$this->assertSame(
			array( 'country', 'address_1', 'city', 'state', 'postcode' ),
			( new EssentialFields() )->names( 'block' )
		);
	}

	public function test_is_essential() {
		$essential = new EssentialFields();

		$this->assertTrue( $essential->is_essential( 'classic', 'billing_email' ) );
		$this->assertFalse( $essential->is_essential( 'classic', 'billing_phone' ) );
		$this->assertFalse( $essential->is_essential( 'block', 'billing_email' ) );
		$this->assertFalse( $essential->is_essential( 'block', 'email' ), 'Block email is locked on, so it never needs confirming.' );
		$this->assertFalse( $essential->is_essential( 'nope', 'country' ) );
	}

	public function test_each_essential_field_says_what_stops_working() {
		$essential = new EssentialFields();

		foreach ( array( 'classic', 'block' ) as $checkout_type ) {
			foreach ( $essential->for_checkout( $checkout_type ) as $name => $consequence ) {
				$this->assertIsString( $consequence );
				$this->assertGreaterThan( 20, strlen( $consequence ), "$checkout_type $name" );
			}
		}

		$this->assertStringContainsString( 'email', $essential->consequence( 'classic', 'billing_email' ) );
		$this->assertSame( '', $essential->consequence( 'classic', 'billing_phone' ) );
	}

	public function test_each_consequence_is_specific_to_its_field() {
		$essential = new EssentialFields();

		foreach ( array( 'classic', 'block' ) as $checkout_type ) {
			$all = $essential->for_checkout( $checkout_type );
			$this->assertSame( count( $all ), count( array_unique( $all ) ), "$checkout_type: every field has its own sentence" );
		}

		// The billing address is not where an order is sent; the shipping address is.
		$this->assertStringNotContainsString( 'send', $essential->consequence( 'classic', 'billing_address_1' ) );
		$this->assertStringContainsString( 'send', $essential->consequence( 'classic', 'shipping_address_1' ) );
		// One Block address form serves both billing and delivery.
		$this->assertStringContainsString( 'billing and delivery', $essential->consequence( 'block', 'address_1' ) );
	}

	public function test_describe_lists_both_checkout_types_for_the_admin() {
		$all = ( new EssentialFields() )->describe();

		$this->assertSame( array( 'classic', 'block' ), array_keys( $all ) );
		$this->assertSame( array( 'name', 'consequence' ), array_keys( $all['block'][0] ) );
		$this->assertSame( 'country', $all['block'][0]['name'] );
	}
}
