<?php
/**
 * Tests for validating classic checkout values.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Classic;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Runs WooCommerce's hooks.

use CheckoutFieldKit\Classic\CheckoutValidation;
use CheckoutFieldKit\Plugin;
use WC_Customer;
use WP_Error;

/**
 * Spec §4: on woocommerce_after_checkout_validation we check number and url
 * values and that choice values are among the options; only non-empty values;
 * messages name the field by its label; shipping fields are skipped when the
 * shopper is not shipping to a different address.
 */
class CheckoutValidationTest extends ClassicTestCase {

	public function set_up() {
		parent::set_up();
		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( 'Everywhere' );
		$zone->save();
		$zone->add_shipping_method( 'flat_rate' );
		delete_transient( 'wc_shipping_method_count' );
		wc_load_cart();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $this->create_product()->get_id() );
	}

	public function tear_down() {
		WC()->cart->empty_cart();
		wc_clear_notices();
		parent::tear_down();
	}

	/**
	 * Runs WooCommerce's after-validation action on posted data.
	 *
	 * @param array $data Posted data as WC_Checkout::get_posted_data() gives it.
	 * @return WP_Error
	 */
	private function validate( array $data ) {
		$errors = new WP_Error();
		do_action( 'woocommerce_after_checkout_validation', array_merge( array( 'ship_to_different_address' => false ), $data ), $errors );

		return $errors;
	}

	/**
	 * Adds the custom fields the tests post values for.
	 */
	private function add_test_fields() {
		$this->add_fields(
			'billing',
			array(
				$this->custom( 'billing_house_number', 'number', array( 'label' => 'House number' ) ),
				$this->custom( 'billing_website', 'url', array( 'label' => 'Website' ) ),
				$this->custom(
					'billing_reference',
					'text',
					array(
						'label'      => 'Reference',
						'validation' => array( 'number' ),
					)
				),
			)
		);
		$this->add_fields(
			'shipping',
			array( $this->custom( 'shipping_floor', 'number', array( 'label' => 'Floor' ) ) )
		);
		$this->add_fields(
			'additional',
			array(
				$this->custom( 'fruit', 'select', array( 'label' => 'Fruit' ) ),
				$this->custom( 'fruit_radio', 'radio', array( 'label' => 'One fruit' ) ),
				$this->custom( 'fruits', 'multiselect', array( 'label' => 'Fruits' ) ),
				$this->custom( 'fruit_boxes', 'checkboxgroup', array( 'label' => 'Fruit boxes' ) ),
				$this->custom(
					'contact_page',
					'url',
					array(
						'label'   => 'Contact page',
						'enabled' => false,
					)
				),
			)
		);
	}

	public function test_the_service_is_registered() {
		$service = Plugin::instance()->get( CheckoutValidation::class );

		$this->assertInstanceOf( CheckoutValidation::class, $service );
		$this->assertNotFalse( has_action( 'woocommerce_after_checkout_validation', array( $service, 'validate_checkout' ) ) );
	}

	public function test_valid_values_pass() {
		$this->add_test_fields();

		$errors = $this->validate(
			array(
				'billing_house_number' => '-12.5',
				'billing_website'      => 'https://example.com/shop?x=1',
				'billing_reference'    => '42',
				'fruit'                => 'apple',
				'fruit_radio'          => 'pear',
				'fruits'               => 'apple, pear',
				'fruit_boxes'          => array( 'pear' ),
			)
		);

		$this->assertSame( array(), $errors->get_error_messages() );
	}

	public function test_empty_values_are_not_checked() {
		$this->add_test_fields();

		$errors = $this->validate(
			array(
				'billing_house_number' => '',
				'billing_website'      => '',
				'billing_reference'    => '',
				'fruit'                => '',
				'fruit_radio'          => '',
				'fruits'               => '',
				'fruit_boxes'          => '',
			)
		);

		$this->assertSame( array(), $errors->get_error_messages() );
	}

	public function test_a_number_field_needs_a_number() {
		$this->add_test_fields();

		$errors = $this->validate( array( 'billing_house_number' => '12a' ) );

		$this->assertSame( array( 'billing_house_number_validation' ), $errors->get_error_codes() );
		$this->assertSame( '<strong>Billing House number</strong> is not a valid number.', $errors->get_error_message() );
		$this->assertSame( array( 'id' => 'billing_house_number' ), $errors->get_error_data() );
	}

	public function test_number_validation_chosen_for_a_text_field_applies() {
		$this->add_test_fields();

		$errors = $this->validate( array( 'billing_reference' => 'forty-two' ) );

		$this->assertSame( array( 'billing_reference_validation' ), $errors->get_error_codes() );
	}

	public function test_a_url_field_needs_a_web_address() {
		$this->add_test_fields();

		foreach ( array( 'example', 'javascript:alert(1)', 'ftp://example.com', 'https://' ) as $value ) {
			$errors = $this->validate( array( 'billing_website' => $value ) );

			$this->assertSame( '<strong>Billing Website</strong> is not a valid web address.', $errors->get_error_message(), $value );
		}
	}

	public function test_choice_values_must_be_among_the_options() {
		$this->add_test_fields();

		$errors = $this->validate(
			array(
				'fruit'       => 'banana',
				'fruit_radio' => 'kiwi',
				'fruits'      => 'apple, banana',
				'fruit_boxes' => array( 'apple', 'lime' ),
			)
		);

		$this->assertSame( array( 'fruit_validation', 'fruit_radio_validation', 'fruits_validation', 'fruit_boxes_validation' ), $errors->get_error_codes() );
		$this->assertSame( '<strong>Fruit</strong> is not one of the choices offered.', $errors->get_error_message( 'fruit_validation' ) );
	}

	public function test_disabled_fields_are_not_checked() {
		$this->add_test_fields();

		$this->assertSame( array(), $this->validate( array( 'contact_page' => 'nope' ) )->get_error_codes() );
	}

	public function test_shipping_fields_are_skipped_when_not_shipping_to_a_different_address() {
		$this->add_test_fields();

		$errors = $this->validate(
			array(
				'ship_to_different_address' => false,
				'shipping_floor'            => 'top',
			)
		);

		$this->assertSame( array(), $errors->get_error_codes() );
	}

	public function test_shipping_fields_are_checked_when_shipping_to_a_different_address() {
		$this->add_test_fields();

		$errors = $this->validate(
			array(
				'ship_to_different_address' => true,
				'shipping_floor'            => 'top',
			)
		);

		$this->assertSame( array( 'shipping_floor_validation' ), $errors->get_error_codes() );
		$this->assertSame( '<strong>Shipping Floor</strong> is not a valid number.', $errors->get_error_message() );
	}

	public function test_shipping_fields_are_skipped_when_the_cart_needs_no_shipping() {
		$this->add_test_fields();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $this->create_product( array( 'virtual' => true ) )->get_id() );

		$errors = $this->validate(
			array(
				'ship_to_different_address' => true,
				'shipping_floor'            => 'top',
			)
		);

		$this->assertSame( array(), $errors->get_error_codes() );
	}

	public function test_a_built_in_field_with_number_validation_is_checked() {
		$this->change_field( 'billing', 'billing_company', array( 'validation' => array( 'number' ) ) );

		$errors = $this->validate( array( 'billing_company' => 'Acme' ) );

		$this->assertSame( array( 'billing_company_validation' ), $errors->get_error_codes() );
	}

	public function test_woocommerce_reports_a_missing_required_custom_field_by_its_label() {
		$this->add_fields(
			'additional',
			array(
				$this->custom(
					'fruit_boxes',
					'checkboxgroup',
					array(
						'label'    => 'Fruit boxes',
						'required' => true,
					)
				),
			)
		);
		$checkout = new \WC_Checkout();
		$method   = new \ReflectionMethod( $checkout, 'validate_posted_data' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		$data   = array(
			'ship_to_different_address' => false,
			'createaccount'             => 0,
			'fruit_boxes'               => '',
		);
		$errors = new WP_Error();

		$method->invokeArgs( $checkout, array( &$data, &$errors ) );

		$this->assertContains( 'fruit_boxes_required', $errors->get_error_codes() );
		$this->assertStringContainsString( 'Fruit boxes', $errors->get_error_message( 'fruit_boxes_required' ) );
	}

	public function test_my_account_address_values_are_checked_too() {
		$this->add_test_fields();
		$user_id = $this->log_in_as( 'customer' );
		$_POST   = array(
			'billing_house_number' => 'twelve',
			'billing_website'      => 'https://example.com',
		);

		do_action( 'woocommerce_after_save_address_validation', $user_id, 'billing', WC()->countries->get_address_fields( 'GB', 'billing_' ), new WC_Customer( $user_id ) );
		$_POST = array();

		$notices = wc_get_notices( 'error' );
		$this->assertCount( 1, $notices );
		$this->assertSame( '<strong>House number</strong> is not a valid number.', $notices[0]['notice'] );
		$this->assertSame( array( 'id' => 'billing_house_number' ), $notices[0]['data'] );
	}
}
