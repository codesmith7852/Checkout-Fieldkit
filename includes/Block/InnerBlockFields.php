<?php
/**
 * The fields our Block checkout inner blocks show.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Block;

use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Fields\FieldTypes;
use CheckoutFieldKit\Storage\FieldSetRepository;

defined( 'ABSPATH' ) || exit;

/**
 * The custom fields of the Block field set's Contact and Additional sections
 * (spec §5, ADR 0008): every enabled custom field, in the stored order. The
 * built-in `email` is WooCommerce's own and is never ours to render.
 *
 * script_data() is what the inner blocks receive in the browser, through the
 * checkout block integration (`getSetting( 'checkout-fieldkit_data' )`):
 *
 *     {
 *       namespace: 'checkout-fieldkit',   // Store API extension namespace.
 *       sections: {
 *         contact:    [ field, ... ],
 *         additional: [ field, ... ],
 *       }
 *     }
 *
 * Each field has `name`, `type`, `label` and `classes`; headings add
 * `headingLevel`; fields that collect a value add `required` and `default`,
 * plus `placeholder`, `options` and `validation` where the type has them.
 * Display flags and other admin-only keys are not sent.
 */
final class InnerBlockFields {

	/**
	 * The Store API extension namespace our values travel under.
	 */
	public const NAMESPACE = 'checkout-fieldkit';

	/**
	 * The sections our inner blocks render, in checkout order.
	 */
	public const SECTIONS = array( 'contact', 'additional' );

	/**
	 * The Block field set.
	 *
	 * @var FieldSetRepository
	 */
	private $field_set;

	/**
	 * Field type registry.
	 *
	 * @var FieldTypes
	 */
	private $types;

	/**
	 * Constructor.
	 *
	 * @param FieldSetRepository $field_set The Block field set.
	 * @param FieldTypes         $types     Field type registry.
	 */
	public function __construct( FieldSetRepository $field_set, FieldTypes $types ) {
		$this->field_set = $field_set;
		$this->types     = $types;
	}

	/**
	 * The enabled custom fields of one section, or of both in checkout order.
	 *
	 * @param string|null $section 'contact', 'additional', or null for both.
	 * @return array<int, array<string, mixed>> Normalised fields.
	 */
	public function fields( ?string $section = null ): array {
		$set    = $this->field_set->get();
		$result = array();
		foreach ( null === $section ? self::SECTIONS : array( $section ) as $name ) {
			if ( ! in_array( $name, self::SECTIONS, true ) ) {
				continue;
			}
			foreach ( $set['sections'][ $name ] ?? array() as $field ) {
				if ( empty( $field['builtIn'] ) && ! empty( $field['enabled'] ) ) {
					$result[] = $field;
				}
			}
		}

		return $result;
	}

	/**
	 * The fields of both sections that collect a value (no headings or paragraphs).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function value_fields(): array {
		return array_values(
			array_filter(
				$this->fields(),
				function ( $field ) {
					return $this->collects_value( $field );
				}
			)
		);
	}

	/**
	 * The section an enabled custom field of ours is in.
	 *
	 * @param string $name Field name.
	 * @return string|null Null when no enabled custom Contact or Additional field has that name.
	 */
	public function section_of( string $name ): ?string {
		foreach ( self::SECTIONS as $section ) {
			if ( in_array( $name, array_column( $this->fields( $section ), 'name' ), true ) ) {
				return $section;
			}
		}

		return null;
	}

	/**
	 * What the inner blocks need in the browser (see the class docblock).
	 *
	 * @return array{namespace: string, sections: array<string, array<int, array<string, mixed>>>}
	 */
	public function script_data(): array {
		$sections = array();
		foreach ( self::SECTIONS as $section ) {
			$sections[ $section ] = array_map( array( $this, 'client_field' ), $this->fields( $section ) );
		}

		return array(
			'namespace' => self::NAMESPACE,
			'sections'  => $sections,
		);
	}

	/**
	 * One field as the browser gets it.
	 *
	 * @param array<string, mixed> $field Normalised field.
	 * @return array<string, mixed>
	 */
	private function client_field( array $field ): array {
		$type   = (string) $field['type'];
		$client = array(
			'name'    => (string) $field['name'],
			'type'    => $type,
			'label'   => (string) $field['label'],
			'classes' => array_values( array_map( 'strval', (array) $field['classes'] ) ),
		);

		if ( 'heading' === $type ) {
			$client['headingLevel'] = (string) $field['headingLevel'];
		}

		if ( ! $this->collects_value( $field ) ) {
			return $client;
		}

		$client['required'] = ! empty( $field['required'] );
		$client['default']  = (string) $field['default'];

		if ( $this->has( $type, 'placeholder' ) ) {
			$client['placeholder'] = (string) $field['placeholder'];
		}
		if ( $this->has( $type, 'options' ) ) {
			$client['options'] = array();
			foreach ( (array) $field['options'] as $option ) {
				$client['options'][] = array(
					'value' => (string) $option['value'],
					'label' => (string) $option['label'],
				);
			}
		}
		if ( $this->has( $type, 'validation' ) ) {
			$client['validation'] = array_values( array_map( 'strval', (array) $field['validation'] ) );
		}

		return $client;
	}

	/**
	 * Whether a field's type collects a value.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return bool
	 */
	private function collects_value( array $field ): bool {
		$definition = $this->types->get( CheckoutType::BLOCK, (string) $field['type'] );

		return null !== $definition && $definition['collectsValue'];
	}

	/**
	 * Whether a setting applies to a Block type.
	 *
	 * @param string $type    Type name.
	 * @param string $setting Field key.
	 * @return bool
	 */
	private function has( string $type, string $setting ): bool {
		return $this->types->has_setting( CheckoutType::BLOCK, $type, $setting );
	}
}
