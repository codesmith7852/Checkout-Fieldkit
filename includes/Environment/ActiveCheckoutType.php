<?php
/**
 * Which checkout type the store's Checkout page uses.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Environment;

use CheckoutFieldKit\Fields\CheckoutType;

defined( 'ABSPATH' ) || exit;

/**
 * Detects the active checkout type (spec §7, last paragraph) from the page set
 * as WooCommerce's Checkout page:
 *
 * - `block` when it contains the woocommerce/checkout block;
 * - `classic` when it contains the [woocommerce_checkout] shortcode or the
 *   classic shortcode block set to the checkout;
 * - `unknown` otherwise, including when no Checkout page is set.
 *
 * Read on every call (WordPress caches the page), so a page changed earlier in
 * the same request counts.
 *
 *     $type = Plugin::instance()->domain()->active_checkout_type()->get();
 */
final class ActiveCheckoutType {

	/**
	 * Neither checkout type could be found on the Checkout page.
	 */
	public const UNKNOWN = 'unknown';

	/**
	 * The active checkout type.
	 *
	 * @return string CheckoutType::BLOCK, CheckoutType::CLASSIC or self::UNKNOWN.
	 */
	public function get(): string {
		$page_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'checkout' ) : 0;
		$page    = $page_id > 0 ? get_post( $page_id ) : null;
		if ( ! $page instanceof \WP_Post ) {
			return self::UNKNOWN;
		}

		$content = (string) $page->post_content;

		if ( has_block( 'woocommerce/checkout', $content ) ) {
			return CheckoutType::BLOCK;
		}

		if ( preg_match( '/' . get_shortcode_regex( array( 'woocommerce_checkout' ) ) . '/', $content ) || $this->has_classic_checkout_block( parse_blocks( $content ) ) ) {
			return CheckoutType::CLASSIC;
		}

		return self::UNKNOWN;
	}

	/**
	 * Whether the store uses the Block checkout.
	 *
	 * @return bool
	 */
	public function is_block(): bool {
		return CheckoutType::BLOCK === $this->get();
	}

	/**
	 * Whether the store uses the Classic checkout.
	 *
	 * @return bool
	 */
	public function is_classic(): bool {
		return CheckoutType::CLASSIC === $this->get();
	}

	/**
	 * Whether parsed blocks include WooCommerce's classic shortcode block
	 * showing the checkout (its default is the cart).
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @return bool
	 */
	private function has_classic_checkout_block( array $blocks ): bool {
		foreach ( $blocks as $block ) {
			if ( 'woocommerce/classic-shortcode' === $block['blockName'] && isset( $block['attrs']['shortcode'] ) && 'checkout' === $block['attrs']['shortcode'] ) {
				return true;
			}

			if ( ! empty( $block['innerBlocks'] ) && $this->has_classic_checkout_block( $block['innerBlocks'] ) ) {
				return true;
			}
		}

		return false;
	}
}
