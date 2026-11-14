<?php
/**
 * Checks classic checkout values WooCommerce does not check.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Classic;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §4, validation on submit. WooCommerce already checks required fields
 * and its own email, phone, postcode and state validations. This adds:
 *
 * - number: the value is a number;
 * - url: the value is an http or https web address;
 * - choice fields (select, radio, multiselect, checkbox group): every value is
 *   one of the field's options.
 *
 * Only non-empty values are checked, and messages name the field by its label
 * as WooCommerce's own do. Shipping fields are skipped when the shopper is not
 * shipping to a different address, by WooCommerce's own rule. The same checks
 * run when a customer saves an address in My Account.
 *
 * Sanitising and saving values is not done here (spec §4.1).
 */
final class CheckoutValidation implements Service {

	/**
	 * Types whose value must be among the options.
	 *
	 * @var string[]
	 */
	private const CHOICE_TYPES = array( 'select', 'radio', 'multiselect', 'checkboxgroup' );

	/**
	 * Types whose value is a list.
	 *
	 * @var string[]
	 */
	private const MULTIPLE_TYPES = array( 'multiselect', 'checkboxgroup' );

	/**
	 * Domain objects.
	 *
	 * @var Domain
	 */
	private $domain;

	/**
	 * Created on first use.
	 *
	 * @var FieldSet|null
	 */
	private $field_set = null;

	/**
	 * Created on first use.
	 *
	 * @var WooCommerceFields|null
	 */
	private $mapper = null;

	/**
	 * Constructor.
	 *
	 * @param Domain $domain Domain objects.
	 */
	public function __construct( Domain $domain ) {
		$this->domain = $domain;
	}

	/**
	 * Adds the checkout and My Account address checks.
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 10, 2 );
		add_action( 'woocommerce_after_save_address_validation', array( $this, 'validate_address' ), 10, 2 );
	}

	/**
	 * Checks posted checkout values and adds a WooCommerce error per problem.
	 *
	 * @param mixed $data   Posted data, as WC_Checkout::get_posted_data() gives it.
	 * @param mixed $errors WooCommerce's errors.
	 */
	public function validate_checkout( $data, $errors ): void {
		if ( ! is_array( $data ) || ! $errors instanceof WP_Error ) {
			return;
		}

		foreach ( array( 'billing', 'shipping', 'additional' ) as $section ) {
			if ( 'shipping' === $section && $this->skips_shipping( $data ) ) {
				continue;
			}

			foreach ( $this->field_set()->section( $section ) as $field ) {
				if ( ! $field['enabled'] || ! array_key_exists( $field['name'], $data ) ) {
					continue;
				}

				$message = $this->problem( $field, $data[ $field['name'] ] );
				if ( null !== $message ) {
					$errors->add(
						$field['name'] . '_validation',
						sprintf( $message, '<strong>' . esc_html( self::checkout_label( $field, $section ) ) . '</strong>' ),
						array( 'id' => $field['name'] )
					);
				}
			}
		}
	}

	/**
	 * Checks an address a customer saves in My Account and adds a notice per
	 * problem, which stops the save. WooCommerce has checked the nonce.
	 *
	 * @param mixed $user_id      User ID.
	 * @param mixed $address_type 'billing' or 'shipping'.
	 */
	public function validate_address( $user_id, $address_type ): void {
		if ( ! in_array( $address_type, array( 'billing', 'shipping' ), true ) ) {
			return;
		}

		foreach ( $this->field_set()->section( $address_type ) as $field ) {
			$name = $field['name'];
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC_Form_Handler::save_address() verified the nonce before this action.
			if ( ! $field['enabled'] || ! isset( $_POST[ $name ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; wc_clean() sanitises.
			$message = $this->problem( $field, wc_clean( wp_unslash( $_POST[ $name ] ) ) );
			if ( null !== $message ) {
				wc_add_notice( sprintf( $message, '<strong>' . esc_html( self::label( $field ) ) . '</strong>' ), 'error', array( 'id' => $name ) );
			}
		}
	}

	/**
	 * The first problem with a field's value, as a message with %s for the
	 * field's label, or null when the value is fine or empty.
	 *
	 * @param array<string, mixed> $field Our field.
	 * @param mixed                $value Value as posted.
	 * @return string|null
	 */
	public function problem( array $field, $value ): ?string {
		$multiple = in_array( $field['type'], self::MULTIPLE_TYPES, true );
		$values   = self::values( $value, $multiple );
		if ( array() === $values ) {
			return null;
		}

		if ( ! $field['builtIn'] && in_array( $field['type'], self::CHOICE_TYPES, true ) ) {
			$options = array_map( 'strval', array_column( $field['options'], 'value' ) );
			if ( array_diff( $values, $options ) ) {
				/* translators: %s: field label. */
				return __( '%s is not one of the choices offered.', 'checkout-fieldkit' );
			}
		}

		if ( $multiple ) {
			return null;
		}

		$validations = $this->mapper()->validations( $field );
		if ( in_array( 'number', $validations, true ) && ! preg_match( '/^[+-]?(\d+(\.\d+)?|\.\d+)$/', $values[0] ) ) {
			/* translators: %s: field label. */
			return __( '%s is not a valid number.', 'checkout-fieldkit' );
		}
		if ( in_array( 'url', $validations, true ) && ! self::is_web_address( $values[0] ) ) {
			/* translators: %s: field label. */
			return __( '%s is not a valid web address.', 'checkout-fieldkit' );
		}

		return null;
	}

	/**
	 * Whether WooCommerce skips the shipping fields: the shopper is not
	 * shipping to a different address, or the cart needs no shipping address
	 * (WC_Checkout::maybe_skip_fieldset()).
	 *
	 * @param array<string, mixed> $data Posted data.
	 * @return bool
	 */
	private function skips_shipping( array $data ): bool {
		if ( empty( $data['ship_to_different_address'] ) ) {
			return true;
		}

		return ! ( function_exists( 'WC' ) && WC()->cart && WC()->cart->needs_shipping_address() );
	}

	/**
	 * Whether a value is an http or https address with a host.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private static function is_web_address( string $value ): bool {
		if ( preg_match( '/\s/', $value ) ) {
			return false;
		}

		$parts = wp_parse_url( $value );

		return is_array( $parts )
			&& isset( $parts['scheme'], $parts['host'] )
			&& in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true )
			&& '' !== $parts['host'];
	}

	/**
	 * The non-empty values of a posted value.
	 *
	 * @param mixed $value    Value as posted: a string, or a list for a checkbox group.
	 * @param bool  $multiple Whether the field takes a list (WooCommerce joins a
	 *                        multiselect's values with ", ").
	 * @return string[]
	 */
	private static function values( $value, bool $multiple ): array {
		if ( is_array( $value ) ) {
			$values = $value;
		} elseif ( is_scalar( $value ) ) {
			$values = $multiple ? explode( ',', (string) $value ) : array( (string) $value );
		} else {
			$values = array();
		}

		$values = array_map( 'trim', array_map( 'strval', array_filter( $values, 'is_scalar' ) ) );

		return array_values( array_filter( $values, 'strlen' ) );
	}

	/**
	 * A field's label as WooCommerce's checkout messages show it: "Billing …"
	 * or "Shipping …" for address fields.
	 *
	 * @param array<string, mixed> $field   Our field.
	 * @param string               $section Section name.
	 * @return string
	 */
	private static function checkout_label( array $field, string $section ): string {
		$label = self::label( $field );

		// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- WooCommerce's own strings, translated by WooCommerce.
		if ( 'billing' === $section ) {
			/* translators: %s: field name */
			return sprintf( _x( 'Billing %s', 'checkout-validation', 'woocommerce' ), $label );
		}
		if ( 'shipping' === $section ) {
			/* translators: %s: field name */
			return sprintf( _x( 'Shipping %s', 'checkout-validation', 'woocommerce' ), $label );
		}
		// phpcs:enable

		return $label;
	}

	/**
	 * A field's label, or its placeholder or name when it has none.
	 *
	 * @param array<string, mixed> $field Our field.
	 * @return string
	 */
	private static function label( array $field ): string {
		foreach ( array( 'label', 'placeholder', 'name' ) as $key ) {
			if ( '' !== (string) $field[ $key ] ) {
				return (string) $field[ $key ];
			}
		}

		return '';
	}

	/**
	 * The classic field set.
	 *
	 * @return FieldSet
	 */
	private function field_set(): FieldSet {
		if ( null === $this->field_set ) {
			$this->field_set = new FieldSet( $this->domain->field_sets( CheckoutType::CLASSIC ) );
		}

		return $this->field_set;
	}

	/**
	 * The field mapper, which knows a field's validations.
	 *
	 * @return WooCommerceFields
	 */
	private function mapper(): WooCommerceFields {
		if ( null === $this->mapper ) {
			$this->mapper = new WooCommerceFields( $this->domain->field_types() );
		}

		return $this->mapper;
	}
}
