<?php
/**
 * PSR-4 style autoloader for the plugin's classes.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit;

defined( 'ABSPATH' ) || exit;

/**
 * Maps a namespace prefix to a base directory: CheckoutFieldKit\Admin\Page is
 * loaded from includes/Admin/Page.php. No Composer at runtime.
 */
final class Autoloader {

	/**
	 * Namespace prefix with trailing backslash, for example "CheckoutFieldKit\".
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Base directory without trailing slash.
	 *
	 * @var string
	 */
	private $base_dir;

	/**
	 * Constructor.
	 *
	 * @param string $prefix   Namespace prefix.
	 * @param string $base_dir Directory that holds the classes of that namespace.
	 */
	public function __construct( string $prefix, string $base_dir ) {
		$this->prefix   = trim( $prefix, '\\' ) . '\\';
		$this->base_dir = rtrim( $base_dir, '/\\' );
	}

	/**
	 * Creates an autoloader and registers it with PHP.
	 *
	 * @param string $prefix   Namespace prefix.
	 * @param string $base_dir Directory that holds the classes of that namespace.
	 * @return callable The registered callback, for spl_autoload_unregister().
	 */
	public static function register( string $prefix, string $base_dir ): callable {
		$loader   = new self( $prefix, $base_dir );
		$callback = array( $loader, 'load' );
		spl_autoload_register( $callback );

		return $callback;
	}

	/**
	 * Loads the file for a class, if the class is in this namespace and the file exists.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return bool Whether a file was loaded.
	 */
	public function load( string $class_name ): bool {
		if ( 0 !== strpos( $class_name, $this->prefix ) ) {
			return false;
		}

		$relative = substr( $class_name, strlen( $this->prefix ) );
		$file     = $this->base_dir . '/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( ! is_readable( $file ) ) {
			return false;
		}

		require_once $file;

		return true;
	}
}
