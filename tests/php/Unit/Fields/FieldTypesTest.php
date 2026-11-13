<?php
/**
 * Tests for the field type registry.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Unit\Fields;

use CheckoutFieldKit\Fields\FieldTypes;
use CheckoutFieldKit\Fields\Sections;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Spec §2.4: the field types of each checkout type, where they are allowed
 * and which settings apply to them.
 */
class FieldTypesTest extends TestCase {

	/**
	 * Registry under test.
	 *
	 * @var FieldTypes
	 */
	private $types;

	public function set_up() {
		parent::set_up();
		$this->types = new FieldTypes( new Sections() );
	}

	public function test_classic_offers_twenty_types_for_custom_fields() {
		$this->assertSame(
			array( 'text', 'number', 'email', 'url', 'tel', 'password', 'hidden', 'textarea', 'select', 'multiselect', 'radio', 'checkbox', 'checkboxgroup', 'date', 'time', 'datetime-local', 'month', 'week', 'heading', 'paragraph' ),
			$this->types->custom_types( 'classic' )
		);
	}

	public function test_block_offers_ten_types_for_custom_fields() {
		$this->assertSame(
			array( 'text', 'number', 'email', 'url', 'textarea', 'select', 'radio', 'checkbox', 'heading', 'paragraph' ),
			$this->types->custom_types( 'block' )
		);
	}

	public function test_classic_types_are_allowed_in_every_classic_section() {
		foreach ( $this->types->custom_types( 'classic' ) as $type ) {
			foreach ( array( 'billing', 'shipping', 'additional' ) as $section ) {
				$this->assertTrue( $this->types->is_allowed( 'classic', $section, $type ), "$type in $section" );
			}
		}
	}

	/**
	 * @dataProvider data_block_placement
	 *
	 * @param string $type    Field type.
	 * @param bool   $address Whether it is allowed in the Address section.
	 */
	public function test_block_types_by_section( $type, $address ) {
		$this->assertTrue( $this->types->is_allowed( 'block', 'contact', $type ) );
		$this->assertTrue( $this->types->is_allowed( 'block', 'additional', $type ) );
		$this->assertSame( $address, $this->types->is_allowed( 'block', 'address', $type ) );
	}

	public function data_block_placement() {
		return array(
			'text'      => array( 'text', true ),
			'number'    => array( 'number', true ),
			'email'     => array( 'email', true ),
			'url'       => array( 'url', true ),
			'select'    => array( 'select', true ),
			'checkbox'  => array( 'checkbox', true ),
			'textarea'  => array( 'textarea', false ),
			'radio'     => array( 'radio', false ),
			'heading'   => array( 'heading', false ),
			'paragraph' => array( 'paragraph', false ),
		);
	}

	public function test_types_of_the_other_checkout_or_unknown_are_not_allowed() {
		$this->assertFalse( $this->types->is_allowed( 'block', 'contact', 'tel' ) );
		$this->assertFalse( $this->types->is_allowed( 'block', 'contact', 'date' ) );
		$this->assertFalse( $this->types->is_allowed( 'classic', 'contact', 'text' ) );
		$this->assertFalse( $this->types->is_allowed( 'classic', 'billing', 'colour' ) );
		$this->assertFalse( $this->types->is_allowed( 'nope', 'billing', 'text' ) );
	}

	public function test_built_in_only_types_are_described_but_not_offered() {
		$this->assertSame( 'Country / Region', $this->types->get( 'classic', 'country' )['label'] );
		$this->assertTrue( $this->types->get( 'classic', 'state' )['builtInOnly'] );
		$this->assertTrue( $this->types->get( 'block', 'tel' )['builtInOnly'] );
		$this->assertFalse( $this->types->is_allowed( 'classic', 'billing', 'country' ) );
		$this->assertFalse( $this->types->is_allowed( 'block', 'address', 'tel' ) );
	}

	/**
	 * @dataProvider data_settings
	 *
	 * @param string   $type     Field type.
	 * @param string[] $settings Settings that apply, besides the display flags.
	 */
	public function test_settings_that_apply_to_each_classic_type( $type, $settings ) {
		$display_flags = in_array( $type, array( 'heading', 'paragraph' ), true ) ? array() : array( 'showInEmails', 'showOnOrderPages' );

		$this->assertSame( array_merge( $settings, $display_flags ), $this->types->get( 'classic', $type )['settings'], $type );
	}

	public function data_settings() {
		$text    = array( 'label', 'placeholder', 'default', 'required', 'validation', 'classes' );
		$options = array( 'label', 'placeholder', 'options', 'default', 'required', 'classes' );
		$plain   = array( 'label', 'default', 'required', 'classes' );

		return array(
			'text'           => array( 'text', $text ),
			'number'         => array( 'number', $text ),
			'email'          => array( 'email', $text ),
			'url'            => array( 'url', $text ),
			'tel'            => array( 'tel', $text ),
			'password'       => array( 'password', $text ),
			'hidden'         => array( 'hidden', array( 'label', 'default', 'classes' ) ),
			'textarea'       => array( 'textarea', array( 'label', 'placeholder', 'default', 'required', 'classes' ) ),
			'select'         => array( 'select', $options ),
			'multiselect'    => array( 'multiselect', $options ),
			'radio'          => array( 'radio', array( 'label', 'options', 'default', 'required', 'classes' ) ),
			'checkbox'       => array( 'checkbox', $plain ),
			'checkboxgroup'  => array( 'checkboxgroup', array( 'label', 'options', 'default', 'required', 'classes' ) ),
			'date'           => array( 'date', $plain ),
			'time'           => array( 'time', $plain ),
			'datetime-local' => array( 'datetime-local', $plain ),
			'month'          => array( 'month', $plain ),
			'week'           => array( 'week', $plain ),
			'heading'        => array( 'heading', array( 'label', 'headingLevel', 'classes' ) ),
			'paragraph'      => array( 'paragraph', array( 'label', 'classes' ) ),
		);
	}

	public function test_type_flags() {
		$number = $this->types->get( 'classic', 'number' );
		$this->assertSame( 'number', $number['impliedValidation'] );
		$this->assertTrue( $number['collectsValue'] );
		$this->assertFalse( $number['hasOptions'] );

		$this->assertSame( 'email', $this->types->get( 'block', 'email' )['impliedValidation'] );
		$this->assertSame( 'url', $this->types->get( 'classic', 'url' )['impliedValidation'] );
		$this->assertSame( 'phone', $this->types->get( 'classic', 'tel' )['impliedValidation'] );
		$this->assertNull( $this->types->get( 'classic', 'text' )['impliedValidation'] );

		$this->assertTrue( $this->types->get( 'classic', 'checkboxgroup' )['multiple'] );
		$this->assertTrue( $this->types->get( 'classic', 'multiselect' )['multiple'] );
		$this->assertFalse( $this->types->get( 'classic', 'select' )['multiple'] );
		$this->assertTrue( $this->types->get( 'classic', 'radio' )['hasOptions'] );

		$this->assertFalse( $this->types->get( 'block', 'heading' )['collectsValue'] );
		$this->assertFalse( $this->types->get( 'block', 'paragraph' )['collectsValue'] );
	}

	public function test_has_setting() {
		$this->assertTrue( $this->types->has_setting( 'classic', 'select', 'options' ) );
		$this->assertFalse( $this->types->has_setting( 'classic', 'text', 'options' ) );
		$this->assertFalse( $this->types->has_setting( 'classic', 'nope', 'label' ) );
	}

	public function test_validations_per_checkout_type() {
		$this->assertSame( array( 'email', 'phone', 'postcode', 'state', 'number', 'url' ), array_column( $this->types->validations( 'classic' ), 'name' ) );
		$this->assertSame( array( 'email', 'phone', 'postcode', 'url' ), array_column( $this->types->validations( 'block' ), 'name' ) );
		$this->assertSame( 'Email address', $this->types->validations( 'block' )[0]['label'] );
	}

	public function test_describe_serialises_for_the_admin() {
		$all = $this->types->describe();

		$this->assertSame( array( 'classic', 'block' ), array_keys( $all ) );
		$this->assertSame( 'text', $all['block'][0]['name'] );
		$this->assertSame( array( 'contact', 'address', 'additional' ), $all['block'][0]['sections'] );
		$this->assertSame( array( 'contact', 'additional' ), $all['block'][4]['sections'] );
		$this->assertSame( wp_json_encode( $all ), wp_json_encode( json_decode( wp_json_encode( $all ), true ) ) );
	}

	public function test_types_are_filterable_per_checkout_type() {
		add_filter(
			'checkout_fieldkit_field_types',
			static function ( $types, $checkout_type ) {
				if ( 'block' === $checkout_type ) {
					$types['date'] = array(
						'label'    => 'Date',
						'sections' => array( 'contact' ),
						'settings' => array( 'label', 'required' ),
					);
				}
				return $types;
			},
			10,
			2
		);

		$this->assertTrue( $this->types->is_allowed( 'block', 'contact', 'date' ) );
		$this->assertFalse( $this->types->is_allowed( 'block', 'address', 'date' ) );
		$this->assertSame( 'date', $this->types->get( 'block', 'date' )['name'] );
		$this->assertTrue( $this->types->get( 'block', 'date' )['collectsValue'], 'Missing flags take their defaults.' );
	}
}
