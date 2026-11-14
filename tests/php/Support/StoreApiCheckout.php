<?php
/**
 * Places orders through WooCommerce's Store API in tests.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Support;

use WC_Customer;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Dispatches POST /wc/store/v1/checkout, the request the Block checkout
 * sends when the shopper places an order, with one free virtual product in
 * the cart (so neither payment nor shipping is needed), as the current user.
 */
trait StoreApiCheckout {

	/**
	 * A billing address that passes WooCommerce's own checks.
	 *
	 * @param array<string, mixed> $values Values to add or replace.
	 * @return array<string, mixed>
	 */
	protected function billing_address( array $values = array() ) {
		return array_merge(
			array(
				'first_name' => 'Ada',
				'last_name'  => 'Lovelace',
				'company'    => '',
				'address_1'  => '1 Test Street',
				'address_2'  => '',
				'city'       => 'London',
				'state'      => '',
				'postcode'   => 'SW1A 1AA',
				'country'    => 'GB',
				'email'      => 'ada@example.org',
				'phone'      => '01234 567890',
			),
			$values
		);
	}

	/**
	 * Places an order through the Store API.
	 *
	 * @param array<string, mixed> $billing Billing address, see billing_address().
	 * @param array<string, mixed> $body    Other body parameters to add or replace.
	 * @return WP_REST_Response
	 */
	protected function store_api_checkout( array $billing, array $body = array() ) {
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );

		wc_load_cart();
		WC()->customer = new WC_Customer( get_current_user_id(), true );
		WC()->cart->empty_cart();
		$product = $this->create_product(
			array(
				'regular_price' => '0',
				'virtual'       => true,
			)
		);
		WC()->cart->add_to_cart( $product->get_id() );

		$shipping = $billing;
		unset( $shipping['email'] );

		$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array_merge(
					array(
						'billing_address'  => $billing,
						'shipping_address' => $shipping,
						'payment_method'   => '',
					),
					$body
				)
			)
		);

		return rest_get_server()->dispatch( $request );
	}
}
