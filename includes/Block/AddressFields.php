<?php
/**
 * Custom Address fields on the Block checkout, through WooCommerce's API.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Block;

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\Blocks\Package;
use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Environment\BlockSupport;
use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the custom fields of the Block field set's Address section with
 * woocommerce_register_additional_checkout_field() (spec §5, ADR 0008), so
 * WooCommerce renders them in both address forms and stores, validates and
 * displays their values itself:
 *
 * - id `checkout-fieldkit/<name>`, location `address`;
 * - text, select and checkbox as themselves; number, email and url as text
 *   with input attributes (pattern, title, autocomplete) and validation;
 * - required, select options (an empty first option becomes the placeholder),
 *   order (`index`, position × 10 like the built-in fields' order);
 * - the default value through `woocommerce_get_default_value_for_<id>`;
 * - validation `email`, `phone`, `postcode`, `url` and the type's own in the
 *   field's validate callback (WC_Validation for phone and postcode), plus the
 *   postcode for the address's country in the address location check.
 *
 * Values are stored by WooCommerce as order meta `_wc_billing/<id>` and
 * `_wc_shipping/<id>`, and on the customer under the same keys.
 *
 * Display flags: `showOnOrderPages` becomes `show_in_order_confirmation` and
 * also hides the field on the admin order screen; `showInEmails` is applied
 * through `woocommerce_filter_fields_for_order_confirmation` (WooCommerce
 * 10.1+; before that, emails follow `showOnOrderPages`).
 *
 * Disabled fields are not registered. Nothing is registered when the Block
 * checkout features are not available (see BlockSupport).
 */
final class AddressFields implements Service {

	/**
	 * Namespace of the ids our fields have in WooCommerce.
	 */
	public const ID_PREFIX = 'checkout-fieldkit/';

	/**
	 * The section whose custom fields this service registers.
	 */
	private const SECTION = 'address';

	/**
	 * Field types WooCommerce's API renders as text inputs, with their input attributes.
	 */
	private const TEXT_TYPES = array( 'text', 'number', 'email', 'url' );

	/**
	 * Field set, settings and environment.
	 *
	 * @var Domain
	 */
	private $domain;

	/**
	 * Whether Block features can run; null to ask the domain.
	 *
	 * @var BlockSupport|null
	 */
	private $support;

	/**
	 * Our fields registered in this request, keyed by WooCommerce id.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $registered = array();

	/**
	 * Constructor.
	 *
	 * @param Domain            $domain  Field set, settings and environment.
	 * @param BlockSupport|null $support Whether Block features can run; defaults to the domain's.
	 */
	public function __construct( Domain $domain, ?BlockSupport $support = null ) {
		$this->domain  = $domain;
		$this->support = $support;
	}

	/**
	 * Registers the fields when WooCommerce initialises, which is when its
	 * API expects them, and adds the validation and display hooks.
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_init', array( $this, 'register_fields' ) );
		add_action( 'woocommerce_blocks_validate_location_address_fields', array( $this, 'validate_address' ), 10, 3 );
		add_filter( 'woocommerce_filter_fields_for_order_confirmation', array( $this, 'filter_order_confirmation' ), 10, 4 );
		add_filter( 'woocommerce_admin_billing_fields', array( $this, 'filter_admin_fields' ), 20 );
		add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'filter_admin_fields' ), 20 );
	}

	/**
	 * Registers every enabled custom Address field with WooCommerce. Fields
	 * already registered in this request are skipped.
	 */
	public function register_fields(): void {
		if ( ! $this->support()->is_available() ) {
			return;
		}

		$fields = $this->domain->field_sets( CheckoutType::BLOCK )->get()['sections'][ self::SECTION ] ?? array();

		foreach ( array_values( $fields ) as $position => $field ) {
			$definition = $this->definition( $field, $position );
			if ( null === $definition || isset( $this->registered[ $definition['id'] ] ) ) {
				continue;
			}

			woocommerce_register_additional_checkout_field( $definition );
			if ( ! $this->woocommerce_has( $definition['id'] ) ) {
				continue; // WooCommerce refused it and said why ("doing it wrong").
			}
			$this->registered[ $definition['id'] ] = $field;

			add_filter( 'woocommerce_get_default_value_for_' . $definition['id'], array( $this, 'default_value' ), 10, 3 );
		}
	}

	/**
	 * The options passed to woocommerce_register_additional_checkout_field()
	 * for one field of the Address section.
	 *
	 * @param array<string, mixed> $field    A normalised field.
	 * @param int                  $position Its position in the section, from 0.
	 * @return array<string, mixed>|null Null for built-in, disabled and unsupported fields.
	 */
	public function definition( array $field, int $position ): ?array {
		$type = (string) $field['type'];
		if ( ! empty( $field['builtIn'] ) || empty( $field['enabled'] ) ) {
			return null;
		}

		$label      = '' !== trim( (string) $field['label'] ) ? (string) $field['label'] : (string) $field['name'];
		$definition = array(
			'id'                         => self::ID_PREFIX . $field['name'],
			'label'                      => $label,
			/* translators: %s: field label. */
			'optionalLabel'              => sprintf( __( '%s (optional)', 'checkout-fieldkit' ), $label ),
			'location'                   => self::SECTION,
			'required'                   => ! empty( $field['required'] ),
			'index'                      => ( $position + 1 ) * 10,
			'show_in_order_confirmation' => ! empty( $field['showOnOrderPages'] ),
		);

		if ( in_array( $type, self::TEXT_TYPES, true ) ) {
			return array_merge(
				$definition,
				array(
					'type'              => 'text',
					'attributes'        => $this->attributes( $type ),
					'validate_callback' => array( $this, 'validate_value' ),
				)
			);
		}

		if ( 'select' === $type ) {
			$options     = array();
			$placeholder = (string) $field['placeholder'];
			foreach ( (array) $field['options'] as $index => $option ) {
				if ( '' === (string) $option['value'] ) {
					if ( 0 === $index && '' !== (string) $option['label'] ) {
						$placeholder = (string) $option['label'];
					}
					continue;
				}
				$options[] = array(
					'value' => (string) $option['value'],
					'label' => (string) $option['label'],
				);
			}
			if ( ! $options ) {
				return null;
			}

			return array_merge(
				$definition,
				array(
					'type'    => 'select',
					'options' => $options,
				),
				'' !== $placeholder ? array( 'placeholder' => $placeholder ) : array()
			);
		}

		if ( 'checkbox' === $type ) {
			return array_merge( $definition, array( 'type' => 'checkbox' ) );
		}

		return null;
	}

	/**
	 * Validates a value of one of our text-like fields (WooCommerce calls this
	 * for non-empty values; empty values only fail when required).
	 *
	 * @param mixed                $value Submitted value.
	 * @param array<string, mixed> $field Field as WooCommerce registered it.
	 * @return true|WP_Error
	 */
	public function validate_value( $value, $field ) {
		$id    = is_array( $field ) && isset( $field['id'] ) ? (string) $field['id'] : '';
		$label = is_array( $field ) && isset( $field['label'] ) ? (string) $field['label'] : $id;
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( '' === $value ) {
			if ( is_array( $field ) && ! empty( $field['required'] ) ) {
				/* translators: %s: field label. */
				return new WP_Error( 'checkout_fieldkit_required', sprintf( __( '%s is required.', 'checkout-fieldkit' ), $label ) );
			}

			return true;
		}

		if ( ! isset( $this->registered[ $id ] ) ) {
			return true;
		}

		foreach ( $this->validations( $this->registered[ $id ] ) as $validation ) {
			$message = $this->problem( $validation, $value, null, $label );
			if ( '' !== $message ) {
				return new WP_Error( 'checkout_fieldkit_invalid_' . $validation, $message );
			}
		}

		return true;
	}

	/**
	 * Checks postcode fields against the address's country, which a single
	 * field's validate callback does not know.
	 *
	 * @param WP_Error             $errors  Errors of this address.
	 * @param array<string, mixed> $address Address values, keyed by field (WooCommerce id for additional fields).
	 * @param string               $group   'billing' or 'shipping'.
	 */
	public function validate_address( $errors, $address, $group = '' ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Hook signature.
		if ( ! $errors instanceof WP_Error || ! is_array( $address ) ) {
			return;
		}

		$country = isset( $address['country'] ) && is_string( $address['country'] ) ? strtoupper( trim( $address['country'] ) ) : '';
		if ( '' === $country ) {
			return;
		}

		foreach ( $this->registered as $id => $field ) {
			$value = isset( $address[ $id ] ) && is_scalar( $address[ $id ] ) ? trim( (string) $address[ $id ] ) : '';
			if ( '' === $value || ! in_array( 'postcode', $this->validations( $field ), true ) || '' !== $this->problem( 'postcode', $value, null, '' ) ) {
				continue; // Empty, not a postcode field, or already refused by the field's own check.
			}

			$label   = '' !== trim( (string) $field['label'] ) ? (string) $field['label'] : (string) $field['name'];
			$message = $this->problem( 'postcode', $value, $country, $label );
			if ( '' !== $message ) {
				$errors->add( 'checkout_fieldkit_invalid_postcode', $message, array( 'key' => $id ) );
			}
		}
	}

	/**
	 * The default value of one of our fields, for a customer or order without
	 * a saved value.
	 *
	 * @param mixed  $value Default so far (null).
	 * @param string $group 'billing', 'shipping' or 'other'.
	 * @param mixed  $data  Customer or order.
	 * @return mixed
	 */
	public function default_value( $value, $group = '', $data = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Hook signature.
		$id = substr( current_filter(), strlen( 'woocommerce_get_default_value_for_' ) );
		if ( null !== $value || ! isset( $this->registered[ $id ] ) ) {
			return $value;
		}

		$field   = $this->registered[ $id ];
		$default = (string) $field['default'];
		if ( '' === $default ) {
			return $value;
		}

		if ( 'checkbox' === $field['type'] ) {
			return '1' === $default ? '1' : $value;
		}

		if ( 'select' === $field['type'] && ! in_array( $default, array_map( 'strval', array_column( (array) $field['options'], 'value' ) ), true ) ) {
			return $value;
		}

		return $default;
	}

	/**
	 * Decides whether one of our fields shows on an order confirmation:
	 * emails follow `showInEmails`, the thank-you page and the customer's
	 * order view follow `showOnOrderPages`.
	 *
	 * @param bool                 $show    Whether WooCommerce would show it.
	 * @param array<string, mixed> $field   Field with its value.
	 * @param array<string, mixed> $fields  Every field being shown.
	 * @param array<string, mixed> $context Where it is shown; `caller` names the method.
	 * @return bool
	 */
	public function filter_order_confirmation( $show, $field, $fields = array(), $context = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClassBeforeLastUsed,Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- Hook signature.
		$id = is_array( $field ) && isset( $field['id'] ) ? (string) $field['id'] : '';
		if ( ! isset( $this->registered[ $id ] ) ) {
			return $show;
		}

		$caller = is_array( $context ) && isset( $context['caller'] ) ? (string) $context['caller'] : '';

		return 0 === strpos( $caller, 'WC_Email::' )
			? ! empty( $this->registered[ $id ]['showInEmails'] )
			: ! empty( $this->registered[ $id ]['showOnOrderPages'] );
	}

	/**
	 * Removes our fields that should not show on order pages from the admin
	 * order screen's billing and shipping details.
	 *
	 * @param mixed $fields Fields of the admin order screen.
	 * @return mixed
	 */
	public function filter_admin_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}

		foreach ( $fields as $key => $field ) {
			$id = is_array( $field ) && isset( $field['id'] ) ? (string) $field['id'] : '';
			if ( ! preg_match( '#^_wc_(?:billing|shipping)/(' . preg_quote( self::ID_PREFIX, '#' ) . '.+)$#', $id, $match ) ) {
				continue;
			}
			if ( isset( $this->registered[ $match[1] ] ) && empty( $this->registered[ $match[1] ]['showOnOrderPages'] ) ) {
				unset( $fields[ $key ] );
			}
		}

		return $fields;
	}

	/**
	 * Input attributes of a text-like type. WooCommerce allows only a few
	 * (no input type or inputmode), so number, email and url get a pattern the
	 * browser checks and a title that explains it.
	 *
	 * @param string $type Our field type.
	 * @return array<string, string>
	 */
	private function attributes( string $type ): array {
		switch ( $type ) {
			case 'number':
				return array(
					'pattern' => '[+\-]?(\d+(\.\d*)?|\.\d+)',
					'title'   => __( 'Enter a number, for example 12 or 4.5.', 'checkout-fieldkit' ),
				);
			case 'email':
				return array(
					'pattern'        => '[^@\s]+@[^@\s]+\.[^@\s]+',
					'title'          => __( 'Enter an email address, for example name@example.com.', 'checkout-fieldkit' ),
					'autocomplete'   => 'email',
					'autocapitalize' => 'none',
				);
			case 'url':
				return array(
					'pattern'        => 'https?://\S+',
					'title'          => __( 'Enter a web address that starts with http:// or https://.', 'checkout-fieldkit' ),
					'autocomplete'   => 'url',
					'autocapitalize' => 'none',
				);
		}

		return array();
	}

	/**
	 * The checks a field's values get: the type's own, then the chosen validations.
	 *
	 * @param array<string, mixed> $field A normalised field.
	 * @return string[]
	 */
	private function validations( array $field ): array {
		$implied = array(
			'number' => 'number',
			'email'  => 'email',
			'url'    => 'url',
		);
		$list    = isset( $implied[ $field['type'] ] ) ? array( $implied[ $field['type'] ] ) : array();

		return array_values( array_unique( array_merge( $list, array_map( 'strval', (array) $field['validation'] ) ) ) );
	}

	/**
	 * What is wrong with a value under one validation.
	 *
	 * @param string      $validation 'email', 'phone', 'postcode', 'url' or 'number'.
	 * @param string      $value      Non-empty value.
	 * @param string|null $country    Country for postcodes and phones, or null when unknown.
	 * @param string      $label      Field label for the message.
	 * @return string Empty when the value is fine.
	 */
	private function problem( string $validation, string $value, ?string $country, string $label ): string {
		switch ( $validation ) {
			case 'email':
				/* translators: %s: field label. */
				return is_email( $value ) ? '' : sprintf( __( '%s is not a valid email address.', 'checkout-fieldkit' ), $label );
			case 'url':
				/* translators: %s: field label. */
				return $this->is_url( $value ) ? '' : sprintf( __( '%s is not a valid web address. Start it with http:// or https://.', 'checkout-fieldkit' ), $label );
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
	private function is_url( string $value ): bool {
		$scheme = wp_parse_url( $value, PHP_URL_SCHEME );

		return in_array( is_string( $scheme ) ? strtolower( $scheme ) : '', array( 'http', 'https' ), true )
			&& false !== filter_var( $value, FILTER_VALIDATE_URL );
	}

	/**
	 * Whether WooCommerce has a checkout field with this id.
	 *
	 * @param string $id Field id.
	 * @return bool
	 */
	private function woocommerce_has( string $id ): bool {
		if ( ! class_exists( Package::class ) || ! class_exists( CheckoutFields::class ) ) {
			return false;
		}

		return Package::container()->get( CheckoutFields::class )->is_field( $id );
	}

	/**
	 * Whether Block features can run.
	 *
	 * @return BlockSupport
	 */
	private function support(): BlockSupport {
		return null !== $this->support ? $this->support : $this->domain->block_support();
	}
}
