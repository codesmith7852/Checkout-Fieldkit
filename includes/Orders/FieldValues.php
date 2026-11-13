<?php
/**
 * Reads custom field values from orders and formats them for display.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Orders;

use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Fields\FieldTypes;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * The display side of custom field values, shared by both checkout types
 * (spec §4.2). It has no hooks; display locations call it.
 *
 * Where a value lives (meta_key()):
 * - Classic fields named `billing_*` or `shipping_*` are saved by WooCommerce
 *   itself as order meta `_<name>`;
 * - every other custom field, and every Block field we save, is order meta `<name>`.
 *
 * How a value is shown (format()):
 * - choice fields show the option label instead of the stored value (an
 *   option that no longer exists shows the stored value); multi-value fields
 *   list the labels separated by ", ";
 * - a ticked checkbox shows "Yes", an unticked one nothing;
 * - textarea keeps its line breaks (`<br />` in HTML, "\n" in plain text);
 * - FieldValues::HTML escapes for HTML; FieldValues::PLAIN returns plain text
 *   that the caller must escape for wherever it goes.
 *
 * Usage, for any checkout type:
 *
 *     $values = new FieldValues( Plugin::instance()->domain()->field_types() );
 *
 *     // One field.
 *     $raw  = $values->stored( $order, $field, 'block' );
 *     $html = $values->format( $field, $raw, 'block', FieldValues::HTML, $order );
 *
 *     // Ready-made rows for a list of fields (for example one section, or the
 *     // fields whose showInEmails is on): custom fields that collect a value
 *     // and have one on this order, in the given order.
 *     foreach ( $values->entries( $order, $fields, 'block', FieldValues::HTML ) as $entry ) {
 *         printf( '<p><strong>%s:</strong> %s</p>', $entry['label'], $entry['value'] ); // Both escaped.
 *     }
 *
 * Choosing which fields to show (display flags, fields still in the field set,
 * which orders belong to which checkout type: checkout_type_of()) is up to the
 * caller; see ValueDisplay.
 */
final class FieldValues {

	/**
	 * Format: escaped HTML, line breaks as `<br />`.
	 */
	public const HTML = 'html';

	/**
	 * Format: plain text, line breaks as "\n", not escaped.
	 */
	public const PLAIN = 'plain';

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
	 * The order meta key a custom field's value is stored under.
	 *
	 * @param string $name          Field name.
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return string
	 */
	public static function meta_key( string $name, string $checkout_type ): string {
		return CheckoutType::CLASSIC === $checkout_type && self::is_saved_by_woocommerce( $name ) ? '_' . $name : $name;
	}

	/**
	 * Whether WooCommerce's classic checkout saves a field itself (its name
	 * starts with `billing_` or `shipping_`), on the order as `_<name>` and on
	 * the customer as `<name>`.
	 *
	 * @param string $name Field name.
	 * @return bool
	 */
	public static function is_saved_by_woocommerce( string $name ): bool {
		return 0 === strpos( $name, 'billing_' ) || 0 === strpos( $name, 'shipping_' );
	}

	/**
	 * The checkout type an order was placed on: 'block' for orders created
	 * through the Store API (the Block checkout), otherwise 'classic'.
	 *
	 * @param WC_Order $order Order.
	 * @return string CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 */
	public static function checkout_type_of( WC_Order $order ): string {
		return 'store-api' === $order->get_created_via() ? CheckoutType::BLOCK : CheckoutType::CLASSIC;
	}

	/**
	 * A field's stored value on an order.
	 *
	 * @param WC_Order             $order         Order.
	 * @param array<string, mixed> $field         Field; only `name` is used.
	 * @param string               $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return string "" when nothing is stored.
	 */
	public function stored( WC_Order $order, array $field, string $checkout_type ): string {
		$name = isset( $field['name'] ) ? (string) $field['name'] : '';
		if ( '' === $name ) {
			return '';
		}

		$value = $order->get_meta( self::meta_key( $name, $checkout_type ), true );
		if ( is_array( $value ) ) {
			$value = implode( ',', array_filter( $value, 'is_scalar' ) );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * A stored value as it is shown.
	 *
	 * @param array<string, mixed> $field         Field.
	 * @param string               $value         Stored value.
	 * @param string               $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param string               $format        FieldValues::HTML or FieldValues::PLAIN.
	 * @param WC_Order|null        $order         The order the value belongs to, for the filter.
	 * @return string "" when there is nothing to show.
	 */
	public function format( array $field, string $value, string $checkout_type, string $format = self::HTML, ?WC_Order $order = null ): string {
		$text = $this->to_text( $field, $value, $checkout_type );

		if ( self::HTML === $format ) {
			$formatted = 'textarea' === ( isset( $field['type'] ) ? $field['type'] : '' ) ? nl2br( esc_html( $text ) ) : esc_html( $text );
		} else {
			$formatted = $text;
		}

		/**
		 * Filters how a custom field's stored value is shown on order pages,
		 * the admin order screen and in emails.
		 *
		 * For the 'html' format the result is printed as HTML, so escape
		 * anything you add. For 'plain' it is text. Return "" to show nothing.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $formatted     The value as it will be shown.
		 * @param string               $value         The stored value.
		 * @param array<string, mixed> $field         The field.
		 * @param string               $format        'html' or 'plain'.
		 * @param string               $checkout_type 'classic' or 'block'.
		 * @param WC_Order|null        $order         The order, when known.
		 */
		$formatted = apply_filters( 'checkout_fieldkit_display_value', $formatted, $value, $field, $format, $checkout_type, $order );

		return is_scalar( $formatted ) ? (string) $formatted : '';
	}

	/**
	 * The fields among $fields that have something to show on an order, with
	 * their label and formatted value, in the order given. Built-in fields,
	 * display fields and fields without a stored value are left out.
	 *
	 * @param WC_Order                         $order         Order.
	 * @param array<int, array<string, mixed>> $fields        Fields to consider (normalised).
	 * @param string                           $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param string                           $format        FieldValues::HTML (label and value escaped)
	 *                                                        or FieldValues::PLAIN (neither escaped).
	 * @return array<int, array{name: string, label: string, value: string}>
	 */
	public function entries( WC_Order $order, array $fields, string $checkout_type, string $format = self::HTML ): array {
		$entries = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || ! empty( $field['builtIn'] ) || ! $this->collects_value( $field, $checkout_type ) ) {
				continue;
			}

			$value = $this->stored( $order, $field, $checkout_type );
			if ( '' === $value ) {
				continue;
			}

			$formatted = $this->format( $field, $value, $checkout_type, $format, $order );
			if ( '' === $formatted ) {
				continue;
			}

			$name      = (string) $field['name'];
			$label     = isset( $field['label'] ) && '' !== trim( (string) $field['label'] ) ? (string) $field['label'] : $name;
			$entries[] = array(
				'name'  => $name,
				'label' => self::HTML === $format ? esc_html( $label ) : $label,
				'value' => $formatted,
			);
		}

		return $entries;
	}

	/**
	 * Whether a field's type collects a value (display fields do not).
	 *
	 * @param array<string, mixed> $field         Field.
	 * @param string               $checkout_type Checkout type.
	 * @return bool
	 */
	private function collects_value( array $field, string $checkout_type ): bool {
		$definition = $this->types->get( $checkout_type, isset( $field['type'] ) ? (string) $field['type'] : '' );

		return null === $definition || $definition['collectsValue'];
	}

	/**
	 * The text shown for a stored value, before escaping.
	 *
	 * @param array<string, mixed> $field         Field.
	 * @param string               $value         Stored value.
	 * @param string               $checkout_type Checkout type.
	 * @return string
	 */
	private function to_text( array $field, string $value, string $checkout_type ): string {
		$type = isset( $field['type'] ) ? (string) $field['type'] : '';

		if ( 'checkbox' === $type ) {
			return '' !== $value && '0' !== $value ? __( 'Yes', 'checkout-fieldkit' ) : '';
		}

		$definition = $this->types->get( $checkout_type, $type );
		if ( null === $definition || ! $definition['hasOptions'] ) {
			return $value;
		}

		$labels = array();
		foreach ( isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array() as $option ) {
			if ( is_array( $option ) && isset( $option['value'], $option['label'] ) ) {
				$labels[ (string) $option['value'] ] = (string) $option['label'];
			}
		}

		$values = $definition['multiple'] ? array_map( 'trim', explode( ',', $value ) ) : array( $value );
		$shown  = array();
		foreach ( $values as $item ) {
			if ( '' === $item ) {
				continue;
			}
			$shown[] = isset( $labels[ $item ] ) && '' !== $labels[ $item ] ? $labels[ $item ] : $item;
		}

		return implode( ', ', $shown );
	}
}
