<?php
/**
 * WooCommerce's own fields of each checkout type.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Fields;

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\Blocks\Domain\Services\CoreCheckoutFields;
use Automattic\WooCommerce\Blocks\Package;

defined( 'ABSPATH' ) || exit;

/**
 * The built-in fields of each checkout type (spec §2.3), as a field set: what
 * a store has before anything is saved, and what Reset returns to.
 *
 * - Classic: WooCommerce's billing and shipping address fields for the store's
 *   base country (WC_Countries::get_address_fields()), plus order_comments.
 * - Block: WooCommerce's core block checkout fields; email in Contact, the
 *   address fields in Address.
 *
 * Defaults must not include the plugin's own changes. While they are being
 * read, is_computing() returns true, and every filter this plugin adds to
 * WooCommerce's field or locale hooks must return its input unchanged:
 *
 *     if ( DefaultFields::is_computing() ) {
 *         return $fields;
 *     }
 *
 * That also stops the recursion of a field filter that reads a field set,
 * which falls back to these defaults. Filters of other plugins do apply, so
 * a field another plugin adds to the address fields counts as built-in.
 */
final class DefaultFields {

	/**
	 * Schema version of the field sets this class returns.
	 */
	public const VERSION = 1;

	/**
	 * How deeply defaults are being computed right now.
	 *
	 * @var int
	 */
	private static $computing = 0;

	/**
	 * Computed sections per checkout type and locale.
	 *
	 * @var array<string, array<string, array<int, array<string, mixed>>>>
	 */
	private $cache = array();

	/**
	 * Whether built-in fields are being read from WooCommerce right now. Our
	 * own field and locale filters return their input unchanged while it is true.
	 *
	 * @return bool
	 */
	public static function is_computing(): bool {
		return self::$computing > 0;
	}

	/**
	 * The default field set of a checkout type.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return array{version: int, sections: array<string, array<int, array<string, mixed>>>} Fields have every key of
	 *         Field::DEFAULTS. Run them through FieldNormalizer to add extension keys.
	 */
	public function field_set( string $checkout_type ): array {
		return array(
			'version'  => self::VERSION,
			'sections' => $this->sections( $checkout_type ),
		);
	}

	/**
	 * One built-in field.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param string $name          Field name.
	 * @return array<string, mixed>|null Null when it is not a built-in field of that checkout type.
	 */
	public function get( string $checkout_type, string $name ): ?array {
		foreach ( $this->sections( $checkout_type ) as $fields ) {
			foreach ( $fields as $field ) {
				if ( $field['name'] === $name ) {
					return $field;
				}
			}
		}

		return null;
	}

	/**
	 * The section a built-in field belongs to.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param string $name          Field name.
	 * @return string|null Null when it is not a built-in field of that checkout type.
	 */
	public function section_of( string $checkout_type, string $name ): ?string {
		foreach ( $this->sections( $checkout_type ) as $section => $fields ) {
			if ( in_array( $name, array_column( $fields, 'name' ), true ) ) {
				return $section;
			}
		}

		return null;
	}

	/**
	 * Names of the built-in fields of a checkout type, in display order.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return string[]
	 */
	public function names( string $checkout_type ): array {
		$names = array();
		foreach ( $this->sections( $checkout_type ) as $fields ) {
			$names = array_merge( $names, array_column( $fields, 'name' ) );
		}

		return $names;
	}

	/**
	 * The settings of a built-in field that are ours to change (spec §2.3).
	 * Position is always ours; it is not a setting.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param string $name          Field name.
	 * @return string[] Field keys.
	 */
	public function editable_settings( string $checkout_type, string $name ): array {
		if ( CheckoutType::BLOCK === $checkout_type ) {
			return 'email' === $name ? array() : array( 'label', 'required', 'enabled' );
		}

		return array( 'label', 'placeholder', 'default', 'required', 'enabled', 'validation', 'classes' );
	}

	/**
	 * Names a custom field may not use (rule 1 of spec §2.5): every built-in
	 * name of either checkout type, whether or not this store shows it, and
	 * the names of WooCommerce's other checkout inputs.
	 *
	 * @return string[]
	 */
	public function reserved_names(): array {
		$address = array( 'first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone' );
		$names   = array_merge(
			$address,
			array( 'email', 'billing_email', 'shipping_email' ),
			array_map(
				static function ( $key ) {
					return 'billing_' . $key;
				},
				$address
			),
			array_map(
				static function ( $key ) {
					return 'shipping_' . $key;
				},
				$address
			),
			array( 'order_comments', 'account_username', 'account_password', 'createaccount', 'ship_to_different_address', 'terms', 'payment_method', 'shipping_method' ),
			$this->names( CheckoutType::CLASSIC ),
			$this->names( CheckoutType::BLOCK )
		);

		return array_values( array_unique( $names ) );
	}

	/**
	 * Whether WooCommerce would hand a value posted under this name to a
	 * setter of the order or the customer. The classic checkout calls
	 * set_<name>() on WC_Order and WC_Customer for every posted field that has
	 * one (WC_Checkout::create_order() and process_customer(), and
	 * WC_Form_Handler::save_address() on My Account), so a custom field named
	 * `role` or `status` would let a shopper set those. Custom fields may not
	 * use such a name, on either checkout type.
	 *
	 * @param string $name Field name.
	 * @return bool
	 */
	public function is_woocommerce_property( string $name ): bool {
		if ( '' === $name ) {
			return false;
		}

		foreach ( array( 'WC_Order', 'WC_Customer' ) as $class ) {
			if ( class_exists( $class ) && method_exists( $class, 'set_' . $name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The built-in fields of a checkout type, by section. Cached per language,
	 * except while WooCommerce is building its country locale (then the base
	 * country's locale is not available yet).
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function sections( string $checkout_type ): array {
		$key = $checkout_type . '|' . determine_locale();
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		++self::$computing;
		try {
			if ( CheckoutType::CLASSIC === $checkout_type ) {
				$sections = $this->classic_sections();
			} elseif ( CheckoutType::BLOCK === $checkout_type ) {
				$sections = $this->block_sections();
			} else {
				$sections = array();
			}
		} finally {
			--self::$computing;
		}

		if ( ! doing_filter( 'woocommerce_get_country_locale' ) && ! doing_filter( 'woocommerce_get_country_locale_default' ) && ! doing_filter( 'woocommerce_get_country_locale_base' ) ) {
			$this->cache[ $key ] = $sections;
		}

		return $sections;
	}

	/**
	 * Classic built-ins: the address fields for the base country, and order notes.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function classic_sections(): array {
		$countries = function_exists( 'WC' ) ? WC()->countries : null;
		if ( ! $countries instanceof \WC_Countries ) {
			return array(
				'billing'    => array(),
				'shipping'   => array(),
				'additional' => array( $this->order_comments() ),
			);
		}

		/*
		 * WooCommerce caches the country locale. Build it afresh, so it does
		 * not carry our changes into the defaults, and drop that copy
		 * afterwards, so WooCommerce rebuilds it with our changes when it
		 * next needs it.
		 */
		$countries->locale = array();
		try {
			$country  = $countries->get_base_country();
			$billing  = $countries->get_address_fields( $country, 'billing_' );
			$shipping = $countries->get_address_fields( $country, 'shipping_' );
		} finally {
			$countries->locale = array();
		}

		return array(
			'billing'    => $this->from_woocommerce( (array) $billing ),
			'shipping'   => $this->from_woocommerce( (array) $shipping ),
			'additional' => array( $this->order_comments() ),
		);
	}

	/**
	 * WooCommerce's order notes field, as WC_Checkout defines it.
	 *
	 * @return array<string, mixed>
	 */
	private function order_comments(): array {
		return $this->built_in(
			array(
				'name'        => 'order_comments',
				'type'        => 'textarea',
				// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- WooCommerce's own strings, translated by WooCommerce.
				'label'       => __( 'Order notes', 'woocommerce' ),
				'placeholder' => esc_attr__( 'Notes about your order, e.g. special notes for delivery.', 'woocommerce' ),
				// phpcs:enable
				'classes'     => array( 'notes' ),
			)
		);
	}

	/**
	 * Converts WooCommerce classic field definitions to fields.
	 *
	 * @param array<string, mixed> $definitions WooCommerce fields keyed by name, in display order.
	 * @return array<int, array<string, mixed>>
	 */
	private function from_woocommerce( array $definitions ): array {
		$fields = array();
		foreach ( $definitions as $name => $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			$options = array();
			if ( isset( $definition['options'] ) && is_array( $definition['options'] ) ) {
				foreach ( $definition['options'] as $value => $label ) {
					$options[] = array(
						'value' => (string) $value,
						'label' => is_scalar( $label ) ? (string) $label : '',
					);
				}
			}

			$fields[] = $this->built_in(
				array(
					'name'        => (string) $name,
					'type'        => isset( $definition['type'] ) && is_string( $definition['type'] ) ? $definition['type'] : 'text',
					'required'    => ! empty( $definition['required'] ),
					'label'       => isset( $definition['label'] ) && is_scalar( $definition['label'] ) ? (string) $definition['label'] : '',
					'placeholder' => isset( $definition['placeholder'] ) && is_scalar( $definition['placeholder'] ) ? (string) $definition['placeholder'] : '',
					'default'     => isset( $definition['default'] ) && is_scalar( $definition['default'] ) ? (string) $definition['default'] : '',
					'options'     => $options,
					'validation'  => isset( $definition['validate'] ) ? array_values( array_filter( (array) $definition['validate'], 'is_string' ) ) : array(),
					'classes'     => isset( $definition['class'] ) ? array_values( array_filter( (array) $definition['class'], 'is_string' ) ) : array(),
				)
			);
		}

		return $fields;
	}

	/**
	 * Block built-ins: WooCommerce's core block checkout fields.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function block_sections(): array {
		$sections = array(
			'contact'    => array(),
			'address'    => array(),
			'additional' => array(),
		);

		$core = $this->block_core_fields();
		uasort(
			$core,
			static function ( $a, $b ) {
				return ( isset( $a['index'] ) ? (int) $a['index'] : 0 ) <=> ( isset( $b['index'] ) ? (int) $b['index'] : 0 );
			}
		);

		$types = array(
			'email'   => 'email',
			'country' => 'country',
			'state'   => 'state',
			'phone'   => 'tel',
		);

		foreach ( $core as $name => $definition ) {
			$name                   = (string) $name;
			$section                = 'email' === $name ? 'contact' : 'address';
			$sections[ $section ][] = $this->built_in(
				array(
					'name'     => $name,
					'type'     => isset( $types[ $name ] ) ? $types[ $name ] : 'text',
					'enabled'  => empty( $definition['hidden'] ),
					'required' => ! empty( $definition['required'] ),
					'label'    => isset( $definition['label'] ) && is_scalar( $definition['label'] ) ? (string) $definition['label'] : '',
				)
			);
		}

		return $sections;
	}

	/**
	 * WooCommerce's core block checkout field definitions, keyed by name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function block_core_fields(): array {
		if ( class_exists( CoreCheckoutFields::class ) ) {
			return (array) CoreCheckoutFields::get_fields();
		}

		if ( class_exists( Package::class ) && class_exists( CheckoutFields::class ) ) {
			return (array) Package::container()->get( CheckoutFields::class )->get_core_fields();
		}

		return array();
	}

	/**
	 * A built-in field with every key.
	 *
	 * @param array<string, mixed> $values Values that differ from the key defaults.
	 * @return array<string, mixed>
	 */
	private function built_in( array $values ): array {
		return array_merge( Field::DEFAULTS, $values, array( 'builtIn' => true ) );
	}
}
