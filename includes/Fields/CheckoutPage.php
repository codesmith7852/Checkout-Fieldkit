<?php
/**
 * The store's Checkout page and the checkout type it uses.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Fields;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the page set as WooCommerce's Checkout page (WooCommerce → Settings →
 * Advanced) and works out the active checkout type from its content
 * (spec §7, last paragraph):
 *
 * - 'block' when it contains the woocommerce/checkout block;
 * - 'classic' when it contains the [woocommerce_checkout] shortcode, or
 *   WooCommerce's classic shortcode block set to the checkout;
 * - 'unknown' otherwise, including when no Checkout page is set, it no longer
 *   exists or it is in the trash.
 *
 * Blocks nested inside other blocks (a group, columns) count.
 */
final class CheckoutPage {

	/**
	 * The active checkout type when it cannot be worked out.
	 */
	public const UNKNOWN = 'unknown';

	/**
	 * The checkout type the Checkout page uses.
	 *
	 * @return string CheckoutType::BLOCK, CheckoutType::CLASSIC or self::UNKNOWN.
	 */
	public function active_checkout_type(): string {
		$page = $this->page();
		if ( null === $page ) {
			return self::UNKNOWN;
		}

		$content = (string) $page->post_content;
		$found   = $this->find_blocks( parse_blocks( $content ) );

		if ( $found['checkout'] ) {
			return CheckoutType::BLOCK;
		}

		if ( $found['classic_shortcode'] || preg_match( '/' . get_shortcode_regex( array( 'woocommerce_checkout' ) ) . '/', $content ) ) {
			return CheckoutType::CLASSIC;
		}

		return self::UNKNOWN;
	}

	/**
	 * Address of the Checkout page.
	 *
	 * @return string|null Null when there is no usable Checkout page.
	 */
	public function url(): ?string {
		$page = $this->page();
		if ( null === $page ) {
			return null;
		}

		$url = get_permalink( $page );

		return is_string( $url ) ? $url : null;
	}

	/**
	 * The Checkout page.
	 *
	 * @return WP_Post|null Null when none is set, it does not exist or it is in the trash.
	 */
	private function page(): ?WP_Post {
		$page_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'checkout' ) : 0;
		if ( $page_id <= 0 ) {
			return null;
		}

		$page = get_post( $page_id );
		if ( ! $page instanceof WP_Post || 'trash' === $page->post_status ) {
			return null;
		}

		return $page;
	}

	/**
	 * Looks through blocks, and the blocks inside them, for the checkout blocks.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return array{checkout: bool, classic_shortcode: bool}
	 */
	private function find_blocks( array $blocks ): array {
		$found = array(
			'checkout'          => false,
			'classic_shortcode' => false,
		);

		foreach ( $blocks as $block ) {
			$name = isset( $block['blockName'] ) ? $block['blockName'] : null;

			if ( 'woocommerce/checkout' === $name ) {
				$found['checkout'] = true;
			} elseif ( 'woocommerce/classic-shortcode' === $name && isset( $block['attrs']['shortcode'] ) && 'checkout' === $block['attrs']['shortcode'] ) {
				// The block shows the cart unless its shortcode attribute says checkout.
				$found['classic_shortcode'] = true;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$inner = $this->find_blocks( $block['innerBlocks'] );
				$found = array(
					'checkout'          => $found['checkout'] || $inner['checkout'],
					'classic_shortcode' => $found['classic_shortcode'] || $inner['classic_shortcode'],
				);
			}
		}

		return $found;
	}
}
