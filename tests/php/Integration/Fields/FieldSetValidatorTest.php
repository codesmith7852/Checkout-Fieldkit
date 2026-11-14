<?php
/**
 * Tests for the field set rules.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Fields;

use CheckoutFieldKit\Fields\DefaultFields;
use CheckoutFieldKit\Fields\FieldNormalizer;
use CheckoutFieldKit\Fields\FieldSetValidator;
use CheckoutFieldKit\Fields\FieldTypes;
use CheckoutFieldKit\Fields\Sections;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Spec §2.5: every rule, each problem reported with section, index, key and message.
 */
class FieldSetValidatorTest extends TestCase {

	/**
	 * Validator under test.
	 *
	 * @var FieldSetValidator
	 */
	private $validator;

	/**
	 * Built-in fields.
	 *
	 * @var DefaultFields
	 */
	private $defaults;

	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_default_country', 'GB' );
		update_option( 'woocommerce_checkout_phone_field', 'optional' );
		WC()->countries->locale = array();

		$types           = new FieldTypes( new Sections() );
		$this->defaults  = new DefaultFields();
		$this->validator = new FieldSetValidator( $types, $this->defaults, new FieldNormalizer( $types, $this->defaults ) );
	}

	public function tear_down() {
		parent::tear_down();
		WC()->countries->locale = array();
	}

	public function test_the_default_field_sets_are_valid() {
		$this->assertSame( array(), $this->validator->validate( 'classic', $this->defaults->field_set( 'classic' ) ) );
		$this->assertSame( array(), $this->validator->validate( 'block', $this->defaults->field_set( 'block' ) ) );
	}

	public function test_valid_custom_fields_in_every_section() {
		$set                             = $this->classic_with(
			'billing',
			array(
				'name'  => 'billing_vat',
				'type'  => 'text',
				'label' => 'VAT number',
			)
		);
		$set['sections']['shipping'][]   = array(
			'name'    => 'shipping_slot',
			'type'    => 'select',
			'options' => array(
				array(
					'value' => '',
					'label' => 'Choose a slot',
				),
				array(
					'value' => 'am',
					'label' => 'Morning',
				),
			),
		);
		$set['sections']['additional'][] = array(
			'name'    => 'gift_wrap',
			'type'    => 'checkboxgroup',
			'default' => 'paper,ribbon',
			'options' => array(
				array( 'value' => 'paper' ),
				array( 'value' => 'ribbon' ),
			),
		);

		$this->assertSame( array(), $this->validator->validate( 'classic', $set ) );
	}

	// Rule 1: name format, uniqueness, reserved names.

	/**
	 * @dataProvider data_bad_names
	 *
	 * @param mixed $name A name that breaks the rule.
	 */
	public function test_name_must_match_the_rule( $name ) {
		$set = $this->classic_with( 'additional', array( 'name' => $name ) );

		$this->assertSame(
			array( $this->error( 'additional', 1, 'name' ) ),
			$this->keys_of( $this->validator->validate( 'classic', $set ) )
		);
	}

	public function data_bad_names() {
		return array(
			'empty'             => array( '' ),
			'starts with digit' => array( '1field' ),
			'starts with _'     => array( '_field' ),
			'upper case'        => array( 'Gift' ),
			'hyphen'            => array( 'gift-note' ),
			'space'             => array( 'gift note' ),
			'61 characters'     => array( 'a' . str_repeat( 'b', 60 ) ),
			'not a string'      => array( array( 'gift' ) ),
		);
	}

	public function test_sixty_characters_is_the_longest_name() {
		$set = $this->classic_with( 'additional', array( 'name' => 'a' . str_repeat( 'b', 59 ) ) );

		$this->assertSame( array(), $this->validator->validate( 'classic', $set ) );
	}

	public function test_name_must_be_unique_across_the_field_set() {
		$set                           = $this->classic_with( 'additional', array( 'name' => 'gift_note' ) );
		$set['sections']['billing'][]  = array( 'name' => 'gift_note' );
		$set['sections']['shipping'][] = array( 'name' => 'gift_note' );

		$errors = $this->validator->validate( 'classic', $set );

		$this->assertSame(
			array(
				$this->error( 'shipping', count( $set['sections']['shipping'] ) - 1, 'name' ),
				$this->error( 'additional', 1, 'name' ),
			),
			$this->keys_of( $errors ),
			'The first use (billing) is fine; later ones are reported.'
		);
	}

	/**
	 * @dataProvider data_reserved_names
	 *
	 * @param string $checkout_type Checkout type.
	 * @param string $section       Section to add the field to.
	 * @param string $name          A name WooCommerce reserves.
	 */
	public function test_custom_fields_cannot_use_a_name_woocommerce_reserves( $checkout_type, $section, $name ) {
		$set                           = $this->defaults->field_set( $checkout_type );
		$set['sections'][ $section ][] = array( 'name' => $name );

		$errors = $this->validator->validate( $checkout_type, $set );

		$this->assertContains( $this->error( $section, count( $set['sections'][ $section ] ) - 1, 'name' ), $this->keys_of( $errors ) );
	}

	public function data_reserved_names() {
		return array(
			'block name on classic'     => array( 'classic', 'additional', 'email' ),
			'classic name on block'     => array( 'block', 'additional', 'billing_email' ),
			'shipping_phone on classic' => array( 'classic', 'shipping', 'shipping_phone' ),
			'order_comments on block'   => array( 'block', 'contact', 'order_comments' ),
			'account_username'          => array( 'classic', 'additional', 'account_username' ),
			'account_password'          => array( 'classic', 'additional', 'account_password' ),
			'createaccount'             => array( 'classic', 'additional', 'createaccount' ),
			'ship_to_different_address' => array( 'classic', 'additional', 'ship_to_different_address' ),
			'terms'                     => array( 'block', 'additional', 'terms' ),
			'payment_method'            => array( 'block', 'additional', 'payment_method' ),
			'shipping_method'           => array( 'classic', 'additional', 'shipping_method' ),
		);
	}

	/**
	 * WooCommerce passes every posted checkout value whose key has a setter on
	 * WC_Order or WC_Customer straight to that setter (WC_Checkout::create_order,
	 * process_customer; WC_Form_Handler::save_address). A custom field with
	 * such a name would let a shopper set, say, their own user role.
	 *
	 * @dataProvider data_setter_names
	 *
	 * @param string $checkout_type Checkout type.
	 * @param string $section       Section to add the field to.
	 * @param string $name          A name with a setter.
	 */
	public function test_custom_fields_cannot_use_the_name_of_an_order_or_customer_property( $checkout_type, $section, $name ) {
		$set                           = $this->defaults->field_set( $checkout_type );
		$set['sections'][ $section ][] = array( 'name' => $name );
		$index                         = count( $set['sections'][ $section ] ) - 1;

		$errors = $this->validator->validate( $checkout_type, $set );

		$this->assertSame( array( $this->error( $section, $index, 'name' ) ), $this->keys_of( $errors ) );
		$this->assertStringContainsString( 'order or the customer', $errors[0]['message'] );
	}

	public function data_setter_names() {
		return array(
			'role on classic'                 => array( 'classic', 'additional', 'role' ),
			'role on block'                   => array( 'block', 'contact', 'role' ),
			'status'                          => array( 'classic', 'additional', 'status' ),
			'currency'                        => array( 'classic', 'billing', 'currency' ),
			'total'                           => array( 'block', 'additional', 'total' ),
			'customer_note'                   => array( 'classic', 'additional', 'customer_note' ),
			'customer_id'                     => array( 'classic', 'shipping', 'customer_id' ),
			'password'                        => array( 'classic', 'billing', 'password' ),
			'is_vat_exempt'                   => array( 'block', 'address', 'is_vat_exempt' ),
			'transaction_id'                  => array( 'classic', 'additional', 'transaction_id' ),
			'billing_ prefixed, not built-in' => array( 'block', 'contact', 'billing_address_1' ),
		);
	}

	public function test_no_setter_of_the_order_or_the_customer_is_left_open() {
		$names = array();
		foreach ( array( \WC_Order::class, \WC_Customer::class ) as $class ) {
			foreach ( get_class_methods( $class ) as $method ) {
				$name = substr( $method, 4 );
				if ( 0 === strpos( $method, 'set_' ) && preg_match( FieldSetValidator::NAME_PATTERN, $name ) ) {
					$names[ $name ] = true;
				}
			}
		}
		$this->assertArrayHasKey( 'role', $names );

		$open = array();
		foreach ( array_keys( $names ) as $name ) {
			$set                             = $this->defaults->field_set( 'classic' );
			$set['sections']['additional'][] = array( 'name' => $name );
			if ( array() === $this->validator->validate( 'classic', $set ) ) {
				$open[] = $name;
			}
		}

		$this->assertSame( array(), $open );
	}

	public function test_names_without_a_setter_stay_free() {
		$set                             = $this->defaults->field_set( 'classic' );
		$set['sections']['billing'][]    = array( 'name' => 'billing_vat' );
		$set['sections']['additional'][] = array( 'name' => 'roles_note' );

		$this->assertSame( array(), $this->validator->validate( 'classic', $set ) );
	}

	/**
	 * Rule 2: built-in fields keep name, type and builtIn, and cannot be removed.
	 */
	public function test_built_in_fields_cannot_be_removed() {
		$set = $this->defaults->field_set( 'classic' );
		array_shift( $set['sections']['billing'] ); // billing_first_name.

		$errors = $this->validator->validate( 'classic', $set );

		$this->assertSame( array( $this->error( 'billing', null, 'name' ) ), $this->keys_of( $errors ) );
		$this->assertStringContainsString( 'billing_first_name', $errors[0]['message'] );
	}

	public function test_built_in_fields_cannot_change_type() {
		$set                                   = $this->defaults->field_set( 'block' );
		$set['sections']['address'][1]['type'] = 'select';

		$this->assertSame( array( $this->error( 'address', 1, 'type' ) ), $this->keys_of( $this->validator->validate( 'block', $set ) ) );
	}

	public function test_built_in_fields_cannot_be_renamed() {
		$set                                      = $this->defaults->field_set( 'classic' );
		$set['sections']['additional'][0]['name'] = 'order_notes';

		$errors = $this->keys_of( $this->validator->validate( 'classic', $set ) );

		$this->assertContains( $this->error( 'additional', 0, 'builtIn' ), $errors, 'order_notes is not a WooCommerce field.' );
		$this->assertContains( $this->error( 'additional', null, 'name' ), $errors, 'order_comments is gone.' );
	}

	public function test_built_in_fields_cannot_become_custom_fields() {
		$set                                      = $this->defaults->field_set( 'block' );
		$set['sections']['contact'][0]['builtIn'] = false;

		$this->assertSame( array( $this->error( 'contact', 0, 'builtIn' ) ), $this->keys_of( $this->validator->validate( 'block', $set ) ) );
	}

	public function test_custom_fields_cannot_claim_to_be_built_in() {
		$set = $this->classic_with(
			'additional',
			array(
				'name'    => 'gift_note',
				'builtIn' => true,
			)
		);

		$this->assertSame( array( $this->error( 'additional', 1, 'builtIn' ) ), $this->keys_of( $this->validator->validate( 'classic', $set ) ) );
	}

	public function test_built_in_fields_stay_in_their_section() {
		$set   = $this->defaults->field_set( 'classic' );
		$email = array_pop( $set['sections']['billing'] );
		array_unshift( $set['sections']['shipping'], $email );

		$this->assertSame( array( $this->error( 'shipping', 0, 'name' ) ), $this->keys_of( $this->validator->validate( 'classic', $set ) ) );
	}

	public function test_built_in_fields_can_be_reordered_disabled_and_edited() {
		$set                                       = $this->defaults->field_set( 'classic' );
		$set['sections']['billing']                = array_reverse( $set['sections']['billing'] );
		$set['sections']['billing'][0]['enabled']  = false;
		$set['sections']['billing'][1]['label']    = 'Mobile';
		$set['sections']['billing'][1]['required'] = true;

		$this->assertSame( array(), $this->validator->validate( 'classic', $set ) );
	}

	// Rule 3: defaults of choice fields are option values; number defaults are numbers.

	/**
	 * @dataProvider data_choice_defaults
	 *
	 * @param string $type    Choice type.
	 * @param string $given   Default value.
	 * @param bool   $valid   Whether it is valid.
	 */
	public function test_choice_defaults_must_be_option_values( $type, $given, $valid ) {
		$set = $this->classic_with(
			'additional',
			array(
				'name'    => 'colour',
				'type'    => $type,
				'default' => $given,
				'options' => array(
					array( 'value' => 'red' ),
					array( 'value' => 'blue' ),
				),
			)
		);

		$errors = $this->keys_of( $this->validator->validate( 'classic', $set ) );

		$this->assertSame( $valid ? array() : array( $this->error( 'additional', 1, 'default' ) ), $errors );
	}

	public function data_choice_defaults() {
		return array(
			'select, option'            => array( 'select', 'red', true ),
			'select, empty'             => array( 'select', '', true ),
			'select, not an option'     => array( 'select', 'green', false ),
			'radio, not an option'      => array( 'radio', 'Red', false ),
			'multiselect, two options'  => array( 'multiselect', 'red,blue', true ),
			'multiselect, one unknown'  => array( 'multiselect', 'red,green', false ),
			'checkboxgroup, unknown'    => array( 'checkboxgroup', 'green', false ),
			'checkboxgroup, one option' => array( 'checkboxgroup', 'blue', true ),
		);
	}

	/**
	 * @dataProvider data_number_defaults
	 *
	 * @param string $given   Default value.
	 * @param bool   $valid   Whether it is valid.
	 */
	public function test_number_default_must_be_a_number( $given, $valid ) {
		$set = $this->classic_with(
			'additional',
			array(
				'name'    => 'guests',
				'type'    => 'number',
				'default' => $given,
			)
		);

		$errors = $this->keys_of( $this->validator->validate( 'classic', $set ) );

		$this->assertSame( $valid ? array() : array( $this->error( 'additional', 1, 'default' ) ), $errors );
	}

	public function data_number_defaults() {
		return array(
			'integer'  => array( '3', true ),
			'decimal'  => array( '2.5', true ),
			'negative' => array( '-1', true ),
			'empty'    => array( '', true ),
			'text'     => array( 'three', false ),
			'mixed'    => array( '3 people', false ),
		);
	}

	// Rule 4: option values unique and non-empty, except a select's first option.

	/**
	 * @dataProvider data_options
	 *
	 * @param string   $type   Choice type.
	 * @param string[] $values Option values.
	 * @param bool     $valid  Whether they are valid.
	 */
	public function test_option_values_are_unique_and_not_empty( $type, $values, $valid ) {
		$options = array();
		foreach ( $values as $value ) {
			$options[] = array(
				'value' => $value,
				'label' => 'Label ' . $value,
			);
		}
		$set = $this->classic_with(
			'additional',
			array(
				'name'    => 'colour',
				'type'    => $type,
				'options' => $options,
			)
		);

		$errors = $this->keys_of( $this->validator->validate( 'classic', $set ) );

		$this->assertSame( $valid ? array() : array( $this->error( 'additional', 1, 'options' ) ), $errors );
	}

	public function data_options() {
		return array(
			'distinct'                  => array( 'radio', array( 'a', 'b' ), true ),
			'duplicate'                 => array( 'radio', array( 'a', 'b', 'a' ), false ),
			'empty value'               => array( 'radio', array( 'a', '' ), false ),
			'select prompt first'       => array( 'select', array( '', 'a' ), true ),
			'select empty not first'    => array( 'select', array( 'a', '' ), false ),
			'select two prompts'        => array( 'select', array( '', '' ), false ),
			'multiselect prompt first'  => array( 'multiselect', array( '', 'a' ), false ),
			'checkboxgroup empty first' => array( 'checkboxgroup', array( '', 'a' ), false ),
			'no options'                => array( 'radio', array(), false ),
			'select with only a prompt' => array( 'select', array( '' ), false ),
		);
	}

	// Rule 5: the type is allowed for the checkout type and section.

	/**
	 * @dataProvider data_types
	 *
	 * @param string $checkout_type Checkout type.
	 * @param string $section       Section.
	 * @param string $type          Field type.
	 * @param bool   $valid         Whether it is allowed.
	 */
	public function test_type_must_be_allowed_in_the_section( $checkout_type, $section, $type, $valid ) {
		$set                           = $this->defaults->field_set( $checkout_type );
		$set['sections'][ $section ][] = array(
			'name'    => 'extra',
			'type'    => $type,
			'options' => array( array( 'value' => 'a' ) ),
		);

		$errors = $this->keys_of( $this->validator->validate( $checkout_type, $set ) );

		$this->assertSame( $valid ? array() : array( $this->error( $section, count( $set['sections'][ $section ] ) - 1, 'type' ) ), $errors );
	}

	public function data_types() {
		return array(
			'classic date in billing'    => array( 'classic', 'billing', 'date', true ),
			'classic heading in billing' => array( 'classic', 'billing', 'heading', true ),
			'block radio in contact'     => array( 'block', 'contact', 'radio', true ),
			'block select in address'    => array( 'block', 'address', 'select', true ),
			'block radio in address'     => array( 'block', 'address', 'radio', false ),
			'block textarea in address'  => array( 'block', 'address', 'textarea', false ),
			'block heading in address'   => array( 'block', 'address', 'heading', false ),
			'block date anywhere'        => array( 'block', 'additional', 'date', false ),
			'block tel'                  => array( 'block', 'contact', 'tel', false ),
			'unknown type'               => array( 'classic', 'additional', 'colour', false ),
			'built-in only type'         => array( 'classic', 'billing', 'country', false ),
		);
	}

	/**
	 * Structure and extras.
	 */
	public function test_validation_choices_must_belong_to_the_checkout_type() {
		$set                          = $this->defaults->field_set( 'block' );
		$set['sections']['contact'][] = array(
			'name'       => 'company_id',
			'validation' => array( 'email', 'state' ),
		);

		$this->assertSame( array( $this->error( 'contact', 1, 'validation' ) ), $this->keys_of( $this->validator->validate( 'block', $set ) ) );
	}

	public function test_unknown_sections_and_non_field_entries_are_reported() {
		$set                          = $this->defaults->field_set( 'block' );
		$set['sections']['billing']   = array();
		$set['sections']['contact'][] = 'not a field';

		$this->assertSame(
			array(
				$this->error( 'contact', 1, '' ),
				$this->error( 'billing', null, '' ),
			),
			$this->keys_of( $this->validator->validate( 'block', $set ) )
		);
	}

	public function test_a_field_set_without_sections_is_reported() {
		$this->assertSame( array( $this->error( '', null, 'sections' ) ), $this->keys_of( $this->validator->validate( 'block', array( 'version' => 1 ) ) ) );
		$this->assertSame( array( $this->error( '', null, 'sections' ) ), $this->keys_of( $this->validator->validate( 'block', 'nope' ) ) );
	}

	public function test_an_unknown_checkout_type_is_reported() {
		$this->assertCount( 1, $this->validator->validate( 'nope', array( 'sections' => array() ) ) );
	}

	public function test_every_problem_is_returned_at_once_with_section_index_key_and_message() {
		$set = $this->defaults->field_set( 'classic' );
		array_pop( $set['sections']['billing'] ); // billing_email.
		$set['sections']['additional'][] = array( 'name' => 'Bad Name' );
		$set['sections']['additional'][] = array(
			'name'    => 'guests',
			'type'    => 'number',
			'default' => 'many',
		);

		$errors = $this->validator->validate( 'classic', $set );

		$this->assertSame(
			array(
				$this->error( 'additional', 1, 'name' ),
				$this->error( 'additional', 2, 'default' ),
				$this->error( 'billing', null, 'name' ),
			),
			$this->keys_of( $errors )
		);
		foreach ( $errors as $error ) {
			$this->assertSame( array( 'section', 'index', 'key', 'message' ), array_keys( $error ) );
			$this->assertNotSame( '', $error['message'] );
		}
	}

	public function test_extensions_can_add_field_rules() {
		add_filter(
			'checkout_fieldkit_validate_field',
			static function ( $errors, $field, $checkout_type, $section, $index ) {
				if ( 'gift_note' === $field['name'] && '' === $field['label'] ) {
					$errors[] = array(
						'key'     => 'label',
						'message' => "Gift note needs a label in $checkout_type $section $index.",
					);
				}
				return $errors;
			},
			10,
			5
		);
		$set = $this->classic_with( 'additional', array( 'name' => 'gift_note' ) );

		$errors = $this->validator->validate( 'classic', $set );

		$this->assertSame(
			array(
				array(
					'section' => 'additional',
					'index'   => 1,
					'key'     => 'label',
					'message' => 'Gift note needs a label in classic additional 1.',
				),
			),
			$errors
		);
	}

	/**
	 * The default classic field set with one more field in a section.
	 *
	 * @param string               $section Section.
	 * @param array<string, mixed> $field   Field.
	 * @return array<string, mixed>
	 */
	private function classic_with( $section, $field ) {
		$set                           = $this->defaults->field_set( 'classic' );
		$set['sections'][ $section ][] = $field;

		return $set;
	}

	/**
	 * An error without its message.
	 *
	 * @param string   $section Section.
	 * @param int|null $index   Field index.
	 * @param string   $key     Field key.
	 * @return array{section: string, index: int|null, key: string}
	 */
	private function error( $section, $index, $key ) {
		return array(
			'section' => $section,
			'index'   => $index,
			'key'     => $key,
		);
	}

	/**
	 * Errors without their messages.
	 *
	 * @param array<int, array<string, mixed>> $errors Errors.
	 * @return array<int, array{section: string, index: int|null, key: string}>
	 */
	private function keys_of( $errors ) {
		return array_map(
			function ( $error ) {
				return $this->error( $error['section'], $error['index'], $error['key'] );
			},
			$errors
		);
	}
}
