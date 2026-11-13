<?php
/**
 * Tests for exporting and importing the plugin's setup.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Tools;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Tests\TestCase;
use WP_Error;

/**
 * Spec §6.5 and §7: the export document, import validation, the preview of
 * what an import changes, and the import itself.
 */
class ImportExportTest extends TestCase {

	/**
	 * Fresh domain objects for each test.
	 *
	 * @var Domain
	 */
	private $domain;

	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_default_country', 'GB' );
		update_option( 'woocommerce_checkout_phone_field', 'optional' );
		WC()->countries->locale = array();
		$this->domain           = new Domain();
	}

	public function tear_down() {
		parent::tear_down();
		WC()->countries->locale = array();
	}

	public function test_export_contains_the_chosen_targets() {
		$block                             = $this->domain->field_sets( 'block' )->get();
		$block['sections']['additional'][] = array(
			'name'  => 'gift_note',
			'type'  => 'text',
			'label' => 'Gift note',
		);
		$this->domain->field_sets( 'block' )->save( $block );

		$document = $this->domain->import_export()->export( array( 'block', 'settings' ) );

		$this->assertSame( array( 'plugin', 'format', 'exportedAt', 'block', 'settings' ), array_keys( $document ) );
		$this->assertSame( 'checkout-fieldkit', $document['plugin'] );
		$this->assertSame( 1, $document['format'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', $document['exportedAt'] );
		$this->assertSame( $this->domain->field_sets( 'block' )->get(), $document['block'] );
		$this->assertSame( 'gift_note', $document['block']['sections']['additional'][0]['name'] );
		$this->assertSame( $this->domain->settings()->get(), $document['settings'] );
	}

	public function test_export_includes_everything_by_default_and_ignores_unknown_targets() {
		$this->assertSame( array( 'plugin', 'format', 'exportedAt', 'classic', 'block', 'settings' ), array_keys( $this->domain->import_export()->export() ) );
		$this->assertSame( array( 'plugin', 'format', 'exportedAt', 'classic' ), array_keys( $this->domain->import_export()->export( array( 'classic', 'orders' ) ) ) );
	}

	public function test_export_contains_no_order_or_customer_data() {
		$this->create_order( array( 'meta' => array( 'gift_note' => 'Secret message' ) ) );

		$json = wp_json_encode( $this->domain->import_export()->export() );

		$this->assertStringNotContainsString( 'Secret message', $json );
	}

	public function test_export_file_name() {
		$this->assertSame( 'checkout-fieldkit-example.org-2026-11-13.json', $this->domain->import_export()->file_name( gmmktime( 12, 0, 0, 11, 13, 2026 ) ) );
	}

	public function test_an_exported_document_survives_json_and_imports_cleanly() {
		$document = json_decode( wp_json_encode( $this->domain->import_export()->export() ), true );

		$this->assertSame( array(), $this->domain->import_export()->validate( $document ) );
	}

	/**
	 * @dataProvider data_invalid_documents
	 *
	 * @param mixed  $document An invalid document.
	 * @param string $key      Key of the expected problem.
	 */
	public function test_invalid_documents_are_refused( $document, $key ) {
		$errors = $this->domain->import_export()->validate( $document );

		$this->assertSame( array( $key ), array_column( $errors, 'key' ) );
		$this->assertSame( array( 'target', 'section', 'index', 'key', 'message' ), array_keys( $errors[0] ) );
	}

	public function data_invalid_documents() {
		return array(
			'not an object'      => array( 'hello', '' ),
			'another plugin'     => array(
				array(
					'plugin' => 'something-else',
					'format' => 1,
				),
				'plugin',
			),
			'newer format'       => array(
				array(
					'plugin'   => 'checkout-fieldkit',
					'format'   => 2,
					'settings' => array(),
				),
				'format',
			),
			'no format'          => array(
				array(
					'plugin'   => 'checkout-fieldkit',
					'settings' => array(),
				),
				'format',
			),
			'nothing to import'  => array(
				array(
					'plugin' => 'checkout-fieldkit',
					'format' => 1,
				),
				'',
			),
			'bad settings'       => array(
				array(
					'plugin'   => 'checkout-fieldkit',
					'format'   => 1,
					'settings' => array( 'localeOverrides' => array( 'label' => 'on' ) ),
				),
				'localeOverrides.label',
			),
			'field set not data' => array(
				array(
					'plugin' => 'checkout-fieldkit',
					'format' => 1,
					'block'  => 'all of them',
				),
				'sections',
			),
		);
	}

	public function test_field_set_problems_name_their_target_section_index_and_key() {
		$document                                        = $this->domain->import_export()->export( array( 'classic', 'block' ) );
		$document['block']['sections']['contact'][]      = array( 'name' => 'Bad' );
		$document['classic']['sections']['additional'][] = array(
			'name' => 'x',
			'type' => 'nope',
		);

		$errors = $this->domain->import_export()->validate( $document );

		$this->assertSame(
			array(
				array( 'classic', 'additional', 1, 'type' ),
				array( 'block', 'contact', 1, 'name' ),
			),
			array_map(
				static function ( $error ) {
					return array( $error['target'], $error['section'], $error['index'], $error['key'] );
				},
				$errors
			)
		);
	}

	public function test_preview_lists_added_removed_and_changed_names_per_target() {
		$repository                          = $this->domain->field_sets( 'classic' );
		$current                             = $repository->get();
		$current['sections']['additional'][] = array(
			'name' => 'old_field',
			'type' => 'text',
		);
		$current['sections']['additional'][] = array(
			'name'  => 'kept_field',
			'type'  => 'text',
			'label' => 'Kept',
		);
		$repository->save( $current );

		$document                                      = $this->domain->import_export()->export( array( 'classic', 'settings' ) );
		$document['classic']['sections']['additional'] = array(
			$document['classic']['sections']['additional'][0], // order_comments, unchanged.
			$document['classic']['sections']['additional'][2], // kept_field, unchanged.
			array(
				'name' => 'new_field',
				'type' => 'date',
			),
		);
		$document['classic']['sections']['billing'][0]['label'] = 'Given name';
		$document['settings']['localeOverrides']['classes']     = true;

		$preview = $this->domain->import_export()->preview( $document );

		$this->assertSame(
			array(
				'targets' => array(
					array(
						'target'  => 'classic',
						'action'  => 'replace',
						'added'   => array( 'new_field' ),
						'removed' => array( 'old_field' ),
						'changed' => array( 'billing_first_name' ),
					),
					array(
						'target'  => 'block',
						'action'  => 'keep',
						'added'   => array(),
						'removed' => array(),
						'changed' => array(),
					),
					array(
						'target'  => 'settings',
						'action'  => 'replace',
						'added'   => array(),
						'removed' => array(),
						'changed' => array( 'classes' ),
					),
				),
			),
			$preview
		);
	}

	public function test_preview_reports_moved_fields_as_changed() {
		$document                                 = $this->domain->import_export()->export( array( 'block' ) );
		$address                                  = $document['block']['sections']['address'];
		$document['block']['sections']['address'] = array_merge( array( $address[1], $address[0] ), array_slice( $address, 2 ) );

		$preview = $this->domain->import_export()->preview( $document );

		$this->assertSame( array( 'first_name', 'country' ), $preview['targets'][1]['changed'] );
	}

	public function test_preview_of_an_invalid_document_is_an_error() {
		$result = $this->domain->import_export()->preview( array( 'plugin' => 'nope' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'checkout_fieldkit_invalid_import', $result->get_error_code() );
		$this->assertSame( 422, $result->get_error_data()['status'] );
		$this->assertNotEmpty( $result->get_error_data()['errors'] );
	}

	public function test_import_replaces_the_included_targets_and_keeps_the_others() {
		$this->domain->settings()->save( array( 'localeOverrides' => array( 'label' => false ) ) );
		$block_before = get_option( 'checkout_fieldkit_field_set_block' );

		$document                                      = $this->domain->import_export()->export( array( 'classic' ) );
		$document['classic']['sections']['shipping'][] = array(
			'name'  => 'shipping_door_code',
			'type'  => 'text',
			'label' => 'Door code',
		);

		$this->assertTrue( $this->domain->import_export()->import( $document ) );

		$classic = ( new Domain() )->field_sets( 'classic' )->get();
		$this->assertSame( 'shipping_door_code', end( $classic['sections']['shipping'] )['name'] );
		$this->assertFalse( ( new Domain() )->settings()->get()['localeOverrides']['label'], 'Settings were not in the file: kept.' );
		$this->assertSame( $block_before, get_option( 'checkout_fieldkit_field_set_block' ) );
	}

	public function test_an_invalid_import_changes_nothing() {
		$document = $this->domain->import_export()->export();
		$document['classic']['sections']['billing'][0]['label'] = 'Changed';
		$document['settings']['localeOverrides']['order']       = false;
		$document['block']['sections']['contact'][]             = array( 'name' => 'Not Valid' );

		$result = $this->domain->import_export()->import( $document );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertFalse( get_option( 'checkout_fieldkit_field_set_classic' ) );
		$this->assertFalse( get_option( 'checkout_fieldkit_field_set_block' ) );
		$this->assertFalse( get_option( 'checkout_fieldkit_settings' ) );
	}

	public function test_import_fits_built_in_fields_to_this_stores_woocommerce() {
		$document = $this->domain->import_export()->export( array( 'classic' ) );
		update_option( 'woocommerce_checkout_phone_field', 'hidden' );
		$domain = new Domain();

		$this->assertSame( array(), $domain->import_export()->validate( $document ), 'billing_phone is not a field on this store; it is dropped, not refused.' );
		$this->assertTrue( $domain->import_export()->import( $document ) );
		$this->assertNotContains( 'billing_phone', array_column( $domain->field_sets( 'classic' )->get()['sections']['billing'], 'name' ) );
	}
}
