<?php
/**
 * Tests for our Block checkout inner blocks: integration, block types and
 * how they get into existing Checkout pages.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Block;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry;
use CheckoutFieldKit\Block\CheckoutBlocks;
use CheckoutFieldKit\Block\CheckoutIntegration;
use CheckoutFieldKit\Block\InnerBlockFields;
use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Environment\BlockSupport;
use CheckoutFieldKit\Plugin;
use CheckoutFieldKit\Tests\TestCase;
use WP_Block_Type_Registry;

/**
 * Spec §5, second bullet: two checkout inner blocks, Contact (after the
 * Contact information block) and Additional (in the order information area),
 * registered with WooCommerce's checkout block integration and present in
 * existing Checkout pages without editing them.
 */
class CheckoutBlocksTest extends TestCase {

	/**
	 * The fixture plugin file whose build folder holds known asset files.
	 *
	 * @var string
	 */
	private $fixture_file;

	public function set_up() {
		parent::set_up();
		$this->fixture_file = dirname( __DIR__, 2 ) . '/Fixtures/plugin/checkout-fieldkit.php';
	}

	public function tear_down() {
		foreach ( array( CheckoutIntegration::FRONTEND_HANDLE, CheckoutIntegration::EDITOR_HANDLE ) as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
		parent::tear_down();
	}

	/**
	 * An integration reading the fixture build folder.
	 *
	 * @return CheckoutIntegration
	 */
	private function integration() {
		$domain = new Domain();

		return new CheckoutIntegration( new InnerBlockFields( $domain->field_sets( 'block' ), $domain->field_types() ), $this->fixture_file );
	}

	public function test_the_plugin_registers_the_service() {
		$this->assertInstanceOf( CheckoutBlocks::class, Plugin::instance()->get( CheckoutBlocks::class ) );
	}

	public function test_the_integration_is_registered_with_the_checkout_block() {
		$service  = new CheckoutBlocks( new Domain(), $this->fixture_file );
		$registry = new IntegrationRegistry();

		$service->register_integration( $registry );

		$this->assertTrue( $registry->is_registered( 'checkout-fieldkit' ) );
		$this->assertInstanceOf( CheckoutIntegration::class, $registry->get_registered( 'checkout-fieldkit' ) );
	}

	public function test_the_running_plugin_has_registered_its_integration_with_woocommerce() {
		$this->assertNotFalse( has_action( 'woocommerce_blocks_checkout_block_registration', array( Plugin::instance()->get( CheckoutBlocks::class ), 'register_integration' ) ) );
	}

	public function test_without_block_support_nothing_is_hooked() {
		$service = new CheckoutBlocks( new Domain(), $this->fixture_file, new BlockSupport( null, false ) );
		$service->register_hooks();

		$this->assertFalse( has_action( 'woocommerce_blocks_checkout_block_registration', array( $service, 'register_integration' ) ) );
		$this->assertFalse( has_filter( 'render_block_woocommerce/checkout', array( $service, 'add_additional_block' ) ) );
	}

	public function test_the_integration_registers_its_frontend_and_editor_scripts_from_the_build() {
		$integration = $this->integration();

		$integration->initialize();

		$this->assertSame( 'checkout-fieldkit', $integration->get_name() );
		$this->assertSame( array( 'checkout-fieldkit-checkout-blocks' ), $integration->get_script_handles() );
		$this->assertSame( array( 'checkout-fieldkit-checkout-blocks-editor' ), $integration->get_editor_script_handles() );

		$frontend = wp_scripts()->registered['checkout-fieldkit-checkout-blocks'];
		$this->assertStringEndsWith( '/build/checkout-blocks.js', $frontend->src );
		$this->assertSame( 'frontend-fixture', $frontend->ver );
		$this->assertContains( 'wc-blocks-checkout', $frontend->deps, 'WooCommerce packages are external dependencies.' );
		$this->assertContains( 'wc-blocks-components', $frontend->deps );

		$editor = wp_scripts()->registered['checkout-fieldkit-checkout-blocks-editor'];
		$this->assertStringEndsWith( '/build/checkout-blocks-editor.js', $editor->src );
		$this->assertSame( 'editor-fixture', $editor->ver );
		$this->assertContains( 'wp-blocks', $editor->deps );

		$this->assertSame( 'checkout-fieldkit', wp_scripts()->registered['checkout-fieldkit-checkout-blocks']->textdomain );

		$style = wp_styles()->registered['checkout-fieldkit-checkout-blocks'];
		$this->assertStringEndsWith( '/build/checkout-blocks.css', $style->src );
	}

	public function test_without_a_build_the_scripts_are_not_registered() {
		$integration = new CheckoutIntegration( $this->integration_fields(), dirname( __DIR__, 2 ) . '/Fixtures/no-such-plugin/checkout-fieldkit.php' );

		$integration->initialize();

		$this->assertFalse( wp_script_is( 'checkout-fieldkit-checkout-blocks', 'registered' ) );
		$this->assertSame( array(), $integration->get_script_handles(), 'WooCommerce is not given a handle it cannot load.' );
		$this->assertSame( array(), $integration->get_editor_script_handles() );
	}

	/**
	 * The inner block fields of a fresh domain.
	 *
	 * @return InnerBlockFields
	 */
	private function integration_fields() {
		$domain = new Domain();

		return new InnerBlockFields( $domain->field_sets( 'block' ), $domain->field_types() );
	}

	public function test_the_script_data_is_the_inner_block_fields() {
		$repository = ( new Domain() )->field_sets( 'block' );
		$set        = $repository->get();

		$set['sections']['contact'][] = array(
			'name'  => 'nickname',
			'type'  => 'text',
			'label' => 'Nickname',
		);
		$this->assertTrue( $repository->save( $set ) );

		$data = $this->integration()->get_script_data();

		$this->assertSame( $this->integration_fields()->script_data(), $data );
		$this->assertSame( 'nickname', $data['sections']['contact'][0]['name'] );
	}

	public function test_the_style_is_enqueued_with_the_checkout_block_scripts() {
		$service = new CheckoutBlocks( new Domain(), $this->fixture_file );
		$this->integration()->initialize();

		$service->enqueue_style();

		$this->assertTrue( wp_style_is( 'checkout-fieldkit-checkout-blocks', 'enqueued' ) );
		$this->assertNotFalse( has_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_after', array( Plugin::instance()->get( CheckoutBlocks::class ), 'enqueue_style' ) ) );
	}

	/**
	 * @dataProvider data_block_types
	 *
	 * @param string $name   Block name.
	 * @param string $area   Parent block.
	 */
	public function test_the_block_types_are_registered_inside_woocommerce_checkout_areas( $name, $area ) {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( $name );

		$this->assertNotNull( $type, $name . ' is registered.' );
		$this->assertSame( array( $area ), $type->parent );
		$this->assertSame( 'woocommerce', $type->category );
		$this->assertSame( 3, $type->api_version );
		$this->assertFalse( $type->supports['multiple'] );
		$this->assertFalse( $type->supports['inserter'], 'Added automatically, never by hand.' );
	}

	public function data_block_types() {
		return array(
			'contact'    => array( 'checkout-fieldkit/contact-fields', 'woocommerce/checkout-contact-information-block' ),
			'additional' => array( 'checkout-fieldkit/additional-fields', 'woocommerce/checkout-fields-block' ),
		);
	}

	public function test_the_contact_block_is_forced_and_the_additional_block_is_placed_by_us() {
		$contact    = WP_Block_Type_Registry::get_instance()->get_registered( 'checkout-fieldkit/contact-fields' );
		$additional = WP_Block_Type_Registry::get_instance()->get_registered( 'checkout-fieldkit/additional-fields' );

		$this->assertTrue( $contact->attributes['lock']['default']['remove'], 'WooCommerce inserts locked blocks of its areas by itself.' );
		$this->assertArrayNotHasKey( 'default', $additional->attributes['lock'], 'WooCommerce would append it after Place order; we place it.' );
	}

	/**
	 * Checkout block content as WooCommerce renders it: inner blocks with
	 * their data-block-name added.
	 *
	 * @param string[] $inner Inner block names of the fields block, without the namespace.
	 * @return string
	 */
	private function rendered_checkout( array $inner ) {
		$html = '<div class="wp-block-woocommerce-checkout alignwide wc-block-checkout is-loading" data-block-name="woocommerce/checkout"><div class="wp-block-woocommerce-checkout-fields-block" data-block-name="woocommerce/checkout-fields-block">';
		foreach ( $inner as $name ) {
			$html .= "\n" . '<div class="wp-block-woocommerce-' . $name . '" data-block-name="woocommerce/' . $name . '"></div>';
		}

		return $html . '</div></div>';
	}

	/**
	 * Our Additional block's placeholder, as the frontend finds it.
	 *
	 * @return string
	 */
	private function additional_placeholder() {
		return '<div data-block-name="checkout-fieldkit/additional-fields" class="wp-block-checkout-fieldkit-additional-fields"></div>';
	}

	public function test_the_additional_block_is_added_after_the_additional_information_block() {
		$service = new CheckoutBlocks( new Domain(), $this->fixture_file );
		$content = $this->rendered_checkout( array( 'checkout-contact-information-block', 'checkout-payment-block', 'checkout-additional-information-block', 'checkout-order-note-block', 'checkout-actions-block' ) );

		$result = $service->add_additional_block( $content );

		$this->assertStringContainsString(
			'data-block-name="woocommerce/checkout-additional-information-block"></div>' . $this->additional_placeholder() . "\n" . '<div class="wp-block-woocommerce-checkout-order-note-block"',
			$result
		);
		$this->assertSame( 1, substr_count( $result, 'checkout-fieldkit/additional-fields' ) );
	}

	/**
	 * @dataProvider data_fallback_places
	 *
	 * @param string[] $inner  Inner blocks.
	 * @param string   $before The block ours goes before.
	 */
	public function test_without_the_additional_information_block_it_goes_before_the_next_block_there_is( $inner, $before ) {
		$service = new CheckoutBlocks( new Domain(), $this->fixture_file );

		$result = $service->add_additional_block( $this->rendered_checkout( $inner ) );

		$this->assertStringContainsString( $this->additional_placeholder() . '<div class="wp-block-woocommerce-' . $before . '"', $result );
	}

	public function data_fallback_places() {
		return array(
			'order note' => array( array( 'checkout-payment-block', 'checkout-order-note-block', 'checkout-terms-block', 'checkout-actions-block' ), 'checkout-order-note-block' ),
			'terms'      => array( array( 'checkout-payment-block', 'checkout-terms-block', 'checkout-actions-block' ), 'checkout-terms-block' ),
			'actions'    => array( array( 'checkout-payment-block', 'checkout-actions-block' ), 'checkout-actions-block' ),
		);
	}

	public function test_content_without_any_known_place_is_left_alone() {
		$service = new CheckoutBlocks( new Domain(), $this->fixture_file );
		$content = '[woocommerce_checkout]';

		$this->assertSame( $content, $service->add_additional_block( $content ) );
	}

	public function test_a_page_that_already_has_the_additional_block_keeps_it_where_the_merchant_put_it() {
		$service = new CheckoutBlocks( new Domain(), $this->fixture_file );
		$content = str_replace(
			'<div class="wp-block-woocommerce-checkout-payment-block"',
			'<div class="wp-block-checkout-fieldkit-additional-fields" data-block-name="checkout-fieldkit/additional-fields"></div><div class="wp-block-woocommerce-checkout-payment-block"',
			$this->rendered_checkout( array( 'checkout-payment-block', 'checkout-additional-information-block', 'checkout-actions-block' ) )
		);

		$this->assertSame( $content, $service->add_additional_block( $content ) );
	}

	public function test_an_existing_checkout_page_renders_with_the_additional_block_in_place() {
		$page = '<!-- wp:woocommerce/checkout -->
<div class="wp-block-woocommerce-checkout alignwide wc-block-checkout is-loading"><!-- wp:woocommerce/checkout-fields-block -->
<div class="wp-block-woocommerce-checkout-fields-block"><!-- wp:woocommerce/checkout-contact-information-block -->
<div class="wp-block-woocommerce-checkout-contact-information-block"></div>
<!-- /wp:woocommerce/checkout-contact-information-block -->

<!-- wp:woocommerce/checkout-payment-block -->
<div class="wp-block-woocommerce-checkout-payment-block"></div>
<!-- /wp:woocommerce/checkout-payment-block -->

<!-- wp:woocommerce/checkout-additional-information-block -->
<div class="wp-block-woocommerce-checkout-additional-information-block"></div>
<!-- /wp:woocommerce/checkout-additional-information-block -->

<!-- wp:woocommerce/checkout-order-note-block -->
<div class="wp-block-woocommerce-checkout-order-note-block"></div>
<!-- /wp:woocommerce/checkout-order-note-block -->

<!-- wp:woocommerce/checkout-actions-block -->
<div class="wp-block-woocommerce-checkout-actions-block"></div>
<!-- /wp:woocommerce/checkout-actions-block --></div>
<!-- /wp:woocommerce/checkout-fields-block --></div>
<!-- /wp:woocommerce/checkout -->';

		$html = do_blocks( $page );

		$this->assertSame( 1, preg_match( '#data-block-name="woocommerce/checkout-additional-information-block"[^>]*></div>\s*<div data-block-name="checkout-fieldkit/additional-fields"#', $html ), $html );
		$this->assertLessThan( strpos( $html, 'woocommerce/checkout-order-note-block' ), strpos( $html, 'checkout-fieldkit/additional-fields' ) );
	}

	public function test_a_page_saved_with_the_additional_block_gets_it_once_where_saved() {
		$page = '<!-- wp:woocommerce/checkout -->
<div class="wp-block-woocommerce-checkout alignwide wc-block-checkout is-loading"><!-- wp:woocommerce/checkout-fields-block -->
<div class="wp-block-woocommerce-checkout-fields-block"><!-- wp:woocommerce/checkout-payment-block -->
<div class="wp-block-woocommerce-checkout-payment-block"></div>
<!-- /wp:woocommerce/checkout-payment-block -->

<!-- wp:checkout-fieldkit/additional-fields {"lock":{"remove":true}} -->
<div class="wp-block-checkout-fieldkit-additional-fields"></div>
<!-- /wp:checkout-fieldkit/additional-fields -->

<!-- wp:woocommerce/checkout-additional-information-block -->
<div class="wp-block-woocommerce-checkout-additional-information-block"></div>
<!-- /wp:woocommerce/checkout-additional-information-block -->

<!-- wp:woocommerce/checkout-actions-block -->
<div class="wp-block-woocommerce-checkout-actions-block"></div>
<!-- /wp:woocommerce/checkout-actions-block --></div>
<!-- /wp:woocommerce/checkout-fields-block --></div>
<!-- /wp:woocommerce/checkout -->';

		$html = do_blocks( $page );

		$this->assertSame( 1, substr_count( $html, 'data-block-name="checkout-fieldkit/additional-fields"' ), $html );
		$this->assertLessThan( strpos( $html, 'woocommerce/checkout-additional-information-block' ), strpos( $html, 'checkout-fieldkit/additional-fields' ) );
	}
}
