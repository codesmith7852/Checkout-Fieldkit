<?php
/**
 * Configuration for the WordPress test suite.
 *
 * Every value can be set with an environment variable. Values that are not in
 * the environment are read from the git-ignored file tests/php/local-config.php
 * (copy local-config.sample.php to create it), then fall back to the defaults
 * below. Give every developer or parallel run its own database: the suite
 * empties the test tables on every run.
 *
 * This file is also loaded by the suite's installer in a separate PHP process,
 * so it must not depend on anything defined in bootstrap.php.
 *
 * @package CheckoutFieldKit\Tests
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- WordPress test suite configuration names.

if ( ! function_exists( 'cfk_tests_setting' ) ) {
	/**
	 * Reads one test setting: environment first, then the local config file, then the default.
	 * An empty value counts as not set.
	 *
	 * @param string $name     Setting name, for example CFK_TEST_DB_NAME.
	 * @param string $fallback Value used when the setting is not set anywhere.
	 * @return string
	 */
	function cfk_tests_setting( $name, $fallback ) {
		static $local = null;

		$value = getenv( $name );
		if ( false !== $value && '' !== $value ) {
			return $value;
		}

		if ( null === $local ) {
			$file  = __DIR__ . '/local-config.php';
			$local = is_readable( $file ) ? (array) require $file : array();
		}

		return isset( $local[ $name ] ) && '' !== (string) $local[ $name ] ? (string) $local[ $name ] : $fallback;
	}
}

$cfk_plugin_dir = dirname( __DIR__, 2 );

/*
 * WordPress core files. Defaults to the site this plugin is installed in
 * (wp-content/plugins/checkout-fieldkit → three levels up).
 */
$cfk_abspath = rtrim( cfk_tests_setting( 'CFK_WP_CORE_DIR', dirname( $cfk_plugin_dir, 3 ) ), '/\\' ) . '/';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $cfk_abspath );
}

define( 'DB_NAME', cfk_tests_setting( 'CFK_TEST_DB_NAME', 'cfk_tests' ) );
define( 'DB_USER', cfk_tests_setting( 'CFK_TEST_DB_USER', 'root' ) );
define( 'DB_PASSWORD', cfk_tests_setting( 'CFK_TEST_DB_PASSWORD', '' ) );
define( 'DB_HOST', cfk_tests_setting( 'CFK_TEST_DB_HOST', 'localhost' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = cfk_tests_setting( 'CFK_TEST_TABLE_PREFIX', 'wptests_' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The test suite reads it.

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Checkout FieldKit Tests' );
define( 'WP_PHP_BINARY', cfk_tests_setting( 'CFK_PHP_BINARY', PHP_BINARY ) );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
define( 'WP_DEFAULT_THEME', 'default' );
