<?php
/**
 * The rules a field set must follow.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Checks a whole field set against the rules of spec §2.5 and returns every
 * problem at once.
 *
 * Each problem is a plain array that serialises straight to JSON:
 *
 *     section  string    Section name as given; '' for problems with the whole set.
 *     index    int|null  Position of the field in that section as given; null when
 *                        the problem is not one field's (a missing built-in field,
 *                        an unknown section).
 *     key      string    Field key the problem is about, for example 'name';
 *                        '' when it is about the field or section as a whole.
 *     message  string    Sentence for the store owner.
 *
 * Fields are normalised (FieldNormalizer) before the rules are checked, so a
 * field set that passes can be normalised and saved as it is.
 */
final class FieldSetValidator {

	/**
	 * Rule for field names (spec §2.2).
	 */
	public const NAME_PATTERN = '/^[a-z][a-z0-9_]{0,59}$/';

	/**
	 * Field type registry.
	 *
	 * @var FieldTypes
	 */
	private $types;

	/**
	 * Built-in fields.
	 *
	 * @var DefaultFields
	 */
	private $defaults;

	/**
	 * Field normaliser.
	 *
	 * @var FieldNormalizer
	 */
	private $normalizer;

	/**
	 * Constructor.
	 *
	 * @param FieldTypes      $types      Field type registry.
	 * @param DefaultFields   $defaults   Built-in fields.
	 * @param FieldNormalizer $normalizer Field normaliser.
	 */
	public function __construct( FieldTypes $types, DefaultFields $defaults, FieldNormalizer $normalizer ) {
		$this->types      = $types;
		$this->defaults   = $defaults;
		$this->normalizer = $normalizer;
	}

	/**
	 * Every problem with a field set.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param mixed  $field_set     Field set as given: {version?, sections: {name: [field, ...]}}.
	 * @return array<int, array{section: string, index: int|null, key: string, message: string}> Empty when valid.
	 */
	public function validate( string $checkout_type, $field_set ): array {
		if ( ! CheckoutType::is_valid( $checkout_type ) ) {
			return array(
				self::error( '', null, '', __( 'Unknown checkout type.', 'checkout-fieldkit' ) ),
			);
		}

		if ( ! is_array( $field_set ) || ! isset( $field_set['sections'] ) || ! is_array( $field_set['sections'] ) ) {
			return array(
				self::error( '', null, 'sections', __( 'The field set has no sections.', 'checkout-fieldkit' ) ),
			);
		}

		$context = array(
			'checkout_type' => $checkout_type,
			'types'         => $this->types->for_checkout( $checkout_type ),
			'sections'      => $this->types->sections()->for_checkout( $checkout_type ),
			'validations'   => $this->types->validation_names( $checkout_type ),
			'reserved'      => $this->defaults->reserved_names(),
			'built_ins'     => $this->defaults->names( $checkout_type ),
		);

		$errors    = array();
		$used      = array();
		$built_ins = array();

		foreach ( $field_set['sections'] as $section => $fields ) {
			$section = (string) $section;

			if ( ! isset( $context['sections'][ $section ] ) ) {
				$errors[] = self::error(
					$section,
					null,
					'',
					/* translators: %s: section name. */
					sprintf( __( 'The checkout has no section called "%s".', 'checkout-fieldkit' ), $section )
				);
				continue;
			}

			if ( ! is_array( $fields ) ) {
				$errors[] = self::error( $section, null, '', __( 'A section must be a list of fields.', 'checkout-fieldkit' ) );
				continue;
			}

			foreach ( array_values( $fields ) as $index => $field ) {
				if ( ! is_array( $field ) ) {
					$errors[] = self::error( $section, $index, '', __( 'Each field must be an object with its settings.', 'checkout-fieldkit' ) );
					continue;
				}

				$built_in = $this->built_in_for( $checkout_type, $field );
				$field    = $this->normalizer->normalize(
					$field,
					$checkout_type,
					$built_in,
					null === $built_in ? null : $this->defaults->editable_settings( $checkout_type, $built_in['name'] )
				);

				$field_errors = $this->check_field( $field, $section, $built_in, $context, $used );
				if ( $field['builtIn'] || in_array( $field['name'], $context['built_ins'], true ) ) {
					$built_ins[ $field['name'] ] = true;
				}
				$used[ $field['name'] ] = true;

				/**
				 * Filters the problems found with one field, so extensions can add rules.
				 *
				 * Add an array with 'key' (the field key) and 'message' for each problem.
				 * Section and index are filled in.
				 *
				 * @since 0.1.0
				 *
				 * @param array<int, array<string, mixed>> $errors        Problems found so far: section, index, key, message.
				 * @param array<string, mixed>             $field         The normalised field.
				 * @param string                           $checkout_type 'classic' or 'block'.
				 * @param string                           $section       Section name.
				 * @param int                              $index         Position of the field in the section.
				 */
				$field_errors = apply_filters( 'checkout_fieldkit_validate_field', $field_errors, $field, $checkout_type, $section, $index );

				foreach ( (array) $field_errors as $error ) {
					if ( is_array( $error ) && isset( $error['message'] ) ) {
						$errors[] = self::error( $section, $index, isset( $error['key'] ) ? (string) $error['key'] : '', (string) $error['message'] );
					}
				}
			}
		}

		// Rule 2: built-in fields cannot be removed.
		foreach ( $context['built_ins'] as $name ) {
			if ( ! isset( $built_ins[ $name ] ) ) {
				$errors[] = self::error(
					(string) $this->defaults->section_of( $checkout_type, $name ),
					null,
					'name',
					/* translators: %s: field name. */
					sprintf( __( 'WooCommerce\'s field "%s" cannot be removed. Turn it off instead.', 'checkout-fieldkit' ), $name )
				);
			}
		}

		return $errors;
	}

	/**
	 * Problems with one normalised field. At most one problem per key.
	 *
	 * @param array<string, mixed>      $field    Normalised field.
	 * @param string                    $section  Section name.
	 * @param array<string, mixed>|null $built_in WooCommerce's definition, for a built-in field.
	 * @param array<string, mixed>      $context  What the checkout type allows.
	 * @param array<string, bool>       $used     Names used by earlier fields.
	 * @return array<int, array{key: string, message: string}>
	 */
	private function check_field( array $field, string $section, ?array $built_in, array $context, array $used ): array {
		$errors = array();
		$name   = $field['name'];

		// Rule 1: name format and uniqueness.
		if ( ! preg_match( self::NAME_PATTERN, $name ) ) {
			$errors[] = self::problem( 'name', __( 'The field name must start with a lower-case letter and use only lower-case letters, digits and underscores, up to 60 characters.', 'checkout-fieldkit' ) );
		} elseif ( isset( $used[ $name ] ) ) {
			$errors[] = self::problem( 'name', __( 'Another field already uses this name. Field names must be unique.', 'checkout-fieldkit' ) );
		}

		// Rule 2: built-in fields stay what they are, where they are.
		if ( $field['builtIn'] ) {
			if ( null === $built_in ) {
				$errors[] = self::problem( 'builtIn', __( 'Only WooCommerce\'s own fields can be built-in. Add this as a custom field instead.', 'checkout-fieldkit' ) );
				return $errors;
			}
			if ( $field['type'] !== $built_in['type'] ) {
				$errors[] = self::problem( 'type', __( 'The type of WooCommerce\'s own fields cannot be changed.', 'checkout-fieldkit' ) );
			}
			$home = $this->defaults->section_of( $context['checkout_type'], $name );
			if ( $home !== $section && empty( $errors ) ) {
				$errors[] = self::problem(
					'name',
					/* translators: %s: section label. */
					sprintf( __( 'This WooCommerce field belongs in the %s section.', 'checkout-fieldkit' ), isset( $context['sections'][ $home ] ) ? $context['sections'][ $home ]['label'] : $home )
				);
			}
			return array_merge( $errors, $this->check_validation( $field, $built_in, $context ) );
		}

		if ( in_array( $name, $context['built_ins'], true ) ) {
			$errors[] = self::problem( 'builtIn', __( 'This is one of WooCommerce\'s own fields. It cannot become a custom field.', 'checkout-fieldkit' ) );
			return $errors;
		}

		if ( empty( $errors ) && $this->defaults->is_woocommerce_property( $name ) ) {
			$errors[] = self::problem( 'name', __( 'WooCommerce would use a value sent under this name to change the order or the customer\'s account, so a shopper could set it. Choose another name.', 'checkout-fieldkit' ) );
		} elseif ( empty( $errors ) && in_array( $name, $context['reserved'], true ) ) {
			$errors[] = self::problem( 'name', __( 'WooCommerce uses this name for one of its own fields. Choose another name.', 'checkout-fieldkit' ) );
		}

		// Rule 5: the type is allowed for the checkout type and section.
		$type = isset( $context['types'][ $field['type'] ] ) ? $context['types'][ $field['type'] ] : null;
		if ( null === $type || $type['builtInOnly'] ) {
			$errors[] = self::problem(
				'type',
				/* translators: %s: field type. */
				sprintf( __( '"%s" is not a field type this checkout supports.', 'checkout-fieldkit' ), $field['type'] )
			);
			return $errors;
		}
		if ( ! in_array( $section, $type['sections'], true ) ) {
			$errors[] = self::problem(
				'type',
				sprintf(
					/* translators: 1: field type label, 2: section label. */
					__( '%1$s fields cannot be used in the %2$s section.', 'checkout-fieldkit' ),
					$type['label'],
					$context['sections'][ $section ]['label']
				)
			);
		}

		// Rule 3: defaults.
		$default_error = $this->check_default( $field, $type );
		if ( null !== $default_error ) {
			$errors[] = $default_error;
		}

		// Rule 4: options.
		if ( $type['hasOptions'] ) {
			$options_error = $this->check_options( $field['options'], 'select' === $field['type'] );
			if ( null !== $options_error ) {
				$errors[] = $options_error;
			}
		}

		return array_merge( $errors, $this->check_validation( $field, null, $context ) );
	}

	/**
	 * Rule 3: a choice field's default is among its option values; a number
	 * field's default is a number. An empty default is always fine.
	 *
	 * @param array<string, mixed> $field Normalised field.
	 * @param array<string, mixed> $type  The field's type definition.
	 * @return array{key: string, message: string}|null
	 */
	private function check_default( array $field, array $type ): ?array {
		if ( '' === $field['default'] ) {
			return null;
		}

		if ( $type['hasOptions'] ) {
			$values   = array_column( $field['options'], 'value' );
			$defaults = $type['multiple'] ? explode( ',', $field['default'] ) : array( $field['default'] );
			if ( array_diff( $defaults, $values ) ) {
				return self::problem( 'default', __( 'The default must be one of the option values.', 'checkout-fieldkit' ) );
			}
		}

		if ( 'number' === $field['type'] && ! is_numeric( $field['default'] ) ) {
			return self::problem( 'default', __( 'The default of a number field must be a number.', 'checkout-fieldkit' ) );
		}

		return null;
	}

	/**
	 * Rule 4: option values are unique and not empty, except that a select's
	 * first option may have an empty value (a "choose one" prompt). There must
	 * be at least one option with a value.
	 *
	 * @param array<int, array{value: string, label: string}> $options   Normalised options.
	 * @param bool                                            $is_select Whether the field is a select.
	 * @return array{key: string, message: string}|null
	 */
	private function check_options( array $options, bool $is_select ): ?array {
		$values = array_column( $options, 'value' );

		foreach ( $values as $position => $value ) {
			if ( '' === $value && ! ( $is_select && 0 === $position ) ) {
				return self::problem(
					'options',
					$is_select
						? __( 'Every option needs a saved value. Only the first option of a dropdown may be empty, as a prompt.', 'checkout-fieldkit' )
						: __( 'Every option needs a saved value.', 'checkout-fieldkit' )
				);
			}
		}

		if ( count( $values ) !== count( array_unique( $values ) ) ) {
			return self::problem( 'options', __( 'Two options have the same saved value. Each option needs its own.', 'checkout-fieldkit' ) );
		}

		if ( array() === array_filter( $values, 'strlen' ) ) {
			return self::problem( 'options', __( 'Add at least one option.', 'checkout-fieldkit' ) );
		}

		return null;
	}

	/**
	 * Validation choices belong to the checkout type. A built-in field may
	 * also keep the validations WooCommerce gives it.
	 *
	 * @param array<string, mixed>      $field    Normalised field.
	 * @param array<string, mixed>|null $built_in WooCommerce's definition, for a built-in field.
	 * @param array<string, mixed>      $context  What the checkout type allows.
	 * @return array<int, array{key: string, message: string}>
	 */
	private function check_validation( array $field, ?array $built_in, array $context ): array {
		$allowed = array_merge( $context['validations'], null === $built_in ? array() : $built_in['validation'] );

		if ( array_diff( $field['validation'], $allowed ) ) {
			return array( self::problem( 'validation', __( 'Choose validations from the list this checkout supports.', 'checkout-fieldkit' ) ) );
		}

		return array();
	}

	/**
	 * WooCommerce's definition of a field that claims to be built-in.
	 *
	 * @param string               $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param array<string, mixed> $field         Field as given.
	 * @return array<string, mixed>|null
	 */
	private function built_in_for( string $checkout_type, array $field ): ?array {
		$normalized = $this->normalizer->normalize( $field, $checkout_type );

		return $normalized['builtIn'] ? $this->defaults->get( $checkout_type, $normalized['name'] ) : null;
	}

	/**
	 * A problem with one field.
	 *
	 * @param string $key     Field key.
	 * @param string $message Message.
	 * @return array{key: string, message: string}
	 */
	private static function problem( string $key, string $message ): array {
		return array(
			'key'     => $key,
			'message' => $message,
		);
	}

	/**
	 * A problem with its location.
	 *
	 * @param string   $section Section name.
	 * @param int|null $index   Field position.
	 * @param string   $key     Field key.
	 * @param string   $message Message.
	 * @return array{section: string, index: int|null, key: string, message: string}
	 */
	private static function error( string $section, ?int $index, string $key, string $message ): array {
		return array(
			'section' => $section,
			'index'   => $index,
			'key'     => $key,
			'message' => $message,
		);
	}
}
