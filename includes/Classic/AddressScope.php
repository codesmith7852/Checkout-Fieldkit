<?php
/**
 * Where the classic field set's billing and shipping sections apply.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Classic;

use CheckoutFieldKit\Environment\ActiveCheckoutType;

defined( 'ABSPATH' ) || exit;

/**
 * The Billing and Shipping sections of the classic field set shape
 * WooCommerce's address fields, which the Classic checkout and My Account →
 * Addresses share. On a store whose Checkout page uses the Block checkout,
 * My Account is left to WooCommerce (and to the Block field set's address
 * changes), so the classic sections apply only when the active checkout type
 * is classic or unknown.
 */
final class AddressScope {

	/**
	 * The active checkout type.
	 *
	 * @var ActiveCheckoutType
	 */
	private $active;

	/**
	 * Constructor.
	 *
	 * @param ActiveCheckoutType $active The active checkout type.
	 */
	public function __construct( ActiveCheckoutType $active ) {
		$this->active = $active;
	}

	/**
	 * Whether the classic Billing and Shipping sections apply.
	 *
	 * @return bool
	 */
	public function applies(): bool {
		return ! $this->active->is_block();
	}
}
