<?php
/**
 * Tests for validating submitted field values.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Orders;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Orders\ValueValidator;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Spec §4 and §5: a value is checked against required, the type's own
 * validation, the field's chosen validations and its options; only
 * non-empty values are validated; messages name the field by its label.
 */
class ValueValidatorTest extends TestCase {

	/**
	 * Validator under test.
	 *
	 * @var ValueValidator
	 */
	private $validator;

	public function set_up() {
		parent::set_up();
		$this->validator = new ValueValidator( ( new Domain() )->field_types() );
	}

	/**
	 * A normalised custom field.
	 *
	 * @param string $type  Field type.
	 * @param array  $extra Other keys.
	 * @return array
	 */
	private function field( $type, array $extra = array() ) {
		return array_merge(
			array(
				'name'       => 'answer',
				'type'       => $type,
				'builtIn'    => false,
				'enabled'    => true,
				'required'   => false,
				'label'      => 'Answer',
				'options'    => array(),
				'validation' => array(),
			),
			$extra
		);
	}

	/**
	 * Options a and b after an empty prompt.
	 *
	 * @return array
	 */
	private function options() {
		return array(
			array(
				'value' => '',
				'label' => 'Choose one',
			),
			array(
				'value' => 'a',
				'label' => 'Apple',
			),
			array(
				'value' => 'b',
				'label' => 'Banana',
			),
		);
	}

	/**
	 * @dataProvider data_valid
	 *
	 * @param string $type  Field type.
	 * @param mixed  $raw   Submitted value.
	 * @param array  $extra Other field keys.
	 */
	public function test_accepts_valid_values( $type, $raw, array $extra = array() ) {
		$field = $this->field( $type, $extra );
		if ( in_array( $type, array( 'select', 'radio' ), true ) ) {
			$field['options'] = $this->options();
		}

		$this->assertSame( '', $this->validator->problem( $field, $raw, 'block', 'GB' ) );
	}

	public function data_valid() {
		return array(
			'text'                      => array( 'text', 'Anything at all' ),
			'number'                    => array( 'number', '-4.5' ),
			'number zero'               => array( 'number', '0' ),
			'number as a JSON number'   => array( 'number', 12 ),
			'email'                     => array( 'email', 'ada@example.org' ),
			'url'                       => array( 'url', 'https://example.org/page?x=1' ),
			'textarea'                  => array( 'textarea', "Two\nlines" ),
			'select'                    => array( 'select', 'a' ),
			'radio'                     => array( 'radio', 'b' ),
			'checkbox ticked'           => array( 'checkbox', true ),
			'checkbox "1"'              => array( 'checkbox', '1' ),
			'text with phone'           => array( 'text', '+44 (0)1234 567-890', array( 'validation' => array( 'phone' ) ) ),
			'text with postcode'        => array( 'text', 'SW1A 1AA', array( 'validation' => array( 'postcode' ) ) ),
			'text with email'           => array( 'text', 'ada@example.org', array( 'validation' => array( 'email' ) ) ),
			'text with url'             => array( 'text', 'http://example.org', array( 'validation' => array( 'url' ) ) ),
			'empty optional text'       => array( 'text', '' ),
			'missing optional email'    => array( 'email', null ),
			'empty optional select'     => array( 'select', '' ),
			'unticked optional box'     => array( 'checkbox', false ),
			'heading never has a value' => array( 'heading', 'whatever', array( 'required' => true ) ),
			'paragraph'                 => array( 'paragraph', null, array( 'required' => true ) ),
		);
	}

	/**
	 * @dataProvider data_invalid
	 *
	 * @param string $type     Field type.
	 * @param mixed  $raw      Submitted value.
	 * @param array  $extra    Other field keys.
	 * @param string $expected The message.
	 */
	public function test_rejects_invalid_values_with_a_message_naming_the_field( $type, $raw, array $extra, $expected ) {
		$field = $this->field( $type, $extra );
		if ( in_array( $type, array( 'select', 'radio' ), true ) ) {
			$field['options'] = $this->options();
		}

		$this->assertSame( $expected, $this->validator->problem( $field, $raw, 'block', 'GB' ) );
	}

	public function data_invalid() {
		$required = array( 'required' => true );

		return array(
			'required text missing'        => array( 'text', null, $required, 'Answer is required.' ),
			'required text blank'          => array( 'text', '   ', $required, 'Answer is required.' ),
			'required textarea'            => array( 'textarea', '', $required, 'Answer is required.' ),
			'required select prompt'       => array( 'select', '', $required, 'Answer is required.' ),
			'required radio'               => array( 'radio', null, $required, 'Answer is required.' ),
			'required checkbox unticked'   => array( 'checkbox', false, $required, 'Answer is required.' ),
			'required checkbox "0"'        => array( 'checkbox', '0', $required, 'Answer is required.' ),
			'number'                       => array( 'number', '12 apples', array(), 'Answer must be a number.' ),
			'email'                        => array( 'email', 'not-an-email', array(), 'Answer is not a valid email address.' ),
			'url without scheme'           => array( 'url', 'example.org', array(), 'Answer is not a valid web address. Start it with http:// or https://.' ),
			'url with another scheme'      => array( 'url', 'javascript:alert(1)', array(), 'Answer is not a valid web address. Start it with http:// or https://.' ),
			'phone validation'             => array( 'text', 'call me', array( 'validation' => array( 'phone' ) ), 'Answer is not a valid phone number.' ),
			'postcode for the country'     => array( 'text', '12345', array( 'validation' => array( 'postcode' ) ), 'Answer is not a valid postcode / ZIP.' ),
			'email validation on text'     => array( 'text', 'nope', array( 'validation' => array( 'email' ) ), 'Answer is not a valid email address.' ),
			'url validation on text'       => array( 'text', 'nope', array( 'validation' => array( 'url' ) ), 'Answer is not a valid web address. Start it with http:// or https://.' ),
			'select value not an option'   => array( 'select', 'z', array(), 'Answer: choose one of the options.' ),
			'radio value not an option'    => array( 'radio', 'z', array(), 'Answer: choose one of the options.' ),
			'array for a text field'       => array( 'text', array( 'a' ), array(), 'Answer is not valid.' ),
			'label falls back to the name' => array( 'text', null, array_merge( $required, array( 'label' => '' ) ), 'answer is required.' ),
		);
	}

	public function test_postcodes_are_checked_for_the_country_given() {
		$field = $this->field( 'text', array( 'validation' => array( 'postcode' ) ) );

		$this->assertSame( '', $this->validator->problem( $field, '12345', 'block', 'US' ) );
		$this->assertNotSame( '', $this->validator->problem( $field, '12345', 'block', 'GB' ) );
		$this->assertSame( '', $this->validator->problem( $field, '12345', 'block', null ), 'Without a country only the characters are checked.' );
		$this->assertNotSame( '', $this->validator->problem( $field, '12#45', 'block', null ) );
	}

	public function test_problems_lists_every_invalid_field_by_name_in_order() {
		$fields = array(
			$this->field(
				'text',
				array(
					'name'     => 'first',
					'label'    => 'First',
					'required' => true,
				)
			),
			$this->field(
				'email',
				array(
					'name'  => 'second',
					'label' => 'Second',
				)
			),
			$this->field(
				'text',
				array(
					'name'  => 'third',
					'label' => 'Third',
				)
			),
		);

		$this->assertSame(
			array(
				'first'  => 'First is required.',
				'second' => 'Second is not a valid email address.',
			),
			$this->validator->problems(
				$fields,
				array(
					'second' => 'nope',
					'third'  => 'fine',
				),
				'block',
				'GB'
			)
		);
	}
}
