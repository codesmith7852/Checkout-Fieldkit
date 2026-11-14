<?php
/**
 * Tests for saving classic checkout values on the order.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Classic;

use CheckoutFieldKit\Tests\Support\ClassicOrders;
use CheckoutFieldKit\Tests\TestCase;
use WC_Customer;
use WC_Order;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Tests fire WooCommerce's hooks.

/**
 * Spec §4.1: WooCommerce saves billing_/shipping_ fields; we save the rest as
 * order meta <name>, sanitised by type, empty values skipped, "0" kept.
 */
class OrderValueSaverTest extends TestCase {

	use ClassicOrders;

	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_default_country', 'GB' );
		WC()->countries->locale = array();
	}

	public function tear_down() {
		$_POST                  = array();
		WC()->countries->locale = array();
		parent::tear_down();
	}

	/**
	 * Values of every type, as the form posts them.
	 *
	 * @return array
	 */
	private function posted_values() {
		return array(
			'gift_message'  => "Happy birthday,\nlove from <b>Ada</b>",
			'nickname'      => '  Countess  ',
			'contact_email' => 'ada()@example.org',
			'guests'        => '0',
			'website'       => 'https://example.org/a b',
			'alt_phone'     => '+44 20 7946 0000',
			'secret_word'   => 'swordfish',
			'campaign'      => 'autumn',
			'heard_from'    => 'friend',
			'contact_by'    => 'not-an-option',
			'interests'     => array( 'web', 'post', 'nope' ),
			'extras'        => array( 'friend' ),
			'gift_wrap'     => '1',
			'delivery_date' => '2026-12-24',
			'delivery_time' => '09:30',
			'deliver_at'    => '2026-12-24T09:30',
			'start_month'   => '2027-01',
			'start_week'    => '2027-W02',
			'internal_code' => 'X-1',
			'email_only'    => 'E-1',
			'switched_off'  => 'should not be saved',
		);
	}

	/**
	 * @dataProvider data_order_storage
	 *
	 * @param bool $hpos Whether HPOS is on.
	 */
	public function test_saves_additional_fields_as_order_meta_by_type( $hpos ) {
		$this->use_hpos( $hpos );
		$this->save_classic_fields();

		$order = $this->place_classic_order( $this->posted_values() );

		$expected = array(
			'gift_message'  => "Happy birthday,\nlove from Ada",
			'nickname'      => 'Countess',
			'contact_email' => 'ada@example.org',
			'guests'        => '0',
			'website'       => 'https://example.org/a%20b',
			'alt_phone'     => '+44 20 7946 0000',
			'secret_word'   => 'swordfish',
			'campaign'      => 'autumn',
			'heard_from'    => 'friend',
			'interests'     => 'post,web',
			'extras'        => 'friend',
			'gift_wrap'     => '1',
			'delivery_date' => '2026-12-24',
			'delivery_time' => '09:30',
			'deliver_at'    => '2026-12-24T09:30',
			'start_month'   => '2027-01',
			'start_week'    => '2027-W02',
			'internal_code' => 'X-1',
			'email_only'    => 'E-1',
		);
		foreach ( $expected as $key => $value ) {
			$this->assertSame( $value, $order->get_meta( $key ), $key );
			$this->assertCount( 1, $order->get_meta( $key, false ), "$key is stored once." );
		}

		foreach ( array( 'contact_by', 'switched_off', 'notes_heading', 'notes_intro' ) as $key ) {
			$this->assertFalse( $order->meta_exists( $key ), "$key is not stored." );
		}
	}

	/**
	 * @dataProvider data_order_storage
	 *
	 * @param bool $hpos Whether HPOS is on.
	 */
	public function test_empty_values_are_not_stored( $hpos ) {
		$this->use_hpos( $hpos );
		$this->save_classic_fields();

		$order = $this->place_classic_order(
			array(
				'nickname'     => '',
				'gift_message' => '   ',
				'heard_from'   => '',
				'extras'       => array(),
				'billing_vat'  => '',
			)
		);

		foreach ( array( 'nickname', 'gift_message', 'heard_from', 'extras', 'gift_wrap', 'guests', '_billing_vat', '_billing_newsletter', 'billing_vat' ) as $key ) {
			$this->assertFalse( $order->meta_exists( $key ), "$key is not stored." );
		}
	}

	/**
	 * @dataProvider data_order_storage
	 *
	 * @param bool $hpos Whether HPOS is on.
	 */
	public function test_billing_fields_are_left_where_woocommerce_saves_them( $hpos ) {
		$this->use_hpos( $hpos );
		$this->save_classic_fields();
		$customer_id = $this->log_in_as( 'customer' );

		$order = $this->place_classic_order(
			array(
				'billing_vat'        => ' GB 123 ',
				'billing_newsletter' => '1',
				'billing_topics'     => array( 'web', 'friend', 'nope' ),
				'invoice_ref'        => 'INV-9',
			)
		);

		$this->assertSame( 'GB 123', $order->get_meta( '_billing_vat' ) );
		$this->assertSame( '1', $order->get_meta( '_billing_newsletter' ) );
		$this->assertSame( 'friend,web', $order->get_meta( '_billing_topics' ), 'Multi-value fields are a list of option values, also when WooCommerce saves them.' );
		$this->assertCount( 1, $order->get_meta( '_billing_vat', false ) );
		$this->assertFalse( $order->meta_exists( 'billing_vat' ), 'Not saved a second time under <name>.' );
		$this->assertSame( 'INV-9', $order->get_meta( 'invoice_ref' ), 'A billing field without the prefix is saved by us.' );
		$this->assertFalse( $order->meta_exists( '_invoice_ref' ) );

		$customer = new WC_Customer( $customer_id );
		$this->assertSame( 'GB 123', $customer->get_meta( 'billing_vat' ) );
		$this->assertSame( '1', $customer->get_meta( 'billing_newsletter' ) );
		$this->assertSame( 'friend,web', $customer->get_meta( 'billing_topics' ) );
		$this->assertSame( '', $customer->get_meta( 'invoice_ref' ), 'Only WooCommerce-prefixed fields are kept on the customer.' );
	}

	/**
	 * @dataProvider data_order_storage
	 *
	 * @param bool $hpos Whether HPOS is on.
	 */
	public function test_shipping_fields_are_saved_when_shipping_to_a_different_address( $hpos ) {
		$this->use_hpos( $hpos );
		$this->save_classic_fields();

		$order = $this->place_classic_order(
			array(
				'ship_to_different_address' => '1',
				'shipping_first_name'       => 'Charles',
				'shipping_last_name'        => 'Babbage',
				'shipping_address_1'        => '2 Engine Row',
				'shipping_city'             => 'London',
				'shipping_postcode'         => 'SW1A 2AA',
				'shipping_country'          => 'GB',
				'shipping_gate_code'        => '4321',
				'delivery_floor'            => '0',
			)
		);

		$this->assertSame( '4321', $order->get_meta( '_shipping_gate_code' ) );
		$this->assertSame( '0', $order->get_meta( 'delivery_floor' ) );
	}

	/**
	 * @dataProvider data_order_storage
	 *
	 * @param bool $hpos Whether HPOS is on.
	 */
	public function test_shipping_fields_are_skipped_when_not_shipping_elsewhere( $hpos ) {
		$this->use_hpos( $hpos );
		$this->save_classic_fields();

		$order = $this->place_classic_order(
			array(
				'shipping_gate_code' => '4321',
				'delivery_floor'     => '3',
			)
		);

		$this->assertFalse( $order->meta_exists( 'delivery_floor' ) );
		$this->assertFalse( $order->meta_exists( '_shipping_gate_code' ), 'WooCommerce copies the (empty) billing value; empty values are not stored.' );
	}

	public function test_announces_the_saved_values() {
		$this->save_classic_fields();
		$seen = null;
		add_action(
			'checkout_fieldkit_order_values_saved',
			static function ( $order, $values, $checkout_type ) use ( &$seen ) {
				$seen = array(
					'order_id'      => $order instanceof WC_Order ? $order->get_id() : null,
					'saved'         => $order instanceof WC_Order ? $order->get_meta( 'nickname' ) : null,
					'values'        => $values,
					'checkout_type' => $checkout_type,
				);
			},
			10,
			3
		);

		$order = $this->place_classic_order(
			array(
				'nickname'    => 'Countess',
				'billing_vat' => 'GB1',
				'guests'      => '0',
			)
		);

		$this->assertSame( $order->get_id(), $seen['order_id'] );
		$this->assertSame( 'Countess', $seen['saved'], 'Fires once the order is saved.' );
		$this->assertSame( 'classic', $seen['checkout_type'] );
		$this->assertSame(
			array(
				'billing_vat' => 'GB1',
				'nickname'    => 'Countess',
				'guests'      => '0',
			),
			$seen['values']
		);
	}

	public function test_a_sanitize_value_filter_applies_to_every_custom_field() {
		$this->save_classic_fields();
		add_filter(
			'checkout_fieldkit_sanitize_value',
			static function ( $value, $raw, $field ) {
				return 'nickname' === $field['name'] || 'billing_vat' === $field['name'] ? strtoupper( $value ) : $value;
			},
			10,
			3
		);

		$order = $this->place_classic_order(
			array(
				'nickname'    => 'countess',
				'billing_vat' => 'gb1',
			)
		);

		$this->assertSame( 'COUNTESS', $order->get_meta( 'nickname' ) );
		$this->assertSame( 'GB1', $order->get_meta( '_billing_vat' ) );
	}

	public function test_a_field_missing_from_the_form_is_left_alone() {
		$this->save_classic_fields();
		$order = $this->create_order( array( 'meta' => array( 'nickname' => 'Earlier' ) ) );
		do_action( 'woocommerce_checkout_create_order', $order, array( 'ship_to_different_address' => false ) );
		$order->save();

		$this->assertSame( 'Earlier', wc_get_order( $order->get_id() )->get_meta( 'nickname' ) );
	}

	public function test_a_resumed_order_loses_values_the_shopper_cleared() {
		$this->save_classic_fields();
		$order = $this->create_order(
			array(
				'meta' => array(
					'nickname'     => 'Earlier',
					'_billing_vat' => 'GB0',
				),
			)
		);

		do_action(
			'woocommerce_checkout_create_order',
			$order,
			array(
				'ship_to_different_address' => false,
				'nickname'                  => '',
				'billing_vat'               => '',
			)
		);
		$order->save();

		$order = wc_get_order( $order->get_id() );
		$this->assertFalse( $order->meta_exists( 'nickname' ) );
		$this->assertFalse( $order->meta_exists( '_billing_vat' ) );
	}
}
