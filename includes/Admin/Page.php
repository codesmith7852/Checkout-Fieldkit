<?php
/**
 * The admin page: WooCommerce → Checkout Fields.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Admin;

use CheckoutFieldKit\Rest\Routes;
use CheckoutFieldKit\Service;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the Checkout Fields page under WooCommerce for users who can manage
 * WooCommerce (spec §6), and loads the React app built into build/ on that
 * screen only. The app gets its first data through `window.checkoutFieldkitAdmin`
 * (filter `checkout_fieldkit_admin_settings`), so it starts without waiting
 * for REST requests; it fetches anything that could not be preloaded.
 */
final class Page implements Service {

	/**
	 * Page slug (`admin.php?page=checkout-fieldkit`).
	 */
	public const SLUG = 'checkout-fieldkit';

	/**
	 * Capability needed to see the page; the same as the REST routes.
	 */
	public const CAPABILITY = Routes::CAPABILITY;

	/**
	 * Handle of the app's script.
	 */
	public const SCRIPT_HANDLE = 'checkout-fieldkit-admin';

	/**
	 * Handle of the app's stylesheet.
	 */
	public const STYLE_HANDLE = 'checkout-fieldkit-admin';

	/**
	 * Class added to the admin body on this screen, for the app's layout.
	 */
	public const BODY_CLASS = 'cfk-admin-screen';

	/**
	 * Priority on admin_menu: after WooCommerce's Status item (60), before Extensions (70).
	 */
	private const MENU_PRIORITY = 65;

	/**
	 * Main plugin file, for asset URLs.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Folder with admin.js, admin.css and admin.asset.php.
	 *
	 * @var string
	 */
	private $build_dir;

	/**
	 * The screen's hook suffix once the page is added; empty before, or when
	 * the current user cannot see it.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Constructor.
	 *
	 * @param string      $plugin_file Main plugin file.
	 * @param string|null $build_dir   Build folder; build/ next to the plugin file when null.
	 */
	public function __construct( string $plugin_file, ?string $build_dir = null ) {
		$this->plugin_file = $plugin_file;
		$this->build_dir   = null === $build_dir ? dirname( $plugin_file ) . '/build' : $build_dir;
	}

	/**
	 * Adds the menu item, the assets and the body class.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ), self::MENU_PRIORITY );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Adds WooCommerce → Checkout Fields.
	 */
	public function add_page(): void {
		$hook_suffix = add_submenu_page(
			'woocommerce',
			__( 'Checkout Fields', 'checkout-fieldkit' ),
			__( 'Checkout Fields', 'checkout-fieldkit' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);

		$this->hook_suffix = is_string( $hook_suffix ) ? $hook_suffix : '';
	}

	/**
	 * The screen's hook suffix (also its screen id), such as
	 * `woocommerce_page_checkout-fieldkit`; empty until the page is added.
	 *
	 * @return string
	 */
	public function hook_suffix(): string {
		return $this->hook_suffix;
	}

	/**
	 * Loads the app on this page's screen only.
	 *
	 * @param string $hook_suffix The current admin screen's hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ): void {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		$asset = $this->asset();
		if ( null === $asset ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'build/admin.js', $this->plugin_file ),
			$asset['dependencies'],
			$asset['version'],
			array( 'in_footer' => true )
		);
		wp_set_script_translations( self::SCRIPT_HANDLE, 'checkout-fieldkit' );
		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.checkoutFieldkitAdmin = ' . wp_json_encode( $this->app_settings() ) . ';',
			'before'
		);

		// The app's dialogs use WordPress's Modal, styled by wp-components.
		wp_enqueue_style(
			self::STYLE_HANDLE,
			plugins_url( 'build/admin.css', $this->plugin_file ),
			array( 'wp-components' ),
			$asset['version']
		);
		wp_style_add_data( self::STYLE_HANDLE, 'rtl', 'replace' );
	}

	/**
	 * Adds the app's body class on this page's screen.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function add_body_class( $classes ): string {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( '' === $this->hook_suffix || null === $screen || $screen->id !== $this->hook_suffix ) {
			return (string) $classes;
		}

		return $classes . ' ' . self::BODY_CLASS . ' ';
	}

	/**
	 * Prints the element the app mounts into. Admin notices land above it, at
	 * the header-end marker, so they never end up inside the app.
	 */
	public function render(): void {
		echo '<div class="cfk-admin-page">';
		echo '<hr class="wp-header-end" />';

		if ( null === $this->asset() ) {
			echo '<div class="notice notice-error inline"><p>';
			echo esc_html__( 'The Checkout Fields screen has not been built. Run npm run build in the plugin folder.', 'checkout-fieldkit' );
			echo '</p></div>';
		}

		echo '<div id="cfk-admin-root"></div>';
		echo '<noscript><div class="notice notice-error inline"><p>';
		echo esc_html__( 'Checkout Fields needs JavaScript. Turn it on in your browser to edit your checkout fields.', 'checkout-fieldkit' );
		echo '</p></div></noscript>';
		echo '</div>';
	}

	/**
	 * What the app receives as `window.checkoutFieldkitAdmin`.
	 *
	 * @return array<string, mixed>
	 */
	private function app_settings(): array {
		$settings = array(
			'restNamespace' => Routes::NAMESPACE,
			'preloaded'     => array(
				'context'   => $this->preload( '/context' ),
				'fieldSets' => array(
					'classic' => $this->preload( '/field-sets/classic' ),
					'block'   => $this->preload( '/field-sets/block' ),
				),
				'settings'  => $this->preload( '/settings' ),
			),
		);

		/**
		 * Filters the data passed to the admin app as `window.checkoutFieldkitAdmin`.
		 *
		 * `preloaded` holds the bodies of GET /context, /field-sets/classic,
		 * /field-sets/block and /settings, so the app can start without
		 * waiting for them; an entry is null when its request failed, and the
		 * app then fetches it itself. Add-ons can pass data of their own under
		 * a new key. The REST responses themselves are not filtered here.
		 *
		 * @since 0.1.0
		 *
		 * @param array $settings {
		 *     @type string $restNamespace REST namespace, `checkout-fieldkit/v1`.
		 *     @type array  $preloaded     {
		 *         @type array|null $context   GET /context.
		 *         @type array      $fieldSets `classic` and `block`: GET /field-sets/{type}, or null.
		 *         @type array|null $settings  GET /settings.
		 *     }
		 * }
		 */
		$filtered = apply_filters( 'checkout_fieldkit_admin_settings', $settings );

		return is_array( $filtered ) ? $filtered : $settings;
	}

	/**
	 * The body of a GET request to one of the plugin's routes, made inside
	 * this request with the current user's permissions; null on any error.
	 *
	 * @param string $route Route below the namespace, such as '/context'.
	 * @return mixed
	 */
	private function preload( string $route ) {
		$response = rest_do_request( new WP_REST_Request( 'GET', '/' . Routes::NAMESPACE . $route ) );
		if ( $response->is_error() ) {
			return null;
		}

		return rest_get_server()->response_to_data( $response, false );
	}

	/**
	 * The build's asset file: script dependencies and version. Null when the
	 * app has not been built.
	 *
	 * @return array{dependencies: string[], version: string}|null
	 */
	private function asset(): ?array {
		$file = $this->build_dir . '/admin.asset.php';
		if ( ! is_readable( $file ) || ! is_readable( $this->build_dir . '/admin.js' ) ) {
			return null;
		}

		$asset = require $file;
		if ( ! is_array( $asset ) || ! isset( $asset['dependencies'], $asset['version'] ) || ! is_array( $asset['dependencies'] ) ) {
			return null;
		}

		return array(
			'dependencies' => array_values( array_map( 'strval', $asset['dependencies'] ) ),
			'version'      => (string) $asset['version'],
		);
	}
}
