<?php
/**
 * Starts the plugin once WordPress has loaded all plugins.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the plugin when its requirements are met; otherwise shows why not
 * and loads nothing else.
 */
final class Loader {

	/**
	 * Runs on plugins_loaded, when WooCommerce (if active) has been included.
	 */
	public static function on_plugins_loaded(): void {
		self::load( Requirements::from_environment(), Plugin::instance() );
	}

	/**
	 * Boots the plugin or registers the requirements notice.
	 *
	 * @param Requirements $requirements What the plugin needs.
	 * @param Plugin       $plugin       The plugin to boot.
	 * @return bool Whether the plugin was booted.
	 */
	public static function load( Requirements $requirements, Plugin $plugin ): bool {
		if ( ! $requirements->are_met() ) {
			( new RequirementsNotice( $requirements ) )->register_hooks();
			return false;
		}

		$plugin->boot();

		return true;
	}
}
