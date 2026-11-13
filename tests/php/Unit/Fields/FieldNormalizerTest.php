<?php
/**
 * Tests for field normalisation.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Unit\Fields;

use CheckoutFieldKit\Fields\Field;
use CheckoutFieldKit\Fields\FieldNormalizer;
use CheckoutFieldKit\Fields\FieldTypes;
use CheckoutFieldKit\Fields\Sections;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Spec §2.2: missing keys take their defaults, unknown keys are dropped, and
 * settings that do not apply to a field's type are cleared.
 */
class FieldNormalizerTest extends TestCase {

	/**
	 * Normaliser under test.
	 *
	 * @var FieldNormalizer
	 */
	private $normalizer;

	public function set_up() {
		parent::set_up();
		$this->normalizer = new FieldNormalizer( new FieldTypes( new Sections() ) );
	}

	public function test_key_defaults() {
		$this->assertSame(
			array(
				'name'             => '',
				'type'             => 'text',
				'builtIn'          => false,
				'enabled'          => true,
				'required'         => false,
				'label'            => '',
				'placeholder'      => '',
				'default'          => '',
				'options'          => array(),
				'validation'       => array(),
				'classes'          => array(),
				'headingLevel'     => 'h3',
				'showInEmails'     => true,
				'showOnOrderPages' => true,
			),
			Field::defaults( 'classic' )
		);
	}

	public function test_missing_keys_take_their_defaults() {
		$field = $this->normalizer->normalize( array( 'name' => 'gift_note' ), 'classic' );

		$this->assertSame( array_merge( Field::defaults( 'classic' ), array( 'name' => 'gift_note' ) ), $field );
	}

	public function test_unknown_keys_are_dropped() {
		$field = $this->normalizer->normalize(
			array(
				'name'  => 'gift_note',
				'color' => 'red',
				'id'    => 7,
			),
			'classic'
		);

		$this->assertArrayNotHasKey( 'color', $field );
		$this->assertArrayNotHasKey( 'id', $field );
		$this->assertSame( array_keys( Field::defaults( 'classic' ) ), array_keys( $field ) );
	}

	public function test_values_get_the_type_of_their_key() {
		$field = $this->normalizer->normalize(
			array(
				'name'     => ' gift_note ',
				'type'     => 'text',
				'builtIn'  => 'false',
				'enabled'  => '0',
				'required' => 'yes',
				'label'    => '<b>Gift</b> note ',
				'classes'  => 'one  two two',
			),
			'classic'
		);

		$this->assertSame( 'gift_note', $field['name'] );
		$this->assertFalse( $field['builtIn'] );
		$this->assertFalse( $field['enabled'] );
		$this->assertTrue( $field['required'] );
		$this->assertSame( 'Gift note', $field['label'] );
		$this->assertSame( array( 'one', 'two' ), $field['classes'] );
	}

	public function test_non_scalar_values_fall_back_to_the_default() {
		$field = $this->normalizer->normalize(
			array(
				'name'       => array( 'x' ),
				'label'      => array( 'x' ),
				'classes'    => 5,
				'validation' => 'email',
				'options'    => 'a,b',
			),
			'classic'
		);

		$this->assertSame( '', $field['name'] );
		$this->assertSame( '', $field['label'] );
		$this->assertSame( array(), $field['classes'] );
		$this->assertSame( array( 'email' ), $field['validation'] );
		$this->assertSame( array(), $field['options'] );
	}

	public function test_paragraph_text_keeps_line_breaks() {
		$field = $this->normalizer->normalize(
			array(
				'type'  => 'paragraph',
				'label' => "Line one\nLine two",
			),
			'block'
		);

		$this->assertSame( "Line one\nLine two", $field['label'] );
	}

	public function test_options_become_value_label_pairs() {
		$field = $this->normalizer->normalize(
			array(
				'type'    => 'select',
				'options' => array(
					array(
						'value' => ' red ',
						'label' => 'Red <i>!</i>',
					),
					array( 'value' => 'blue' ),
					'not an option',
					array(
						'value' => 3,
						'label' => 'Three',
						'extra' => true,
					),
				),
			),
			'classic'
		);

		$this->assertSame(
			array(
				array(
					'value' => 'red',
					'label' => 'Red !',
				),
				array(
					'value' => 'blue',
					'label' => '',
				),
				array(
					'value' => '3',
					'label' => 'Three',
				),
			),
			$field['options']
		);
	}

	public function test_settings_that_do_not_apply_to_the_type_are_cleared() {
		$field = $this->normalizer->normalize(
			array(
				'type'         => 'radio',
				'placeholder'  => 'Pick one',
				'validation'   => array( 'email' ),
				'headingLevel' => 'h2',
				'options'      => array( array( 'value' => 'a' ) ),
			),
			'classic'
		);

		$this->assertSame( '', $field['placeholder'] );
		$this->assertSame( array(), $field['validation'] );
		$this->assertSame( 'h3', $field['headingLevel'] );
		$this->assertCount( 1, $field['options'] );

		$text = $this->normalizer->normalize(
			array(
				'type'    => 'text',
				'options' => array( array( 'value' => 'a' ) ),
			),
			'classic'
		);
		$this->assertSame( array(), $text['options'] );
	}

	public function test_display_fields_are_never_required_and_keep_their_heading_level() {
		$field = $this->normalizer->normalize(
			array(
				'type'         => 'heading',
				'required'     => true,
				'default'      => 'x',
				'headingLevel' => 'h5',
				'showInEmails' => false,
			),
			'block'
		);

		$this->assertFalse( $field['required'] );
		$this->assertSame( '', $field['default'] );
		$this->assertSame( 'h5', $field['headingLevel'] );
		$this->assertTrue( $field['showInEmails'], 'Display flags do not apply to display fields.' );
	}

	public function test_an_invalid_heading_level_becomes_h3() {
		$field = $this->normalizer->normalize(
			array(
				'type'         => 'heading',
				'headingLevel' => 'h1',
			),
			'classic'
		);

		$this->assertSame( 'h3', $field['headingLevel'] );
	}

	/**
	 * @dataProvider data_checkbox_defaults
	 *
	 * @param mixed  $given    Default as given.
	 * @param string $expected Normalised default.
	 */
	public function test_checkbox_default_is_ticked_or_empty( $given, $expected ) {
		$field = $this->normalizer->normalize(
			array(
				'type'    => 'checkbox',
				'default' => $given,
			),
			'classic'
		);

		$this->assertSame( $expected, $field['default'] );
	}

	public function data_checkbox_defaults() {
		return array(
			'"1"'   => array( '1', '1' ),
			'true'  => array( true, '1' ),
			'"yes"' => array( 'yes', '1' ),
			'""'    => array( '', '' ),
			'"0"'   => array( '0', '' ),
			'false' => array( false, '' ),
		);
	}

	public function test_multi_value_default_is_a_tidy_comma_separated_list() {
		$field = $this->normalizer->normalize(
			array(
				'type'    => 'checkboxgroup',
				'default' => ' a, ,b,a ',
			),
			'classic'
		);

		$this->assertSame( 'a,b', $field['default'] );
	}

	public function test_textarea_default_keeps_line_breaks() {
		$field = $this->normalizer->normalize(
			array(
				'type'    => 'textarea',
				'default' => "a\nb",
			),
			'classic'
		);

		$this->assertSame( "a\nb", $field['default'] );
	}

	public function test_validation_and_classes_are_unique_lists() {
		$field = $this->normalizer->normalize(
			array(
				'type'       => 'text',
				'validation' => array( 'email', 'email', 'phone', '' ),
				'classes'    => array( 'form-row-wide', 'bad"class', 'form-row-wide' ),
			),
			'classic'
		);

		$this->assertSame( array( 'email', 'phone' ), $field['validation'] );
		$this->assertSame( array( 'form-row-wide', 'badclass' ), $field['classes'] );
	}

	public function test_built_in_fields_keep_woocommerce_values_for_settings_that_are_not_ours() {
		$built_in = array_merge(
			Field::defaults( 'block' ),
			array(
				'name'     => 'postcode',
				'type'     => 'text',
				'builtIn'  => true,
				'label'    => 'Postal code',
				'required' => true,
			)
		);

		$field = $this->normalizer->normalize(
			array(
				'name'        => 'postcode',
				'type'        => 'text',
				'builtIn'     => true,
				'label'       => 'ZIP',
				'required'    => false,
				'enabled'     => false,
				'placeholder' => 'Not ours on Block',
				'classes'     => array( 'nope' ),
				'validation'  => array( 'email' ),
			),
			'block',
			$built_in,
			array( 'label', 'required', 'enabled' )
		);

		$this->assertSame( 'ZIP', $field['label'] );
		$this->assertFalse( $field['required'] );
		$this->assertFalse( $field['enabled'] );
		$this->assertSame( '', $field['placeholder'] );
		$this->assertSame( array(), $field['classes'] );
		$this->assertSame( array(), $field['validation'] );
	}

	public function test_add_ons_can_add_field_keys() {
		add_filter(
			'checkout_fieldkit_field_defaults',
			static function ( $defaults ) {
				$defaults['conditions'] = array();
				return $defaults;
			}
		);

		$kept = $this->normalizer->normalize( array( 'conditions' => array( array( 'field' => 'a' ) ) ), 'classic' );
		$this->assertSame( array( array( 'field' => 'a' ) ), $kept['conditions'] );

		$wrong_type = $this->normalizer->normalize( array( 'conditions' => 'x' ), 'classic' );
		$this->assertSame( array(), $wrong_type['conditions'] );
	}

	public function test_compact_drops_values_equal_to_the_key_default_and_normalize_restores_them() {
		$field = $this->normalizer->normalize(
			array(
				'name'     => 'gift_note',
				'type'     => 'text',
				'label'    => 'Gift note',
				'required' => true,
			),
			'classic'
		);

		$compact = $this->normalizer->compact( $field, 'classic' );

		$this->assertSame(
			array(
				'name'     => 'gift_note',
				'type'     => 'text',
				'required' => true,
				'label'    => 'Gift note',
			),
			$compact
		);
		$this->assertSame( $field, $this->normalizer->normalize( $compact, 'classic' ) );
	}
}
