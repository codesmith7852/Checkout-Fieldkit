<?php
/**
 * Checks the values shoppers submit for custom fields.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Orders;

use CheckoutFieldKit\Fields\FieldTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Says what is wrong with a submitted value (spec §4 "Validation on submit",
 * §5), for either checkout type:
 *
 * - an empty value (nothing, blank text, an unticked checkbox, a select's
 *   empty prompt) fails only when the field is required;
 * - a non-empty value gets its type's own check (number, email, url), then
 *   each of the field's chosen validations (email, phone, postcode, url,
 *   number), then, for choice fields, must be one of the option values;
 * - display fields (heading, paragraph) are never checked.
 *
 * Phone numbers use WC_Validation::is_phone(), postcodes
 * WC_Validation::is_postcode() for the country given (format only without one).
 * Messages name the field by its label (its name when the label is empty).
 *
 *     $validator = new ValueValidator( Plugin::instance()->domain()->field_types() );
 *     $message   = $validator->problem( $field, $posted, 'block', 'GB' ); // '' when fine.
 */
final class ValueValidator {

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
	 * The problems of several fields' values, in field order.
	 *
	 * @param array<int, array<string, mixed>> $fields        Normalised fields.
	 * @param array<string, mixed>             $values        Submitted values keyed by field name; a missing key is empty.
	 * @param string                           $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param string|null                      $country       Country for postcodes and phone numbers, or null when unknown.
	 * @return array<string, string> Messages keyed by field name; fields without a problem are left out.
	 */
	public function problems( array $fields, array $values, string $checkout_type, ?string $country = null ): array {
		$problems = array();
		foreach ( $fields as $field ) {
			$name    = isset( $field['name'] ) ? (string) $field['name'] : '';
			$message = $this->problem( $field, array_key_exists( $name, $values ) ? $values[ $name ] : null, $checkout_type, $country );
			if ( '' !== $message ) {
				$problems[ $name ] = $message;
			}
		}

		return $problems;
	}

	/**
	 * What is wrong with one field's submitted value.
	 *
	 * @param array<string, mixed> $field         Normalised field.
	 * @param mixed                $raw           Submitted value (unslashed): a string, number, bool or null.
	 * @param string               $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param string|null          $country       Country for postcodes and phone numbers, or null when unknown.
	 * @return string The message; empty when the value is fine.
	 */
	public function problem( array $field, $raw, string $checkout_type, ?string $country = null ): string {
		$type       = isset( $field['type'] ) ? (string) $field['type'] : 'text';
		$definition = $this->types->get( $checkout_type, $type );
		if ( null !== $definition && ! $definition['collectsValue'] ) {
			return '';
		}

		$label = isset( $field['label'] ) && '' !== trim( (string) $field['label'] ) ? (string) $field['label'] : (string) ( $field['name'] ?? '' );

		if ( null !== $raw && ! is_scalar( $raw ) ) {
			/* translators: %s: field label. */
			return sprintf( __( '%s is not valid.', 'checkout-fieldkit' ), $label );
		}

		$value = self::to_string( $type, $raw );
		if ( '' === $value ) {
			/* translators: %s: field label. */
			return empty( $field['required'] ) ? '' : sprintf( __( '%s is required.', 'checkout-fieldkit' ), $label );
		}

		if ( 'checkbox' === $type ) {
			return '';
		}

		foreach ( $this->validations( $field, $definition ) as $validation ) {
			$message = self::check( $validation, $value, $country, $label );
			if ( '' !== $message ) {
				return $message;
			}
		}

		if ( null !== $definition && $definition['hasOptions'] && ! $definition['multiple'] && ! in_array( $value, self::option_values( $field ), true ) ) {
			/* translators: %s: field label. */
			return sprintf( __( '%s: choose one of the options.', 'checkout-fieldkit' ), $label );
		}

		return '';
	}

	/**
	 * A submitted value as a trimmed string; "" for nothing and for an unticked checkbox.
	 *
	 * @param string $type Field type.
	 * @param mixed  $raw  Scalar or null.
	 * @return string
	 */
	private static function to_string( string $type, $raw ): string {
		if ( null === $raw || false === $raw ) {
			return '';
		}
		if ( true === $raw ) {
			return '1';
		}

		$value = trim( (string) $raw );

		return 'checkbox' === $type && '0' === $value ? '' : $value;
	}

	/**
	 * The checks a field's values get: the type's own, then the chosen validations.
	 *
	 * @param array<string, mixed>      $field      Field.
	 * @param array<string, mixed>|null $definition Type definition.
	 * @return string[]
	 */
	private function validations( array $field, ?array $definition ): array {
		$list = null !== $definition && null !== $definition['impliedValidation'] ? array( (string) $definition['impliedValidation'] ) : array();
		$own  = isset( $field['validation'] ) && is_array( $field['validation'] ) ? array_map( 'strval', $field['validation'] ) : array();

		return array_values( array_unique( array_merge( $list, $own ) ) );
	}

	/**
	 * What is wrong with a non-empty value under one validation.
	 *
	 * @param string      $validation 'email', 'phone', 'postcode', 'url' or 'number'.
	 * @param string      $value      Non-empty value.
	 * @param string|null $country    Country, or null when unknown.
	 * @param string      $label      Field label for the message.
	 * @return string Empty when the value is fine or the validation is unknown.
	 */
	private static function check( string $validation, string $value, ?string $country, string $label ): string {
		switch ( $validation ) {
			case 'email':
				/* translators: %s: field label. */
				return is_email( $value ) ? '' : sprintf( __( '%s is not a valid email address.', 'checkout-fieldkit' ), $label );
			case 'url':
				/* translators: %s: field label. */
				return self::is_url( $value ) ? '' : sprintf( __( '%s is not a valid web address. Start it with http:// or https://.', 'checkout-fieldkit' ), $label );
			case 'number':
				/* translators: %s: field label. */
				return is_numeric( $value ) ? '' : sprintf( __( '%s must be a number.', 'checkout-fieldkit' ), $label );
			case 'phone':
				/* translators: %s: field label. */
				return \WC_Validation::is_phone( $value, $country ) ? '' : sprintf( __( '%s is not a valid phone number.', 'checkout-fieldkit' ), $label );
			case 'postcode':
				/* translators: %s: field label. */
				return \WC_Validation::is_postcode( $value, (string) $country ) ? '' : sprintf( __( '%s is not a valid postcode / ZIP.', 'checkout-fieldkit' ), $label );
		}

		return '';
	}

	/**
	 * Whether a value is an http or https address.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private static function is_url( string $value ): bool {
		$scheme = wp_parse_url( $value, PHP_URL_SCHEME );

		return in_array( is_string( $scheme ) ? strtolower( $scheme ) : '', array( 'http', 'https' ), true )
			&& false !== filter_var( $value, FILTER_VALIDATE_URL );
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
}
