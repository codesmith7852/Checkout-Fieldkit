<?php
/**
 * The field type registry.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Which field types each checkout type supports, in which sections, and which
 * field settings apply to each (spec §2.4). Also lists the validation choices.
 *
 * A type definition is a plain array that serialises straight to JSON:
 *
 *     name              string        Type name, the field's `type` value.
 *     label             string        Name shown in the admin.
 *     sections          string[]      Sections the type is allowed in.
 *     settings          string[]      Field keys that apply to the type, in editor order.
 *     collectsValue     bool          False for display fields (heading, paragraph).
 *     hasOptions        bool          Choice types, which have `options`.
 *     multiple          bool          Choice types whose value is a comma-separated list.
 *     impliedValidation string|null   Validation every value gets, for example 'email'.
 *     builtInOnly       bool          Only WooCommerce's own fields have this type (country,
 *                                     state, and phone on the Block checkout); never offered
 *                                     for custom fields. Listed so the admin can name it.
 */
final class FieldTypes {

	/**
	 * Section lists.
	 *
	 * @var Sections
	 */
	private $sections;

	/**
	 * Constructor.
	 *
	 * @param Sections $sections Section lists.
	 */
	public function __construct( Sections $sections ) {
		$this->sections = $sections;
	}

	/**
	 * Every type of a checkout type, keyed by name, custom types first in
	 * the order the admin offers them, then the built-in-only types.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return array<string, array<string, mixed>> Type definitions (see the class docblock).
	 */
	public function for_checkout( string $checkout_type ): array {
		if ( ! CheckoutType::is_valid( $checkout_type ) ) {
			return array();
		}

		$all_sections = $this->sections->names( $checkout_type );

		/**
		 * Filters the field types of a checkout type.
		 *
		 * Add, remove or change types. Missing keys of a definition take their
		 * defaults (label: the name; sections: every section; settings: label;
		 * collectsValue: true; hasOptions, multiple, builtInOnly: false;
		 * impliedValidation: null). A new type also needs code that renders,
		 * sanitises and validates it on the checkout.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, array<string, mixed>> $types         Type definitions keyed by name.
		 * @param string                              $checkout_type 'classic' or 'block'.
		 */
		$filtered = apply_filters( 'checkout_fieldkit_field_types', $this->built_in_definitions( $checkout_type ), $checkout_type );

		$types = array();
		foreach ( (array) $filtered as $name => $definition ) {
			$name = (string) $name;
			if ( '' === $name || ! is_array( $definition ) ) {
				continue;
			}
			$types[ $name ] = $this->complete( $name, $definition, $all_sections );
		}

		return $types;
	}

	/**
	 * One type definition.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param string $type          Type name.
	 * @return array<string, mixed>|null Null when the checkout type has no such type.
	 */
	public function get( string $checkout_type, string $type ): ?array {
		$types = $this->for_checkout( $checkout_type );

		return isset( $types[ $type ] ) ? $types[ $type ] : null;
	}

	/**
	 * Names of the types a custom field can have on a checkout type, in the
	 * order the admin offers them.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return string[]
	 */
	public function custom_types( string $checkout_type ): array {
		$names = array();
		foreach ( $this->for_checkout( $checkout_type ) as $name => $definition ) {
			if ( ! $definition['builtInOnly'] ) {
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * Whether a custom field may have a type in a section (rule 5 of spec §2.5).
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param string $section       Section name.
	 * @param string $type          Type name.
	 * @return bool
	 */
	public function is_allowed( string $checkout_type, string $section, string $type ): bool {
		$definition = $this->get( $checkout_type, $type );

		return null !== $definition
			&& ! $definition['builtInOnly']
			&& in_array( $section, $definition['sections'], true );
	}

	/**
	 * Whether a field setting applies to a type.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param string $type          Type name.
	 * @param string $setting       Field key, for example 'options'.
	 * @return bool False for an unknown type.
	 */
	public function has_setting( string $checkout_type, string $type, string $setting ): bool {
		$definition = $this->get( $checkout_type, $type );

		return null !== $definition && in_array( $setting, $definition['settings'], true );
	}

	/**
	 * The validation choices of a checkout type (spec §2.4).
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return array<int, array{name: string, label: string}>
	 */
	public function validations( string $checkout_type ): array {
		$labels = array(
			'email'    => __( 'Email address', 'checkout-fieldkit' ),
			'phone'    => __( 'Phone number', 'checkout-fieldkit' ),
			'postcode' => __( 'Postcode / ZIP', 'checkout-fieldkit' ),
			'state'    => __( 'State / County', 'checkout-fieldkit' ),
			'number'   => __( 'Number', 'checkout-fieldkit' ),
			'url'      => __( 'Web address (URL)', 'checkout-fieldkit' ),
		);

		if ( CheckoutType::CLASSIC === $checkout_type ) {
			$names = array( 'email', 'phone', 'postcode', 'state', 'number', 'url' );
		} elseif ( CheckoutType::BLOCK === $checkout_type ) {
			$names = array( 'email', 'phone', 'postcode', 'url' );
		} else {
			$names = array();
		}

		$result = array();
		foreach ( $names as $name ) {
			$result[] = array(
				'name'  => $name,
				'label' => $labels[ $name ],
			);
		}

		return $result;
	}

	/**
	 * Names of the validation choices of a checkout type.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return string[]
	 */
	public function validation_names( string $checkout_type ): array {
		return array_column( $this->validations( $checkout_type ), 'name' );
	}

	/**
	 * The types of both checkout types as lists, ready to serialise for the
	 * admin and the REST context route: {classic: [definition, ...], block: [...]}.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function describe(): array {
		$result = array();
		foreach ( CheckoutType::all() as $checkout_type ) {
			$result[ $checkout_type ] = array_values( $this->for_checkout( $checkout_type ) );
		}

		return $result;
	}

	/**
	 * Fills in the missing keys of a type definition.
	 *
	 * @param string               $name         Type name.
	 * @param array<string, mixed> $definition   Possibly partial definition.
	 * @param string[]             $all_sections Sections of the checkout type.
	 * @return array<string, mixed>
	 */
	private function complete( string $name, array $definition, array $all_sections ): array {
		$sections = isset( $definition['sections'] ) && is_array( $definition['sections'] )
			? array_values( array_intersect( $all_sections, $definition['sections'] ) )
			: $all_sections;

		return array(
			'name'              => $name,
			'label'             => isset( $definition['label'] ) ? (string) $definition['label'] : $name,
			'sections'          => $sections,
			'settings'          => isset( $definition['settings'] ) && is_array( $definition['settings'] ) ? array_values( array_map( 'strval', $definition['settings'] ) ) : array( 'label' ),
			'collectsValue'     => isset( $definition['collectsValue'] ) ? (bool) $definition['collectsValue'] : true,
			'hasOptions'        => ! empty( $definition['hasOptions'] ),
			'multiple'          => ! empty( $definition['multiple'] ),
			'impliedValidation' => isset( $definition['impliedValidation'] ) && '' !== $definition['impliedValidation'] ? (string) $definition['impliedValidation'] : null,
			'builtInOnly'       => ! empty( $definition['builtInOnly'] ),
		);
	}

	/**
	 * The plugin's own types of a checkout type, before the filter.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return array<string, array<string, mixed>>
	 */
	private function built_in_definitions( string $checkout_type ): array {
		$display  = array( 'showInEmails', 'showOnOrderPages' );
		$text     = array_merge( array( 'label', 'placeholder', 'default', 'required', 'validation', 'classes' ), $display );
		$textarea = array_merge( array( 'label', 'placeholder', 'default', 'required', 'classes' ), $display );
		$dropdown = array_merge( array( 'label', 'placeholder', 'options', 'default', 'required', 'classes' ), $display );
		$choices  = array_merge( array( 'label', 'options', 'default', 'required', 'classes' ), $display );
		$plain    = array_merge( array( 'label', 'default', 'required', 'classes' ), $display );
		$single   = array( 'hasOptions' => true );
		$multiple = array(
			'hasOptions' => true,
			'multiple'   => true,
		);

		// Name => [label, settings, extra flags, block sections or null when not on Block].
		$catalogue = array(
			'text'           => array( __( 'Text', 'checkout-fieldkit' ), $text, array(), 'all' ),
			'number'         => array( __( 'Number', 'checkout-fieldkit' ), $text, array( 'impliedValidation' => 'number' ), 'all' ),
			'email'          => array( __( 'Email', 'checkout-fieldkit' ), $text, array( 'impliedValidation' => 'email' ), 'all' ),
			'url'            => array( __( 'Web address (URL)', 'checkout-fieldkit' ), $text, array( 'impliedValidation' => 'url' ), 'all' ),
			'tel'            => array( __( 'Phone', 'checkout-fieldkit' ), $text, array( 'impliedValidation' => 'phone' ), null ),
			'password'       => array( __( 'Password', 'checkout-fieldkit' ), $text, array(), null ),
			'hidden'         => array( __( 'Hidden', 'checkout-fieldkit' ), array_merge( array( 'label', 'default', 'classes' ), $display ), array(), null ),
			'textarea'       => array( __( 'Text area', 'checkout-fieldkit' ), $textarea, array(), array( 'contact', 'additional' ) ),
			'select'         => array( __( 'Dropdown', 'checkout-fieldkit' ), $dropdown, $single, 'all' ),
			'multiselect'    => array( __( 'Multi-select dropdown', 'checkout-fieldkit' ), $dropdown, $multiple, null ),
			'radio'          => array( __( 'Radio buttons', 'checkout-fieldkit' ), $choices, $single, array( 'contact', 'additional' ) ),
			'checkbox'       => array( __( 'Checkbox', 'checkout-fieldkit' ), $plain, array(), 'all' ),
			'checkboxgroup'  => array( __( 'Checkbox group', 'checkout-fieldkit' ), $choices, $multiple, null ),
			'date'           => array( __( 'Date', 'checkout-fieldkit' ), $plain, array(), null ),
			'time'           => array( __( 'Time', 'checkout-fieldkit' ), $plain, array(), null ),
			'datetime-local' => array( __( 'Date and time', 'checkout-fieldkit' ), $plain, array(), null ),
			'month'          => array( __( 'Month', 'checkout-fieldkit' ), $plain, array(), null ),
			'week'           => array( __( 'Week', 'checkout-fieldkit' ), $plain, array(), null ),
			'heading'        => array( __( 'Heading', 'checkout-fieldkit' ), array( 'label', 'headingLevel', 'classes' ), array( 'collectsValue' => false ), array( 'contact', 'additional' ) ),
			'paragraph'      => array( __( 'Paragraph', 'checkout-fieldkit' ), array( 'label', 'classes' ), array( 'collectsValue' => false ), array( 'contact', 'additional' ) ),
		);

		$types = array();
		foreach ( $catalogue as $name => list( $label, $settings, $flags, $block_sections ) ) {
			if ( CheckoutType::BLOCK === $checkout_type && null === $block_sections ) {
				continue;
			}
			$sections       = CheckoutType::BLOCK === $checkout_type && is_array( $block_sections ) ? $block_sections : null;
			$types[ $name ] = array_merge(
				array(
					'label'    => $label,
					'settings' => $settings,
				),
				null === $sections ? array() : array( 'sections' => $sections ),
				$flags
			);
		}

		// Types only WooCommerce's own fields have. Their settings are those of the built-in field.
		$built_in_only = array(
			'country' => __( 'Country / Region', 'checkout-fieldkit' ),
			'state'   => __( 'State / County', 'checkout-fieldkit' ),
		);
		if ( CheckoutType::BLOCK === $checkout_type ) {
			$built_in_only['tel'] = __( 'Phone', 'checkout-fieldkit' );
		}
		foreach ( $built_in_only as $name => $label ) {
			$types[ $name ] = array(
				'label'       => $label,
				'sections'    => CheckoutType::CLASSIC === $checkout_type ? array( 'billing', 'shipping' ) : array( 'address' ),
				'settings'    => $text,
				'builtInOnly' => true,
			);
		}

		return $types;
	}
}
