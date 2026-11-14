<?php
/**
 * Tests for custom Address fields on the Block checkout.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Block;

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFieldsAdmin;
use Automattic\WooCommerce\Blocks\Package;
use CheckoutFieldKit\Block\AddressFields;
use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Environment\BlockSupport;
use CheckoutFieldKit\Plugin;
use CheckoutFieldKit\Tests\Support\StoreApiCheckout;
use CheckoutFieldKit\Tests\TestCase;
use WC_Customer;

/**
 * Spec §5, first bullet: custom Address fields are registered with
 * woocommerce_register_additional_checkout_field() at location `address`,
 * id `checkout-fieldkit/<name>`; WooCommerce stores, validates and displays
 * them. Display flags map onto WooCommerce's options.
 */
class AddressFieldsTest extends TestCase {

	use StoreApiCheckout;

	/**
	 * The service under test, with its hooks added.
	 *
	 * @var AddressFields
	 */
	private $service;

	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_default_country', 'GB' );
		WC()->countries->locale = array();

		$this->service = new AddressFields( new Domain() );
		$this->service->register_hooks();
	}

	public function tear_down() {
		$controller = $this->controller();
		foreach ( array_keys( $controller->get_additional_fields() ) as $id ) {
			if ( 0 === strpos( $id, 'checkout-fieldkit/' ) ) {
				$controller->deregister_checkout_field( $id );
			}
		}
		WC()->countries->locale = array();
		parent::tear_down();
	}

	/**
	 * WooCommerce's checkout fields controller.
	 *
	 * @return CheckoutFields
	 */
	private function controller() {
		return Package::container()->get( CheckoutFields::class );
	}

	/**
	 * Saves the default Block field set with these custom fields appended to Address.
	 *
	 * @param array<int, array<string, mixed>> $fields Custom fields.
	 */
	private function save_address_fields( array $fields ) {
		$repository = ( new Domain() )->field_sets( 'block' );
		$set        = $repository->get();
		foreach ( $fields as $field ) {
			$set['sections']['address'][] = $field;
		}

		$this->assertTrue( $repository->save( $set ) );
	}

	/**
	 * Saves one custom field and registers the custom fields.
	 *
	 * @param array<string, mixed> $field Custom field.
	 * @return array<string, mixed> The field as WooCommerce registered it.
	 */
	private function register_one( array $field ) {
		$this->save_address_fields( array( $field ) );
		$this->service->register_fields();

		$fields = $this->controller()->get_additional_fields();
		$this->assertArrayHasKey( 'checkout-fieldkit/' . $field['name'], $fields );

		return $fields[ 'checkout-fieldkit/' . $field['name'] ];
	}

	public function test_the_plugin_registers_the_service() {
		$this->assertInstanceOf( AddressFields::class, Plugin::instance()->get( AddressFields::class ) );
	}

	public function test_fields_are_registered_on_woocommerce_init() {
		$this->assertNotFalse( has_action( 'woocommerce_init', array( $this->service, 'register_fields' ) ) );
	}

	public function test_a_text_field_is_registered_at_the_address_location() {
		$field = $this->register_one(
			array(
				'name'     => 'vat_number',
				'type'     => 'text',
				'label'    => 'VAT number',
				'required' => true,
			)
		);

		$this->assertSame( 'checkout-fieldkit/vat_number', $field['id'] );
		$this->assertSame( 'address', $this->controller()->get_field_location( 'checkout-fieldkit/vat_number' ) );
		$this->assertSame( 'text', $field['type'] );
		$this->assertSame( 'VAT number', $field['label'] );
		$this->assertSame( 'VAT number (optional)', $field['optionalLabel'] );
		$this->assertTrue( $field['required'] );
		$this->assertSame( array(), $field['attributes'] );
		$this->assertTrue( $field['show_in_order_confirmation'] );
		$this->assertSame( 110, $field['index'], 'After the ten built-in address fields: position 11 × 10.' );
	}

	public function test_the_index_follows_the_position_in_the_section() {
		$repository = ( new Domain() )->field_sets( 'block' );
		$set        = $repository->get();
		array_splice(
			$set['sections']['address'],
			2,
			0,
			array(
				array(
					'name'  => 'title',
					'type'  => 'text',
					'label' => 'Title',
				),
			)
		);
		$this->assertTrue( $repository->save( $set ) );

		$this->service->register_fields();

		$this->assertSame( 30, $this->controller()->get_additional_fields()['checkout-fieldkit/title']['index'] );
	}

	/**
	 * @dataProvider data_text_like_types
	 *
	 * @param string               $type       Our type.
	 * @param array<string, mixed> $attributes Expected input attributes.
	 */
	public function test_number_email_and_url_are_text_fields_with_input_attributes( $type, $attributes ) {
		$field = $this->register_one(
			array(
				'name'  => 'extra',
				'type'  => $type,
				'label' => 'Extra',
			)
		);

		$this->assertSame( 'text', $field['type'] );
		foreach ( $attributes as $name => $value ) {
			$this->assertArrayHasKey( $name, $field['attributes'] );
			$this->assertSame( esc_attr( $value ), $field['attributes'][ $name ] );
		}
	}

	public function data_text_like_types() {
		return array(
			'number' => array( 'number', array() ),
			'email'  => array(
				'email',
				array(
					'autocomplete'   => 'email',
					'autocapitalize' => 'none',
				),
			),
			'url'    => array(
				'url',
				array(
					'autocomplete'   => 'url',
					'autocapitalize' => 'none',
				),
			),
		);
	}

	public function test_text_like_types_get_a_pattern_and_title() {
		$examples = array(
			'number' => '-12.5',
			'email'  => 'a@b.co',
			'url'    => 'https://example.org/x',
		);
		foreach ( $examples as $type => $example ) {
			$definition = $this->service->definition(
				array_merge(
					\CheckoutFieldKit\Fields\Field::DEFAULTS,
					array(
						'name'  => 'x',
						'type'  => $type,
						'label' => 'X',
					)
				),
				0
			);
			$this->assertNotEmpty( $definition['attributes']['pattern'], $type );
			$this->assertNotEmpty( $definition['attributes']['title'], $type );
			$this->assertSame( 1, preg_match( '~^(?:' . $definition['attributes']['pattern'] . ')$~u', $example ), $type );
			$this->assertSame( 0, preg_match( '~^(?:' . $definition['attributes']['pattern'] . ')$~u', 'nope' ), $type );
		}
	}

	public function test_a_select_keeps_its_options_and_turns_an_empty_first_option_into_the_placeholder() {
		$field = $this->register_one(
			array(
				'name'     => 'delivery_slot',
				'type'     => 'select',
				'label'    => 'Delivery slot',
				'required' => true,
				'options'  => array(
					array(
						'value' => '',
						'label' => 'Choose a slot',
					),
					array(
						'value' => 'am',
						'label' => 'Morning',
					),
					array(
						'value' => 'pm',
						'label' => 'Afternoon',
					),
				),
			)
		);

		$this->assertSame( 'select', $field['type'] );
		$this->assertSame(
			array(
				array(
					'value' => 'am',
					'label' => 'Morning',
				),
				array(
					'value' => 'pm',
					'label' => 'Afternoon',
				),
			),
			$field['options']
		);
		$this->assertSame( 'Choose a slot', $field['placeholder'] );
	}

	public function test_a_select_uses_its_placeholder_setting_without_a_prompt_option() {
		$field = $this->register_one(
			array(
				'name'        => 'size',
				'type'        => 'select',
				'label'       => 'Size',
				'placeholder' => 'Pick a size',
				'options'     => array(
					array(
						'value' => 's',
						'label' => 'Small',
					),
				),
			)
		);

		$this->assertSame( 'Pick a size', $field['placeholder'] );
	}

	public function test_a_checkbox_is_registered_as_a_checkbox() {
		$field = $this->register_one(
			array(
				'name'     => 'gate_code_ok',
				'type'     => 'checkbox',
				'label'    => 'Leave at the gate',
				'required' => true,
			)
		);

		$this->assertSame( 'checkbox', $field['type'] );
		$this->assertTrue( $field['required'] );
	}

	public function test_a_field_without_a_label_is_registered_under_its_name() {
		$field = $this->register_one(
			array(
				'name' => 'reference',
				'type' => 'text',
			)
		);

		$this->assertSame( 'reference', $field['label'] );
	}

	public function test_disabled_fields_and_built_ins_are_not_registered() {
		$this->save_address_fields(
			array(
				array(
					'name'    => 'hidden_one',
					'type'    => 'text',
					'label'   => 'Hidden',
					'enabled' => false,
				),
			)
		);

		$this->service->register_fields();

		$ours = array_filter(
			array_keys( $this->controller()->get_additional_fields() ),
			static function ( $id ) {
				return 0 === strpos( $id, 'checkout-fieldkit/' );
			}
		);
		$this->assertSame( array(), array_values( $ours ) );
	}

	public function test_registering_again_changes_nothing() {
		$this->register_one(
			array(
				'name'  => 'vat_number',
				'type'  => 'text',
				'label' => 'VAT number',
			)
		);

		$this->service->register_fields(); // WooCommerce would report a duplicate as "doing it wrong", which fails the test.

		$this->assertTrue( $this->controller()->is_field( 'checkout-fieldkit/vat_number' ) );
	}

	public function test_nothing_is_registered_without_block_support() {
		$this->save_address_fields(
			array(
				array(
					'name'  => 'vat_number',
					'type'  => 'text',
					'label' => 'VAT number',
				),
			)
		);

		( new AddressFields( new Domain(), new BlockSupport( '8.8.0', true ) ) )->register_fields();

		$this->assertFalse( $this->controller()->is_field( 'checkout-fieldkit/vat_number' ) );
	}

	public function test_custom_fields_of_other_sections_are_not_registered_here() {
		$repository                   = ( new Domain() )->field_sets( 'block' );
		$set                          = $repository->get();
		$set['sections']['contact'][] = array(
			'name'  => 'nickname',
			'type'  => 'text',
			'label' => 'Nickname',
		);
		$this->assertTrue( $repository->save( $set ) );

		$this->service->register_fields();

		$this->assertFalse( $this->controller()->is_field( 'checkout-fieldkit/nickname' ) );
	}

	/**
	 * @dataProvider data_invalid_values
	 *
	 * @param array<string, mixed> $field   Custom field.
	 * @param string               $invalid A value that must be refused.
	 * @param string               $valid   A value that must be accepted.
	 */
	public function test_values_are_validated( $field, $invalid, $valid ) {
		$registered = $this->register_one( array_merge( array( 'label' => 'Extra' ), $field ) );

		$error = $this->controller()->validate_field( $registered, $invalid );
		$this->assertTrue( $error->has_errors(), 'Refuses ' . $invalid );
		$this->assertStringContainsString( 'Extra', $error->get_error_message() );

		$this->assertFalse( $this->controller()->validate_field( $registered, $valid )->has_errors(), 'Accepts ' . $valid );
	}

	public function data_invalid_values() {
		return array(
			'email type'          => array(
				array(
					'name' => 'extra',
					'type' => 'email',
				),
				'not-an-email',
				'ada@example.org',
			),
			'url type'            => array(
				array(
					'name' => 'extra',
					'type' => 'url',
				),
				'example',
				'https://example.org/page',
			),
			'number type'         => array(
				array(
					'name' => 'extra',
					'type' => 'number',
				),
				'12a',
				'-12.5',
			),
			'email validation'    => array(
				array(
					'name'       => 'extra',
					'type'       => 'text',
					'validation' => array( 'email' ),
				),
				'ada@',
				'ada@example.org',
			),
			'phone validation'    => array(
				array(
					'name'       => 'extra',
					'type'       => 'text',
					'validation' => array( 'phone' ),
				),
				'call me',
				'+44 (0)20 7946 0000',
			),
			'postcode validation' => array(
				array(
					'name'       => 'extra',
					'type'       => 'text',
					'validation' => array( 'postcode' ),
				),
				'AB$12',
				'AB1 2CD',
			),
			'url validation'      => array(
				array(
					'name'       => 'extra',
					'type'       => 'text',
					'validation' => array( 'url' ),
				),
				'ftp://example.org',
				'http://example.org',
			),
		);
	}

	public function test_postcodes_are_checked_for_the_addresss_country() {
		$this->register_one(
			array(
				'name'       => 'alt_postcode',
				'type'       => 'text',
				'label'      => 'Second postcode',
				'validation' => array( 'postcode' ),
			)
		);

		$errors = $this->controller()->validate_fields_for_location(
			array(
				'country'                        => 'US',
				'checkout-fieldkit/alt_postcode' => 'SW1A 1AA',
			),
			'address',
			'billing'
		);
		$this->assertTrue( $errors->has_errors() );
		$this->assertStringContainsString( 'Second postcode', $errors->get_error_message() );

		$this->assertFalse(
			$this->controller()->validate_fields_for_location(
				array(
					'country'                        => 'US',
					'checkout-fieldkit/alt_postcode' => '90210',
				),
				'address',
				'billing'
			)->has_errors()
		);
	}

	public function test_an_empty_optional_value_is_valid() {
		$registered = $this->register_one(
			array(
				'name' => 'extra',
				'type' => 'email',
			)
		);

		$this->assertFalse( $this->controller()->validate_field( $registered, '' )->has_errors() );
	}

	public function test_the_default_value_prefills_the_address() {
		$this->save_address_fields(
			array(
				array(
					'name'    => 'region_code',
					'type'    => 'text',
					'label'   => 'Region code',
					'default' => 'R1',
				),
				array(
					'name'    => 'leave_safe',
					'type'    => 'checkbox',
					'label'   => 'Leave in a safe place',
					'default' => '1',
				),
				array(
					'name'    => 'slot',
					'type'    => 'select',
					'label'   => 'Slot',
					'default' => 'pm',
					'options' => array(
						array(
							'value' => 'am',
							'label' => 'Morning',
						),
						array(
							'value' => 'pm',
							'label' => 'Afternoon',
						),
					),
				),
				array(
					'name'  => 'no_default',
					'type'  => 'text',
					'label' => 'No default',
				),
			)
		);
		$this->service->register_fields();

		$customer = new WC_Customer();
		$this->assertSame( 'R1', $this->controller()->get_field_from_object( 'checkout-fieldkit/region_code', $customer, 'billing' ) );
		$this->assertSame( 'R1', $this->controller()->get_field_from_object( 'checkout-fieldkit/region_code', $customer, 'shipping' ) );
		$this->assertTrue( $this->controller()->get_field_from_object( 'checkout-fieldkit/leave_safe', $customer, 'billing' ) );
		$this->assertSame( 'pm', $this->controller()->get_field_from_object( 'checkout-fieldkit/slot', $customer, 'billing' ) );
		$this->assertSame( '', $this->controller()->get_field_from_object( 'checkout-fieldkit/no_default', $customer, 'billing' ) );

		$customer->update_meta_data( '_wc_billing/checkout-fieldkit/region_code', 'R9' );
		$this->assertSame( 'R9', $this->controller()->get_field_from_object( 'checkout-fieldkit/region_code', $customer, 'billing' ), 'A saved value wins.' );
	}

	/**
	 * Fields with values on an order, as WooCommerce reads them for display.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<string, array<string, mixed>>
	 */
	private function order_fields( $order ) {
		return $this->controller()->get_order_additional_fields_with_values( $order, 'address', 'billing', 'view' );
	}

	public function test_display_flags_decide_emails_and_order_pages_separately() {
		$this->save_address_fields(
			array(
				array(
					'name'         => 'pages_only',
					'type'         => 'text',
					'label'        => 'Pages only',
					'showInEmails' => false,
				),
				array(
					'name'             => 'emails_only',
					'type'             => 'text',
					'label'            => 'Emails only',
					'showOnOrderPages' => false,
				),
				array(
					'name'  => 'everywhere',
					'type'  => 'text',
					'label' => 'Everywhere',
				),
			)
		);
		$this->service->register_fields();
		$order  = $this->create_order(
			array(
				'meta' => array(
					'_wc_billing/checkout-fieldkit/pages_only'  => 'a',
					'_wc_billing/checkout-fieldkit/emails_only' => 'b',
					'_wc_billing/checkout-fieldkit/everywhere'  => 'c',
				),
			)
		);
		$fields = $this->order_fields( $order );

		$email = $this->controller()->filter_fields_for_order_confirmation(
			$fields,
			array(
				'caller'       => 'WC_Email::additional_address_fields',
				'address_type' => 'billing',
				'order'        => $order,
			)
		);
		$this->assertSame( array( 'checkout-fieldkit/emails_only', 'checkout-fieldkit/everywhere' ), array_keys( $email ) );

		foreach ( array( 'CheckoutFieldsFrontend::render_order_address_fields', 'BillingAddress::render_content' ) as $caller ) {
			$page = $this->controller()->filter_fields_for_order_confirmation(
				$fields,
				array(
					'caller' => $caller,
					'order'  => $order,
				)
			);
			$this->assertSame( array( 'checkout-fieldkit/pages_only', 'checkout-fieldkit/everywhere' ), array_keys( $page ), $caller );
		}
	}

	public function test_the_display_filter_leaves_other_fields_alone() {
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's hook.
		$this->assertFalse( apply_filters( 'woocommerce_filter_fields_for_order_confirmation', false, array( 'id' => 'other/field' ), array(), array( 'caller' => 'WC_Email::additional_address_fields' ), $this->controller() ) );
		$this->assertTrue( apply_filters( 'woocommerce_filter_fields_for_order_confirmation', true, array( 'id' => 'other/field' ), array(), array(), $this->controller() ) );
		// phpcs:enable
	}

	public function test_the_emails_show_only_fields_meant_for_emails() {
		$this->save_address_fields(
			array(
				array(
					'name'         => 'pages_only',
					'type'         => 'text',
					'label'        => 'Pages only',
					'showInEmails' => false,
				),
				array(
					'name'             => 'emails_only',
					'type'             => 'text',
					'label'            => 'Emails only',
					'showOnOrderPages' => false,
				),
			)
		);
		$this->service->register_fields();
		$order = $this->create_order(
			array(
				'meta' => array(
					'_wc_billing/checkout-fieldkit/pages_only'  => 'Visible on pages',
					'_wc_billing/checkout-fieldkit/emails_only' => 'Visible in emails',
				),
			)
		);

		ob_start();
		WC()->mailer()->additional_address_fields( 'billing', $order, false, true );
		$email = ob_get_clean();

		$this->assertStringContainsString( 'Emails only: Visible in emails', $email );
		$this->assertStringNotContainsString( 'Visible on pages', $email );
	}

	public function test_the_admin_order_screen_hides_fields_not_meant_for_order_pages() {
		$this->save_address_fields(
			array(
				array(
					'name'             => 'emails_only',
					'type'             => 'text',
					'label'            => 'Emails only',
					'showOnOrderPages' => false,
				),
				array(
					'name'  => 'everywhere',
					'type'  => 'text',
					'label' => 'Everywhere',
				),
			)
		);
		$this->service->register_fields();
		Package::container()->get( CheckoutFieldsAdmin::class )->init(); // Only added on admin screens.
		$order = $this->create_order(
			array(
				'meta' => array(
					'_wc_billing/checkout-fieldkit/emails_only'  => 'b',
					'_wc_shipping/checkout-fieldkit/emails_only' => 'b',
					'_wc_billing/checkout-fieldkit/everywhere'   => 'c',
					'_wc_shipping/checkout-fieldkit/everywhere'  => 'c',
				),
			)
		);

		foreach ( array( 'billing', 'shipping' ) as $group ) {
			$fields = apply_filters( "woocommerce_admin_{$group}_fields", array( 'state' => array( 'label' => 'State' ) ), $order, 'edit' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's hook.
			$ids    = array_column( $fields, 'id' );

			$this->assertContains( "_wc_{$group}/checkout-fieldkit/everywhere", $ids, $group );
			$this->assertNotContains( "_wc_{$group}/checkout-fieldkit/emails_only", $ids, $group );
			$this->assertArrayHasKey( 'state', $fields, 'Other fields stay.' );
		}
	}

	public function test_a_store_api_checkout_stores_the_value_on_the_order_and_the_customer_address() {
		$this->register_one(
			array(
				'name'     => 'vat_number',
				'type'     => 'text',
				'label'    => 'VAT number',
				'required' => true,
			)
		);

		$user_id = $this->log_in_as( 'customer' );

		$response = $this->store_api_checkout( $this->billing_address( array( 'checkout-fieldkit/vat_number' => 'GB123456789' ) ) );

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$order = wc_get_order( $response->get_data()['order_id'] );
		$this->assertSame( 'GB123456789', $order->get_meta( '_wc_billing/checkout-fieldkit/vat_number' ) );
		$this->assertSame( 'GB123456789', ( (array) $response->get_data()['billing_address'] )['checkout-fieldkit/vat_number'] );
		$this->assertSame( $user_id, $order->get_customer_id() );
		$this->assertSame( 'GB123456789', ( new WC_Customer( $user_id ) )->get_meta( '_wc_billing/checkout-fieldkit/vat_number' ), 'Also saved with the customer\'s address.' );
	}

	public function test_a_store_api_checkout_refuses_a_missing_required_value() {
		$this->register_one(
			array(
				'name'     => 'vat_number',
				'type'     => 'text',
				'label'    => 'VAT number',
				'required' => true,
			)
		);

		$response = $this->store_api_checkout( $this->billing_address( array( 'checkout-fieldkit/vat_number' => '' ) ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertStringContainsString( 'VAT number is required', wp_json_encode( $response->get_data() ) );
	}

	/**
	 * @dataProvider data_store_api_invalid
	 *
	 * @param array<string, mixed> $field   Custom field.
	 * @param string               $value   Invalid value.
	 * @param string               $message Expected part of the error message.
	 */
	public function test_a_store_api_checkout_refuses_an_invalid_value( $field, $value, $message ) {
		$this->register_one( $field );

		$response = $this->store_api_checkout( $this->billing_address( array( 'checkout-fieldkit/' . $field['name'] => $value ) ) );

		$this->assertSame( 400, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertStringContainsString( $field['label'] . ' ' . $message, wp_json_encode( $response->get_data() ) );
		$this->assertSame( 0, count( wc_get_orders( array( 'status' => array( 'processing', 'completed' ) ) ) ), 'No order was placed.' );
	}

	public function data_store_api_invalid() {
		return array(
			'bad email'                => array(
				array(
					'name'  => 'invoice_email',
					'type'  => 'email',
					'label' => 'Invoice email',
				),
				'not-an-email',
				'is not a valid email address',
			),
			'bad phone'                => array(
				array(
					'name'       => 'gate_phone',
					'type'       => 'text',
					'label'      => 'Gate phone',
					'validation' => array( 'phone' ),
				),
				'call me maybe',
				'is not a valid phone number',
			),
			'postcode for the country' => array(
				array(
					'name'       => 'alt_postcode',
					'type'       => 'text',
					'label'      => 'Second postcode',
					'validation' => array( 'postcode' ),
				),
				'12345',
				'is not a valid postcode',
			),
		);
	}

	public function test_a_store_api_checkout_refuses_a_value_that_is_not_an_option() {
		$this->register_one(
			array(
				'name'    => 'slot',
				'type'    => 'select',
				'label'   => 'Slot',
				'options' => array(
					array(
						'value' => 'am',
						'label' => 'Morning',
					),
				),
			)
		);

		$response = $this->store_api_checkout( $this->billing_address( array( 'checkout-fieldkit/slot' => 'midnight' ) ) );

		$this->assertSame( 400, $response->get_status() );
	}
}
