<?php
/**
 * Tests for sanitising submitted field values.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Orders;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Orders\ValueSanitizer;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Spec §4.1: values are sanitised by field type before they are stored.
 */
class ValueSanitizerTest extends TestCase {

	/**
	 * Sanitiser under test.
	 *
	 * @var ValueSanitizer
	 */
	private $sanitizer;

	public function set_up() {
		parent::set_up();
		$this->sanitizer = new ValueSanitizer( ( new Domain() )->field_types() );
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
				'name'    => 'answer',
				'type'    => $type,
				'builtIn' => false,
				'label'   => 'Answer',
				'options' => array(),
			),
			$extra
		);
	}

	/**
	 * Options a, b, c.
	 *
	 * @return array
	 */
	private function options() {
		return array(
			array(
				'value' => 'a',
				'label' => 'Apple',
			),
			array(
				'value' => 'b',
				'label' => 'Banana',
			),
			array(
				'value' => 'c',
				'label' => 'Cherry',
			),
		);
	}

	/**
	 * @dataProvider data_values
	 *
	 * @param string $type     Field type.
	 * @param mixed  $raw      Submitted value.
	 * @param string $expected Stored value.
	 */
	public function test_sanitises_by_type( $type, $raw, $expected ) {
		$field = $this->field( $type, array( 'options' => $this->options() ) );

		$this->assertSame( $expected, $this->sanitizer->sanitize( $field, $raw, 'classic' ) );
	}

	public function data_values() {
		return array(
			'text is cleaned'                            => array( 'text', "  Hello <b>world</b>\n ", 'Hello world' ),
			'text keeps "0"'                             => array( 'text', '0', '0' ),
			'empty text'                                 => array( 'text', '', '' ),
			'null'                                       => array( 'text', null, '' ),
			'textarea keeps line breaks'                 => array( 'textarea', "Line one\nLine <i>two</i>", "Line one\nLine two" ),
			'email'                                      => array( 'email', ' ada()@example.org ', 'ada@example.org' ),
			'number'                                     => array( 'number', ' 12.5 ', '12.5' ),
			'number "0"'                                 => array( 'number', '0', '0' ),
			'not a number'                               => array( 'number', 'twelve', '' ),
			'url'                                        => array( 'url', 'https://example.org/a b', 'https://example.org/a%20b' ),
			'tel'                                        => array( 'tel', ' +44 20 7946 0000 ', '+44 20 7946 0000' ),
			'date'                                       => array( 'date', '2026-11-13', '2026-11-13' ),
			'time'                                       => array( 'time', '09:30', '09:30' ),
			'hidden'                                     => array( 'hidden', 'campaign-7', 'campaign-7' ),
			'select keeps an option value'               => array( 'select', 'b', 'b' ),
			'select drops other values'                  => array( 'select', 'z', '' ),
			'radio keeps an option value'                => array( 'radio', 'c', 'c' ),
			'radio drops other values'                   => array( 'radio', 'Apple', '' ),
			'multiselect array to a list'                => array( 'multiselect', array( 'c', 'a' ), 'a,c' ),
			'multiselect as WooCommerce posts it'        => array( 'multiselect', 'c, a, z', 'a,c' ),
			'checkboxgroup drops unknown and duplicates' => array( 'checkboxgroup', array( 'b', 'x', 'b' ), 'b' ),
			'checkboxgroup with nothing valid'           => array( 'checkboxgroup', array( 'x' ), '' ),
			'checkbox ticked as WooCommerce posts it'    => array( 'checkbox', 1, '1' ),
			'checkbox ticked'                            => array( 'checkbox', 'on', '1' ),
			'checkbox not ticked'                        => array( 'checkbox', '', '' ),
			'checkbox "0"'                               => array( 'checkbox', '0', '' ),
			'heading collects nothing'                   => array( 'heading', 'x', '' ),
			'paragraph collects nothing'                 => array( 'paragraph', 'x', '' ),
			'array for a single value'                   => array( 'text', array( 'a' ), '' ),
		);
	}

	public function test_a_select_prompt_with_an_empty_value_is_not_a_value() {
		$options = array_merge(
			array(
				array(
					'value' => '',
					'label' => 'Choose one',
				),
			),
			$this->options()
		);

		$this->assertSame( '', $this->sanitizer->sanitize( $this->field( 'select', array( 'options' => $options ) ), '', 'classic' ) );
	}

	public function test_block_types_are_sanitised_the_same_way() {
		$this->assertSame( "a\nb", $this->sanitizer->sanitize( $this->field( 'textarea' ), "a\nb", 'block' ) );
		$this->assertSame( '1', $this->sanitizer->sanitize( $this->field( 'checkbox' ), true, 'block' ) );
	}

	public function test_an_unknown_type_is_cleaned_as_text() {
		$this->assertSame( 'x', $this->sanitizer->sanitize( $this->field( 'colour' ), ' <b>x</b> ', 'classic' ) );
	}

	public function test_the_sanitize_value_filter_has_the_last_word() {
		$seen = null;
		add_filter(
			'checkout_fieldkit_sanitize_value',
			static function ( $value, $raw, $field, $checkout_type ) use ( &$seen ) {
				$seen = array( $value, $raw, $field['name'], $checkout_type );

				return strtoupper( $value );
			},
			10,
			4
		);

		$this->assertSame( 'HELLO', $this->sanitizer->sanitize( $this->field( 'text' ), ' hello ', 'classic' ) );
		$this->assertSame( array( 'hello', ' hello ', 'answer', 'classic' ), $seen );
	}
}
