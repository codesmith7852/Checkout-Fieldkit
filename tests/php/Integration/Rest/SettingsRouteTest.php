<?php
/**
 * Tests for GET and PUT /settings.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Rest;

use CheckoutFieldKit\Storage\SettingsRepository;

/**
 * Spec §7: read or replace the store-wide settings.
 */
class SettingsRouteTest extends RestTestCase {

	/**
	 * Settings repository to compare with.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	public function set_up() {
		parent::set_up();
		$this->settings = new SettingsRepository();
		$this->log_in_as( 'shop_manager' );
	}

	public function test_get_returns_the_defaults_before_anything_is_saved() {
		$response = $this->request( 'GET', '/settings' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'version'         => 1,
				'localeOverrides' => array(
					'label'       => true,
					'placeholder' => true,
					'order'       => true,
					'required'    => false,
					'classes'     => false,
				),
			),
			$this->json( $response )
		);
	}

	public function test_put_replaces_the_settings_and_returns_them() {
		$response = $this->request(
			'PUT',
			'/settings',
			array(
				'version'         => 1,
				'localeOverrides' => array(
					'label'    => false,
					'required' => true,
				),
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $this->settings->get(), $this->json( $response ) );
		$this->assertFalse( $this->settings->get()['localeOverrides']['label'] );
		$this->assertTrue( $this->settings->get()['localeOverrides']['required'] );
		$this->assertTrue( $this->settings->get()['localeOverrides']['placeholder'] );
	}

	public function test_settings_survive_a_round_trip() {
		$settings                               = $this->json( $this->request( 'GET', '/settings' ) );
		$settings['localeOverrides']['classes'] = true;
		$settings['localeOverrides']['order']   = false;

		$this->request( 'PUT', '/settings', $settings );

		$this->assertSame( $settings, $this->json( $this->request( 'GET', '/settings' ) ) );
	}

	public function test_put_drops_unknown_keys() {
		$data = $this->json(
			$this->request(
				'PUT',
				'/settings',
				array(
					'colour'          => 'red',
					'localeOverrides' => array(
						'label' => false,
						'other' => true,
					),
				)
			)
		);

		$this->assertSame( array( 'version', 'localeOverrides' ), array_keys( $data ) );
		$this->assertArrayNotHasKey( 'other', $data['localeOverrides'] );
		$this->assertArrayNotHasKey( 'colour', get_option( 'checkout_fieldkit_settings' ) );
	}

	public function test_put_refuses_values_that_are_not_switches_and_saves_nothing() {
		$response = $this->request(
			'PUT',
			'/settings',
			array(
				'localeOverrides' => array(
					'label'   => 'yes',
					'classes' => 1,
				),
			)
		);

		$this->assertErrorResponse( 422, 'checkout_fieldkit_invalid_settings', $response );
		$this->assertSame(
			array( 'localeOverrides.label', 'localeOverrides.classes' ),
			array_column( $this->json( $response )['data']['errors'], 'key' )
		);
		$this->assertFalse( $this->settings->is_stored() );
	}

	/**
	 * @dataProvider data_bodies_that_are_not_settings
	 *
	 * @param mixed $body JSON body.
	 */
	public function test_put_refuses_a_body_that_is_not_settings( $body ) {
		$this->assertErrorResponse( 422, 'checkout_fieldkit_invalid_settings', $this->request( 'PUT', '/settings', $body ) );
		$this->assertFalse( $this->settings->is_stored() );
	}

	public function data_bodies_that_are_not_settings() {
		return array(
			'a string'                    => array( 'label' ),
			'no body'                     => array( null ),
			'localeOverrides as a list'   => array( array( 'localeOverrides' => 'label' ) ),
			'from a newer plugin version' => array( array( 'version' => 2 ) ),
		);
	}
}
