<?php
/**
 * Base class for tests of the plugin's REST routes.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Rest;

use CheckoutFieldKit\Tests\TestCase;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Gives each test a fresh REST server with every route registered, and a
 * helper that sends a request to it the way the admin app does (JSON body).
 */
abstract class RestTestCase extends TestCase {

	/**
	 * Route namespace.
	 */
	const NAMESPACE = '/checkout-fieldkit/v1';

	public function set_up() {
		parent::set_up();

		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride -- WordPress's own REST server.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
		// phpcs:enable

		update_option( 'woocommerce_default_country', 'GB' );
		update_option( 'woocommerce_checkout_phone_field', 'optional' );
		WC()->countries->locale = array();
	}

	public function tear_down() {
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride -- WordPress's own REST server.
		global $wp_rest_server;
		$wp_rest_server = null;
		// phpcs:enable

		WC()->countries->locale = array();
		parent::tear_down();
	}

	/**
	 * Sends a request to one of the plugin's routes.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $route  Route below the namespace, for example '/settings'.
	 * @param mixed|null $body   Value sent as the JSON body; null sends none; a
	 * closure is called when the request is sent and
	 *                           its result is the body.
	 * @param array      $query  Query string parameters.
	 * @return WP_REST_Response
	 */
	protected function request( $method, $route, $body = null, array $query = array() ) {
		$request = new WP_REST_Request( $method, self::NAMESPACE . $route );
		if ( $query ) {
			$request->set_query_params( $query );
		}
		if ( $body instanceof \Closure ) {
			$body = $body();
		}
		if ( null !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * The response data after a round trip through JSON, as the admin gets it.
	 *
	 * @param WP_REST_Response $response Response.
	 * @return mixed
	 */
	protected function json( WP_REST_Response $response ) {
		return json_decode( wp_json_encode( rest_get_server()->response_to_data( $response, false ) ), true );
	}

	/**
	 * Asserts the response is an error with a status and code.
	 *
	 * @param int              $status   Expected HTTP status.
	 * @param string           $code     Expected error code.
	 * @param WP_REST_Response $response Response.
	 */
	protected function assertErrorResponse( $status, $code, WP_REST_Response $response ) {
		$data = $this->json( $response );
		$this->assertSame( $status, $response->get_status(), (string) wp_json_encode( $data ) );
		$this->assertSame( $code, $data['code'] );
		$this->assertSame( $status, $data['data']['status'] );
	}
}
