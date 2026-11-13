<?php
/**
 * Base class for Checkout FieldKit tests.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests;

use CheckoutFieldKit\Tests\Support\CreatesOrders;
use CheckoutFieldKit\Tests\Support\Hpos;
use WP_UnitTestCase;

/**
 * Runs inside a WordPress test site with WooCommerce installed and this plugin loaded.
 *
 * Every test runs in a database transaction that is rolled back afterwards, and
 * starts with the order storage chosen by CFK_TEST_HPOS (default: HPOS on).
 */
abstract class TestCase extends WP_UnitTestCase {

	use CreatesOrders;

	/**
	 * Resets the order storage to the suite default after each test.
	 */
	public function tear_down() {
		Hpos::set( CFK_TESTS_HPOS );
		parent::tear_down();
	}

	/**
	 * Stores orders in the HPOS tables for the rest of this test.
	 */
	protected function enable_hpos() {
		Hpos::set( true );
	}

	/**
	 * Stores orders as posts for the rest of this test.
	 */
	protected function disable_hpos() {
		Hpos::set( false );
	}

	/**
	 * Data provider that runs a test once with HPOS on and once with it off.
	 * The test receives a bool; call $this->use_hpos( $enabled ) first.
	 *
	 * @return array<string, array{bool}>
	 */
	public function data_order_storage() {
		return array(
			'HPOS on'  => array( true ),
			'HPOS off' => array( false ),
		);
	}

	/**
	 * Switches order storage to the given mode for the rest of this test.
	 *
	 * @param bool $enabled True for HPOS, false for posts.
	 */
	protected function use_hpos( $enabled ) {
		Hpos::set( $enabled );
	}

	/**
	 * Creates a user with the given role and makes them the current user.
	 *
	 * @param string $role Role name, for example 'administrator' or 'shop_manager'.
	 * @return int User ID.
	 */
	protected function log_in_as( $role ) {
		$user_id = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user_id );

		return $user_id;
	}
}
