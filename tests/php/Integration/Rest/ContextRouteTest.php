<?php
/**
 * Tests for GET /context.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Rest;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Fields\Field;
use CheckoutFieldKit\Rest\Context;

/**
 * Spec §7: everything the admin needs that isn't stored data.
 */
class ContextRouteTest extends RestTestCase {

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
	 * Uses a page with the given content as WooCommerce's Checkout page.
	 *
	 * @param string $content Post content.
	 * @return int Page ID.
	 */
	private function use_checkout_page( $content ) {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => $content,
			)
		);
		update_option( 'woocommerce_checkout_page_id', $page_id );

		return $page_id;
	}

	public function test_returns_every_key_the_admin_uses() {
		$response = $this->request( 'GET', '/context' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'activeCheckoutType',
				'checkoutPageUrl',
				'blockSupported',
				'wooCommerceVersion',
				'sections',
				'fieldTypes',
				'validations',
				'essentialFields',
				'builtInEditableSettings',
				'reservedNames',
				'fieldNamePattern',
				'fieldDefaults',
				'headingLevels',
				'defaultSettings',
			),
			array_keys( $this->json( $response ) )
		);
	}

	public function test_describes_sections_field_types_and_essential_fields_of_both_checkout_types() {
		$data = $this->json( $this->request( 'GET', '/context' ) );

		$this->assertSame( $this->domain->sections()->describe(), $data['sections'] );
		$this->assertSame( $this->domain->field_types()->describe(), $data['fieldTypes'] );
		$this->assertSame( $this->domain->essential_fields()->describe(), $data['essentialFields'] );
	}

	public function test_field_types_carry_labels_allowed_sections_and_settings() {
		$data = $this->json( $this->request( 'GET', '/context' ) );

		$block_radio = array_values(
			array_filter(
				$data['fieldTypes']['block'],
				static function ( $type ) {
					return 'radio' === $type['name'];
				}
			)
		)[0];

		$this->assertSame( 'Radio buttons', $block_radio['label'] );
		$this->assertSame( array( 'contact', 'additional' ), $block_radio['sections'] );
		$this->assertContains( 'options', $block_radio['settings'] );
		$this->assertCount( 20, $this->custom_types( $data['fieldTypes']['classic'] ) );
		$this->assertCount( 10, $this->custom_types( $data['fieldTypes']['block'] ) );
	}

	public function test_lists_the_validation_choices_of_each_checkout_type() {
		$data = $this->json( $this->request( 'GET', '/context' ) );

		$this->assertSame(
			array(
				'classic' => array( 'email', 'phone', 'postcode', 'state', 'number', 'url' ),
				'block'   => array( 'email', 'phone', 'postcode', 'url' ),
			),
			array_map(
				static function ( $choices ) {
					return array_column( $choices, 'name' );
				},
				$data['validations']
			)
		);
		$this->assertSame( 'Email address', $data['validations']['classic'][0]['label'] );
	}

	public function test_says_which_settings_of_each_built_in_field_can_change() {
		$data = $this->json( $this->request( 'GET', '/context' ) );

		$this->assertSame( $this->domain->defaults()->names( 'classic' ), array_keys( $data['builtInEditableSettings']['classic'] ) );
		$this->assertSame( $this->domain->defaults()->names( 'block' ), array_keys( $data['builtInEditableSettings']['block'] ) );
		$this->assertSame( array( 'label', 'placeholder', 'default', 'required', 'enabled', 'validation', 'classes' ), $data['builtInEditableSettings']['classic']['billing_email'] );
		$this->assertSame( array( 'label', 'required', 'enabled' ), $data['builtInEditableSettings']['block']['country'] );
		$this->assertSame( array(), $data['builtInEditableSettings']['block']['email'] );
	}

	public function test_gives_what_the_admin_needs_to_check_new_fields() {
		$data = $this->json( $this->request( 'GET', '/context' ) );

		$this->assertSame( $this->domain->defaults()->reserved_names(), $data['reservedNames'] );
		$this->assertContains( 'payment_method', $data['reservedNames'] );
		$this->assertSame( '^[a-z][a-z0-9_]{0,59}$', $data['fieldNamePattern'] );
		$this->assertSame( Field::defaults( 'classic' ), $data['fieldDefaults']['classic'] );
		$this->assertSame( Field::defaults( 'block' ), $data['fieldDefaults']['block'] );
		$this->assertSame( array( 'h2', 'h3', 'h4', 'h5', 'h6' ), $data['headingLevels'] );
		$this->assertSame( $this->domain->settings()->defaults(), $data['defaultSettings'] );
	}

	public function test_field_defaults_keep_empty_lists_as_json_arrays() {
		$response = $this->request( 'GET', '/context' );
		$json     = wp_json_encode( rest_get_server()->response_to_data( $response, false ) );

		$this->assertStringContainsString( '"options":[]', $json );
		$this->assertStringContainsString( '"email":[]', $json );
	}

	public function test_reports_the_block_checkout_page() {
		$page_id = $this->use_checkout_page( '<!-- wp:woocommerce/checkout /-->' );

		$data = $this->json( $this->request( 'GET', '/context' ) );

		$this->assertSame( 'block', $data['activeCheckoutType'] );
		$this->assertSame( get_permalink( $page_id ), $data['checkoutPageUrl'] );
	}

	public function test_reports_the_shortcode_checkout_page() {
		$this->use_checkout_page( '[woocommerce_checkout]' );

		$this->assertSame( 'classic', $this->json( $this->request( 'GET', '/context' ) )['activeCheckoutType'] );
	}

	public function test_reports_the_classic_shortcode_block_checkout_page() {
		$this->use_checkout_page( '<!-- wp:woocommerce/classic-shortcode {"shortcode":"checkout"} /-->' );

		$this->assertSame( 'classic', $this->json( $this->request( 'GET', '/context' ) )['activeCheckoutType'] );
	}

	public function test_reports_unknown_without_a_checkout_page() {
		delete_option( 'woocommerce_checkout_page_id' );

		$data = $this->json( $this->request( 'GET', '/context' ) );

		$this->assertSame( 'unknown', $data['activeCheckoutType'] );
		$this->assertNull( $data['checkoutPageUrl'] );
	}

	public function test_reports_the_woocommerce_version_and_block_support() {
		$data = $this->json( $this->request( 'GET', '/context' ) );

		$this->assertSame( WC_VERSION, $data['wooCommerceVersion'] );
		$this->assertTrue( $data['blockSupported'] );
	}

	/**
	 * @dataProvider data_block_support
	 *
	 * @param string $version  WooCommerce version.
	 * @param bool   $expected Whether block features are available.
	 */
	public function test_block_features_need_woocommerce_8_9( $version, $expected ) {
		$this->assertSame( $expected, Context::block_supported( $version ) );
	}

	public function data_block_support() {
		return array(
			'8.8.5'  => array( '8.8.5', false ),
			'8.9'    => array( '8.9', true ),
			'8.9.0'  => array( '8.9.0', true ),
			'11.3.0' => array( '11.3.0', true ),
		);
	}

	/**
	 * Names of the types a custom field may have.
	 *
	 * @param array<int, array<string, mixed>> $types Type definitions.
	 * @return string[]
	 */
	private function custom_types( array $types ) {
		return array_column(
			array_filter(
				$types,
				static function ( $type ) {
					return ! $type['builtInOnly'];
				}
			),
			'name'
		);
	}
}
