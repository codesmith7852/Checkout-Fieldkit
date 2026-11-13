<?php
/**
 * Tests for storing field sets.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Storage;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Storage\FieldSetRepository;
use CheckoutFieldKit\Storage\Migrator;
use CheckoutFieldKit\Tests\TestCase;
use WP_Error;

/**
 * Spec §3: one versioned, autoloaded option per field set; defaults until the
 * first save; reset deletes the option.
 */
class FieldSetRepositoryTest extends TestCase {

	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_default_country', 'GB' );
		update_option( 'woocommerce_checkout_phone_field', 'optional' );
		WC()->countries->locale = array();
	}

	public function tear_down() {
		parent::tear_down();
		WC()->countries->locale = array();
	}

	public function test_option_names() {
		$domain = new Domain();

		$this->assertSame( 'checkout_fieldkit_field_set_classic', $domain->field_sets( 'classic' )->option_name() );
		$this->assertSame( 'checkout_fieldkit_field_set_block', $domain->field_sets( 'block' )->option_name() );
		$this->assertSame( 'block', $domain->field_sets( 'block' )->checkout_type() );
		$this->assertSame( $domain->field_sets( 'block' ), $domain->field_sets( 'block' ), 'One repository per checkout type.' );
	}

	public function test_an_unknown_checkout_type_is_refused() {
		$this->expectException( \InvalidArgumentException::class );

		( new Domain() )->field_sets( 'nope' );
	}

	/**
	 * @dataProvider data_checkout_types
	 *
	 * @param string $checkout_type Checkout type.
	 */
	public function test_reading_without_a_saved_set_returns_the_defaults_and_writes_nothing( $checkout_type ) {
		$domain     = new Domain();
		$repository = $domain->field_sets( $checkout_type );

		$set = $repository->get();

		$this->assertSame( $domain->normalizer()->normalize_set( $checkout_type, $domain->defaults()->field_set( $checkout_type )['sections'] ), $set['sections'] );
		$this->assertSame( 1, $set['version'] );
		$this->assertSame( $set, $repository->defaults() );
		$this->assertFalse( get_option( $repository->option_name() ) );
		$this->assertFalse( $repository->is_stored() );
	}

	public function data_checkout_types() {
		return array(
			'classic' => array( 'classic' ),
			'block'   => array( 'block' ),
		);
	}

	public function test_saving_a_valid_set_stores_it_normalised() {
		$repository                             = ( new Domain() )->field_sets( 'block' );
		$set                                    = $repository->get();
		$set['sections']['address'][2]['label'] = 'Surname';
		$set['sections']['additional'][]        = array(
			'name'    => 'gift_note',
			'type'    => 'textarea',
			'label'   => 'Gift note',
			'surplus' => 'dropped',
		);

		$result = $repository->save( $set );

		$this->assertTrue( $result );
		$this->assertTrue( $repository->is_stored() );
		$saved = ( new Domain() )->field_sets( 'block' )->get();
		$this->assertSame( 'Surname', $saved['sections']['address'][2]['label'] );
		$this->assertSame( 'gift_note', $saved['sections']['additional'][0]['name'] );
		$this->assertArrayNotHasKey( 'surplus', $saved['sections']['additional'][0] );
		$this->assertTrue( $saved['sections']['additional'][0]['showInEmails'], 'Missing keys take their defaults.' );
	}

	public function test_the_stored_option_is_lean_versioned_and_autoloaded() {
		global $wpdb;
		$repository = ( new Domain() )->field_sets( 'classic' );

		$repository->save( $repository->get() );

		$stored = get_option( 'checkout_fieldkit_field_set_classic' );
		$this->assertSame( 1, $stored['version'] );
		$this->assertSame( array( 'billing', 'shipping', 'additional' ), array_keys( $stored['sections'] ) );
		$this->assertArrayNotHasKey( 'enabled', $stored['sections']['billing'][0], 'Values equal to the key default are not stored.' );
		$this->assertSame( 'billing_first_name', $stored['sections']['billing'][0]['name'] );

		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'checkout_fieldkit_field_set_classic' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertContains( $autoload, wp_autoload_values_to_autoload() );
	}

	public function test_saving_an_invalid_set_stores_nothing_and_returns_every_problem() {
		$repository = ( new Domain() )->field_sets( 'classic' );
		$set        = $repository->get();
		array_pop( $set['sections']['billing'] ); // billing_email.
		$set['sections']['additional'][] = array( 'name' => 'Bad Name' );
		$set['sections']['additional'][] = array(
			'name' => 'gift',
			'type' => 'colour',
		);

		$result = $repository->save( $set );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'checkout_fieldkit_invalid_field_set', $result->get_error_code() );
		$data = $result->get_error_data();
		$this->assertSame( 422, $data['status'] );
		$this->assertSame(
			array( array( 'additional', 1, 'name' ), array( 'additional', 2, 'type' ), array( 'billing', null, 'name' ) ),
			array_map(
				static function ( $error ) {
					return array( $error['section'], $error['index'], $error['key'] );
				},
				$data['errors']
			)
		);
		$this->assertFalse( get_option( 'checkout_fieldkit_field_set_classic' ) );
	}

	public function test_an_invalid_save_keeps_the_previous_set() {
		$repository                             = ( new Domain() )->field_sets( 'block' );
		$set                                    = $repository->get();
		$set['sections']['address'][1]['label'] = 'Given name';
		$repository->save( $set );
		$before = get_option( 'checkout_fieldkit_field_set_block' );

		$set['sections']['contact'] = array();
		$this->assertInstanceOf( WP_Error::class, $repository->save( $set ) );

		$this->assertSame( $before, get_option( 'checkout_fieldkit_field_set_block' ) );
	}

	public function test_block_email_stays_on_and_required_and_only_its_position_is_ours() {
		$repository                    = ( new Domain() )->field_sets( 'block' );
		$set                           = $repository->get();
		$set['sections']['contact'][0] = array_merge(
			$set['sections']['contact'][0],
			array(
				'enabled'  => false,
				'required' => false,
				'label'    => 'Mail',
			)
		);
		$set['sections']['contact'][]  = array(
			'name' => 'nickname',
			'type' => 'text',
		);
		$set['sections']['contact']    = array_reverse( $set['sections']['contact'] );

		$this->assertTrue( $repository->save( $set ) );

		$email = $repository->get()['sections']['contact'][1];
		$this->assertSame( 'email', $email['name'] );
		$this->assertTrue( $email['enabled'] );
		$this->assertTrue( $email['required'] );
		$this->assertSame( 'Email address', $email['label'] );
	}

	public function test_block_built_ins_keep_woocommerces_values_for_what_is_not_ours() {
		$repository                             = ( new Domain() )->field_sets( 'block' );
		$set                                    = $repository->get();
		$set['sections']['address'][4]['label'] = 'Street';
		$set['sections']['address'][4]['placeholder'] = 'Not on Block';
		$set['sections']['address'][4]['classes']     = array( 'wide' );

		$repository->save( $set );

		$address_1 = $repository->get()['sections']['address'][4];
		$this->assertSame( 'address_1', $address_1['name'] );
		$this->assertSame( 'Street', $address_1['label'] );
		$this->assertSame( '', $address_1['placeholder'] );
		$this->assertSame( array(), $address_1['classes'] );
	}

	public function test_sections_come_back_complete_and_in_display_order() {
		$repository      = ( new Domain() )->field_sets( 'classic' );
		$set             = $repository->get();
		$set['sections'] = array(
			'additional' => $set['sections']['additional'],
			'shipping'   => $set['sections']['shipping'],
			'billing'    => $set['sections']['billing'],
		);

		$repository->save( $set );

		$this->assertSame( array( 'billing', 'shipping', 'additional' ), array_keys( $repository->get()['sections'] ) );
	}

	public function test_saving_announces_the_saved_set() {
		$heard = array();
		add_action(
			'checkout_fieldkit_field_set_saved',
			static function ( $set, $checkout_type ) use ( &$heard ) {
				$heard[] = array( $checkout_type, $set['version'] );
			},
			10,
			2
		);
		$repository = ( new Domain() )->field_sets( 'block' );

		$repository->save( $repository->get() );
		$set                        = $repository->get();
		$set['sections']['contact'] = array();
		$repository->save( $set );

		$this->assertSame( array( array( 'block', 1 ) ), $heard, 'Only the valid save is announced.' );
	}

	public function test_a_set_from_a_newer_plugin_version_is_refused() {
		$repository     = ( new Domain() )->field_sets( 'block' );
		$set            = $repository->get();
		$set['version'] = 2;

		$result = $repository->save( $set );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'version', $result->get_error_data()['errors'][0]['key'] );
		$this->assertFalse( get_option( 'checkout_fieldkit_field_set_block' ) );
	}

	public function test_reset_deletes_the_option_so_the_defaults_return() {
		$heard                           = 0;
		$repository                      = ( new Domain() )->field_sets( 'block' );
		$set                             = $repository->get();
		$set['sections']['additional'][] = array(
			'name' => 'gift_note',
			'type' => 'text',
		);
		$repository->save( $set );
		add_action(
			'checkout_fieldkit_field_set_saved',
			static function () use ( &$heard ) {
				++$heard;
			}
		);

		$repository->reset();

		$this->assertFalse( get_option( 'checkout_fieldkit_field_set_block' ) );
		$this->assertSame( $repository->defaults(), $repository->get() );
		$this->assertSame( 1, $heard, 'Reset is announced too, with the defaults.' );
	}

	public function test_the_migration_hook_runs_on_read_for_older_stored_sets_without_writing() {
		update_option(
			'checkout_fieldkit_field_set_block',
			array(
				'version'  => 1,
				'sections' => array( 'contact' => array() ),
			)
		);
		$migrator   = new Migrator(
			2,
			array(
				1 => static function ( $value ) {
					$value['sections']['additional'] = array(
						array(
							'name'  => 'migrated',
							'label' => 'Added by the 1 → 2 step',
						),
					);
					return $value;
				},
			)
		);
		$domain     = new Domain();
		$repository = new FieldSetRepository( 'block', $domain->defaults(), $domain->normalizer(), $domain->validator(), $migrator );

		$set = $repository->get();

		$this->assertSame( 2, $set['version'] );
		$this->assertSame( 'migrated', $set['sections']['additional'][0]['name'] );
		$this->assertSame( 1, get_option( 'checkout_fieldkit_field_set_block' )['version'], 'Migrated in memory; stored on the next save.' );
	}

	public function test_the_migration_hook_runs_on_save_for_older_sets() {
		$migrator                        = new Migrator(
			2,
			array(
				1 => static function ( $value ) {
					$value['sections']['additional'][0]['label'] = 'Migrated';
					return $value;
				},
			)
		);
		$domain                          = new Domain();
		$repository                      = new FieldSetRepository( 'block', $domain->defaults(), $domain->normalizer(), $domain->validator(), $migrator );
		$set                             = $repository->defaults();
		$set['version']                  = 1;
		$set['sections']['additional'][] = array( 'name' => 'gift_note' );

		$this->assertTrue( $repository->save( $set ) );

		$stored = get_option( 'checkout_fieldkit_field_set_block' );
		$this->assertSame( 2, $stored['version'] );
		$this->assertSame( 'Migrated', $stored['sections']['additional'][0]['label'] );
	}

	public function test_reading_follows_woocommerces_built_in_fields_when_they_change() {
		$repository                             = ( new Domain() )->field_sets( 'classic' );
		$set                                    = $repository->get();
		$set['sections']['billing'][0]['label'] = 'Given name';
		$repository->save( $set );

		update_option( 'woocommerce_checkout_phone_field', 'hidden' );
		$without_phone = ( new Domain() )->field_sets( 'classic' )->get();
		$this->assertNotContains( 'billing_phone', array_column( $without_phone['sections']['billing'], 'name' ) );
		$this->assertSame( 'Given name', $without_phone['sections']['billing'][0]['label'] );

		( new Domain() )->field_sets( 'classic' )->save( $without_phone );
		update_option( 'woocommerce_checkout_phone_field', 'optional' );
		$names = array_column( ( new Domain() )->field_sets( 'classic' )->get()['sections']['billing'], 'name' );
		$this->assertSame( array_search( 'billing_postcode', $names, true ) + 1, array_search( 'billing_phone', $names, true ), 'A returning built-in goes back after the one before it.' );
	}

	public function test_reading_a_stored_set_is_cached_until_the_option_changes() {
		$repository = ( new Domain() )->field_sets( 'block' );
		$repository->save( $repository->get() );
		$first = $repository->get();

		$set                                    = $first;
		$set['sections']['address'][0]['label'] = 'Land';
		update_option( 'checkout_fieldkit_field_set_block', $set );

		$this->assertSame( 'Land', $repository->get()['sections']['address'][0]['label'] );
	}
}
