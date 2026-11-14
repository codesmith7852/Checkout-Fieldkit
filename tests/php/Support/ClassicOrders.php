<?php
/**
 * Places classic checkout orders through WooCommerce in tests.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Support;

use CheckoutFieldKit\Plugin;
use ReflectionProperty;
use WC_Order;

/**
 * A classic field set with custom fields of every type, and a way to place an
 * order with posted values the way WooCommerce's classic checkout does:
 * WC_Checkout::get_posted_data(), process_customer() and create_order().
 *
 * The checkout form itself (rendering the custom fields, adding them to
 * WooCommerce's checkout fields) is not this plugin's saving code, so the
 * helper adds the custom fields to `woocommerce_checkout_fields` itself.
 */
trait ClassicOrders {

	/**
	 * Options used by the choice fields.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	protected function fixture_options() {
		return array(
			array(
				'value' => 'post',
				'label' => 'By post',
			),
			array(
				'value' => 'friend',
				'label' => 'A friend & family',
			),
			array(
				'value' => 'web',
				'label' => 'Web "shop"',
			),
		);
	}

	/**
	 * Custom fields keyed by section, one or more of every classic type.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	protected function fixture_custom_fields() {
		$options = $this->fixture_options();

		return array(
			'billing'    => array(
				array(
					'name'  => 'billing_vat',
					'type'  => 'text',
					'label' => 'VAT number',
				),
				array(
					'name'  => 'billing_newsletter',
					'type'  => 'checkbox',
					'label' => 'Newsletter',
				),
				array(
					'name'    => 'billing_topics',
					'type'    => 'checkboxgroup',
					'label'   => 'Topics',
					'options' => $options,
				),
				array(
					'name'  => 'invoice_ref',
					'type'  => 'text',
					'label' => 'Invoice reference',
				),
			),
			'shipping'   => array(
				array(
					'name'  => 'shipping_gate_code',
					'type'  => 'text',
					'label' => 'Gate code',
				),
				array(
					'name'  => 'delivery_floor',
					'type'  => 'number',
					'label' => 'Floor',
				),
			),
			'additional' => array(
				array(
					'name'  => 'notes_heading',
					'type'  => 'heading',
					'label' => 'More about you',
				),
				array(
					'name'  => 'notes_intro',
					'type'  => 'paragraph',
					'label' => 'Tell us more.',
				),
				array(
					'name'  => 'gift_message',
					'type'  => 'textarea',
					'label' => 'Gift message',
				),
				array(
					'name'  => 'nickname',
					'type'  => 'text',
					'label' => 'Nickname',
				),
				array(
					'name'  => 'contact_email',
					'type'  => 'email',
					'label' => 'Other email',
				),
				array(
					'name'  => 'guests',
					'type'  => 'number',
					'label' => 'Guests',
				),
				array(
					'name'  => 'website',
					'type'  => 'url',
					'label' => 'Website',
				),
				array(
					'name'  => 'alt_phone',
					'type'  => 'tel',
					'label' => 'Other phone',
				),
				array(
					'name'  => 'secret_word',
					'type'  => 'password',
					'label' => 'Secret word',
				),
				array(
					'name'    => 'campaign',
					'type'    => 'hidden',
					'label'   => 'Campaign',
					'default' => 'autumn',
				),
				array(
					'name'    => 'heard_from',
					'type'    => 'select',
					'label'   => 'Heard from',
					'options' => $options,
				),
				array(
					'name'    => 'contact_by',
					'type'    => 'radio',
					'label'   => 'Contact by',
					'options' => $options,
				),
				array(
					'name'    => 'interests',
					'type'    => 'multiselect',
					'label'   => 'Interests',
					'options' => $options,
				),
				array(
					'name'    => 'extras',
					'type'    => 'checkboxgroup',
					'label'   => 'Extras',
					'options' => $options,
				),
				array(
					'name'  => 'gift_wrap',
					'type'  => 'checkbox',
					'label' => 'Gift wrap',
				),
				array(
					'name'  => 'delivery_date',
					'type'  => 'date',
					'label' => 'Delivery date',
				),
				array(
					'name'  => 'delivery_time',
					'type'  => 'time',
					'label' => 'Delivery time',
				),
				array(
					'name'  => 'deliver_at',
					'type'  => 'datetime-local',
					'label' => 'Deliver at',
				),
				array(
					'name'  => 'start_month',
					'type'  => 'month',
					'label' => 'Start month',
				),
				array(
					'name'  => 'start_week',
					'type'  => 'week',
					'label' => 'Start week',
				),
				array(
					'name'         => 'internal_code',
					'type'         => 'text',
					'label'        => 'Internal code',
					'showInEmails' => false,
				),
				array(
					'name'             => 'email_only',
					'type'             => 'text',
					'label'            => 'Email only',
					'showOnOrderPages' => false,
				),
				array(
					'name'    => 'switched_off',
					'type'    => 'text',
					'label'   => 'Switched off',
					'enabled' => false,
				),
			),
		);
	}

	/**
	 * Saves the classic field set: WooCommerce's built-in fields followed by
	 * the given custom fields in each section.
	 *
	 * @param array<string, array<int, array<string, mixed>>>|null $custom Custom fields keyed by section;
	 *                                                                    defaults to fixture_custom_fields().
	 * @return array The saved field set.
	 */
	protected function save_classic_fields( $custom = null ) {
		$repository = Plugin::instance()->domain()->field_sets( 'classic' );
		$set        = $repository->defaults();
		foreach ( null === $custom ? $this->fixture_custom_fields() : $custom as $section => $fields ) {
			$set['sections'][ $section ] = array_merge( $set['sections'][ $section ], $fields );
		}

		$result = $repository->save( $set );
		$this->assertTrue( $result, is_wp_error( $result ) ? wp_json_encode( $result->get_error_data() ) : '' );

		return $repository->get();
	}

	/**
	 * Places a classic checkout order the way WC_Checkout::process_checkout()
	 * does, without payment: reads the posted data, updates the customer when
	 * one is logged in, and creates the order.
	 *
	 * @param array $posted Posted values on top of a complete billing address, payment
	 *                      method and the checkout nonce (as if the form was shown).
	 * @return WC_Order The order, read back fresh from the store.
	 */
	protected function place_classic_order( array $posted = array() ) {
		$this->add_custom_fields_to_checkout_form();
		add_filter( 'woocommerce_cart_needs_shipping_address', '__return_true' );

		wc_load_cart();
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $this->create_product()->get_id() );
		WC()->session->set( 'order_awaiting_payment', 0 );

		$_POST = array_merge( // phpcs:ignore WordPress.Security.NonceVerification.Missing
			array(
				'woocommerce-process-checkout-nonce' => 'form-was-shown',
				'billing_first_name'                 => 'Ada',
				'billing_last_name'                  => 'Lovelace',
				'billing_address_1'                  => '1 Test Street',
				'billing_city'                       => 'London',
				'billing_postcode'                   => 'SW1A 1AA',
				'billing_country'                    => 'GB',
				'billing_email'                      => 'ada@example.org',
				'billing_phone'                      => '01234 567890',
				'payment_method'                     => 'bacs',
			),
			wp_slash( $posted )
		);

		$checkout = WC()->checkout();
		$this->reset_checkout_fields( $checkout );
		$data = $checkout->get_posted_data();

		if ( get_current_user_id() ) {
			$process_customer = new \ReflectionMethod( $checkout, 'process_customer' );
			if ( PHP_VERSION_ID < 80100 ) {
				$process_customer->setAccessible( true );
			}
			$process_customer->invoke( $checkout, $data );
		}

		$order_id = $checkout->create_order( $data );
		$this->assertIsInt( $order_id, is_wp_error( $order_id ) ? $order_id->get_error_message() : '' );

		$_POST = array();
		$this->reset_checkout_fields( $checkout );
		WC()->cart->empty_cart();

		return wc_get_order( $order_id );
	}

	/**
	 * Stands in for the checkout form: adds the enabled custom fields of the
	 * classic field set to WooCommerce's checkout fields, so
	 * WC_Checkout::get_posted_data() picks up their posted values.
	 */
	private function add_custom_fields_to_checkout_form() {
		add_filter(
			'woocommerce_checkout_fields',
			static function ( $fieldsets ) {
				$set = Plugin::instance()->domain()->field_sets( 'classic' )->get();
				$map = array(
					'billing'    => 'billing',
					'shipping'   => 'shipping',
					'additional' => 'order',
				);
				foreach ( $set['sections'] as $section => $fields ) {
					foreach ( $fields as $field ) {
						if ( $field['builtIn'] || ! $field['enabled'] ) {
							continue;
						}
						$fieldsets[ $map[ $section ] ][ $field['name'] ] = array(
							'type'    => $field['type'],
							'label'   => $field['label'],
							'default' => $field['default'],
							'options' => wp_list_pluck( $field['options'], 'label', 'value' ),
						);
					}
				}

				return $fieldsets;
			},
			1000
		);
	}

	/**
	 * Makes WC_Checkout read its fields again, as on a new request.
	 *
	 * @param \WC_Checkout $checkout Checkout.
	 */
	private function reset_checkout_fields( $checkout ) {
		$fields = new ReflectionProperty( $checkout, 'fields' );
		if ( PHP_VERSION_ID < 80100 ) {
			$fields->setAccessible( true );
		}
		$fields->setValue( $checkout, null );
	}
}
