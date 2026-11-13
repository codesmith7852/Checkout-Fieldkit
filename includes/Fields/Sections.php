<?php
/**
 * The sections of each checkout type.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Which sections a field set has, in display order (spec §2.1):
 * Classic billing, shipping, additional; Block contact, address, additional.
 */
final class Sections {

	/**
	 * Sections of a checkout type, keyed by section name, in display order.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return array<string, array{name: string, label: string}> Empty for an unknown checkout type.
	 */
	public function for_checkout( string $checkout_type ): array {
		if ( ! CheckoutType::is_valid( $checkout_type ) ) {
			return array();
		}

		$sections = CheckoutType::CLASSIC === $checkout_type
			? array(
				'billing'    => __( 'Billing', 'checkout-fieldkit' ),
				'shipping'   => __( 'Shipping', 'checkout-fieldkit' ),
				'additional' => __( 'Additional', 'checkout-fieldkit' ),
			)
			: array(
				'contact'    => __( 'Contact', 'checkout-fieldkit' ),
				'address'    => __( 'Address', 'checkout-fieldkit' ),
				'additional' => __( 'Additional', 'checkout-fieldkit' ),
			);

		foreach ( $sections as $name => $label ) {
			$sections[ $name ] = array(
				'name'  => $name,
				'label' => $label,
			);
		}

		/**
		 * Filters the sections of a checkout type.
		 *
		 * A section added here also needs field types that allow it
		 * (checkout_fieldkit_field_types) and code that renders it.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, array{name: string, label: string}> $sections      Sections keyed by name, in display order.
		 * @param string                                            $checkout_type 'classic' or 'block'.
		 */
		$filtered = apply_filters( 'checkout_fieldkit_sections', $sections, $checkout_type );

		$result = array();
		foreach ( (array) $filtered as $name => $section ) {
			$name = (string) $name;
			if ( '' === $name ) {
				continue;
			}
			$result[ $name ] = array(
				'name'  => $name,
				'label' => is_array( $section ) && isset( $section['label'] ) ? (string) $section['label'] : $name,
			);
		}

		return $result;
	}

	/**
	 * Section names of a checkout type, in display order.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return string[]
	 */
	public function names( string $checkout_type ): array {
		return array_keys( $this->for_checkout( $checkout_type ) );
	}

	/**
	 * Whether a checkout type has a section.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param string $section       Section name.
	 * @return bool
	 */
	public function has( string $checkout_type, string $section ): bool {
		return in_array( $section, $this->names( $checkout_type ), true );
	}

	/**
	 * The sections of both checkout types as lists, ready to serialise for the
	 * admin: {classic: [{name, label}, ...], block: [...]}.
	 *
	 * @return array<string, array<int, array{name: string, label: string}>>
	 */
	public function describe(): array {
		$result = array();
		foreach ( CheckoutType::all() as $checkout_type ) {
			$result[ $checkout_type ] = array_values( $this->for_checkout( $checkout_type ) );
		}

		return $result;
	}
}
