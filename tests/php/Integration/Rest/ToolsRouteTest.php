<?php
/**
 * Tests for /export, /import/preview, /import and /reset.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Rest;

use CheckoutFieldKit\Domain;

/**
 * Spec §6.5 and §7: the Tools screen's routes.
 */
class ToolsRouteTest extends RestTestCase {

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
	 * Saves a block field set with a custom field in Additional.
	 *
	 * @param string $name Field name.
	 */
	private function save_block_custom_field( $name ) {
		$set                             = $this->domain->field_sets( 'block' )->get();
		$set['sections']['additional'][] = array(
			'name'  => $name,
			'type'  => 'text',
			'label' => 'Gift note',
		);
		$this->assertTrue( $this->domain->field_sets( 'block' )->save( $set ) );
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

	public function test_export_includes_everything_by_default() {
		$this->save_block_custom_field( 'gift_note' );

		$response = $this->request( 'GET', '/export' );
		$data     = $this->json( $response );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'plugin', 'format', 'exportedAt', 'classic', 'block', 'settings' ), array_keys( $data ) );
		$this->assertSame( 'checkout-fieldkit', $data['plugin'] );
		$this->assertSame( 1, $data['format'] );
		$this->assertSame( $this->domain->field_sets( 'classic' )->get(), $data['classic'] );
		$this->assertSame( $this->domain->field_sets( 'block' )->get(), $data['block'] );
		$this->assertSame( $this->domain->settings()->get(), $data['settings'] );
	}

	/**
	 * @dataProvider data_include
	 *
	 * @param mixed    $targets  Value of the include parameter.
	 * @param string[] $expected Targets in the document.
	 */
	public function test_export_includes_the_chosen_targets( $targets, $expected ) {
		$data = $this->json( $this->request( 'GET', '/export', null, array( 'include' => $targets ) ) );

		$this->assertSame( array_merge( array( 'plugin', 'format', 'exportedAt' ), $expected ), array_keys( $data ) );
	}

	public function data_include() {
		return array(
			'comma-separated' => array( 'classic,settings', array( 'classic', 'settings' ) ),
			'one target'      => array( 'block', array( 'block' ) ),
			'a list'          => array( array( 'settings', 'block' ), array( 'block', 'settings' ) ),
			'with spaces'     => array( 'classic, block', array( 'classic', 'block' ) ),
			'a target twice'  => array( 'block,block', array( 'block' ) ),
			'every target'    => array( 'settings,block,classic', array( 'classic', 'block', 'settings' ) ),
		);
	}

	/**
	 * @dataProvider data_invalid_include
	 *
	 * @param mixed $targets Value of the include parameter.
	 */
	public function test_export_refuses_an_unknown_or_empty_include( $targets ) {
		$this->assertErrorResponse( 400, 'rest_invalid_param', $this->request( 'GET', '/export', null, array( 'include' => $targets ) ) );
	}

	public function data_invalid_include() {
		return array(
			'unknown target' => array( 'classic,orders' ),
			'empty'          => array( '' ),
			'only a comma'   => array( ',' ),
		);
	}

	public function test_export_suggests_a_file_name() {
		$response = $this->request( 'GET', '/export' );
		$headers  = $response->get_headers();

		$this->assertSame(
			'attachment; filename="' . $this->domain->import_export()->file_name() . '"',
			$headers['Content-Disposition']
		);
		$this->assertMatchesRegularExpression( '/^attachment; filename="checkout-fieldkit-[a-z0-9.-]+-\d{4}-\d{2}-\d{2}\.json"$/', $headers['Content-Disposition'] );
	}

	public function test_preview_says_what_an_import_would_change_and_changes_nothing() {
		$document                                      = $this->json( $this->request( 'GET', '/export', null, array( 'include' => 'block,settings' ) ) );
		$document['block']['sections']['additional'][] = array(
			'name'  => 'gift_note',
			'type'  => 'text',
			'label' => 'Gift note',
		);
		$document['settings']['localeOverrides']['label'] = false;
		$before = $this->stored_options();

		$response = $this->request( 'POST', '/import/preview', $document );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'targets' => array(
					array(
						'target'  => 'classic',
						'action'  => 'keep',
						'added'   => array(),
						'removed' => array(),
						'changed' => array(),
					),
					array(
						'target'  => 'block',
						'action'  => 'replace',
						'added'   => array( 'gift_note' ),
						'removed' => array(),
						'changed' => array(),
					),
					array(
						'target'  => 'settings',
						'action'  => 'replace',
						'added'   => array(),
						'removed' => array(),
						'changed' => array( 'label' ),
					),
				),
			),
			$this->json( $response )
		);
		$this->assertSame( $before, $this->stored_options() );
	}

	/**
	 * @dataProvider data_invalid_documents
	 *
	 * @param mixed  $document JSON body.
	 * @param string $key      Key of the first problem.
	 */
	public function test_preview_refuses_an_invalid_document( $document, $key ) {
		$response = $this->request( 'POST', '/import/preview', $document );

		$this->assertErrorResponse( 422, 'checkout_fieldkit_invalid_import', $response );
		$error = $this->json( $response )['data']['errors'][0];
		$this->assertSame( array( 'target', 'section', 'index', 'key', 'message' ), array_keys( $error ) );
		$this->assertSame( $key, $error['key'] );
	}

	/**
	 * @dataProvider data_invalid_documents
	 *
	 * @param mixed  $document JSON body.
	 * @param string $key      Key of the first problem.
	 */
	public function test_import_refuses_an_invalid_document_and_changes_nothing( $document, $key ) {
		$this->save_block_custom_field( 'gift_note' );
		$before = $this->stored_options();

		$response = $this->request( 'POST', '/import', $document );

		$this->assertErrorResponse( 422, 'checkout_fieldkit_invalid_import', $response );
		$this->assertSame( $key, $this->json( $response )['data']['errors'][0]['key'] );
		$this->assertSame( $before, $this->stored_options() );
	}

	public function data_invalid_documents() {
		$valid_settings = array( 'localeOverrides' => array( 'label' => false ) );

		return array(
			'no body'                     => array( null, '' ),
			'not an object'               => array( 'checkout', '' ),
			'another plugin'              => array(
				array(
					'plugin'   => 'other',
					'format'   => 1,
					'settings' => $valid_settings,
				),
				'plugin',
			),
			'newer format'                => array(
				array(
					'plugin'   => 'checkout-fieldkit',
					'format'   => 2,
					'settings' => $valid_settings,
				),
				'format',
			),
			'one valid, one invalid part' => array(
				array(
					'plugin'   => 'checkout-fieldkit',
					'format'   => 1,
					'settings' => $valid_settings,
					'block'    => array( 'sections' => 'none' ),
				),
				'sections',
			),
		);
	}

	public function test_import_replaces_the_included_targets_and_keeps_the_others() {
		$this->save_block_custom_field( 'gift_note' );
		$classic_before = get_option( 'checkout_fieldkit_field_set_classic', 'missing' );
		$document       = $this->json( $this->request( 'GET', '/export', null, array( 'include' => 'block,settings' ) ) );
		$this->domain->field_sets( 'block' )->reset();
		$this->domain->settings()->save( array( 'localeOverrides' => array( 'order' => false ) ) );
		$document['settings']['localeOverrides']['classes'] = true;

		$response = $this->request( 'POST', '/import', $document );

		$this->assertSame( 200, $response->get_status(), (string) wp_json_encode( $this->json( $response ) ) );
		$this->assertSame( $document['block'], $this->domain->field_sets( 'block' )->get() );
		$this->assertSame( $document['settings'], $this->domain->settings()->get() );
		$this->assertTrue( $this->domain->settings()->get()['localeOverrides']['order'] );
		$this->assertSame( $classic_before, get_option( 'checkout_fieldkit_field_set_classic', 'missing' ) );
	}

	public function test_import_returns_what_is_now_in_effect_for_each_imported_target() {
		$document = $this->json( $this->request( 'GET', '/export', null, array( 'include' => 'classic,settings' ) ) );

		$data = $this->json( $this->request( 'POST', '/import', $document ) );

		$this->assertSame( array( 'imported', 'classic', 'settings' ), array_keys( $data ) );
		$this->assertSame( array( 'classic', 'settings' ), $data['imported'] );
		$this->assertSame( $this->domain->field_sets( 'classic' )->get(), $data['classic'] );
		$this->assertSame( $this->domain->settings()->get(), $data['settings'] );
	}

	public function test_export_then_import_round_trips_everything() {
		$this->save_block_custom_field( 'gift_note' );
		$this->domain->settings()->save( array( 'localeOverrides' => array( 'label' => false ) ) );
		$exported = $this->json( $this->request( 'GET', '/export' ) );

		$this->request( 'POST', '/reset', array( 'target' => 'block' ) );
		$this->request( 'POST', '/reset', array( 'target' => 'settings' ) );
		$this->assertSame( 200, $this->request( 'POST', '/import', $exported )->get_status() );

		$again = $this->json( $this->request( 'GET', '/export' ) );
		unset( $exported['exportedAt'], $again['exportedAt'] );
		$this->assertSame( $exported, $again );
	}

	/**
	 * @dataProvider data_field_set_targets
	 *
	 * @param string $target Checkout type.
	 */
	public function test_reset_returns_a_field_set_to_the_defaults( $target ) {
		$set                             = $this->domain->field_sets( $target )->get();
		$set['sections']['additional'][] = array(
			'name'  => 'gift_note',
			'type'  => 'text',
			'label' => 'Gift note',
		);
		$this->domain->field_sets( $target )->save( $set );

		$response = $this->request( 'POST', '/reset', array( 'target' => $target ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $this->domain->field_sets( $target )->is_stored() );
		$this->assertSame(
			array(
				'reset' => $target,
				$target => $this->domain->field_sets( $target )->defaults(),
			),
			$this->json( $response )
		);
	}

	public function data_field_set_targets() {
		return array(
			'classic' => array( 'classic' ),
			'block'   => array( 'block' ),
		);
	}

	public function test_reset_returns_settings_to_the_defaults_and_keeps_the_field_sets() {
		$this->save_block_custom_field( 'gift_note' );
		$this->domain->settings()->save( array( 'localeOverrides' => array( 'label' => false ) ) );

		$response = $this->request( 'POST', '/reset', array( 'target' => 'settings' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $this->domain->settings()->is_stored() );
		$this->assertTrue( $this->domain->field_sets( 'block' )->is_stored() );
		$this->assertSame(
			array(
				'reset'    => 'settings',
				'settings' => $this->domain->settings()->defaults(),
			),
			$this->json( $response )
		);
	}

	public function test_reset_without_a_target_is_refused() {
		$this->assertErrorResponse( 400, 'rest_missing_callback_param', $this->request( 'POST', '/reset', new \stdClass() ) );
	}

	/**
	 * @dataProvider data_invalid_reset_targets
	 *
	 * @param mixed $target Target.
	 */
	public function test_reset_with_an_unknown_target_is_refused_and_changes_nothing( $target ) {
		$this->save_block_custom_field( 'gift_note' );
		$before = $this->stored_options();

		$this->assertErrorResponse( 400, 'rest_invalid_param', $this->request( 'POST', '/reset', array( 'target' => $target ) ) );
		$this->assertSame( $before, $this->stored_options() );
	}

	public function data_invalid_reset_targets() {
		return array(
			'unknown'      => array( 'orders' ),
			'empty'        => array( '' ),
			'two at once'  => array( array( 'classic', 'block' ) ),
			'other casing' => array( 'Block' ),
		);
	}
}
