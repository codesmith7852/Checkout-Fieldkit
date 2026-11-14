<?php
/**
 * Turns fields into WooCommerce checkout field definitions.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Classic;

use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Fields\FieldTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Maps classic fields onto the arrays WooCommerce's checkout and address
 * forms use (the arguments of woocommerce_form_field()):
 *
 * - A built-in field starts from WooCommerce's own definition, which keeps its
 *   type, options and autocomplete; our label, placeholder, default, required,
 *   validation, classes and priority go on top (spec §4).
 * - A custom field becomes a new definition of its own type.
 */
final class WooCommerceFields {

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
	 * A built-in field's WooCommerce definition with our settings on top.
	 *
	 * A setting we leave at an empty value is not added where WooCommerce's
	 * definition lacks the key, so an unchanged field stays as WooCommerce
	 * has it.
	 *
	 * @param array<string, mixed> $definition WooCommerce's definition.
	 * @param array<string, mixed> $field      Our field.
	 * @param int                  $priority   WooCommerce priority of its position.
	 * @param string[]             $keep       WooCommerce keys whose value stays as WooCommerce has it
	 *                                         (a country's own rule; see LocaleOverrides).
	 * @return array<string, mixed>
	 */
	public function overlay( array $definition, array $field, int $priority, array $keep = array() ): array {
		$ours = array(
			'label'       => $field['label'],
			'placeholder' => $field['placeholder'],
			'default'     => $field['default'],
			'required'    => $field['required'],
			'validate'    => $field['validation'],
			'class'       => $field['classes'],
			'priority'    => $priority,
		);

		foreach ( $ours as $key => $value ) {
			if ( in_array( $key, $keep, true ) ) {
				continue;
			}
			if ( ! array_key_exists( $key, $definition ) && ( '' === $value || array() === $value || false === $value ) ) {
				continue;
			}
			$definition[ $key ] = $value;
		}

		return $definition;
	}

	/**
	 * A custom field as a WooCommerce definition.
	 *
	 * @param array<string, mixed> $field    Our field.
	 * @param int                  $priority WooCommerce priority of its position.
	 * @return array<string, mixed>
	 */
	public function custom( array $field, int $priority ): array {
		$type       = (string) $field['type'];
		$definition = $this->types->get( CheckoutType::CLASSIC, $type );
		$collects   = null === $definition || $definition['collectsValue'];

		$result = array(
			'type'     => $type,
			'label'    => $field['label'],
			'required' => $collects && 'hidden' !== $type && $field['required'],
			'class'    => $field['classes'],
			'priority' => $priority,
		);

		if ( '' !== $field['placeholder'] ) {
			$result['placeholder'] = $field['placeholder'];
		}
		if ( '' !== $field['default'] ) {
			$result['default'] = $field['default'];
		}
		if ( null !== $definition && $definition['hasOptions'] ) {
			$result['options'] = array();
			foreach ( $field['options'] as $option ) {
				$result['options'][ (string) $option['value'] ] = (string) $option['label'];
			}
		}

		$validations = $this->validations( $field );
		if ( $validations ) {
			$result['validate'] = $validations;
		}

		return $result;
	}

	/**
	 * The validations a field's value gets: for a custom field the one its
	 * type implies (number, email, url, phone) and those chosen for it; for a
	 * built-in field only those chosen.
	 *
	 * @param array<string, mixed> $field Our field.
	 * @return string[]
	 */
	public function validations( array $field ): array {
		$chosen = array_values( array_filter( (array) $field['validation'], 'is_string' ) );
		if ( ! empty( $field['builtIn'] ) ) {
			return $chosen;
		}

		$definition = $this->types->get( CheckoutType::CLASSIC, (string) $field['type'] );
		$implied    = null !== $definition && null !== $definition['impliedValidation'] ? array( $definition['impliedValidation'] ) : array();

		return array_values( array_unique( array_merge( $implied, $chosen ) ) );
	}
}
