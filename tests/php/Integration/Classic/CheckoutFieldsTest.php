<?php
/**
 * Tests for applying the classic field set to WooCommerce's checkout fields.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Classic;

use CheckoutFieldKit\Classic\CheckoutFields;
use CheckoutFieldKit\Fields\DefaultFields;
use CheckoutFieldKit\Plugin;
use WC_Checkout;
use WC_Shortcode_My_Account;

/**
 * Spec §4: late filters on woocommerce_billing_fields, woocommerce_shipping_fields
 * and woocommerce_checkout_fields apply the classic field set; built-in fields
 * keep WooCommerce's definition with our settings on top; disabled fields are
 * removed; position becomes priority (index + 1) × 10.
 */
class CheckoutFieldsTest extends ClassicTestCase {

	/**
	 * WooCommerce's billing fields for a country without our filters.
	 *
	 * @param string $country Country code.
	 * @param string $type    'billing_' or 'shipping_'.
	 * @return array
	 */
	private function woocommerce_fields( $country, $type = 'billing_' ) {
		$service = Plugin::instance()->get( CheckoutFields::class );
		remove_filter( 'woocommerce_' . $type . 'fields', array( $service, 'filter_' . $type . 'fields' ), CheckoutFields::PRIORITY );
		WC()->countries->locale = array();
		$fields                 = WC()->countries->get_address_fields( $country, $type );
		add_filter( 'woocommerce_' . $type . 'fields', array( $service, 'filter_' . $type . 'fields' ), CheckoutFields::PRIORITY, 2 );

		return $fields;
	}

	/**
	 * Checkout fields of a fresh checkout object.
	 *
	 * @param string $fieldset 'billing', 'shipping', 'order' or 'account'.
	 * @return array
	 */
	private function checkout_fields( $fieldset ) {
		return ( new WC_Checkout() )->get_checkout_fields( $fieldset );
	}

	public function test_the_service_is_registered_with_late_filters() {
		$service = Plugin::instance()->get( CheckoutFields::class );

		$this->assertInstanceOf( CheckoutFields::class, $service );
		$this->assertSame( CheckoutFields::PRIORITY, has_filter( 'woocommerce_billing_fields', array( $service, 'filter_billing_fields' ) ) );
		$this->assertSame( CheckoutFields::PRIORITY, has_filter( 'woocommerce_shipping_fields', array( $service, 'filter_shipping_fields' ) ) );
		$this->assertSame( CheckoutFields::PRIORITY, has_filter( 'woocommerce_checkout_fields', array( $service, 'filter_checkout_fields' ) ) );
		$this->assertGreaterThanOrEqual( 1000, CheckoutFields::PRIORITY );
	}

	public function test_the_default_field_set_leaves_the_base_country_fields_as_woocommerce_has_them() {
		$this->assertSame( $this->woocommerce_fields( 'GB' ), WC()->countries->get_address_fields( 'GB', 'billing_' ) );
		$this->assertSame( $this->woocommerce_fields( 'GB', 'shipping_' ), WC()->countries->get_address_fields( 'GB', 'shipping_' ) );
	}

	public function test_a_built_in_field_gets_our_settings_on_top_of_woocommerce_definition() {
		$this->change_field(
			'billing',
			'billing_company',
			array(
				'label'       => 'Business',
				'placeholder' => 'Your business name',
				'default'     => 'Acme',
				'required'    => true,
				'validation'  => array( 'number' ),
				'classes'     => array( 'form-row-first', 'my-company' ),
			)
		);

		$field = WC()->countries->get_address_fields( 'GB', 'billing_' )['billing_company'];

		$this->assertSame( 'Business', $field['label'] );
		$this->assertSame( 'Your business name', $field['placeholder'] );
		$this->assertSame( 'Acme', $field['default'] );
		$this->assertTrue( $field['required'] );
		$this->assertSame( array( 'number' ), $field['validate'] );
		$this->assertSame( array( 'form-row-first', 'my-company' ), $field['class'] );
		$this->assertSame( 'section-billing billing organization', $field['autocomplete'], 'WooCommerce keeps its own autocomplete.' );
	}

	public function test_built_in_fields_keep_their_woocommerce_type_and_options() {
		$this->change_field( 'billing', 'billing_country', array( 'label' => 'Where' ) );

		$fields = WC()->countries->get_address_fields( 'GB', 'billing_' );

		$this->assertSame( 'country', $fields['billing_country']['type'] );
		$this->assertSame( 'state', $fields['billing_state']['type'] );
		$this->assertSame( 'billing_country', $fields['billing_state']['country_field'] );
		$this->assertSame( 'Where', $fields['billing_country']['label'] );
	}

	public function test_disabled_fields_are_removed() {
		$this->change_field( 'billing', 'billing_company', array( 'enabled' => false ) );
		$this->change_field( 'shipping', 'shipping_address_2', array( 'enabled' => false ) );
		$this->add_fields( 'billing', array( $this->custom( 'billing_vat', 'text', array( 'enabled' => false ) ) ) );

		$billing  = WC()->countries->get_address_fields( 'GB', 'billing_' );
		$shipping = WC()->countries->get_address_fields( 'GB', 'shipping_' );

		$this->assertArrayNotHasKey( 'billing_company', $billing );
		$this->assertArrayNotHasKey( 'billing_vat', $billing );
		$this->assertArrayNotHasKey( 'shipping_address_2', $shipping );
		$this->assertArrayHasKey( 'billing_first_name', $billing );
		$this->assertArrayHasKey( 'shipping_address_1', $shipping );
	}

	public function test_position_becomes_priority_and_sets_the_order() {
		$this->change_fields(
			function ( &$sections ) {
				$email = null;
				foreach ( $sections['billing'] as $index => $field ) {
					if ( 'billing_email' === $field['name'] ) {
						$email = $field;
						unset( $sections['billing'][ $index ] );
					}
				}
				array_unshift( $sections['billing'], $email );
				$sections['billing'][] = $this->custom( 'billing_vat', 'text' );
			}
		);

		$fields   = WC()->countries->get_address_fields( 'GB', 'billing_' );
		$expected = array();
		foreach ( $this->domain()->field_sets( 'classic' )->get()['sections']['billing'] as $index => $field ) {
			$expected[ $field['name'] ] = ( $index + 1 ) * 10;
		}

		$this->assertSame( 'billing_email', array_key_first( $fields ) );
		$this->assertSame( 'billing_vat', array_key_last( $fields ) );
		$this->assertSame(
			$expected,
			array_map(
				static function ( $field ) {
					return $field['priority'];
				},
				$fields
			)
		);
		$this->assertSame( 10, $fields['billing_email']['priority'] );
		$this->assertSame( 20, $fields['billing_first_name']['priority'] );
	}

	/**
	 * @dataProvider data_classic_types
	 *
	 * @param string $type Field type.
	 */
	public function test_a_custom_field_of_every_type_becomes_a_woocommerce_field( $type ) {
		$name = 'billing_' . str_replace( '-', '_', $type ) . '_extra';
		$this->add_fields( 'billing', array( $this->custom( $name, $type, array( 'required' => ! in_array( $type, array( 'hidden', 'heading', 'paragraph' ), true ) ) ) ) );

		$fields = WC()->countries->get_address_fields( 'GB', 'billing_' );
		$set    = $this->domain()->field_sets( 'classic' )->get()['sections']['billing'];
		$field  = $fields[ $name ];

		$this->assertSame( $type, $field['type'] );
		$this->assertSame( count( $set ) * 10, $field['priority'] );
		$this->assertSame( ucfirst( str_replace( '_', ' ', $name ) ), $field['label'] );
		$this->assertSame( in_array( $type, array( 'hidden', 'heading', 'paragraph' ), true ) ? false : true, $field['required'] );

		if ( in_array( $type, array( 'select', 'multiselect', 'radio', 'checkboxgroup' ), true ) ) {
			$this->assertSame(
				array(
					'apple' => 'Apple',
					'pear'  => 'Pear',
				),
				$field['options']
			);
		} else {
			$this->assertArrayNotHasKey( 'options', $field );
		}

		$implied = array(
			'number' => array( 'number' ),
			'email'  => array( 'email' ),
			'url'    => array( 'url' ),
			'tel'    => array( 'phone' ),
		);
		$this->assertSame( isset( $implied[ $type ] ) ? $implied[ $type ] : array(), isset( $field['validate'] ) ? $field['validate'] : array() );
	}

	public function test_the_classic_custom_types_are_the_twenty_tested_here() {
		$this->assertSame( array_keys( $this->data_classic_types() ), $this->domain()->field_types()->custom_types( 'classic' ) );
	}

	public function test_a_custom_field_carries_its_validation_placeholder_default_and_classes() {
		$this->add_fields(
			'billing',
			array(
				$this->custom(
					'billing_reference',
					'text',
					array(
						'placeholder' => 'ABC-123',
						'default'     => 'ABC-',
						'validation'  => array( 'url', 'number' ),
						'classes'     => array( 'form-row-last' ),
					)
				),
				$this->custom(
					'billing_fruits',
					'multiselect',
					array( 'default' => 'apple,pear' )
				),
			)
		);

		$fields = WC()->countries->get_address_fields( 'GB', 'billing_' );

		$this->assertSame( 'ABC-123', $fields['billing_reference']['placeholder'] );
		$this->assertSame( 'ABC-', $fields['billing_reference']['default'] );
		$this->assertSame( array( 'url', 'number' ), $fields['billing_reference']['validate'] );
		$this->assertSame( array( 'form-row-last' ), $fields['billing_reference']['class'] );
		$this->assertSame( 'apple,pear', $fields['billing_fruits']['default'] );
	}

	public function test_shipping_fields_follow_the_shipping_section() {
		$this->change_field( 'shipping', 'shipping_city', array( 'label' => 'Delivery town' ) );
		$this->add_fields( 'shipping', array( $this->custom( 'shipping_gate_code', 'text' ) ) );

		$fields = WC()->countries->get_address_fields( 'GB', 'shipping_' );

		$this->assertSame( 'Delivery town', $fields['shipping_city']['label'] );
		$this->assertArrayHasKey( 'shipping_gate_code', $fields );
		$this->assertSame( 'Town / City', WC()->countries->get_address_fields( 'GB', 'billing_' )['billing_city']['label'], 'Billing keeps its own settings.' );
	}

	public function test_the_checkout_uses_the_billing_and_shipping_sections() {
		$this->add_fields( 'billing', array( $this->custom( 'billing_vat', 'text' ) ) );
		$this->add_fields( 'shipping', array( $this->custom( 'shipping_gate_code', 'text' ) ) );

		$this->assertArrayHasKey( 'billing_vat', $this->checkout_fields( 'billing' ) );
		$this->assertArrayHasKey( 'shipping_gate_code', $this->checkout_fields( 'shipping' ) );
	}

	public function test_the_additional_section_becomes_the_order_fields() {
		$this->change_field(
			'additional',
			'order_comments',
			array(
				'label'       => 'Delivery notes',
				'placeholder' => 'Leave it by the door',
			)
		);
		$this->add_fields( 'additional', array( $this->custom( 'gift_message', 'textarea' ), $this->custom( 'how_found', 'radio' ) ) );

		$fields = $this->checkout_fields( 'order' );

		$this->assertSame( array( 'order_comments', 'gift_message', 'how_found' ), array_keys( $fields ) );
		$this->assertSame( 'Delivery notes', $fields['order_comments']['label'] );
		$this->assertSame( 'Leave it by the door', $fields['order_comments']['placeholder'] );
		$this->assertSame( 'textarea', $fields['order_comments']['type'] );
		$this->assertSame( 20, $fields['gift_message']['priority'] );
		$this->assertSame( 'radio', $fields['how_found']['type'] );
		$this->assertTrue( apply_filters( 'woocommerce_enable_order_notes_field', true ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WooCommerce's hook.
	}

	public function test_a_disabled_order_notes_field_leaves_the_other_additional_fields() {
		$this->change_field( 'additional', 'order_comments', array( 'enabled' => false ) );
		$this->add_fields( 'additional', array( $this->custom( 'gift_message', 'textarea' ) ) );

		$this->assertSame( array( 'gift_message' ), array_keys( $this->checkout_fields( 'order' ) ) );
	}

	public function test_when_every_additional_field_is_disabled_the_additional_heading_is_hidden() {
		$this->change_field( 'additional', 'order_comments', array( 'enabled' => false ) );
		$this->add_fields( 'additional', array( $this->custom( 'gift_message', 'textarea', array( 'enabled' => false ) ) ) );

		$this->assertFalse( apply_filters( 'woocommerce_enable_order_notes_field', true ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WooCommerce's hook.
		$this->assertSame( array(), $this->checkout_fields( 'order' ) );

		$checkout = new WC_Checkout();
		ob_start();
		wc_get_template( 'checkout/form-shipping.php', array( 'checkout' => $checkout ) );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'Additional information', $html );
	}

	public function test_the_additional_heading_shows_while_an_additional_field_is_enabled() {
		$this->add_fields( 'additional', array( $this->custom( 'gift_message', 'textarea' ) ) );

		$checkout = new WC_Checkout();
		ob_start();
		wc_get_template( 'checkout/form-shipping.php', array( 'checkout' => $checkout ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Additional information', $html );
		$this->assertStringContainsString( 'name="gift_message"', $html );
	}

	public function test_fields_other_plugins_add_are_kept() {
		$add = static function ( $fields ) {
			$fields['billing_loyalty'] = array(
				'label'    => 'Loyalty card',
				'priority' => 35,
			);
			return $fields;
		};
		add_filter( 'woocommerce_billing_fields', $add );
		$add_order = static function ( $fields ) {
			$fields['order']['delivery_slot'] = array(
				'label'    => 'Delivery slot',
				'priority' => 5,
			);
			return $fields;
		};
		add_filter( 'woocommerce_checkout_fields', $add_order );

		$billing = WC()->countries->get_address_fields( 'GB', 'billing_' );
		$order   = $this->checkout_fields( 'order' );

		remove_filter( 'woocommerce_billing_fields', $add );
		remove_filter( 'woocommerce_checkout_fields', $add_order );

		$this->assertSame( 'Loyalty card', $billing['billing_loyalty']['label'] );
		$this->assertArrayHasKey( 'delivery_slot', $order );
		$this->assertArrayHasKey( 'order_comments', $order );
	}

	public function test_built_in_defaults_never_include_our_changes() {
		$this->change_field( 'billing', 'billing_city', array( 'label' => 'Suburb' ) );
		$this->change_field( 'billing', 'billing_company', array( 'enabled' => false ) );

		$defaults = ( new DefaultFields() )->get( 'classic', 'billing_city' );

		$this->assertSame( 'Town / City', $defaults['label'] );
		$this->assertNotNull( ( new DefaultFields() )->get( 'classic', 'billing_company' ) );
	}

	public function test_while_defaults_are_computed_the_filters_return_their_input() {
		$this->change_field( 'billing', 'billing_city', array( 'label' => 'Suburb' ) );
		$service = Plugin::instance()->get( CheckoutFields::class );
		$input   = array( 'billing_city' => array( 'label' => 'Town' ) );

		$seen     = null;
		$computed = static function ( $fields ) use ( $service, $input, &$seen ) {
			if ( DefaultFields::is_computing() && null === $seen ) {
				$seen = array(
					$service->filter_billing_fields( $input, 'GB' ),
					$service->filter_checkout_fields( array( 'order' => array() ) ),
				);
			}
			return $fields;
		};
		add_filter( 'woocommerce_billing_fields', $computed, 5 );
		( new DefaultFields() )->field_set( 'classic' );
		remove_filter( 'woocommerce_billing_fields', $computed, 5 );

		$this->assertSame( array( $input, array( 'order' => array() ) ), $seen );
	}

	public function test_my_account_addresses_use_the_same_fields_pre_filled_from_the_customer() {
		$this->change_field( 'billing', 'billing_city', array( 'label' => 'Suburb' ) );
		$this->add_fields( 'billing', array( $this->custom( 'billing_vat', 'text' ) ) );
		$user_id = $this->log_in_as( 'customer' );
		update_user_meta( $user_id, 'billing_vat', 'GB123456789' );
		update_user_meta( $user_id, 'billing_country', 'GB' );

		ob_start();
		WC_Shortcode_My_Account::edit_address( 'billing' );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Suburb', $html );
		$this->assertMatchesRegularExpression( '/<input[^>]*name="billing_vat"[^>]*value="GB123456789"/', $html );
	}

	public function test_on_a_block_store_my_account_addresses_are_left_to_woocommerce() {
		$this->use_checkout_page( '<!-- wp:woocommerce/checkout /-->' );
		$this->change_field( 'billing', 'billing_city', array( 'label' => 'Suburb' ) );
		$this->add_fields( 'billing', array( $this->custom( 'billing_vat', 'text' ) ) );
		$this->change_field( 'shipping', 'shipping_company', array( 'enabled' => false ) );
		$this->log_in_as( 'customer' );

		$this->assertSame( $this->woocommerce_fields( 'GB' ), WC()->countries->get_address_fields( 'GB', 'billing_' ) );
		$this->assertSame( $this->woocommerce_fields( 'GB', 'shipping_' ), WC()->countries->get_address_fields( 'GB', 'shipping_' ) );

		ob_start();
		WC_Shortcode_My_Account::edit_address( 'billing' );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'Suburb', $html );
		$this->assertStringNotContainsString( 'billing_vat', $html );
	}

	/**
	 * @dataProvider data_classic_or_unknown_checkout_pages
	 *
	 * @param string $content Checkout page content.
	 */
	public function test_on_a_classic_or_unknown_store_my_account_addresses_use_the_classic_set( $content ) {
		$this->use_checkout_page( $content );
		$this->change_field( 'billing', 'billing_city', array( 'label' => 'Suburb' ) );

		$this->assertSame( 'Suburb', WC()->countries->get_address_fields( 'GB', 'billing_' )['billing_city']['label'] );
	}

	public function data_classic_or_unknown_checkout_pages() {
		return array(
			'classic shortcode' => array( '[woocommerce_checkout]' ),
			'unknown'           => array( 'Just text.' ),
		);
	}
}
