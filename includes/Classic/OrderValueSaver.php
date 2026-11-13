<?php
/**
 * Saves classic checkout values on the order.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Classic;

use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Orders\FieldValues;
use CheckoutFieldKit\Orders\ValueSanitizer;
use CheckoutFieldKit\Service;
use CheckoutFieldKit\Storage\FieldSetRepository;
use WC_Customer;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Stores what shoppers enter in the classic checkout's custom fields
 * (spec §4.1), once, through WooCommerce's order and customer objects.
 *
 * - Fields named `billing_*` or `shipping_*` are saved by WooCommerce itself,
 *   on the order as `_<name>` and on the customer as `<name>`. We do not save
 *   them again: we only replace the value WooCommerce is about to write with
 *   the sanitised one (or drop it when empty), before it saves.
 * - Every other custom field is saved by us as order meta `<name>`, in the
 *   same order save as WooCommerce's data (woocommerce_checkout_create_order).
 *   Fields in the Shipping section are skipped when the shopper does not ship
 *   to a different address, as WooCommerce skips its shipping fields.
 * - Values come from WooCommerce's posted checkout data, so only fields that
 *   are on the checkout form are saved; a field absent from it is left alone.
 * - Disabled fields, built-in fields and display fields are never saved.
 */
final class OrderValueSaver implements Service {

	/**
	 * The classic field set.
	 *
	 * @var FieldSetRepository
	 */
	private $field_set;

	/**
	 * Value sanitiser.
	 *
	 * @var ValueSanitizer
	 */
	private $sanitizer;

	/**
	 * Values stored on orders not yet saved, keyed by object id.
	 *
	 * @var array<int, array<string, string>>
	 */
	private $pending = array();

	/**
	 * Constructor.
	 *
	 * @param FieldSetRepository $field_set The classic field set.
	 * @param ValueSanitizer     $sanitizer Value sanitiser.
	 */
	public function __construct( FieldSetRepository $field_set, ValueSanitizer $sanitizer ) {
		$this->field_set = $field_set;
		$this->sanitizer = $sanitizer;
	}

	/**
	 * Hooks into WooCommerce's classic checkout order and customer saving.
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_checkout_update_customer', array( $this, 'update_customer' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'update_order' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'announce' ) );
	}

	/**
	 * Before the order is saved: puts the sanitised values on it.
	 *
	 * @param WC_Order             $order Order about to be saved.
	 * @param array<string, mixed> $data  Posted checkout data (WC_Checkout::get_posted_data()).
	 */
	public function update_order( $order, $data ): void {
		if ( ! $order instanceof WC_Order || ! is_array( $data ) ) {
			return;
		}

		$saved = array();
		foreach ( $this->values( $data ) as $name => $value ) {
			$key = FieldValues::meta_key( $name, CheckoutType::CLASSIC );
			if ( '' === $value ) {
				$order->delete_meta_data( $key );
				continue;
			}

			$order->update_meta_data( $key, $value );
			$saved[ $name ] = $value;
		}

		$this->pending[ spl_object_id( $order ) ] = $saved;
	}

	/**
	 * Before the customer is saved: puts the sanitised values of the fields
	 * WooCommerce keeps on the customer (`billing_*`, `shipping_*`) on it.
	 *
	 * @param WC_Customer          $customer Customer about to be saved.
	 * @param array<string, mixed> $data     Posted checkout data.
	 */
	public function update_customer( $customer, $data ): void {
		if ( ! $customer instanceof WC_Customer || ! is_array( $data ) ) {
			return;
		}

		foreach ( $this->values( $data ) as $name => $value ) {
			if ( ! FieldValues::is_saved_by_woocommerce( $name ) ) {
				continue;
			}
			if ( '' === $value ) {
				$customer->delete_meta_data( $name );
			} else {
				$customer->update_meta_data( $name, $value );
			}
		}
	}

	/**
	 * After the order is saved: tells listeners which values it now holds.
	 *
	 * @param WC_Order $order The saved order.
	 */
	public function announce( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$id = spl_object_id( $order );
		if ( ! isset( $this->pending[ $id ] ) ) {
			return;
		}

		$values = $this->pending[ $id ];
		unset( $this->pending[ $id ] );

		/**
		 * Fires after a checkout saved custom field values on a new order.
		 *
		 * @since 0.1.0
		 *
		 * @param WC_Order              $order         The saved order.
		 * @param array<string, string> $values        The stored values keyed by field name, in field
		 *                                             set order; fields left empty are not listed.
		 *                                             See FieldValues::meta_key() for where each lives.
		 * @param string                $checkout_type 'classic' or 'block'.
		 */
		do_action( 'checkout_fieldkit_order_values_saved', $order, $values, CheckoutType::CLASSIC );
	}

	/**
	 * Sanitised values of the custom fields to save, keyed by field name, in
	 * field set order. "" means nothing is stored for that field.
	 *
	 * @param array<string, mixed> $data Posted checkout data.
	 * @return array<string, string>
	 */
	private function values( array $data ): array {
		$ships_elsewhere = self::ships_elsewhere( $data );
		$set             = $this->field_set->get();
		$values          = array();

		foreach ( $set['sections'] as $section => $fields ) {
			foreach ( $fields as $field ) {
				$name = $field['name'];
				if ( $field['builtIn'] || ! $field['enabled'] || ! array_key_exists( $name, $data ) ) {
					continue;
				}

				// WooCommerce writes billing_/shipping_ fields whatever the section, so those are
				// always normalised; the rest of the Shipping section is ours to skip.
				if ( 'shipping' === $section && ! $ships_elsewhere && ! FieldValues::is_saved_by_woocommerce( $name ) ) {
					continue;
				}

				$values[ $name ] = $this->sanitizer->sanitize( $field, $data[ $name ], CheckoutType::CLASSIC );
			}
		}

		return $values;
	}

	/**
	 * Whether the shopper ships to a different address, by WooCommerce's own
	 * rule for skipping the shipping fields (WC_Checkout::maybe_skip_fieldset()).
	 *
	 * @param array<string, mixed> $data Posted checkout data.
	 * @return bool
	 */
	private static function ships_elsewhere( array $data ): bool {
		if ( empty( $data['ship_to_different_address'] ) ) {
			return false;
		}

		return ! function_exists( 'WC' ) || null === WC()->cart || WC()->cart->needs_shipping_address();
	}
}
