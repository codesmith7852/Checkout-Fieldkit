<?php
/**
 * Contract for the parts of the plugin that hook into WordPress.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit;

defined( 'ABSPATH' ) || exit;

/**
 * A service adds its actions and filters when the plugin boots, and does
 * nothing in its constructor, so it can be created and tested in isolation.
 */
interface Service {

	/**
	 * Adds the service's actions and filters.
	 */
	public function register_hooks(): void;
}
