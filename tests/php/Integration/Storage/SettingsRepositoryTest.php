<?php
/**
 * Tests for storing the store-wide settings.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Storage;

use CheckoutFieldKit\Storage\Migrator;
use CheckoutFieldKit\Storage\SettingsRepository;
use CheckoutFieldKit\Tests\TestCase;
use WP_Error;

/**
 * Spec §3: checkout_fieldkit_settings, {version, localeOverrides}.
 */
class SettingsRepositoryTest extends TestCase {

	const DEFAULTS = array(
		'version'         => 1,
		'localeOverrides' => array(
			'label'       => true,
			'placeholder' => true,
			'order'       => true,
			'required'    => false,
			'classes'     => false,
		),
	);

	public function test_defaults_until_the_first_save() {
		$settings = new SettingsRepository();

		$this->assertSame( 'checkout_fieldkit_settings', $settings->option_name() );
		$this->assertSame( self::DEFAULTS, $settings->get() );
		$this->assertSame( self::DEFAULTS, $settings->defaults() );
		$this->assertFalse( $settings->is_stored() );
		$this->assertFalse( get_option( 'checkout_fieldkit_settings' ) );
	}

	public function test_saving_replaces_the_settings() {
		$settings = new SettingsRepository();

		$result = $settings->save(
			array(
				'localeOverrides' => array(
					'label'    => false,
					'required' => true,
					'unknown'  => true,
				),
				'surplus'         => 1,
			)
		);

		$this->assertTrue( $result );
		$expected                                = self::DEFAULTS;
		$expected['localeOverrides']['label']    = false;
		$expected['localeOverrides']['required'] = true;
		$this->assertSame( $expected, ( new SettingsRepository() )->get(), 'Unknown keys are dropped; missing ones take their defaults.' );
		$this->assertSame( $expected, get_option( 'checkout_fieldkit_settings' ) );
		$this->assertTrue( $settings->is_stored() );
	}

	public function test_invalid_settings_store_nothing_and_list_every_problem() {
		$settings = new SettingsRepository();

		$result = $settings->save(
			array(
				'localeOverrides' => array(
					'label'   => 'yes',
					'order'   => 1,
					'classes' => true,
				),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'checkout_fieldkit_invalid_settings', $result->get_error_code() );
		$this->assertSame( 422, $result->get_error_data()['status'] );
		$this->assertSame( array( 'localeOverrides.label', 'localeOverrides.order' ), array_column( $result->get_error_data()['errors'], 'key' ) );
		$this->assertFalse( get_option( 'checkout_fieldkit_settings' ) );
	}

	public function test_settings_must_be_an_object() {
		$this->assertSame( array( '' ), array_column( ( new SettingsRepository() )->validate( 'nope' ), 'key' ) );
		$this->assertSame( array( 'localeOverrides' ), array_column( ( new SettingsRepository() )->validate( array( 'localeOverrides' => 'all' ) ), 'key' ) );
	}

	public function test_settings_from_a_newer_plugin_version_are_refused() {
		$this->assertSame( array( 'version' ), array_column( ( new SettingsRepository() )->validate( array( 'version' => 9 ) ), 'key' ) );
	}

	public function test_reset_deletes_the_option() {
		$settings = new SettingsRepository();
		$settings->save( array( 'localeOverrides' => array( 'label' => false ) ) );

		$settings->reset();

		$this->assertFalse( get_option( 'checkout_fieldkit_settings' ) );
		$this->assertSame( self::DEFAULTS, $settings->get() );
	}

	public function test_a_damaged_stored_value_reads_as_defaults_where_damaged() {
		update_option(
			'checkout_fieldkit_settings',
			array(
				'version'         => 1,
				'localeOverrides' => array(
					'label' => 'nonsense',
					'order' => false,
				),
			)
		);

		$expected                             = self::DEFAULTS;
		$expected['localeOverrides']['order'] = false;
		$this->assertSame( $expected, ( new SettingsRepository() )->get() );
	}

	public function test_the_migration_hook_runs_on_read() {
		update_option( 'checkout_fieldkit_settings', array( 'overrideLabels' => false ) );
		$migrator = new Migrator(
			2,
			array(
				1 => static function ( $value ) {
					$value['localeOverrides'] = array( 'label' => $value['overrideLabels'] );
					unset( $value['overrideLabels'] );
					return $value;
				},
			)
		);

		$settings = ( new SettingsRepository( $migrator ) )->get();

		$this->assertSame( 2, $settings['version'] );
		$this->assertFalse( $settings['localeOverrides']['label'] );
	}
}
