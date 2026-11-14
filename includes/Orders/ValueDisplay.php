<?php
/**
 * Shows custom field values after the order is placed.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Orders;

use CheckoutFieldKit\Service;
use CheckoutFieldKit\Storage\FieldSetRepository;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the values of one field set's custom fields in the display locations
 * of spec §4.2, for orders placed on that field set's checkout type
 * (FieldValues::checkout_type_of()):
 *
 * - emails (fields with showInEmails): WooCommerce's order meta list, in
 *   admin and customer emails, HTML and plain text;
 * - order pages (fields with showOnOrderPages): a table after the order
 *   details on the thank-you page and the My Account order view; on the
 *   admin order screen, `billing` fields under the billing address,
 *   `shipping` fields under the shipping address and fields of every other
 *   section under the general details.
 *
 * Only fields in the current field set are shown, so values left behind by
 * renamed or deleted fields are not. Disabled fields still in the set keep
 * showing the answers orders already have. Values are read and formatted by
 * FieldValues.
 */
final class ValueDisplay implements Service {

	/**
	 * The field set whose values are shown.
	 *
	 * @var FieldSetRepository
	 */
	private $field_set;

	/**
	 * Reads and formats values.
	 *
	 * @var FieldValues
	 */
	private $values;

	/**
	 * Whether the email being rendered is plain text; null outside an email's order meta.
	 *
	 * @var bool|null
	 */
	private $plain_email = null;

	/**
	 * Constructor.
	 *
	 * @param FieldSetRepository $field_set The field set whose values are shown.
	 * @param FieldValues        $values    Reads and formats values.
	 */
	public function __construct( FieldSetRepository $field_set, FieldValues $values ) {
		$this->field_set = $field_set;
		$this->values    = $values;
	}

	/**
	 * Hooks into WooCommerce's emails, order pages and admin order screen.
	 */
	public function register_hooks(): void {
		// WooCommerce prints the order meta list at priority 10; note the email's format around it.
		add_action( 'woocommerce_email_order_meta', array( $this, 'start_email_order_meta' ), 9, 3 );
		add_action( 'woocommerce_email_order_meta', array( $this, 'end_email_order_meta' ), 11, 0 );
		add_filter( 'woocommerce_email_order_meta_fields', array( $this, 'email_order_meta_fields' ), 10, 3 );

		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'order_details_table' ) );

		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'admin_billing' ) );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'admin_shipping' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'admin_general' ) );
	}

	/**
	 * Notes whether the email whose order meta is printed next is plain text.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin Whether the email goes to the store.
	 * @param bool     $plain_text    Whether the email is plain text.
	 */
	public function start_email_order_meta( $order, $sent_to_admin = false, $plain_text = false ): void {
		$this->plain_email = (bool) $plain_text;
	}

	/**
	 * Forgets the email format once the order meta is printed.
	 */
	public function end_email_order_meta(): void {
		$this->plain_email = null;
	}

	/**
	 * Adds values to WooCommerce's email order meta list, which prints
	 * "label: value" for each.
	 *
	 * @param array<string, mixed> $fields        Order meta fields: {label, value} keyed by an id.
	 * @param bool                 $sent_to_admin Whether the email goes to the store.
	 * @param WC_Order|mixed       $order         Order.
	 * @return array<string, mixed>
	 */
	public function email_order_meta_fields( $fields, $sent_to_admin = false, $order = null ) {
		if ( ! $order instanceof WC_Order || ! $this->is_ours( $order ) ) {
			return $fields;
		}

		$fields = is_array( $fields ) ? $fields : array();
		$plain  = true === $this->plain_email;

		foreach ( $this->values->entries( $order, $this->fields( 'showInEmails' ), $this->checkout_type(), $plain ? FieldValues::PLAIN : FieldValues::HTML ) as $entry ) {
			// WooCommerce runs both formats through wp_kses_post() and decodes entities in plain
			// text emails afterwards, so plain text is escaped too, keeping its line breaks.
			$value = $plain ? esc_html( $entry['value'] ) : $entry['value'];

			// WooCommerce leaves out values PHP treats as false. A plain text email strips the tags.
			if ( '0' === $value ) {
				$value = '<span>0</span>';
			}

			$fields[ 'checkout_fieldkit_' . $entry['name'] ] = array(
				'label' => $plain ? esc_html( $entry['label'] ) : $entry['label'],
				'value' => $value,
			);
		}

		return $fields;
	}

	/**
	 * Prints the values table after the order details on the thank-you page
	 * and the My Account order view.
	 *
	 * @param WC_Order|mixed $order Order.
	 */
	public function order_details_table( $order ): void {
		if ( ! $order instanceof WC_Order || ! $this->is_ours( $order ) ) {
			return;
		}

		$entries = $this->values->entries( $order, $this->fields( 'showOnOrderPages' ), $this->checkout_type(), FieldValues::HTML );
		if ( ! $entries ) {
			return;
		}

		echo '<section class="cfk-order-values">';
		echo '<h2 class="woocommerce-order-details__title cfk-order-values__title">' . esc_html__( 'Additional details', 'checkout-fieldkit' ) . '</h2>';
		echo '<table class="woocommerce-table woocommerce-table--cfk-order-values shop_table cfk-order-values__table"><tbody>';
		foreach ( $entries as $entry ) {
			// Label and value are escaped by FieldValues (or a checkout_fieldkit_display_value filter).
			echo '<tr><th scope="row">' . self::labelled( $entry['label'] ) . '</th><td>' . $entry['value'] . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table></section>';
	}

	/**
	 * Admin order screen: Billing section fields, under the billing address.
	 *
	 * @param WC_Order|mixed $order Order.
	 */
	public function admin_billing( $order ): void {
		$this->admin_values( $order, 'billing' );
	}

	/**
	 * Admin order screen: Shipping section fields, under the shipping address.
	 *
	 * @param WC_Order|mixed $order Order.
	 */
	public function admin_shipping( $order ): void {
		$this->admin_values( $order, 'shipping' );
	}

	/**
	 * Admin order screen: fields of every other section, under the general details.
	 *
	 * @param WC_Order|mixed $order Order.
	 */
	public function admin_general( $order ): void {
		$this->admin_values( $order, 'general' );
	}

	/**
	 * Prints the values of one admin order screen area.
	 *
	 * @param WC_Order|mixed $order Order.
	 * @param string         $area  'billing', 'shipping' or 'general'.
	 */
	private function admin_values( $order, string $area ): void {
		if ( ! $order instanceof WC_Order || ! $this->is_ours( $order ) ) {
			return;
		}

		$fields = array();
		foreach ( $this->fields( 'showOnOrderPages', true ) as $section => $section_fields ) {
			if ( self::admin_area( $section ) === $area ) {
				$fields = array_merge( $fields, $section_fields );
			}
		}

		$entries = $this->values->entries( $order, $fields, $this->checkout_type(), FieldValues::HTML );
		if ( ! $entries ) {
			return;
		}

		echo '<div class="cfk-admin-order-values cfk-admin-order-values--' . esc_attr( $area ) . '">';
		foreach ( $entries as $entry ) {
			// Label and value are escaped by FieldValues (or a checkout_fieldkit_display_value filter).
			echo '<p><strong>' . self::labelled( $entry['label'] ) . '</strong> ' . $entry['value'] . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	}

	/**
	 * The admin order screen area a section's fields are shown in.
	 *
	 * @param string $section Section name.
	 * @return string 'billing', 'shipping' or 'general'.
	 */
	private static function admin_area( string $section ): string {
		return in_array( $section, array( 'billing', 'shipping' ), true ) ? $section : 'general';
	}

	/**
	 * A label followed by a colon, as WooCommerce writes labels before values.
	 *
	 * @param string $label Escaped label.
	 * @return string
	 */
	private static function labelled( string $label ): string {
		/* translators: %s: a field's label, shown before its value. */
		return sprintf( esc_html__( '%s:', 'checkout-fieldkit' ), $label );
	}

	/**
	 * The custom fields of the field set whose display flag is on, in field set order.
	 *
	 * @param string $flag       'showInEmails' or 'showOnOrderPages'.
	 * @param bool   $by_section Whether to key the result by section name.
	 * @return array<int|string, mixed> Fields, or fields keyed by section when $by_section.
	 */
	private function fields( string $flag, bool $by_section = false ): array {
		$set    = $this->field_set->get();
		$result = array();
		foreach ( $set['sections'] as $section => $fields ) {
			$shown = array();
			foreach ( $fields as $field ) {
				if ( empty( $field['builtIn'] ) && ! empty( $field[ $flag ] ) ) {
					$shown[] = $field;
				}
			}
			if ( $by_section ) {
				$result[ $section ] = $shown;
			} else {
				$result = array_merge( $result, $shown );
			}
		}

		return $result;
	}

	/**
	 * Whether an order was placed on this field set's checkout type.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	private function is_ours( WC_Order $order ): bool {
		return FieldValues::checkout_type_of( $order ) === $this->checkout_type();
	}

	/**
	 * The checkout type of the field set.
	 *
	 * @return string
	 */
	private function checkout_type(): string {
		return $this->field_set->checkout_type();
	}
}
