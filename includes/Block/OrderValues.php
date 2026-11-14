<?php
/**
 * Values from our Block checkout inner blocks, through the Store API.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Block;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Environment\BlockSupport;
use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Orders\FieldValues;
use CheckoutFieldKit\Orders\ValueSanitizer;
use CheckoutFieldKit\Orders\ValueValidator;
use CheckoutFieldKit\Service;
use WC_Order;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * The Store API extension `checkout-fieldkit` (spec §5): the values of the
 * Contact and Additional inner blocks are sent with the order as checkout
 * `extensions.checkout-fieldkit.<name>`, then checked and saved here.
 *
 * - Schema: one property per enabled custom field of Contact and Additional
 *   that collects a value; strings (numbers also accepted for number fields,
 *   booleans for checkboxes), or null. Values under other names are dropped
 *   by the Store API.
 * - When the order is placed (POST /wc/store/v1/checkout, on
 *   woocommerce_store_api_checkout_update_order_from_request): every value is
 *   checked by ValueValidator (required, the type's own check, the field's
 *   validations with the billing country for postcodes and phone numbers,
 *   options). Any problem rejects the order: HTTP 400, code
 *   `checkout_fieldkit_invalid_value`, every message joined in `message`,
 *   `data.fields` the messages by field name, shown with the checkout's own
 *   notices (`data.context` `wc/checkout`).
 * - Otherwise the values, sanitised by ValueSanitizer, go on the order as
 *   meta `<name>` (an empty value removes the key) in the same order save as
 *   WooCommerce's data, and checkout_fieldkit_order_values_saved fires once
 *   the order has been saved (woocommerce_store_api_checkout_order_processed).
 *
 * Nothing is registered when the Block checkout features are not available.
 */
final class OrderValues implements Service {

	/**
	 * Field set, types and environment.
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
	 * Values stored on orders not yet announced, keyed by order id.
	 *
	 * @var array<int, array<string, string>>
	 */
	private $pending = array();

	/**
	 * Constructor.
	 *
	 * @param Domain            $domain  Field set, types and environment.
	 * @param BlockSupport|null $support Whether Block features can run; defaults to the domain's.
	 */
	public function __construct( Domain $domain, ?BlockSupport $support = null ) {
		$this->domain  = $domain;
		$this->support = $support;
	}

	/**
	 * Registers the extension once WooCommerce's blocks are loaded, and hooks
	 * into placing the order.
	 */
	public function register_hooks(): void {
		if ( ! $this->support()->is_available() ) {
			return;
		}

		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->register_endpoint_data();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( $this, 'register_endpoint_data' ) );
		}

		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'update_order' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'announce' ) );
	}

	/**
	 * Registers our schema for the checkout endpoint.
	 */
	public function register_endpoint_data(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CheckoutSchema::IDENTIFIER,
				'namespace'       => InnerBlockFields::NAMESPACE,
				'schema_callback' => array( $this, 'schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * The properties of our extension data: one per value field, in field set order.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function schema(): array {
		$properties = array();
		foreach ( $this->fields()->value_fields() as $field ) {
			$type = array( 'string', 'null' );
			if ( 'checkbox' === $field['type'] ) {
				$type = array( 'boolean', 'string', 'null' );
			} elseif ( 'number' === $field['type'] ) {
				$type = array( 'string', 'number', 'null' );
			}

			$properties[ (string) $field['name'] ] = array(
				'description' => '' !== trim( (string) $field['label'] ) ? (string) $field['label'] : (string) $field['name'],
				'type'        => $type,
				'context'     => array( 'view', 'edit' ),
			);
		}

		return $properties;
	}

	/**
	 * While the order is placed: checks our values, then puts them on the order.
	 *
	 * @param WC_Order        $order   The order being placed (saved by WooCommerce afterwards).
	 * @param WP_REST_Request $request The checkout request.
	 * @throws RouteException When a value is invalid; WooCommerce turns it into an error response.
	 */
	public function update_order( $order, $request ): void {
		if ( ! $order instanceof WC_Order || ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() ) {
			return;
		}

		$fields   = $this->fields()->value_fields();
		$posted   = self::posted( $request );
		$problems = ( new ValueValidator( $this->domain->field_types() ) )->problems( $fields, $posted, CheckoutType::BLOCK, self::country( $order, $request ) );

		if ( $problems ) {
			throw new RouteException(
				'checkout_fieldkit_invalid_value',
				esc_html( implode( ' ', $problems ) ),
				400,
				array(
					'fields'  => array_map( 'esc_html', $problems ),
					'context' => 'wc/checkout',
				)
			);
		}

		$sanitizer = new ValueSanitizer( $this->domain->field_types() );
		$saved     = array();
		foreach ( $fields as $field ) {
			$name  = (string) $field['name'];
			$key   = FieldValues::meta_key( $name, CheckoutType::BLOCK );
			$value = $sanitizer->sanitize( $field, $posted[ $name ] ?? null, CheckoutType::BLOCK );
			if ( '' === $value ) {
				$order->delete_meta_data( $key );
				continue;
			}

			$order->update_meta_data( $key, $value );
			$saved[ $name ] = $value;
		}

		$this->pending[ $order->get_id() ] = $saved;
	}

	/**
	 * After the order is saved: tells listeners which values it now holds.
	 *
	 * @param WC_Order $order The saved order.
	 */
	public function announce( $order ): void {
		if ( ! $order instanceof WC_Order || ! isset( $this->pending[ $order->get_id() ] ) ) {
			return;
		}

		$values = $this->pending[ $order->get_id() ];
		unset( $this->pending[ $order->get_id() ] );

		/** This action is documented in includes/Classic/OrderValueSaver.php */
		do_action( 'checkout_fieldkit_order_values_saved', $order, $values, CheckoutType::BLOCK );
	}

	/**
	 * Our values in the request, keyed by field name.
	 *
	 * @param WP_REST_Request $request The checkout request.
	 * @return array<string, mixed>
	 */
	private static function posted( WP_REST_Request $request ): array {
		$extensions = $request->get_param( 'extensions' );
		$values     = is_array( $extensions ) && isset( $extensions[ InnerBlockFields::NAMESPACE ] ) ? $extensions[ InnerBlockFields::NAMESPACE ] : array();

		return is_array( $values ) ? $values : array();
	}

	/**
	 * The billing country of the order being placed, for postcode and phone checks.
	 *
	 * @param WC_Order        $order   Order.
	 * @param WP_REST_Request $request The checkout request.
	 * @return string|null Null when unknown.
	 */
	private static function country( WC_Order $order, WP_REST_Request $request ): ?string {
		$billing = $request->get_param( 'billing_address' );
		$country = is_array( $billing ) && isset( $billing['country'] ) && is_string( $billing['country'] ) ? $billing['country'] : $order->get_billing_country();
		$country = strtoupper( trim( (string) $country ) );

		return '' === $country ? null : $country;
	}

	/**
	 * The fields of our inner blocks.
	 *
	 * @return InnerBlockFields
	 */
	private function fields(): InnerBlockFields {
		return new InnerBlockFields( $this->domain->field_sets( CheckoutType::BLOCK ), $this->domain->field_types() );
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
