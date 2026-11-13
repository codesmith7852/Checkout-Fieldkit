<?php
/**
 * Turns any input into a well-formed field.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Normalises fields and field sets (spec §2.2): missing keys take their
 * defaults, unknown keys are dropped, every value gets its key's type and is
 * sanitised, and settings that do not apply to the field's type are cleared.
 * Built-in fields keep WooCommerce's values for the settings that are not
 * ours to change.
 *
 * Normalising never rejects anything; FieldSetValidator reports what is wrong.
 */
final class FieldNormalizer {

	/**
	 * Settings cleared when they do not apply to a custom field's type.
	 *
	 * @var string[]
	 */
	private const TYPE_SETTINGS = array( 'placeholder', 'default', 'required', 'options', 'validation', 'headingLevel', 'showInEmails', 'showOnOrderPages' );

	/**
	 * Field type registry.
	 *
	 * @var FieldTypes
	 */
	private $types;

	/**
	 * Built-in fields, for normalising whole field sets.
	 *
	 * @var DefaultFields|null
	 */
	private $defaults;

	/**
	 * Constructor.
	 *
	 * @param FieldTypes         $types    Field type registry.
	 * @param DefaultFields|null $defaults Built-in fields; needed by normalize_set() only.
	 */
	public function __construct( FieldTypes $types, ?DefaultFields $defaults = null ) {
		$this->types    = $types;
		$this->defaults = $defaults;
	}

	/**
	 * Normalises one field.
	 *
	 * @param array<string, mixed>      $field         Field as given.
	 * @param string                    $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param array<string, mixed>|null $built_in      For a built-in field: WooCommerce's definition
	 *                                                 of it (from DefaultFields).
	 * @param string[]|null             $editable      For a built-in field: the keys that are ours to
	 *                                                 change; every other key takes $built_in's value.
	 * @return array<string, mixed> Field with exactly the keys of Field::defaults().
	 */
	public function normalize( array $field, string $checkout_type, ?array $built_in = null, ?array $editable = null ): array {
		$defaults   = Field::defaults( $checkout_type );
		$type       = self::to_string( isset( $field['type'] ) ? $field['type'] : $defaults['type'], $defaults['type'] );
		$definition = $this->types->get( $checkout_type, $type );

		$result = array();
		foreach ( $defaults as $key => $default ) {
			$result[ $key ] = array_key_exists( $key, $field )
				? $this->cast( $key, $field[ $key ], $default, $type, $definition )
				: $default;
		}
		$result['type'] = $type;

		if ( $result['builtIn'] && null !== $built_in ) {
			$fixed = array_diff( array_keys( $defaults ), array( 'name', 'type', 'builtIn' ), (array) $editable );
			foreach ( $fixed as $key ) {
				$result[ $key ] = array_key_exists( $key, $built_in ) ? $built_in[ $key ] : $defaults[ $key ];
			}

			return $result;
		}

		if ( null !== $definition ) {
			foreach ( self::TYPE_SETTINGS as $key ) {
				if ( ! in_array( $key, $definition['settings'], true ) ) {
					$result[ $key ] = $defaults[ $key ];
				}
			}
		}

		return $result;
	}

	/**
	 * Normalises the sections of a field set. Built-in fields are matched to
	 * WooCommerce's definition by name. Sections the checkout type does not
	 * have, and entries that are not fields, are left out.
	 *
	 * @param string                           $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param array<string, array<int, mixed>> $sections      Fields keyed by section name.
	 * @return array<string, array<int, array<string, mixed>>> Every section of the checkout type, in display order.
	 */
	public function normalize_set( string $checkout_type, array $sections ): array {
		$result = array();
		foreach ( $this->types_sections( $checkout_type ) as $section ) {
			$result[ $section ] = array();
			$fields             = isset( $sections[ $section ] ) && is_array( $sections[ $section ] ) ? $sections[ $section ] : array();

			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$built_in             = $this->built_in_for( $checkout_type, $field );
				$result[ $section ][] = $this->normalize(
					$field,
					$checkout_type,
					$built_in,
					null === $built_in ? null : $this->defaults->editable_settings( $checkout_type, $built_in['name'] )
				);
			}
		}

		return $result;
	}

	/**
	 * Brings the built-in fields of a field set in line with what WooCommerce
	 * defines now, which changes with its settings (for example when the phone
	 * field is hidden) and with other plugins. Fields marked built-in that
	 * WooCommerce no longer has are left out; built-in fields that are missing
	 * are put back in their section, after the built-in field that comes
	 * before them by default. Custom fields are left as they are.
	 *
	 * @param string                           $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param array<string, array<int, mixed>> $sections      Fields keyed by section name, as given.
	 * @return array<string, array<int, mixed>>
	 */
	public function reconcile_built_ins( string $checkout_type, array $sections ): array {
		if ( null === $this->defaults ) {
			return $sections;
		}

		$built_ins = $this->defaults->names( $checkout_type );
		$present   = array();
		foreach ( $sections as $section => $fields ) {
			if ( ! is_array( $fields ) ) {
				continue;
			}
			$kept = array();
			foreach ( $fields as $field ) {
				$name = is_array( $field ) && isset( $field['name'] ) && is_scalar( $field['name'] ) ? trim( (string) $field['name'] ) : '';
				if ( is_array( $field ) && ! empty( $field['builtIn'] ) && ! in_array( $name, $built_ins, true ) ) {
					continue;
				}
				$present[ $name ] = true;
				$kept[]           = $field;
			}
			$sections[ $section ] = $kept;
		}

		foreach ( $this->defaults->field_set( $checkout_type )['sections'] as $section => $defaults ) {
			$fields   = isset( $sections[ $section ] ) && is_array( $sections[ $section ] ) ? array_values( $sections[ $section ] ) : array();
			$previous = array();
			foreach ( $defaults as $default ) {
				if ( ! isset( $present[ $default['name'] ] ) ) {
					$position = 0;
					foreach ( $fields as $index => $field ) {
						if ( is_array( $field ) && isset( $field['name'] ) && in_array( $field['name'], $previous, true ) ) {
							$position = $index + 1;
						}
					}
					array_splice( $fields, $position, 0, array( $default ) );
					$present[ $default['name'] ] = true;
				}
				$previous[] = $default['name'];
			}
			$sections[ $section ] = $fields;
		}

		return $sections;
	}

	/**
	 * Leaves out the keys of a normalised field whose value equals the key's
	 * default, for lean storage. normalize() restores them.
	 *
	 * @param array<string, mixed> $field         Normalised field.
	 * @param string               $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return array<string, mixed>
	 */
	public function compact( array $field, string $checkout_type ): array {
		$defaults = Field::defaults( $checkout_type );
		$result   = array();
		foreach ( $field as $key => $value ) {
			if ( 'name' === $key || 'type' === $key || ! array_key_exists( $key, $defaults ) || $defaults[ $key ] !== $value ) {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Section names of a checkout type, through the registry's sections.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return string[]
	 */
	private function types_sections( string $checkout_type ): array {
		return $this->types->sections()->names( $checkout_type );
	}

	/**
	 * WooCommerce's definition of a field marked built-in, if there is one.
	 *
	 * @param string               $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param array<string, mixed> $field         Field as given.
	 * @return array<string, mixed>|null
	 */
	private function built_in_for( string $checkout_type, array $field ): ?array {
		if ( null === $this->defaults || empty( $field['builtIn'] ) || ! isset( $field['name'] ) || ! is_scalar( $field['name'] ) ) {
			return null;
		}

		return $this->defaults->get( $checkout_type, trim( (string) $field['name'] ) );
	}

	/**
	 * Gives a value the type of its key and sanitises it.
	 *
	 * @param string                    $key        Field key.
	 * @param mixed                     $value      Value as given.
	 * @param mixed                     $fallback   The key's default.
	 * @param string                    $type       The field's type.
	 * @param array<string, mixed>|null $definition The type's definition, if the type exists.
	 * @return mixed
	 */
	private function cast( string $key, $value, $fallback, string $type, ?array $definition ) {
		switch ( $key ) {
			case 'name':
			case 'type':
				return self::to_string( $value, $fallback );

			case 'builtIn':
			case 'enabled':
			case 'required':
			case 'showInEmails':
			case 'showOnOrderPages':
				return self::to_bool( $value, $fallback );

			case 'label':
				return 'paragraph' === $type
					? sanitize_textarea_field( self::to_string( $value, '' ) )
					: sanitize_text_field( self::to_string( $value, '' ) );

			case 'placeholder':
				return sanitize_text_field( self::to_string( $value, '' ) );

			case 'default':
				return $this->cast_default( $value, $type, $definition );

			case 'options':
				return self::to_options( $value );

			case 'validation':
				return self::to_list( is_string( $value ) ? array( $value ) : $value, 'sanitize_key' );

			case 'classes':
				return self::to_list( is_string( $value ) ? preg_split( '/[\s,]+/', $value ) : $value, 'sanitize_html_class' );

			case 'headingLevel':
				return in_array( $value, Field::HEADING_LEVELS, true ) ? $value : $fallback;
		}

		// Keys added by extensions keep a value of the default's type.
		return gettype( $value ) === gettype( $fallback ) ? $value : $fallback;
	}

	/**
	 * Normalises a default value for the field's type: "1" or "" for a checkbox,
	 * a comma-separated list for multi-value types, text otherwise.
	 *
	 * @param mixed                     $value      Default as given.
	 * @param string                    $type       The field's type.
	 * @param array<string, mixed>|null $definition The type's definition, if the type exists.
	 * @return string
	 */
	private function cast_default( $value, string $type, ?array $definition ): string {
		if ( 'checkbox' === $type ) {
			return self::to_bool( $value, false ) ? '1' : '';
		}

		$text = self::to_string( $value, '' );

		if ( null !== $definition && $definition['multiple'] ) {
			$parts = array_map( 'sanitize_text_field', explode( ',', $text ) );

			return implode( ',', array_values( array_unique( array_filter( $parts, 'strlen' ) ) ) );
		}

		return 'textarea' === $type ? sanitize_textarea_field( $text ) : sanitize_text_field( $text );
	}

	/**
	 * A scalar as a trimmed string; anything else becomes the fallback.
	 *
	 * @param mixed  $value    Value.
	 * @param string $fallback Value for non-scalars.
	 * @return string
	 */
	private static function to_string( $value, string $fallback ): string {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}

		return is_scalar( $value ) ? trim( (string) $value ) : $fallback;
	}

	/**
	 * A boolean from a bool, number or string such as "true", "yes", "1", "false".
	 *
	 * @param mixed $value    Value.
	 * @param bool  $fallback Value for anything else.
	 * @return bool
	 */
	private static function to_bool( $value, bool $fallback ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return 0 != $value; // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- 0 and 0.0 alike.
		}
		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return $fallback;
	}

	/**
	 * A list of unique, non-empty strings, each passed through a sanitiser.
	 *
	 * @param mixed    $value     Value.
	 * @param callable $sanitizer Sanitising function for one string.
	 * @return string[]
	 */
	private static function to_list( $value, callable $sanitizer ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$list = array();
		foreach ( $value as $item ) {
			$item = is_scalar( $item ) ? (string) call_user_func( $sanitizer, trim( (string) $item ) ) : '';
			if ( '' !== $item && ! in_array( $item, $list, true ) ) {
				$list[] = $item;
			}
		}

		return $list;
	}

	/**
	 * A list of {value, label} options; entries without a value key are left out.
	 *
	 * @param mixed $value Options as given.
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function to_options( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$options = array();
		foreach ( $value as $option ) {
			if ( ! is_array( $option ) || ! array_key_exists( 'value', $option ) || ! is_scalar( $option['value'] ) ) {
				continue;
			}
			$options[] = array(
				'value' => sanitize_text_field( (string) $option['value'] ),
				'label' => sanitize_text_field( self::to_string( isset( $option['label'] ) ? $option['label'] : '', '' ) ),
			);
		}

		return $options;
	}
}
