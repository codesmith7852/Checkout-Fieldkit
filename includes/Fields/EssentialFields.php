<?php
/**
 * Built-in fields WooCommerce depends on.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * The essential (critical) fields of each checkout type (spec §2.3, ADR 0003).
 * Turning one off, or making it optional, needs an explicit confirmation in
 * the admin that says what stops working.
 *
 * The Block checkout's email field is not listed: it is locked on and required.
 */
final class EssentialFields {

	/**
	 * Essential fields of a checkout type with what stops working without
	 * them, in display order.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return array<string, string> Consequence sentence keyed by field name.
	 */
	public function for_checkout( string $checkout_type ): array {
		$address = array(
			'country'   => __( 'WooCommerce uses the country to work out taxes, shipping methods and which other address fields to ask for. Without it these may be wrong or missing.', 'checkout-fieldkit' ),
			'address_1' => __( 'Orders will have no street address, so there is nowhere to send them.', 'checkout-fieldkit' ),
			'city'      => __( 'Orders will have no town or city, so addresses will be incomplete.', 'checkout-fieldkit' ),
			'state'     => __( 'Taxes and shipping rates that depend on the state or county may be calculated wrongly.', 'checkout-fieldkit' ),
			'postcode'  => __( 'Shipping zones and tax rates that depend on the postcode will no longer match, and addresses will be incomplete.', 'checkout-fieldkit' ),
		);

		if ( CheckoutType::BLOCK === $checkout_type ) {
			return $address;
		}

		if ( CheckoutType::CLASSIC !== $checkout_type ) {
			return array();
		}

		$result = array(
			'billing_email' => __( 'Customers will not get order confirmation or other order emails, and you will have no email address to contact them.', 'checkout-fieldkit' ),
		);
		foreach ( array( 'billing_', 'shipping_' ) as $prefix ) {
			foreach ( $address as $key => $consequence ) {
				$result[ $prefix . $key ] = $consequence;
			}
		}

		return $result;
	}

	/**
	 * Names of the essential fields of a checkout type, in display order.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return string[]
	 */
	public function names( string $checkout_type ): array {
		return array_keys( $this->for_checkout( $checkout_type ) );
	}

	/**
	 * Whether a field is essential.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param string $name          Field name.
	 * @return bool
	 */
	public function is_essential( string $checkout_type, string $name ): bool {
		return array_key_exists( $name, $this->for_checkout( $checkout_type ) );
	}

	/**
	 * What stops working without a field.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param string $name          Field name.
	 * @return string Empty when the field is not essential.
	 */
	public function consequence( string $checkout_type, string $name ): string {
		$all = $this->for_checkout( $checkout_type );

		return isset( $all[ $name ] ) ? $all[ $name ] : '';
	}

	/**
	 * The essential fields of both checkout types as lists, ready to serialise
	 * for the admin: {classic: [{name, consequence}, ...], block: [...]}.
	 *
	 * @return array<string, array<int, array{name: string, consequence: string}>>
	 */
	public function describe(): array {
		$result = array();
		foreach ( CheckoutType::all() as $checkout_type ) {
			$result[ $checkout_type ] = array();
			foreach ( $this->for_checkout( $checkout_type ) as $name => $consequence ) {
				$result[ $checkout_type ][] = array(
					'name'        => $name,
					'consequence' => $consequence,
				);
			}
		}

		return $result;
	}
}
