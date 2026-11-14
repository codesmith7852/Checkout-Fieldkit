<?php
/**
 * Our scripts and data for WooCommerce's Checkout block.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Block;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Registered with the Checkout block's integration registry (see
 * CheckoutBlocks), which makes our scripts dependencies of the block's own
 * scripts, so our inner blocks are registered before the checkout renders:
 *
 * - frontend `checkout-fieldkit-checkout-blocks` (build/checkout-blocks.js):
 *   registers the inner blocks with registerCheckoutBlock();
 * - editor `checkout-fieldkit-checkout-blocks-editor`
 *   (build/checkout-blocks-editor.js): registers the block types and shows
 *   their placeholders in the editor;
 * - style `checkout-fieldkit-checkout-blocks` (build/checkout-blocks.css),
 *   enqueued with the checkout block's scripts by CheckoutBlocks.
 *
 * Dependencies and versions come from the `.asset.php` files that the build
 * writes (WooCommerce's dependency extraction maps `@woocommerce/*` imports to
 * WooCommerce's script handles). Without a build nothing is registered.
 *
 * The browser gets InnerBlockFields::script_data() as
 * `getSetting( 'checkout-fieldkit_data' )`.
 */
final class CheckoutIntegration implements IntegrationInterface {

	/**
	 * Handle of the frontend script and of the frontend style.
	 */
	public const FRONTEND_HANDLE = 'checkout-fieldkit-checkout-blocks';

	/**
	 * Handle of the editor script.
	 */
	public const EDITOR_HANDLE = 'checkout-fieldkit-checkout-blocks-editor';

	/**
	 * Build file names (without extension) by script handle.
	 */
	private const ENTRIES = array(
		self::FRONTEND_HANDLE => 'checkout-blocks',
		self::EDITOR_HANDLE   => 'checkout-blocks-editor',
	);

	/**
	 * The fields of our inner blocks.
	 *
	 * @var InnerBlockFields
	 */
	private $fields;

	/**
	 * The plugin's main file; the build folder is next to it.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Asset files read so far, keyed by entry (null when missing).
	 *
	 * @var array<string, array{dependencies: string[], version: string}|null>
	 */
	private $assets = array();

	/**
	 * Constructor.
	 *
	 * @param InnerBlockFields $fields      The fields of our inner blocks.
	 * @param string           $plugin_file The plugin's main file.
	 */
	public function __construct( InnerBlockFields $fields, string $plugin_file ) {
		$this->fields      = $fields;
		$this->plugin_file = $plugin_file;
	}

	/**
	 * The integration's name; also the prefix of its script data key.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'checkout-fieldkit';
	}

	/**
	 * Registers the scripts and the style (WooCommerce calls this while it
	 * registers the Checkout block, on every request; nothing is enqueued here).
	 */
	public function initialize() {
		foreach ( self::ENTRIES as $handle => $entry ) {
			$asset = $this->asset( $entry );
			if ( null === $asset ) {
				continue;
			}

			wp_register_script( $handle, $this->url( $entry . '.js' ), $asset['dependencies'], $asset['version'], true );
			wp_set_script_translations( $handle, 'checkout-fieldkit', dirname( $this->plugin_file ) . '/languages' );

			if ( self::FRONTEND_HANDLE === $handle && is_readable( $this->path( $entry . '.css' ) ) ) {
				wp_register_style( $handle, $this->url( $entry . '.css' ), array(), $asset['version'] );
			}
		}
	}

	/**
	 * Frontend script handles, for the Checkout block's frontend script to depend on.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return $this->built( self::FRONTEND_HANDLE );
	}

	/**
	 * Editor script handles, for the Checkout block's editor script to depend on.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return $this->built( self::EDITOR_HANDLE );
	}

	/**
	 * Data for the browser: the fields of Contact and Additional.
	 *
	 * @return array<string, mixed>
	 */
	public function get_script_data() {
		return $this->fields->script_data();
	}

	/**
	 * A handle in a list when its build exists, otherwise an empty list.
	 *
	 * @param string $handle Script handle.
	 * @return string[]
	 */
	private function built( string $handle ): array {
		return null === $this->asset( self::ENTRIES[ $handle ] ) ? array() : array( $handle );
	}

	/**
	 * The dependencies and version the build wrote for an entry.
	 *
	 * @param string $entry Entry name.
	 * @return array{dependencies: string[], version: string}|null Null without a build.
	 */
	private function asset( string $entry ): ?array {
		if ( array_key_exists( $entry, $this->assets ) ) {
			return $this->assets[ $entry ];
		}

		$file  = $this->path( $entry . '.asset.php' );
		$asset = is_readable( $file ) ? require $file : null;

		$this->assets[ $entry ] = is_array( $asset ) ? array(
			'dependencies' => isset( $asset['dependencies'] ) ? array_values( array_map( 'strval', (array) $asset['dependencies'] ) ) : array(),
			'version'      => isset( $asset['version'] ) ? (string) $asset['version'] : \CheckoutFieldKit\VERSION,
		) : null;

		return $this->assets[ $entry ];
	}

	/**
	 * Path of a build file.
	 *
	 * @param string $file File name.
	 * @return string
	 */
	private function path( string $file ): string {
		return dirname( $this->plugin_file ) . '/build/' . $file;
	}

	/**
	 * URL of a build file.
	 *
	 * @param string $file File name.
	 * @return string
	 */
	private function url( string $file ): string {
		return plugins_url( 'build/' . $file, $this->plugin_file );
	}
}
