<?php
/**
 * Checks that WooCommerce is present and new enough.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit;

defined( 'ABSPATH' ) || exit;

/**
 * The conditions the plugin needs before it loads anything.
 */
final class Requirements {

	/**
	 * Lowest WooCommerce version the plugin works with. Keep in sync with the
	 * "WC requires at least" plugin header.
	 */
	public const MINIMUM_WOOCOMMERCE_VERSION = '9.0';

	/**
	 * Version of the active WooCommerce, or null when WooCommerce is not active.
	 *
	 * @var string|null
	 */
	private $woocommerce_version;

	/**
	 * Constructor.
	 *
	 * @param string|null $woocommerce_version Active WooCommerce version, or null when it is not active.
	 */
	public function __construct( ?string $woocommerce_version ) {
		$this->woocommerce_version = $woocommerce_version;
	}

	/**
	 * Requirements for the WooCommerce that is running right now.
	 *
	 * @return Requirements
	 */
	public static function from_environment(): Requirements {
		return new self( defined( 'WC_VERSION' ) ? (string) WC_VERSION : null );
	}

	/**
	 * Whether the plugin can load.
	 *
	 * @return bool
	 */
	public function are_met(): bool {
		return null !== $this->woocommerce_version
			&& version_compare( $this->woocommerce_version, self::MINIMUM_WOOCOMMERCE_VERSION, '>=' );
	}

	/**
	 * What is wrong, as a sentence for the site owner. Empty when the requirements are met.
	 *
	 * @return string
	 */
	public function problem(): string {
		if ( null === $this->woocommerce_version ) {
			return __( 'Checkout FieldKit needs WooCommerce. Install and activate WooCommerce to use it.', 'checkout-fieldkit' );
		}

		if ( ! $this->are_met() ) {
			return sprintf(
				/* translators: 1: minimum WooCommerce version, 2: installed WooCommerce version. */
				__( 'Checkout FieldKit needs WooCommerce %1$s or newer, and this site runs WooCommerce %2$s. Update WooCommerce to use Checkout FieldKit.', 'checkout-fieldkit' ),
				self::MINIMUM_WOOCOMMERCE_VERSION,
				$this->woocommerce_version
			);
		}

		return '';
	}
}
