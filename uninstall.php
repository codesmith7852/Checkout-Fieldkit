<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Deletes the plugin's own options. Answers shoppers gave at checkout are
 * order and customer data and stay where they are.
 *
 * @package CheckoutFieldKit
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

array_map( 'delete_option', array( 'cfk_field_set_classic', 'cfk_field_set_block', 'cfk_settings' ) );
