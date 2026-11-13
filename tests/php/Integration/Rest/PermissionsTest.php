<?php
/**
 * Tests for who may use the REST routes.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Rest;

use CheckoutFieldKit\Plugin;

/**
 * Spec §7: every route is under checkout-fieldkit/v1 and needs manage_woocommerce.
 */
class PermissionsTest extends RestTestCase {

	public function test_registers_exactly_the_routes_of_the_spec() {
		$routes = array_keys( rest_get_server()->get_routes( 'checkout-fieldkit/v1' ) );

		$this->assertEqualsCanonicalizing(
			array(
				'/checkout-fieldkit/v1',
				'/checkout-fieldkit/v1/context',
				'/checkout-fieldkit/v1/field-sets/(?P<checkout_type>classic|block)',
				'/checkout-fieldkit/v1/settings',
				'/checkout-fieldkit/v1/export',
				'/checkout-fieldkit/v1/import/preview',
				'/checkout-fieldkit/v1/import',
				'/checkout-fieldkit/v1/reset',
			),
			$routes
		);
	}

	/**
	 * @dataProvider data_requests
	 *
	 * @param string     $method HTTP method.
	 * @param string     $route  Route.
	 * @param mixed|null $body   JSON body.
	 */
	public function test_logged_out_visitors_get_401( $method, $route, $body ) {
		wp_set_current_user( 0 );

		$this->assertErrorResponse( 401, 'rest_forbidden', $this->request( $method, $route, $body ) );
	}

	/**
	 * @dataProvider data_requests
	 *
	 * @param string     $method HTTP method.
	 * @param string     $route  Route.
	 * @param mixed|null $body   JSON body.
	 */
	public function test_shop_customers_get_403( $method, $route, $body ) {
		$this->log_in_as( 'customer' );

		$this->assertErrorResponse( 403, 'rest_forbidden', $this->request( $method, $route, $body ) );
	}

	/**
	 * @dataProvider data_requests
	 *
	 * @param string     $method HTTP method.
	 * @param string     $route  Route.
	 * @param mixed|null $body   JSON body.
	 */
	public function test_editors_without_manage_woocommerce_get_403( $method, $route, $body ) {
		$this->log_in_as( 'editor' );

		$this->assertErrorResponse( 403, 'rest_forbidden', $this->request( $method, $route, $body ) );
	}

	/**
	 * @dataProvider data_requests
	 *
	 * @param string     $method HTTP method.
	 * @param string     $route  Route.
	 * @param mixed|null $body   JSON body.
	 */
	public function test_shop_managers_are_allowed( $method, $route, $body ) {
		$this->log_in_as( 'shop_manager' );

		$response = $this->request( $method, $route, $body );

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $this->json( $response ) ) );
	}

	/**
	 * @dataProvider data_requests
	 *
	 * @param string     $method HTTP method.
	 * @param string     $route  Route.
	 * @param mixed|null $body   JSON body.
	 */
	public function test_nothing_changes_when_permission_is_denied( $method, $route, $body ) {
		Plugin::instance()->domain()->settings()->save( array( 'localeOverrides' => array( 'placeholder' => false ) ) );
		$this->log_in_as( 'customer' );
		$before = $this->stored_options();

		$this->request( $method, $route, $body );

		$this->assertSame( $before, $this->stored_options() );
	}

	/**
	 * Every request the routes accept, with a valid body.
	 *
	 * @return array<string, array{string, string, mixed}>
	 */
	public function data_requests() {
		$document = array(
			'plugin'   => 'checkout-fieldkit',
			'format'   => 1,
			'settings' => array( 'localeOverrides' => array( 'label' => false ) ),
		);

		return array(
			'GET context'           => array( 'GET', '/context', null ),
			'GET classic field set' => array( 'GET', '/field-sets/classic', null ),
			'GET block field set'   => array( 'GET', '/field-sets/block', null ),
			'PUT classic field set' => array( 'PUT', '/field-sets/classic', self::current_field_set( 'classic' ) ),
			'PUT block field set'   => array( 'PUT', '/field-sets/block', self::current_field_set( 'block' ) ),
			'GET settings'          => array( 'GET', '/settings', null ),
			'PUT settings'          => array( 'PUT', '/settings', array( 'localeOverrides' => array( 'label' => false ) ) ),
			'GET export'            => array( 'GET', '/export', null ),
			'POST import preview'   => array( 'POST', '/import/preview', $document ),
			'POST import'           => array( 'POST', '/import', $document ),
			'POST reset'            => array( 'POST', '/reset', array( 'target' => 'settings' ) ),
		);
	}

	/**
	 * A body that is the field set in effect when the request is sent.
	 *
	 * @param string $checkout_type Checkout type.
	 * @return callable
	 */
	private static function current_field_set( $checkout_type ) {
		return static function () use ( $checkout_type ) {
			return Plugin::instance()->domain()->field_sets( $checkout_type )->get();
		};
	}

	/**
	 * The plugin's stored options.
	 *
	 * @return array<string, mixed>
	 */
	private function stored_options() {
		$values = array();
		foreach ( array( 'checkout_fieldkit_field_set_classic', 'checkout_fieldkit_field_set_block', 'checkout_fieldkit_settings' ) as $option ) {
			$values[ $option ] = get_option( $option, 'missing' );
		}

		return $values;
	}
}
