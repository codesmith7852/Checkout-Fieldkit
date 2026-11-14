<?php
/**
 * Tests for loading the classic checkout script and styles.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Classic;

// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Each test starts with empty script and style queues.

use CheckoutFieldKit\Classic\CheckoutFields;
use CheckoutFieldKit\Plugin;
use WP_Scripts;
use WP_Styles;

/**
 * The script and styles load on the checkout and My Account's address form
 * only, with the data the script needs. They need `npm run build`.
 */
class AssetsTest extends ClassicTestCase {

	public function set_up() {
		parent::set_up();
		if ( ! is_readable( dirname( __DIR__, 4 ) . '/build/classic-checkout.asset.php' ) ) {
			$this->markTestSkipped( 'Run npm run build first.' );
		}
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
	}

	public function tear_down() {
		$GLOBALS['wp_scripts'] = new WP_Scripts();
		$GLOBALS['wp_styles']  = new WP_Styles();
		parent::tear_down();
	}

	/**
	 * Creates a page with content and makes it a WooCommerce page.
	 *
	 * @param string $option  WooCommerce page option.
	 * @param string $content Page content.
	 * @return int Page ID.
	 */
	private function woocommerce_page( $option, $content ) {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => $content,
			)
		);
		update_option( $option, $page_id );

		return $page_id;
	}

	/**
	 * Runs the asset loader.
	 */
	private function enqueue() {
		Plugin::instance()->get( CheckoutFields::class )->enqueue_assets();
	}

	public function test_nothing_loads_on_other_pages() {
		$this->go_to( get_permalink( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );

		$this->enqueue();

		$this->assertFalse( wp_script_is( CheckoutFields::HANDLE, 'enqueued' ) );
		$this->assertFalse( wp_style_is( CheckoutFields::HANDLE, 'enqueued' ) );
	}

	public function test_the_checkout_gets_the_script_its_data_and_the_styles() {
		// WooCommerce caches whether this request is its checkout page for the
		// whole run, so the test says so through WooCommerce's own filter.
		add_filter( 'woocommerce_is_checkout', '__return_true' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- WooCommerce's hook.
		$this->go_to( get_permalink( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );

		$this->enqueue();
		remove_filter( 'woocommerce_is_checkout', '__return_true' );

		$this->assertTrue( wp_script_is( CheckoutFields::HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_style_is( CheckoutFields::HANDLE, 'enqueued' ) );
		$this->assertStringContainsString( 'window.checkoutFieldkitClassic = {"localeOverrides"', implode( '', wp_scripts()->get_data( CheckoutFields::HANDLE, 'before' ) ) );
		$this->assertContains( 'jquery', wp_scripts()->registered[ CheckoutFields::HANDLE ]->deps );
	}

	public function test_my_account_loads_it_on_the_address_form_only() {
		$page_id = $this->woocommerce_page( 'woocommerce_myaccount_page_id', '[woocommerce_my_account]' );
		$this->log_in_as( 'customer' );

		$this->go_to( get_permalink( $page_id ) );
		$this->enqueue();
		$this->assertFalse( wp_script_is( CheckoutFields::HANDLE, 'enqueued' ), 'Not on the dashboard.' );

		$this->go_to( add_query_arg( 'edit-address', 'billing', get_permalink( $page_id ) ) );
		$this->enqueue();
		$this->assertTrue( wp_script_is( CheckoutFields::HANDLE, 'enqueued' ) );
	}

	public function test_my_account_on_a_block_store_gets_nothing() {
		$this->use_checkout_page( '<!-- wp:woocommerce/checkout /-->' );
		$page_id = $this->woocommerce_page( 'woocommerce_myaccount_page_id', '[woocommerce_my_account]' );
		$this->log_in_as( 'customer' );

		$this->go_to( add_query_arg( 'edit-address', 'billing', get_permalink( $page_id ) ) );
		$this->enqueue();

		$this->assertFalse( wp_script_is( CheckoutFields::HANDLE, 'enqueued' ) );
	}
}
