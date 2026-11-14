<?php
/**
 * Tests for the admin page: menu item, assets and the data passed to the app.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Admin;

use CheckoutFieldKit\Admin\Page;
use CheckoutFieldKit\Plugin;
use CheckoutFieldKit\Tests\Integration\Rest\RestTestCase;
use WP_Error;
use WP_REST_Request;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride -- Resets WordPress's own globals between tests.

/**
 * WooCommerce → Checkout Fields (spec §6): the page is registered for
 * manage_woocommerce, loads the built app only on its own screen, and hands
 * the app its initial data.
 *
 * Extends RestTestCase for its REST server and store settings: the page
 * preloads its data through the plugin's routes, and the field defaults the
 * shared domain caches must match what the REST tests expect.
 */
class PageTest extends RestTestCase {

	/**
	 * Fixture build folder with an admin.asset.php.
	 *
	 * @var string
	 */
	private $build_dir;

	public function set_up() {
		parent::set_up();
		$this->build_dir = dirname( __DIR__, 2 ) . '/Fixtures/admin-build';
		$this->reset_globals();
	}

	public function tear_down() {
		$this->reset_globals();
		parent::tear_down();
	}

	private function reset_globals() {
		global $submenu, $menu, $_registered_pages, $_parent_pages, $wp_scripts, $wp_styles, $current_screen;
		$submenu           = array();
		$menu              = array();
		$_registered_pages = array();
		$_parent_pages     = array();
		$wp_scripts        = null;
		$wp_styles         = null;
		$current_screen    = null;
	}

	/**
	 * A page whose menu item has been added, as WordPress does on admin_menu.
	 *
	 * @param string|null $build_dir Build folder; the fixture by default.
	 * @return Page
	 */
	private function registered_page( $build_dir = null ) {
		add_menu_page( 'WooCommerce', 'WooCommerce', 'manage_woocommerce', 'woocommerce' );
		$page = new Page( CFK_TESTS_PLUGIN_FILE, null === $build_dir ? $this->build_dir : $build_dir );
		$page->add_page();

		return $page;
	}

	/**
	 * The object the page passes to the app as window.checkoutFieldkitAdmin.
	 *
	 * @return array
	 */
	private function app_settings() {
		$before = wp_scripts()->get_data( Page::SCRIPT_HANDLE, 'before' );
		$this->assertIsArray( $before );
		$script = implode( "\n", array_filter( $before ) );
		$this->assertMatchesRegularExpression( '/^window\.checkoutFieldkitAdmin = (.*);$/s', $script );
		preg_match( '/^window\.checkoutFieldkitAdmin = (.*);$/s', $script, $matches );

		return json_decode( $matches[1], true );
	}

	/**
	 * Body of a GET request to one of the plugin's routes.
	 *
	 * @param string $route Route below the namespace.
	 * @return mixed
	 */
	private function rest_get( $route ) {
		$response = rest_do_request( new WP_REST_Request( 'GET', '/checkout-fieldkit/v1' . $route ) );
		return json_decode( wp_json_encode( rest_get_server()->response_to_data( $response, false ) ), true );
	}

	public function test_plugin_registers_the_page_hooks() {
		$page = Plugin::instance()->get( Page::class );

		$this->assertInstanceOf( Page::class, $page );
		$this->assertSame( 65, has_action( 'admin_menu', array( $page, 'add_page' ) ) );
		$this->assertSame( 10, has_action( 'admin_enqueue_scripts', array( $page, 'enqueue_assets' ) ) );
		$this->assertSame( 10, has_filter( 'admin_body_class', array( $page, 'add_body_class' ) ) );
	}

	public function test_adds_checkout_fields_under_woocommerce_for_shop_managers() {
		global $submenu;
		$this->log_in_as( 'shop_manager' );

		$page = $this->registered_page();

		$this->assertSame( 'woocommerce_page_checkout-fieldkit', $page->hook_suffix() );
		$items = array_values(
			array_filter(
				$submenu['woocommerce'],
				function ( $item ) {
					return 'checkout-fieldkit' === $item[2];
				}
			)
		);
		$this->assertSame(
			array( array( 'Checkout Fields', 'manage_woocommerce', 'checkout-fieldkit', 'Checkout Fields' ) ),
			$items
		);
	}

	public function test_adds_no_menu_item_for_users_who_cannot_manage_woocommerce() {
		global $submenu;
		$this->log_in_as( 'customer' );

		$page = $this->registered_page();

		$this->assertSame( '', $page->hook_suffix() );
		$this->assertArrayNotHasKey( 'woocommerce', $submenu );
	}

	public function test_loads_nothing_on_other_admin_screens() {
		$this->log_in_as( 'shop_manager' );
		$page = $this->registered_page();

		$page->enqueue_assets( 'index.php' );
		$page->enqueue_assets( 'woocommerce_page_wc-settings' );

		$this->assertFalse( wp_script_is( Page::SCRIPT_HANDLE, 'registered' ) );
		$this->assertFalse( wp_style_is( Page::STYLE_HANDLE, 'registered' ) );
	}

	public function test_loads_nothing_before_the_page_is_registered() {
		$page = new Page( CFK_TESTS_PLUGIN_FILE, $this->build_dir );

		$page->enqueue_assets( '' );

		$this->assertFalse( wp_script_is( Page::SCRIPT_HANDLE, 'registered' ) );
	}

	public function test_enqueues_the_built_app_with_its_asset_file_dependencies() {
		$this->log_in_as( 'shop_manager' );
		$page = $this->registered_page();

		$page->enqueue_assets( 'woocommerce_page_checkout-fieldkit' );

		$this->assertTrue( wp_script_is( 'checkout-fieldkit-admin', 'enqueued' ) );
		$script = wp_scripts()->registered['checkout-fieldkit-admin'];
		$this->assertSame(
			array( 'react-jsx-runtime', 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-i18n' ),
			$script->deps
		);
		$this->assertSame( 'test-build-1', $script->ver );
		$this->assertStringEndsWith( '/build/admin.js', $script->src );
		$this->assertSame( 1, wp_scripts()->get_data( 'checkout-fieldkit-admin', 'group' ), 'Loaded in the footer.' );
		$this->assertSame( 'checkout-fieldkit', $script->textdomain );
	}

	public function test_enqueues_the_app_styles_after_wordpress_components() {
		$this->log_in_as( 'shop_manager' );
		$page = $this->registered_page();

		$page->enqueue_assets( 'woocommerce_page_checkout-fieldkit' );

		$this->assertTrue( wp_style_is( 'checkout-fieldkit-admin', 'enqueued' ) );
		$style = wp_styles()->registered['checkout-fieldkit-admin'];
		$this->assertSame( array( 'wp-components' ), $style->deps );
		$this->assertSame( 'test-build-1', $style->ver );
		$this->assertStringEndsWith( '/build/admin.css', $style->src );
		$this->assertSame( 'replace', wp_styles()->get_data( 'checkout-fieldkit-admin', 'rtl' ) );
	}

	public function test_passes_the_context_both_field_sets_and_the_settings_to_the_app() {
		$this->log_in_as( 'shop_manager' );
		$page = $this->registered_page();

		$page->enqueue_assets( 'woocommerce_page_checkout-fieldkit' );
		$settings = $this->app_settings();

		$this->assertSame( 'checkout-fieldkit/v1', $settings['restNamespace'] );
		$this->assertSame( $this->rest_get( '/context' ), $settings['preloaded']['context'] );
		$this->assertSame( $this->rest_get( '/field-sets/classic' ), $settings['preloaded']['fieldSets']['classic'] );
		$this->assertSame( $this->rest_get( '/field-sets/block' ), $settings['preloaded']['fieldSets']['block'] );
		$this->assertSame( $this->rest_get( '/settings' ), $settings['preloaded']['settings'] );
		$this->assertNotEmpty( $settings['preloaded']['fieldSets']['classic']['sections']['billing'] );
	}

	public function test_keeps_empty_sections_as_json_arrays() {
		$this->log_in_as( 'shop_manager' );
		$page = $this->registered_page();

		$page->enqueue_assets( 'woocommerce_page_checkout-fieldkit' );
		$script = implode( "\n", wp_scripts()->get_data( Page::SCRIPT_HANDLE, 'before' ) );

		$this->assertStringContainsString( '"additional":[]', $script );
	}

	public function test_passes_null_for_data_that_could_not_be_loaded() {
		$this->log_in_as( 'shop_manager' );
		add_filter(
			'rest_pre_dispatch',
			function ( $result, $server, $request ) {
				if ( '/checkout-fieldkit/v1/settings' === $request->get_route() ) {
					return new WP_Error( 'broken', 'Broken.', array( 'status' => 500 ) );
				}
				return $result;
			},
			10,
			3
		);
		$page = $this->registered_page();

		$page->enqueue_assets( 'woocommerce_page_checkout-fieldkit' );
		$settings = $this->app_settings();

		$this->assertNull( $settings['preloaded']['settings'] );
		$this->assertIsArray( $settings['preloaded']['context'] );
	}

	public function test_admin_settings_filter_changes_what_the_app_gets() {
		$this->log_in_as( 'shop_manager' );
		add_filter(
			'checkout_fieldkit_admin_settings',
			function ( $settings ) {
				$settings['addOn'] = array( 'enabled' => true );
				return $settings;
			}
		);
		$page = $this->registered_page();

		$page->enqueue_assets( 'woocommerce_page_checkout-fieldkit' );

		$this->assertSame( array( 'enabled' => true ), $this->app_settings()['addOn'] );
	}

	public function test_escapes_closing_script_tags_in_the_data() {
		$this->log_in_as( 'shop_manager' );
		add_filter(
			'checkout_fieldkit_admin_settings',
			function ( $settings ) {
				$settings['label'] = '</script><script>alert(1)</script>';
				return $settings;
			}
		);
		$page = $this->registered_page();

		$page->enqueue_assets( 'woocommerce_page_checkout-fieldkit' );
		$script = implode( "\n", wp_scripts()->get_data( Page::SCRIPT_HANDLE, 'before' ) );

		$this->assertStringNotContainsString( '</script>', $script );
		$this->assertSame( '</script><script>alert(1)</script>', $this->app_settings()['label'] );
	}

	public function test_loads_nothing_when_the_app_is_not_built() {
		$this->log_in_as( 'shop_manager' );
		$page = $this->registered_page( get_temp_dir() . 'cfk-no-build-' . wp_rand() );

		$page->enqueue_assets( 'woocommerce_page_checkout-fieldkit' );

		$this->assertFalse( wp_script_is( Page::SCRIPT_HANDLE, 'registered' ) );
		$this->assertFalse( wp_style_is( Page::STYLE_HANDLE, 'registered' ) );
	}

	public function test_renders_the_app_root_after_the_header_end_marker() {
		$this->log_in_as( 'shop_manager' );
		$page = $this->registered_page();

		ob_start();
		$page->render();
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression( '/<hr class="wp-header-end"\s*\/?>.*<div id="cfk-admin-root"/s', $html );
		$this->assertStringContainsString( '<noscript>', $html );
		$this->assertStringNotContainsString( 'npm run build', $html );
	}

	public function test_says_so_when_the_app_is_not_built() {
		$this->log_in_as( 'shop_manager' );
		$page = $this->registered_page( get_temp_dir() . 'cfk-no-build-' . wp_rand() );

		ob_start();
		$page->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'npm run build', $html );
	}

	public function test_adds_a_body_class_on_its_own_screen_only() {
		$this->log_in_as( 'shop_manager' );
		$page = $this->registered_page();

		set_current_screen( 'dashboard' );
		$this->assertSame( ' folded', $page->add_body_class( ' folded' ) );

		set_current_screen( 'woocommerce_page_checkout-fieldkit' );
		$this->assertSame( ' folded cfk-admin-screen ', $page->add_body_class( ' folded' ) );
	}
}
