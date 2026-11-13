<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Deletes the plugin's own options, on every site of a multisite network
 * (plugins are deleted network-wide there). Answers shoppers gave at checkout
 * are order and customer data and stay where they are.
 *
 * Keep the option names in sync with FieldSetRepository and SettingsRepository.
 *
 * @package CheckoutFieldKit
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

( static function () {
	$options = array( 'checkout_fieldkit_field_set_classic', 'checkout_fieldkit_field_set_block', 'checkout_fieldkit_settings' );

	if ( ! is_multisite() ) {
		array_map( 'delete_option', $options );
		return;
	}

	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		array_map( 'delete_option', $options );
		restore_current_blog();
	}
} )();
