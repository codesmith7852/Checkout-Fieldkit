<?php
/**
 * Local test settings. Copy this file to tests/php/local-config.php (git-ignored)
 * and fill in what differs on your machine. Environment variables with the same
 * names take precedence, so CI and one-off runs can override any of these.
 *
 * The test database must exist and be used for nothing else: every run drops and
 * recreates the tables that start with the table prefix.
 *
 * @package CheckoutFieldKit\Tests
 */

return array(
	// Database. Use your own database name so parallel runs do not collide.
	'CFK_TEST_DB_NAME'      => 'cfk_tests',
	'CFK_TEST_DB_USER'      => 'root',
	'CFK_TEST_DB_PASSWORD'  => '',
	'CFK_TEST_DB_HOST'      => 'localhost',
	'CFK_TEST_TABLE_PREFIX' => 'wptests_',

	// Leave a value empty to use its default.

	// WordPress core directory. Default: the site this plugin is installed in.
	'CFK_WP_CORE_DIR'       => '',

	// WooCommerce plugin directory. Default: the woocommerce folder next to this plugin.
	'CFK_WC_DIR'            => '',

	// Start every test with High-Performance Order Storage on ('1') or off ('0'). Default: on.
	'CFK_TEST_HPOS'         => '',
);
