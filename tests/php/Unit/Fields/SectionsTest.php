<?php
/**
 * Tests for the sections of each checkout type.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Unit\Fields;

use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Fields\Sections;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Spec §2.1: which sections each field set has, in display order.
 */
class SectionsTest extends TestCase {

	public function test_checkout_types() {
		$this->assertSame( array( 'classic', 'block' ), CheckoutType::all() );
		$this->assertTrue( CheckoutType::is_valid( 'classic' ) );
		$this->assertTrue( CheckoutType::is_valid( 'block' ) );
		$this->assertFalse( CheckoutType::is_valid( 'Classic' ) );
		$this->assertFalse( CheckoutType::is_valid( '' ) );
	}

	public function test_classic_sections_in_display_order() {
		$this->assertSame( array( 'billing', 'shipping', 'additional' ), ( new Sections() )->names( 'classic' ) );
	}

	public function test_block_sections_in_display_order() {
		$this->assertSame( array( 'contact', 'address', 'additional' ), ( new Sections() )->names( 'block' ) );
	}

	public function test_an_unknown_checkout_type_has_no_sections() {
		$this->assertSame( array(), ( new Sections() )->names( 'nope' ) );
	}

	public function test_sections_have_labels() {
		$sections = ( new Sections() )->for_checkout( 'classic' );

		$this->assertSame( 'Billing', $sections['billing']['label'] );
		$this->assertSame( 'billing', $sections['billing']['name'] );
	}

	public function test_has_section() {
		$sections = new Sections();

		$this->assertTrue( $sections->has( 'block', 'address' ) );
		$this->assertFalse( $sections->has( 'block', 'billing' ) );
		$this->assertFalse( $sections->has( 'classic', 'contact' ) );
	}

	public function test_describe_lists_both_checkout_types_for_the_admin() {
		$all = ( new Sections() )->describe();

		$this->assertSame( array( 'classic', 'block' ), array_keys( $all ) );
		$this->assertSame( array( 'name', 'label' ), array_keys( $all['block'][1] ) );
		$this->assertSame( 'address', $all['block'][1]['name'] );
	}

	public function test_sections_are_filterable() {
		add_filter(
			'checkout_fieldkit_sections',
			static function ( $sections, $checkout_type ) {
				if ( 'classic' === $checkout_type ) {
					$sections['account'] = array(
						'name'  => 'account',
						'label' => 'Account',
					);
				}
				return $sections;
			},
			10,
			2
		);

		$sections = new Sections();

		$this->assertSame( array( 'billing', 'shipping', 'additional', 'account' ), $sections->names( 'classic' ) );
		$this->assertSame( array( 'contact', 'address', 'additional' ), $sections->names( 'block' ) );
	}
}
