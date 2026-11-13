<?php
/**
 * Tests for the test helpers themselves: orders and order storage switching.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration;

use Automattic\WooCommerce\Utilities\OrderUtil;
use CheckoutFieldKit\Tests\Support\Hpos;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Later tests rely on these helpers putting orders where WooCommerce reads them.
 */
class HarnessTest extends TestCase {

	public function test_each_test_starts_with_the_configured_order_storage() {
		$this->assertSame( CFK_TESTS_HPOS, Hpos::is_enabled() );
	}

	public function test_orders_go_to_the_hpos_tables_when_hpos_is_on() {
		global $wpdb;
		$this->enable_hpos();

		$order = $this->create_order();

		$this->assertTrue( OrderUtil::custom_orders_table_usage_is_enabled() );
		$this->assertSame(
			(string) $order->get_id(),
			$wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id = %d', OrderUtil::get_table_for_orders(), $order->get_id() ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		);
	}

	public function test_orders_are_posts_when_hpos_is_off() {
		$this->disable_hpos();

		$order = $this->create_order();

		$this->assertFalse( OrderUtil::custom_orders_table_usage_is_enabled() );
		$this->assertSame( 'shop_order', get_post_type( $order->get_id() ) );
	}

	/**
	 * @dataProvider data_order_storage
	 *
	 * @param bool $hpos Whether HPOS is on.
	 */
	public function test_create_order_saves_properties_and_meta( $hpos ) {
		$this->use_hpos( $hpos );

		$order = $this->create_order(
			array(
				'billing_email' => 'grace@example.org',
				'meta'          => array( 'cfk_test_field' => 'hello' ),
			)
		);

		$fresh = wc_get_order( $order->get_id() );
		$this->assertSame( 'grace@example.org', $fresh->get_billing_email() );
		$this->assertSame( 'hello', $fresh->get_meta( 'cfk_test_field' ) );
		$this->assertCount( 1, $fresh->get_items() );
		$this->assertSame( '10.00', $fresh->get_total() );
	}
}
