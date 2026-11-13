<?php
/**
 * Switches WooCommerce order storage between HPOS and posts in tests.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Support;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer;
use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * High-Performance Order Storage on/off switch for tests.
 *
 * Synchronisation between the two stores is kept off, so an order lives in exactly
 * one place: the HPOS tables when on, the posts table when off.
 */
final class Hpos {

	/**
	 * Called once from the bootstrap: creates the HPOS tables (outside any test
	 * transaction, so they are real tables) and sets the starting mode.
	 *
	 * @param bool $enabled Whether tests start with HPOS on.
	 */
	public static function prepare( $enabled ) {
		$synchronizer = wc_get_container()->get( DataSynchronizer::class );
		if ( ! $synchronizer->check_orders_table_exists() ) {
			$synchronizer->create_database_tables();
		}

		// Tests switch storage while orders exist in the other store; that is fine here.
		add_filter( 'wc_allow_changing_orders_storage_while_sync_is_pending', '__return_true' );

		self::set( $enabled );
	}

	/**
	 * Turns HPOS on or off for the rest of the current test.
	 *
	 * @param bool $enabled True for HPOS tables, false for posts.
	 */
	public static function set( $enabled ) {
		update_option( DataSynchronizer::ORDERS_DATA_SYNC_ENABLED_OPTION, 'no' );
		update_option( CustomOrdersTableController::CUSTOM_ORDERS_TABLE_USAGE_ENABLED_OPTION, $enabled ? 'yes' : 'no' );
	}

	/**
	 * Whether orders are currently stored in the HPOS tables.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
