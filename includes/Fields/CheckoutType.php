<?php
/**
 * The two checkout types.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Names of WooCommerce's two checkout implementations. Every field set,
 * section list and type list belongs to one of them (ADR 0002).
 */
final class CheckoutType {

	/**
	 * The checkout rendered by the [woocommerce_checkout] shortcode.
	 */
	public const CLASSIC = 'classic';

	/**
	 * The checkout rendered by WooCommerce's Checkout block.
	 */
	public const BLOCK = 'block';

	/**
	 * Both checkout types, in the order the admin lists them.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::CLASSIC, self::BLOCK );
	}

	/**
	 * Whether a string names a checkout type.
	 *
	 * @param mixed $checkout_type Value to check.
	 * @return bool
	 */
	public static function is_valid( $checkout_type ): bool {
		return in_array( $checkout_type, self::all(), true );
	}
}
