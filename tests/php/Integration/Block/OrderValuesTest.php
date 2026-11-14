<?php
/**
 * Tests for the Store API extension that carries inner-block values.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Block;

use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;
use CheckoutFieldKit\Block\OrderValues;
use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Environment\BlockSupport;
use CheckoutFieldKit\Plugin;
use CheckoutFieldKit\Tests\Support\StoreApiCheckout;
use CheckoutFieldKit\Tests\TestCase;
use WC_Order;

/**
 * Spec §5, second and third bullets: values from our Contact and Additional
 * inner blocks travel with the order through a Store API extension
 * (namespace `checkout-fieldkit`), are validated on the server (blocking the
 * order with a field-specific error) and saved on the order as meta `<name>`.
 */
class OrderValuesTest extends TestCase {

	use StoreApiCheckout;

	/**
	 * Calls of checkout_fieldkit_order_values_saved.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private $saved_calls = array();

	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_default_country', 'GB' );
		$this->saved_calls = array();
		add_action( 'checkout_fieldkit_order_values_saved', array( $this, 'record_saved' ), 10, 3 );
	}

	public function tear_down() {
		remove_action( 'checkout_fieldkit_order_values_saved', array( $this, 'record_saved' ), 10 );
		self::reset_rest_server();
		parent::tear_down();
	}

	/**
	 * Records a call of the values-saved action.
	 *
	 * @param WC_Order $order         Order.
	 * @param array    $values        Values.
	 * @param string   $checkout_type Checkout type.
	 */
	public function record_saved( $order, $values, $checkout_type ) {
		$this->saved_calls[] = array( $order->get_id(), $values, $checkout_type );
	}

	/**
	 * Saves the default Block field set with fields appended to sections, and
	 * makes the next REST request register the Store API routes again, so
	 * their schema includes the new fields (as on a fresh page load).
	 *
	 * @param array<string, array<int, array<string, mixed>>> $sections Fields by section.
	 */
	private function save_fields( array $sections ) {
		$repository = ( new Domain() )->field_sets( 'block' );
		$set        = $repository->get();
		foreach ( $sections as $section => $fields ) {
			foreach ( $fields as $field ) {
				$set['sections'][ $section ][] = $field;
			}
		}
		$this->assertTrue( $repository->save( $set ), 'The field set saves.' );

		self::reset_rest_server();
	}

	/**
	 * Makes the next REST request build the server and its routes again. The
	 * Store API reads extension schemas when it registers its routes.
	 */
	private static function reset_rest_server() {
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Test reset.
	}

	/**
	 * One field of each value type, Contact and Additional, plus a heading.
	 */
	private function save_every_type() {
		$options = array(
			array(
				'value' => '',
				'label' => 'Choose',
			),
			array(
				'value' => 'am',
				'label' => 'Morning',
			),
			array(
				'value' => 'pm',
				'label' => 'Afternoon',
			),
		);

		$this->save_fields(
			array(
				'contact'    => array(
					array(
						'name'  => 'nickname',
						'type'  => 'text',
						'label' => 'Nickname',
					),
					array(
						'name'  => 'age',
						'type'  => 'number',
						'label' => 'Age',
					),
					array(
						'name'  => 'work_email',
						'type'  => 'email',
						'label' => 'Work email',
					),
					array(
						'name'  => 'website',
						'type'  => 'url',
						'label' => 'Website',
					),
					array(
						'name'  => 'newsletter',
						'type'  => 'checkbox',
						'label' => 'Newsletter',
					),
				),
				'additional' => array(
					array(
						'name'  => 'intro',
						'type'  => 'heading',
						'label' => 'A few more things',
					),
					array(
						'name'  => 'gift_note',
						'type'  => 'textarea',
						'label' => 'Gift note',
					),
					array(
						'name'    => 'slot',
						'type'    => 'select',
						'label'   => 'Delivery slot',
						'options' => $options,
					),
					array(
						'name'    => 'wrap',
						'type'    => 'radio',
						'label'   => 'Wrapping',
						'options' => array(
							array(
								'value' => 'plain',
								'label' => 'Plain',
							),
							array(
								'value' => 'gold',
								'label' => 'Gold',
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Places an order through the Store API with our extension data.
	 *
	 * @param array<string, mixed>|null $values Our values, or null to send none.
	 * @param array<string, mixed>      $billing Billing values to change.
	 * @return \WP_REST_Response
	 */
	private function checkout_with( $values, array $billing = array() ) {
		$body = null === $values ? array() : array( 'extensions' => array( 'checkout-fieldkit' => $values ) );

		return $this->store_api_checkout( $this->billing_address( $billing ), $body );
	}

	/**
	 * Our registered extension schema for the checkout endpoint.
	 *
	 * @return array<string, mixed>
	 */
	private function extension_schema() {
		$schema = (array) StoreApi::container()->get( ExtendSchema::class )->get_endpoint_schema( CheckoutSchema::IDENTIFIER );
		$this->assertArrayHasKey( 'checkout-fieldkit', $schema );

		return $schema['checkout-fieldkit'];
	}

	public function test_the_plugin_registers_the_service() {
		$this->assertInstanceOf( OrderValues::class, Plugin::instance()->get( OrderValues::class ) );
	}

	public function test_the_schema_lists_every_value_field_of_contact_and_additional() {
		$this->save_every_type();
		$this->save_fields(
			array(
				'address' => array(
					array(
						'name'  => 'vat',
						'type'  => 'text',
						'label' => 'VAT',
					),
				),
				'contact' => array(
					array(
						'name'    => 'off',
						'type'    => 'text',
						'label'   => 'Off',
						'enabled' => false,
					),
				),
			)
		);

		$schema = $this->extension_schema();

		$this->assertSame( array( 'object', 'null' ), $schema['type'] );
		$this->assertSame(
			array( 'nickname', 'age', 'work_email', 'website', 'newsletter', 'gift_note', 'slot', 'wrap' ),
			array_keys( $schema['properties'] ),
			'Headings, Address fields and disabled fields are not part of it.'
		);
		$this->assertSame( array( 'string', 'null' ), $schema['properties']['nickname']['type'] );
		$this->assertSame( array( 'string', 'number', 'null' ), $schema['properties']['age']['type'] );
		$this->assertSame( array( 'boolean', 'string', 'null' ), $schema['properties']['newsletter']['type'] );
		$this->assertSame( 'Gift note', $schema['properties']['gift_note']['description'] );
		$this->assertSame( array( 'view', 'edit' ), $schema['properties']['gift_note']['context'] );
	}

	public function test_without_block_support_nothing_is_registered() {
		$service = new OrderValues( new Domain(), new BlockSupport( '8.8.0', true ) );
		$service->register_hooks();

		$this->assertFalse( has_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $service, 'update_order' ) ) );
	}

	/**
	 * @dataProvider data_order_storage
	 *
	 * @param bool $hpos Whether orders use HPOS.
	 */
	public function test_values_are_saved_as_order_meta_by_name( $hpos ) {
		$this->use_hpos( $hpos );
		$this->save_every_type();

		$response = $this->checkout_with(
			array(
				'nickname'   => '  Ada <b>L</b> ',
				'age'        => '36',
				'work_email' => 'ada@work.example',
				'website'    => 'https://ada.example',
				'newsletter' => true,
				'gift_note'  => "Happy birthday!\nLove, Charles",
				'slot'       => 'pm',
				'wrap'       => 'gold',
			)
		);

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$order = wc_get_order( $response->get_data()['order_id'] );
		$this->assertSame( 'store-api', $order->get_created_via() );
		$this->assertSame( 'Ada L', $order->get_meta( 'nickname' ) );
		$this->assertSame( '36', $order->get_meta( 'age' ) );
		$this->assertSame( 'ada@work.example', $order->get_meta( 'work_email' ) );
		$this->assertSame( 'https://ada.example', $order->get_meta( 'website' ) );
		$this->assertSame( '1', $order->get_meta( 'newsletter' ) );
		$this->assertSame( "Happy birthday!\nLove, Charles", $order->get_meta( 'gift_note' ) );
		$this->assertSame( 'pm', $order->get_meta( 'slot' ) );
		$this->assertSame( 'gold', $order->get_meta( 'wrap' ) );
		$this->assertSame( '', $order->get_meta( 'intro' ), 'Headings store nothing.' );
	}

	public function test_empty_values_are_not_stored_and_zero_is() {
		$this->save_every_type();

		$response = $this->checkout_with(
			array(
				'nickname'   => '',
				'age'        => '0',
				'newsletter' => false,
				'slot'       => '',
			)
		);

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$order = wc_get_order( $response->get_data()['order_id'] );
		$this->assertSame( '0', $order->get_meta( 'age' ) );
		foreach ( array( 'nickname', 'newsletter', 'slot', 'gift_note', 'work_email' ) as $name ) {
			$this->assertFalse( $order->meta_exists( $name ), $name . ' is not stored.' );
		}
	}

	public function test_an_order_without_our_extension_data_is_placed_when_nothing_is_required() {
		$this->save_every_type();

		$response = $this->checkout_with( null );

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( array(), $this->saved_calls[0][1] );
	}

	public function test_the_saved_action_fires_once_with_the_stored_values_after_the_order_is_saved() {
		$this->save_every_type();
		$seen_in_database = null;
		$check            = static function ( $order ) use ( &$seen_in_database ) {
			$seen_in_database = wc_get_order( $order->get_id() )->get_meta( 'nickname' );
		};
		add_action( 'checkout_fieldkit_order_values_saved', $check );

		$response = $this->checkout_with(
			array(
				'nickname' => 'Ada',
				'slot'     => 'am',
				'age'      => '',
			)
		);
		remove_action( 'checkout_fieldkit_order_values_saved', $check );

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertCount( 1, $this->saved_calls );
		$this->assertSame(
			array(
				$response->get_data()['order_id'],
				array(
					'nickname' => 'Ada',
					'slot'     => 'am',
				),
				'block',
			),
			$this->saved_calls[0]
		);
		$this->assertSame( 'Ada', $seen_in_database, 'The order is saved before the action fires.' );
	}

	/**
	 * @dataProvider data_invalid
	 *
	 * @param array<string, mixed> $field   Custom field.
	 * @param mixed                $value   Submitted value.
	 * @param string               $message Expected error message.
	 */
	public function test_invalid_values_reject_the_order_with_a_field_specific_message( array $field, $value, $message ) {
		$this->save_fields( array( 'additional' => array( $field ) ) );

		$response = $this->checkout_with( array( $field['name'] => $value ) );

		$this->assertSame( 400, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( 'checkout_fieldkit_invalid_value', $response->get_data()['code'] );
		$this->assertSame( $message, $response->get_data()['message'] );
		$this->assertSame( array( $field['name'] => $message ), $response->get_data()['data']['fields'] );
		$this->assertSame( 0, count( wc_get_orders( array( 'status' => array( 'pending', 'processing', 'completed' ) ) ) ), 'No order was placed.' );
		$this->assertSame( array(), $this->saved_calls );
	}

	public function data_invalid() {
		$field   = static function ( $type, array $extra = array() ) {
			return array_merge(
				array(
					'name'  => 'answer',
					'type'  => $type,
					'label' => 'Your answer',
				),
				$extra
			);
		};
		$choices = array(
			array(
				'value' => 'a',
				'label' => 'A',
			),
			array(
				'value' => 'b',
				'label' => 'B',
			),
		);

		return array(
			'required text'       => array( $field( 'text', array( 'required' => true ) ), '', 'Your answer is required.' ),
			'required checkbox'   => array( $field( 'checkbox', array( 'required' => true ) ), false, 'Your answer is required.' ),
			'required textarea'   => array( $field( 'textarea', array( 'required' => true ) ), null, 'Your answer is required.' ),
			'email'               => array( $field( 'email' ), 'nope', 'Your answer is not a valid email address.' ),
			'url'                 => array( $field( 'url' ), 'example.org', 'Your answer is not a valid web address. Start it with http:// or https://.' ),
			'number'              => array( $field( 'number' ), 'twelve', 'Your answer must be a number.' ),
			'phone'               => array( $field( 'text', array( 'validation' => array( 'phone' ) ) ), 'call me', 'Your answer is not a valid phone number.' ),
			'postcode for the GB' => array( $field( 'text', array( 'validation' => array( 'postcode' ) ) ), '12345', 'Your answer is not a valid postcode / ZIP.' ),
			'select choice'       => array( $field( 'select', array( 'options' => $choices ) ), 'z', 'Your answer: choose one of the options.' ),
			'radio choice'        => array( $field( 'radio', array( 'options' => $choices ) ), 'z', 'Your answer: choose one of the options.' ),
		);
	}

	public function test_a_required_field_missing_from_the_request_rejects_the_order() {
		$this->save_fields(
			array(
				'contact' => array(
					array(
						'name'     => 'company_size',
						'type'     => 'text',
						'label'    => 'Company size',
						'required' => true,
					),
				),
			)
		);

		$response = $this->checkout_with( null );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'Company size is required.', $response->get_data()['message'] );
	}

	public function test_every_problem_is_reported_at_once() {
		$this->save_every_type();
		$this->save_fields(
			array(
				'contact' => array(
					array(
						'name'     => 'must',
						'type'     => 'text',
						'label'    => 'Must',
						'required' => true,
					),
				),
			)
		);

		$response = $this->checkout_with(
			array(
				'work_email' => 'nope',
				'slot'       => 'never',
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame(
			array(
				'work_email' => 'Work email is not a valid email address.',
				'must'       => 'Must is required.',
				'slot'       => 'Delivery slot: choose one of the options.',
			),
			$response->get_data()['data']['fields']
		);
		$this->assertSame( 'Work email is not a valid email address. Must is required. Delivery slot: choose one of the options.', $response->get_data()['message'] );
		$this->assertSame( 'wc/checkout', $response->get_data()['data']['context'], 'Shown with the checkout\'s own notices.' );
	}

	public function test_postcodes_are_checked_against_the_billing_country() {
		$this->save_fields(
			array(
				'additional' => array(
					array(
						'name'       => 'pickup_zip',
						'type'       => 'text',
						'label'      => 'Pickup ZIP',
						'validation' => array( 'postcode' ),
					),
				),
			)
		);

		$response = $this->checkout_with(
			array( 'pickup_zip' => '90210' ),
			array(
				'country'  => 'US',
				'state'    => 'CA',
				'city'     => 'Beverly Hills',
				'postcode' => '90210',
			)
		);

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( '90210', wc_get_order( $response->get_data()['order_id'] )->get_meta( 'pickup_zip' ) );
	}

	public function test_a_value_of_the_wrong_json_type_is_refused_by_the_schema() {
		$this->save_every_type();

		$response = $this->checkout_with( array( 'nickname' => array( 'not', 'a', 'string' ) ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}

	public function test_values_of_unknown_names_are_ignored() {
		$this->save_every_type();

		$response = $this->checkout_with(
			array(
				'nickname' => 'Ada',
				'role'     => 'administrator',
			)
		);

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$order = wc_get_order( $response->get_data()['order_id'] );
		$this->assertFalse( $order->meta_exists( 'role' ) );
	}
}
