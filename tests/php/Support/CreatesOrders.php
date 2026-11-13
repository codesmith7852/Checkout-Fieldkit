<?php
/**
 * Helpers that create WooCommerce products and orders in tests.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Support;

use WC_Order;
use WC_Product_Simple;

/**
 * Creates products and orders through WooCommerce's CRUD objects, so they land
 * in whichever order storage (HPOS or posts) is active.
 */
trait CreatesOrders {

	/**
	 * Creates and saves a simple product.
	 *
	 * @param array $props Product properties, for example array( 'regular_price' => '5' ).
	 * @return WC_Product_Simple
	 */
	protected function create_product( array $props = array() ) {
		$product = new WC_Product_Simple();
		$product->set_props(
			array_merge(
				array(
					'name'          => 'Test product',
					'regular_price' => '10',
					'sku'           => 'cfk-test-' . wp_generate_password( 8, false ),
				),
				$props
			)
		);
		$product->save();

		return $product;
	}

	/**
	 * Creates and saves an order with one product line and a billing/shipping address.
	 *
	 * @param array $props Order properties (any WC_Order setter name without "set_",
	 *                     for example 'billing_email' or 'customer_id'). Two extra keys:
	 *                     'meta' => array of meta key => value saved on the order,
	 *                     'product' => a WC_Product to use instead of a new one.
	 * @return WC_Order The saved order, read back fresh from the store.
	 */
	protected function create_order( array $props = array() ) {
		$meta    = isset( $props['meta'] ) ? (array) $props['meta'] : array();
		$product = isset( $props['product'] ) ? $props['product'] : $this->create_product();
		unset( $props['meta'], $props['product'] );

		$order = new WC_Order();
		$order->set_props(
			array_merge(
				array(
					'status'              => 'processing',
					'billing_first_name'  => 'Ada',
					'billing_last_name'   => 'Lovelace',
					'billing_address_1'   => '1 Test Street',
					'billing_city'        => 'London',
					'billing_postcode'    => 'SW1A 1AA',
					'billing_country'     => 'GB',
					'billing_email'       => 'ada@example.org',
					'billing_phone'       => '01234 567890',
					'shipping_first_name' => 'Ada',
					'shipping_last_name'  => 'Lovelace',
					'shipping_address_1'  => '1 Test Street',
					'shipping_city'       => 'London',
					'shipping_postcode'   => 'SW1A 1AA',
					'shipping_country'    => 'GB',
					'payment_method'      => 'bacs',
				),
				$props
			)
		);
		$order->add_product( $product, 1 );

		foreach ( $meta as $key => $value ) {
			$order->update_meta_data( $key, $value );
		}

		$order->calculate_totals( false );
		$order->save();

		return wc_get_order( $order->get_id() );
	}
}
