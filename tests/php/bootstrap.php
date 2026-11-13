<?php
/**
 * PHPUnit bootstrap: loads WordPress with WooCommerce and Checkout FieldKit.
 *
 * Order of events:
 * 1. Read the test settings (tests/php/wp-tests-config.php).
 * 2. Empty the test database tables, so WooCommerce tables from the previous run are gone too.
 * 3. Load the WordPress test suite, which installs WordPress, then loads WooCommerce
 *    and this plugin as must-use plugins, and installs WooCommerce's tables and options.
 *
 * @package CheckoutFieldKit\Tests
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test bootstrap.
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.RestrictedFunctions -- Runs before WordPress is loaded.

$cfk_plugin_dir = dirname( __DIR__, 2 );

require_once $cfk_plugin_dir . '/vendor/autoload.php';
require_once __DIR__ . '/wp-tests-config.php';

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $cfk_plugin_dir . '/vendor/yoast/phpunit-polyfills' );
define( 'CFK_TESTS_PLUGIN_FILE', $cfk_plugin_dir . '/checkout-fieldkit.php' );
define( 'CFK_TESTS_WC_DIR', rtrim( cfk_tests_setting( 'CFK_WC_DIR', dirname( $cfk_plugin_dir ) . '/woocommerce' ), '/\\' ) );
define( 'CFK_TESTS_HPOS', '0' !== cfk_tests_setting( 'CFK_TEST_HPOS', '1' ) );

if ( ! is_readable( ABSPATH . 'wp-settings.php' ) ) {
	fwrite( STDERR, 'WordPress not found in ' . ABSPATH . '. Set CFK_WP_CORE_DIR.' . PHP_EOL );
	exit( 1 );
}

if ( ! is_readable( CFK_TESTS_WC_DIR . '/woocommerce.php' ) ) {
	fwrite( STDERR, 'WooCommerce not found in ' . CFK_TESTS_WC_DIR . '. Set CFK_WC_DIR.' . PHP_EOL );
	exit( 1 );
}

/**
 * Drops every table with the test prefix, so each run starts from an empty database.
 *
 * The WordPress installer only drops WordPress's own tables; without this,
 * WooCommerce tables (orders, lookups, Action Scheduler) would carry over between runs.
 *
 * @param string $prefix Table prefix of the test site.
 */
function cfk_tests_empty_database( $prefix ) {
	mysqli_report( MYSQLI_REPORT_OFF );

	$host   = DB_HOST;
	$port   = null;
	$socket = null;
	if ( preg_match( '/^(.+):(\d+)$/', $host, $parts ) ) {
		$host = $parts[1];
		$port = (int) $parts[2];
	} elseif ( preg_match( '/^(.+):(\/.+)$/', $host, $parts ) ) {
		$host   = $parts[1];
		$socket = $parts[2];
	}

	$db = mysqli_connect( $host, DB_USER, DB_PASSWORD, DB_NAME, $port, $socket );
	if ( ! $db ) {
		fwrite( STDERR, sprintf( 'Cannot connect to the test database "%s": %s' . PHP_EOL, DB_NAME, mysqli_connect_error() ) );
		fwrite( STDERR, 'Create it and set CFK_TEST_DB_NAME, CFK_TEST_DB_USER and CFK_TEST_DB_PASSWORD (see README.md).' . PHP_EOL );
		exit( 1 );
	}

	$like   = str_replace( array( '\\', '_', '%' ), array( '\\\\', '\\_', '\\%' ), $prefix ) . '%';
	$result = mysqli_query( $db, "SHOW TABLES LIKE '" . mysqli_real_escape_string( $db, $like ) . "'" );

	mysqli_query( $db, 'SET foreign_key_checks = 0' );
	while ( $result && $row = mysqli_fetch_row( $result ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		mysqli_query( $db, 'DROP TABLE IF EXISTS `' . str_replace( '`', '``', $row[0] ) . '`' );
	}
	mysqli_query( $db, 'SET foreign_key_checks = 1' );
	mysqli_close( $db );
}

if ( '1' !== getenv( 'WP_TESTS_SKIP_INSTALL' ) ) {
	cfk_tests_empty_database( $table_prefix );
}

require_once getenv( 'WP_PHPUNIT__DIR' ) . '/includes/functions.php';

/*
 * Load WooCommerce, then this plugin, before other plugins would load.
 */
tests_add_filter(
	'muplugins_loaded',
	static function () {
		require CFK_TESTS_WC_DIR . '/woocommerce.php';
		require CFK_TESTS_PLUGIN_FILE;
	}
);

/*
 * Install WooCommerce's tables, options, pages and roles into the test database,
 * then choose the order storage every test starts with. This has to run before
 * init, where WooCommerce starts reading its tables.
 */
tests_add_filter(
	'setup_theme',
	static function () {
		// Changing the order-sync option clears scheduled actions before Action
		// Scheduler is ready (it starts on init). Nothing is scheduled in a fresh
		// test database, so silence that one "called incorrectly" notice.
		$quiet = static function ( $trigger, $function_name ) {
			return 'as_unschedule_all_actions' === $function_name ? false : $trigger;
		};
		add_filter( 'doing_it_wrong_trigger_error', $quiet, 10, 2 );

		WC_Install::install();

		// Pick up the capabilities WooCommerce just added.
		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_roles();

		\CheckoutFieldKit\Tests\Support\Hpos::prepare( CFK_TESTS_HPOS );

		remove_filter( 'doing_it_wrong_trigger_error', $quiet, 10 );
	}
);

require getenv( 'WP_PHPUNIT__DIR' ) . '/includes/bootstrap.php';
