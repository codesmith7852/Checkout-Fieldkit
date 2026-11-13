<?php
/**
 * Tests for GET and PUT /field-sets/{classic|block}.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Rest;

use CheckoutFieldKit\Domain;
use WP_REST_Request;

/**
 * Spec §7: read the stored or default field set; replace it whole, or get 422
 * with every problem and nothing saved.
 */
class FieldSetsRouteTest extends RestTestCase {

	/**
	 * Fresh domain objects to compare with.
	 *
	 * @var Domain
	 */
	private $domain;

	public function set_up() {
		parent::set_up();
		$this->domain = new Domain();
		$this->log_in_as( 'shop_manager' );
	}

	/**
	 * A custom field.
	 *
	 * @param string $name Field name.
	 * @param array  $more Other keys.
	 * @return array<string, mixed>
	 */
	private static function custom_field( $name, array $more = array() ) {
		return array_merge(
			array(
				'name'  => $name,
				'type'  => 'text',
				'label' => 'Gift note',
			),
			$more
		);
	}

	/**
	 * @dataProvider data_checkout_types
	 *
	 * @param string $checkout_type Checkout type.
	 */
	public function test_get_returns_the_defaults_before_anything_is_saved( $checkout_type ) {
		$response = $this->request( 'GET', '/field-sets/' . $checkout_type );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $this->domain->field_sets( $checkout_type )->defaults(), $this->json( $response ) );
	}

	public function data_checkout_types() {
		return array(
			'classic' => array( 'classic' ),
			'block'   => array( 'block' ),
		);
	}

	public function test_get_returns_the_saved_field_set() {
		$set                                    = $this->domain->field_sets( 'block' )->get();
		$set['sections']['additional'][]        = self::custom_field( 'gift_note' );
		$set['sections']['address'][0]['label'] = 'Land';
		$this->assertTrue( $this->domain->field_sets( 'block' )->save( $set ) );

		$data = $this->json( $this->request( 'GET', '/field-sets/block' ) );

		$this->assertSame( $this->domain->field_sets( 'block' )->get(), $data );
		$this->assertSame( 'gift_note', $data['sections']['additional'][0]['name'] );
		$this->assertSame( array( 'contact', 'address', 'additional' ), array_keys( $data['sections'] ) );
	}

	public function test_empty_sections_are_json_arrays() {
		$response = $this->request( 'GET', '/field-sets/block' );

		$this->assertStringContainsString( '"additional":[]', wp_json_encode( rest_get_server()->response_to_data( $response, false ) ) );
	}

	/**
	 * @dataProvider data_checkout_types
	 *
	 * @param string $checkout_type Checkout type.
	 */
	public function test_put_replaces_the_field_set_and_returns_it( $checkout_type ) {
		$set                             = $this->domain->field_sets( $checkout_type )->get();
		$set['sections']['additional'][] = self::custom_field( 'gift_note', array( 'required' => true ) );

		$response = $this->request( 'PUT', '/field-sets/' . $checkout_type, $set );

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $this->json( $response ) ) );
		$saved = $this->domain->field_sets( $checkout_type )->get();
		$this->assertTrue( $this->domain->field_sets( $checkout_type )->is_stored() );
		$this->assertSame( $saved, $this->json( $response ) );
		$last = end( $saved['sections']['additional'] );
		$this->assertSame( 'gift_note', $last['name'] );
		$this->assertTrue( $last['required'] );
	}

	public function test_a_field_set_survives_a_round_trip() {
		$set                                    = $this->json( $this->request( 'GET', '/field-sets/classic' ) );
		$set['sections']['billing'][0]['label'] = 'Given name';
		$set['sections']['billing']             = array_reverse( $set['sections']['billing'] );
		$set['sections']['shipping'][]          = self::custom_field( 'shipping_door_code', array( 'placeholder' => '1234' ) );

		$put = $this->json( $this->request( 'PUT', '/field-sets/classic', $set ) );
		$get = $this->json( $this->request( 'GET', '/field-sets/classic' ) );

		$this->assertSame( $put, $get );
		$this->assertSame( array_column( $set['sections']['billing'], 'name' ), array_column( $get['sections']['billing'], 'name' ) );
		$this->assertSame( 'Given name', end( $get['sections']['billing'] )['label'] );
		$this->assertSame( '1234', end( $get['sections']['shipping'] )['placeholder'] );

		$this->assertSame( 200, $this->request( 'PUT', '/field-sets/classic', $get )->get_status() );
		$this->assertSame( $get, $this->json( $this->request( 'GET', '/field-sets/classic' ) ) );
	}

	public function test_put_drops_unknown_keys_and_fills_in_missing_ones() {
		$set                             = $this->domain->field_sets( 'block' )->get();
		$set['sections']['additional'][] = array(
			'name'      => 'gift_note',
			'type'      => 'text',
			'label'     => 'Gift note',
			'colour'    => 'red',
			'__proto__' => 'x',
		);

		$data  = $this->json( $this->request( 'PUT', '/field-sets/block', $set ) );
		$field = $data['sections']['additional'][0];

		$this->assertArrayNotHasKey( 'colour', $field );
		$this->assertArrayNotHasKey( '__proto__', $field );
		$this->assertTrue( $field['enabled'] );
		$this->assertTrue( $field['showInEmails'] );
	}

	public function test_put_refuses_an_invalid_field_set_with_every_problem_and_saves_nothing() {
		$set = $this->domain->field_sets( 'classic' )->get();
		array_shift( $set['sections']['billing'] ); // Removes billing_first_name, a built-in field.
		$set['sections']['additional'][] = self::custom_field( 'Bad Name' );
		$set['sections']['additional'][] = self::custom_field( 'order_comments_copy', array( 'type' => 'no-such-type' ) );

		$response = $this->request( 'PUT', '/field-sets/classic', $set );

		$this->assertErrorResponse( 422, 'checkout_fieldkit_invalid_field_set', $response );
		$data = $this->json( $response );
		$this->assertNotEmpty( $data['message'] );
		$this->assertGreaterThanOrEqual( 3, count( $data['data']['errors'] ) );
		foreach ( $data['data']['errors'] as $error ) {
			$this->assertSame( array( 'section', 'index', 'key', 'message' ), array_keys( $error ) );
		}
		$this->assertContains(
			array(
				'section' => 'additional',
				'index'   => 1,
				'key'     => 'name',
			),
			array_map(
				static function ( $error ) {
					unset( $error['message'] );
					return $error;
				},
				$data['data']['errors']
			)
		);
		$this->assertFalse( $this->domain->field_sets( 'classic' )->is_stored() );
	}

	public function test_put_keeps_the_saved_field_set_when_refused() {
		$set                             = $this->domain->field_sets( 'block' )->get();
		$set['sections']['additional'][] = self::custom_field( 'gift_note' );
		$this->domain->field_sets( 'block' )->save( $set );
		$before = get_option( 'checkout_fieldkit_field_set_block' );

		$set['sections']['additional'][] = self::custom_field( 'gift_note' );
		$this->assertErrorResponse( 422, 'checkout_fieldkit_invalid_field_set', $this->request( 'PUT', '/field-sets/block', $set ) );

		$this->assertSame( $before, get_option( 'checkout_fieldkit_field_set_block' ) );
	}

	/**
	 * @dataProvider data_bodies_that_are_not_field_sets
	 *
	 * @param mixed $body JSON body.
	 */
	public function test_put_refuses_a_body_that_is_not_a_field_set( $body ) {
		$response = $this->request( 'PUT', '/field-sets/block', $body );

		$this->assertErrorResponse( 422, 'checkout_fieldkit_invalid_field_set', $response );
		$this->assertSame( 'sections', $this->json( $response )['data']['errors'][0]['key'] );
		$this->assertFalse( $this->domain->field_sets( 'block' )->is_stored() );
	}

	public function data_bodies_that_are_not_field_sets() {
		return array(
			'empty object'      => array( new \stdClass() ),
			'sections a string' => array( array( 'sections' => 'none' ) ),
			'a list'            => array( array( 1, 2 ) ),
			'a string'          => array( 'fields' ),
		);
	}

	public function test_put_without_a_body_is_refused() {
		$this->assertErrorResponse( 422, 'checkout_fieldkit_invalid_field_set', $this->request( 'PUT', '/field-sets/classic' ) );
	}

	public function test_put_with_malformed_json_is_refused() {
		$request = new WP_REST_Request( 'PUT', self::NAMESPACE . '/field-sets/classic' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( '{"sections":' );

		$this->assertErrorResponse( 400, 'rest_invalid_json', rest_get_server()->dispatch( $request ) );
		$this->assertFalse( $this->domain->field_sets( 'classic' )->is_stored() );
	}

	public function test_put_refuses_a_field_set_from_a_newer_plugin_version() {
		$set            = $this->domain->field_sets( 'block' )->get();
		$set['version'] = 99;

		$response = $this->request( 'PUT', '/field-sets/block', $set );

		$this->assertErrorResponse( 422, 'checkout_fieldkit_invalid_field_set', $response );
		$this->assertSame( 'version', $this->json( $response )['data']['errors'][0]['key'] );
	}

	/**
	 * @dataProvider data_checkout_types_in_the_body
	 *
	 * @param mixed $in_body Value of checkout_type in the body.
	 */
	public function test_a_checkout_type_in_the_body_is_ignored( $in_body ) {
		$set                             = $this->domain->field_sets( 'classic' )->get();
		$set['checkout_type']            = $in_body;
		$set['sections']['additional'][] = self::custom_field( 'gift_note' );

		$response = $this->request( 'PUT', '/field-sets/classic', $set );

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $this->json( $response ) ) );
		$this->assertTrue( $this->domain->field_sets( 'classic' )->is_stored() );
		$this->assertFalse( $this->domain->field_sets( 'block' )->is_stored() );
	}

	public function data_checkout_types_in_the_body() {
		return array(
			'the other type' => array( 'block' ),
			'nonsense'       => array( 'nonsense' ),
			'a list'         => array( array( 'block' ) ),
		);
	}

	public function test_options_describes_the_field_set_schema_and_the_put_body() {
		$data = $this->json( $this->request( 'OPTIONS', '/field-sets/classic' ) );

		$this->assertSame( 'checkout-fieldkit-field-set', $data['schema']['title'] );
		$this->assertSame( array( 'version', 'sections' ), array_keys( $data['schema']['properties'] ) );
		$this->assertSame( array( 'GET' ), $data['endpoints'][0]['methods'] );
		$this->assertSame( array( 'PUT' ), $data['endpoints'][1]['methods'] );
		$this->assertSame( 'object', $data['endpoints'][1]['args']['sections']['type'] );
	}

	/**
	 * @dataProvider data_unknown_checkout_types
	 *
	 * @param string $method HTTP method.
	 */
	public function test_an_unknown_checkout_type_is_not_found( $method ) {
		$this->assertErrorResponse( 404, 'rest_no_route', $this->request( $method, '/field-sets/shortcode', 'PUT' === $method ? array( 'sections' => array() ) : null ) );
	}

	public function data_unknown_checkout_types() {
		return array(
			'GET' => array( 'GET' ),
			'PUT' => array( 'PUT' ),
		);
	}

	public function test_saving_tells_listeners_the_field_set_changed() {
		$calls = array();
		add_action(
			'checkout_fieldkit_field_set_saved',
			static function ( $set, $checkout_type ) use ( &$calls ) {
				$calls[] = $checkout_type;
			},
			10,
			2
		);

		$this->request( 'PUT', '/field-sets/block', $this->domain->field_sets( 'block' )->get() );

		$this->assertSame( array( 'block' ), $calls );
	}
}
