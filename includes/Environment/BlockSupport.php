<?php
/**
 * Whether the Block checkout features can run.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Environment;

defined( 'ABSPATH' ) || exit;

/**
 * Block checkout features (spec §5) need WooCommerce 8.9 or newer, where the
 * additional checkout fields API has the locations and hooks they use. On
 * anything older they stay off, and reason() says why, for the admin and the
 * REST context route (`blockSupported`).
 *
 *     $support = Plugin::instance()->domain()->block_support();
 *     if ( ! $support->is_available() ) {
 *         echo esc_html( $support->reason() );
 *     }
 */
final class BlockSupport {

	/**
	 * Lowest WooCommerce version with everything the Block features use.
	 */
	public const MINIMUM_WOOCOMMERCE_VERSION = '8.9';

	/**
	 * Version of the active WooCommerce, or null when it is not active.
	 *
	 * @var string|null
	 */
	private $woocommerce_version;

	/**
	 * Whether woocommerce_register_additional_checkout_field() exists.
	 *
	 * @var bool
	 */
	private $fields_api;

	/**
	 * Constructor.
	 *
	 * @param string|null $woocommerce_version Active WooCommerce version, or null when it is not active.
	 * @param bool        $fields_api          Whether WooCommerce's additional checkout fields API is loaded.
	 */
	public function __construct( ?string $woocommerce_version, bool $fields_api ) {
		$this->woocommerce_version = $woocommerce_version;
		$this->fields_api          = $fields_api;
	}

	/**
	 * Block support of the WooCommerce that is running right now.
	 *
	 * @return BlockSupport
	 */
	public static function from_environment(): BlockSupport {
		return new self(
			defined( 'WC_VERSION' ) ? (string) WC_VERSION : null,
			function_exists( 'woocommerce_register_additional_checkout_field' )
		);
	}

	/**
	 * Whether the Block checkout features can run.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return '' === $this->reason();
	}

	/**
	 * Why the Block checkout features are off, as a sentence for the store
	 * owner. Empty when they are available.
	 *
	 * @return string
	 */
	public function reason(): string {
		if ( null === $this->woocommerce_version ) {
			return __( 'Block checkout fields need WooCommerce. Install and activate WooCommerce to use them.', 'checkout-fieldkit' );
		}

		if ( ! version_compare( $this->woocommerce_version, self::MINIMUM_WOOCOMMERCE_VERSION, '>=' ) ) {
			return sprintf(
				/* translators: 1: minimum WooCommerce version, 2: installed WooCommerce version. */
				__( 'Block checkout fields need WooCommerce %1$s or newer, and this site runs WooCommerce %2$s. Update WooCommerce to use them.', 'checkout-fieldkit' ),
				self::MINIMUM_WOOCOMMERCE_VERSION,
				$this->woocommerce_version
			);
		}

		if ( ! $this->fields_api ) {
			return __( 'Block checkout fields need WooCommerce’s checkout fields API, which is not loaded on this site. Make sure the WooCommerce blocks are not turned off.', 'checkout-fieldkit' );
		}

		return '';
	}
}
