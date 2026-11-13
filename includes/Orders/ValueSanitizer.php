<?php
/**
 * Sanitises the values shoppers submit for custom fields.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Orders;

use CheckoutFieldKit\Fields\FieldTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a submitted value into the string stored on the order (spec §4.1),
 * for either checkout type:
 *
 * - textarea keeps line breaks; other free text is cleaned to one line;
 * - email, URL and number values are sanitised as such (a non-number is dropped);
 * - choice fields keep only values that are among the field's options;
 *   multi-value fields become a comma-separated list of option values, in
 *   option order, from an array or a comma-separated string;
 * - a checkbox is "1" when ticked and "" when not;
 * - display fields (heading, paragraph) have no value.
 *
 * An empty string means "nothing to store"; "0" is a value.
 *
 *     $sanitizer = new ValueSanitizer( Plugin::instance()->domain()->field_types() );
 *     $value     = $sanitizer->sanitize( $field, $posted, 'block' );
 */
final class ValueSanitizer {

	/**
	 * Field type registry.
	 *
	 * @var FieldTypes
	 */
	private $types;

	/**
	 * Constructor.
	 *
	 * @param FieldTypes $types Field type registry.
	 */
	public function __construct( FieldTypes $types ) {
		$this->types = $types;
	}

	/**
	 * The value to store for a field.
	 *
	 * @param array<string, mixed> $field         Normalised field.
	 * @param mixed                $raw           Submitted value, already unslashed: a string,
	 *                                            a number, a bool, or an array for multi-value fields.
	 * @param string               $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return string The value to store; "" when there is nothing to store.
	 */
	public function sanitize( array $field, $raw, string $checkout_type ): string {
		$value = $this->by_type( $field, $raw, $checkout_type );

		/**
		 * Filters a custom field's value before it is stored on the order.
		 *
		 * Return "" to store nothing.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $value         The sanitised value.
		 * @param mixed                $raw           The value as submitted (unslashed).
		 * @param array<string, mixed> $field         The field.
		 * @param string               $checkout_type 'classic' or 'block'.
		 */
		$value = apply_filters( 'checkout_fieldkit_sanitize_value', $value, $raw, $field, $checkout_type );

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Sanitises by type, before the filter.
	 *
	 * @param array<string, mixed> $field         Normalised field.
	 * @param mixed                $raw           Submitted value.
	 * @param string               $checkout_type Checkout type.
	 * @return string
	 */
	private function by_type( array $field, $raw, string $checkout_type ): string {
		$type       = isset( $field['type'] ) ? (string) $field['type'] : 'text';
		$definition = $this->types->get( $checkout_type, $type );

		if ( null !== $definition && ! $definition['collectsValue'] ) {
			return '';
		}

		if ( 'checkbox' === $type ) {
			return self::is_ticked( $raw ) ? '1' : '';
		}

		if ( null !== $definition && $definition['hasOptions'] ) {
			$allowed = self::option_values( $field );

			return $definition['multiple']
				? implode( ',', array_values( array_intersect( $allowed, self::to_list( $raw ) ) ) )
				: self::pick( $allowed, $raw );
		}

		if ( ! is_scalar( $raw ) || is_bool( $raw ) ) {
			return '';
		}

		$raw = (string) $raw;

		if ( 'textarea' === $type ) {
			return sanitize_textarea_field( $raw );
		}

		$implied = null === $definition ? null : $definition['impliedValidation'];
		$value   = sanitize_text_field( $raw );

		if ( 'email' === $implied ) {
			return sanitize_email( $value );
		}
		if ( 'url' === $implied ) {
			return '' === $value ? '' : esc_url_raw( $value );
		}
		if ( 'number' === $implied ) {
			return is_numeric( $value ) ? $value : '';
		}

		return $value;
	}

	/**
	 * Whether a checkbox value means ticked.
	 *
	 * @param mixed $raw Submitted value.
	 * @return bool
	 */
	private static function is_ticked( $raw ): bool {
		if ( is_bool( $raw ) ) {
			return $raw;
		}

		return is_scalar( $raw ) && '' !== trim( (string) $raw ) && '0' !== trim( (string) $raw );
	}

	/**
	 * The non-empty option values of a choice field.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return string[]
	 */
	private static function option_values( array $field ): array {
		$values = array();
		foreach ( isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array() as $option ) {
			if ( is_array( $option ) && isset( $option['value'] ) && '' !== (string) $option['value'] ) {
				$values[] = (string) $option['value'];
			}
		}

		return $values;
	}

	/**
	 * A submitted single value if it is one of the allowed values.
	 *
	 * @param string[] $allowed Option values.
	 * @param mixed    $raw     Submitted value.
	 * @return string
	 */
	private static function pick( array $allowed, $raw ): string {
		if ( ! is_scalar( $raw ) || is_bool( $raw ) ) {
			return '';
		}

		$value = trim( (string) $raw );

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Submitted values of a multi-value field as a list: an array as posted,
	 * or a comma-separated string (WooCommerce joins multiselect values with ", ").
	 *
	 * @param mixed $raw Submitted value.
	 * @return string[]
	 */
	private static function to_list( $raw ): array {
		if ( is_scalar( $raw ) && ! is_bool( $raw ) ) {
			$raw = explode( ',', (string) $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$list = array();
		foreach ( $raw as $item ) {
			if ( is_scalar( $item ) && ! is_bool( $item ) ) {
				$list[] = trim( (string) $item );
			}
		}

		return $list;
	}
}
