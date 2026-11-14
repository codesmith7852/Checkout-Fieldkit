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
		// Each sentence says what WooCommerce uses the field for, then what goes wrong without it.
		// "Without it" covers both cases the admin confirms: turned off, and left empty when optional.
		if ( CheckoutType::BLOCK === $checkout_type ) {
			// One address form serves both billing and delivery on the Block checkout.
			return array(
				'country'   => __( 'WooCommerce uses the country to choose shipping zones and rates, work out tax, and decide which other address fields to ask for. Without it, shoppers may see no shipping options and tax may be wrong.', 'checkout-fieldkit' ),
				'address_1' => __( 'The street address is used for both billing and delivery. Without it, you won\'t know where to send orders, and payment methods that check the address may refuse payments.', 'checkout-fieldkit' ),
				'city'      => __( 'Without the town or city, billing and delivery addresses are incomplete, and couriers may not be able to deliver.', 'checkout-fieldkit' ),
				'state'     => __( 'In many countries shipping zones and tax depend on the state or county. Without it, shoppers may see the wrong shipping options or prices, and tax may be wrong.', 'checkout-fieldkit' ),
				'postcode'  => __( 'Shipping zones, tax rates and couriers rely on the postcode, and many payment methods check it. Without it, shipping prices and tax may be wrong and parcels may not arrive.', 'checkout-fieldkit' ),
			);
		}

		if ( CheckoutType::CLASSIC !== $checkout_type ) {
			return array();
		}

		return array(
			'billing_email'      => __( 'WooCommerce sends order confirmations and receipts to the billing email, and uses it to create customer accounts. Without it, customers won\'t get emails about their orders and you\'ll have no way to reach them.', 'checkout-fieldkit' ),
			'billing_country'    => __( 'WooCommerce uses the billing country to work out tax and to decide which other billing fields to ask for. Without it, tax may be charged wrongly and fields such as State may not appear.', 'checkout-fieldkit' ),
			'billing_address_1'  => __( 'Many payment methods check the billing street address before they approve a payment. Without it, some payments may be refused, and invoices show an incomplete address.', 'checkout-fieldkit' ),
			'billing_city'       => __( 'Without the billing town or city, invoices and receipts show an incomplete address, and payment methods that check the address may refuse payments.', 'checkout-fieldkit' ),
			'billing_state'      => __( 'In many countries tax depends on the billing state or county. Without it, tax may be charged wrongly, and payment methods that check the address may refuse payments.', 'checkout-fieldkit' ),
			'billing_postcode'   => __( 'Tax rates can depend on the billing postcode, and many payment methods check it before they approve a payment. Without it, tax may be wrong and some payments may be refused.', 'checkout-fieldkit' ),
			'shipping_country'   => __( 'WooCommerce uses the shipping country to choose shipping zones, methods and rates, and often tax. Without it, shoppers may see no shipping options or the wrong price.', 'checkout-fieldkit' ),
			'shipping_address_1' => __( 'This is where the order is delivered. Without the shipping street address, you won\'t know where to send orders that go to a different address.', 'checkout-fieldkit' ),
			'shipping_city'      => __( 'Without the shipping town or city, delivery addresses are incomplete and couriers may not be able to deliver.', 'checkout-fieldkit' ),
			'shipping_state'     => __( 'In many countries shipping zones and tax depend on the state or county. Without the shipping state, shoppers may see the wrong shipping options or prices, and tax may be wrong.', 'checkout-fieldkit' ),
			'shipping_postcode'  => __( 'Shipping zones and couriers rely on the shipping postcode. Without it, shoppers may see the wrong shipping options or prices, and parcels may not arrive.', 'checkout-fieldkit' ),
		);
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
