<?php
/**
 * Tests for detecting the checkout type the store uses.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Environment;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Environment\ActiveCheckoutType;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Spec §7, last paragraph: `block` when the Checkout page contains the
 * woocommerce/checkout block, `classic` when it contains the
 * [woocommerce_checkout] shortcode or the classic shortcode block, else `unknown`.
 */
class ActiveCheckoutTypeTest extends TestCase {

	/**
	 * Makes a page with this content the store's Checkout page.
	 *
	 * @param string $content Page content.
	 */
	private function use_checkout_page( $content ) {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);
		update_option( 'woocommerce_checkout_page_id', $page_id );
	}

	/**
	 * @dataProvider data_pages
	 *
	 * @param string $content  Page content.
	 * @param string $expected Detected type.
	 */
	public function test_detects_the_checkout_type_from_the_checkout_page( $content, $expected ) {
		$this->use_checkout_page( $content );

		$this->assertSame( $expected, ( new ActiveCheckoutType() )->get() );
	}

	public function data_pages() {
		return array(
			'checkout block'           => array( "<!-- wp:woocommerce/checkout -->\n<div class=\"wp-block-woocommerce-checkout\"></div>\n<!-- /wp:woocommerce/checkout -->", 'block' ),
			'checkout block in group'  => array( '<!-- wp:group --><div><!-- wp:woocommerce/checkout {"align":"wide"} --><div></div><!-- /wp:woocommerce/checkout --></div><!-- /wp:group -->', 'block' ),
			'shortcode'                => array( '[woocommerce_checkout]', 'classic' ),
			'shortcode in a paragraph' => array( '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->', 'classic' ),
			'classic shortcode block'  => array( '<!-- wp:woocommerce/classic-shortcode {"shortcode":"checkout"} /-->', 'classic' ),
			'classic cart block'       => array( '<!-- wp:woocommerce/classic-shortcode {"shortcode":"cart"} /-->', 'unknown' ),
			'cart block'               => array( '<!-- wp:woocommerce/cart --><div></div><!-- /wp:woocommerce/cart -->', 'unknown' ),
			'plain text'               => array( 'Checkout', 'unknown' ),
		);
	}

	public function test_unknown_without_a_checkout_page() {
		update_option( 'woocommerce_checkout_page_id', 0 );

		$this->assertSame( 'unknown', ( new ActiveCheckoutType() )->get() );
	}

	public function test_unknown_when_the_checkout_page_is_gone() {
		update_option( 'woocommerce_checkout_page_id', 999999 );

		$this->assertSame( 'unknown', ( new ActiveCheckoutType() )->get() );
	}

	public function test_is_block_and_is_classic() {
		$this->use_checkout_page( '<!-- wp:woocommerce/checkout --><div></div><!-- /wp:woocommerce/checkout -->' );
		$detector = new ActiveCheckoutType();
		$this->assertTrue( $detector->is_block() );
		$this->assertFalse( $detector->is_classic() );

		$this->use_checkout_page( '[woocommerce_checkout]' );
		$this->assertFalse( $detector->is_block() );
		$this->assertTrue( $detector->is_classic() );
	}

	public function test_follows_changes_to_the_page_within_a_request() {
		$detector = new ActiveCheckoutType();
		$this->use_checkout_page( '[woocommerce_checkout]' );
		$this->assertSame( 'classic', $detector->get() );

		wp_update_post(
			array(
				'ID'           => (int) get_option( 'woocommerce_checkout_page_id' ),
				'post_content' => '<!-- wp:woocommerce/checkout --><div></div><!-- /wp:woocommerce/checkout -->',
			)
		);

		$this->assertSame( 'block', $detector->get() );
	}

	public function test_the_domain_shares_one_instance() {
		$domain = new Domain();

		$this->assertInstanceOf( ActiveCheckoutType::class, $domain->active_checkout_type() );
		$this->assertSame( $domain->active_checkout_type(), $domain->active_checkout_type() );
	}
}
