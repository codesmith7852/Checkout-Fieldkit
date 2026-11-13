<?php
/**
 * Tests for detecting which checkout type the store's Checkout page uses.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Fields;

use CheckoutFieldKit\Fields\CheckoutPage;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Spec §7, last paragraph: the active checkout type is read from the page set
 * as WooCommerce's Checkout page.
 */
class CheckoutPageTest extends TestCase {

	/**
	 * Uses a page with the given content as WooCommerce's Checkout page.
	 *
	 * @param string $content Post content.
	 * @param string $status  Post status.
	 * @return int Page ID.
	 */
	private function use_checkout_page( $content, $status = 'publish' ) {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'Checkout',
				'post_name'    => 'checkout',
				'post_status'  => $status,
				'post_content' => $content,
			)
		);
		update_option( 'woocommerce_checkout_page_id', $page_id );

		return $page_id;
	}

	/**
	 * @dataProvider data_page_contents
	 *
	 * @param string $content  Content of the Checkout page.
	 * @param string $expected Expected checkout type.
	 */
	public function test_detects_the_checkout_type_of_the_checkout_page( $content, $expected ) {
		$this->use_checkout_page( $content );

		$this->assertSame( $expected, ( new CheckoutPage() )->active_checkout_type() );
	}

	public function data_page_contents() {
		$checkout_block = '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout wc-block-checkout is-loading">'
			. '<!-- wp:woocommerce/checkout-fields-block --><div class="wp-block-woocommerce-checkout-fields-block"></div><!-- /wp:woocommerce/checkout-fields-block -->'
			. '</div><!-- /wp:woocommerce/checkout -->';

		return array(
			'Checkout block'                        => array( $checkout_block, 'block' ),
			'Checkout block, self-closing'          => array( '<!-- wp:woocommerce/checkout /-->', 'block' ),
			'Checkout block inside a group'         => array( '<!-- wp:group --><div class="wp-block-group">' . $checkout_block . '</div><!-- /wp:group -->', 'block' ),
			'Shortcode in classic content'          => array( '[woocommerce_checkout]', 'classic' ),
			'Shortcode in a shortcode block'        => array( "<!-- wp:shortcode -->\n[woocommerce_checkout]\n<!-- /wp:shortcode -->", 'classic' ),
			'Classic shortcode block for checkout'  => array( '<!-- wp:woocommerce/classic-shortcode {"shortcode":"checkout"} /-->', 'classic' ),
			'Classic shortcode block for cart'      => array( '<!-- wp:woocommerce/classic-shortcode {"shortcode":"cart"} /-->', 'unknown' ),
			'Classic shortcode block without attrs' => array( '<!-- wp:woocommerce/classic-shortcode /-->', 'unknown' ),
			'Only an inner checkout block'          => array( '<!-- wp:woocommerce/checkout-fields-block /-->', 'unknown' ),
			'Cart block'                            => array( '<!-- wp:woocommerce/cart /-->', 'unknown' ),
			'Cart shortcode'                        => array( '[woocommerce_cart]', 'unknown' ),
			'Another shortcode with a similar name' => array( '[woocommerce_checkout_extra]', 'unknown' ),
			'Plain text'                            => array( 'Checkout is coming soon.', 'unknown' ),
			'Empty page'                            => array( '', 'unknown' ),
		);
	}

	public function test_the_checkout_type_is_unknown_without_a_checkout_page() {
		update_option( 'woocommerce_checkout_page_id', 0 );

		$page = new CheckoutPage();

		$this->assertSame( 'unknown', $page->active_checkout_type() );
		$this->assertNull( $page->url() );
	}

	public function test_the_checkout_type_is_unknown_when_the_checkout_page_no_longer_exists() {
		update_option( 'woocommerce_checkout_page_id', PHP_INT_MAX );

		$page = new CheckoutPage();

		$this->assertSame( 'unknown', $page->active_checkout_type() );
		$this->assertNull( $page->url() );
	}

	public function test_the_checkout_type_is_unknown_when_the_checkout_page_is_in_the_trash() {
		$this->use_checkout_page( '<!-- wp:woocommerce/checkout /-->', 'trash' );

		$page = new CheckoutPage();

		$this->assertSame( 'unknown', $page->active_checkout_type() );
		$this->assertNull( $page->url() );
	}

	public function test_url_is_the_permalink_of_the_checkout_page() {
		$page_id = $this->use_checkout_page( '[woocommerce_checkout]' );

		$this->assertSame( get_permalink( $page_id ), ( new CheckoutPage() )->url() );
	}
}
