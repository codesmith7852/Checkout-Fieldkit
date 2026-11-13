<?php
/**
 * The plugin's composition root.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the plugin's services and registers their hooks once WooCommerce is
 * known to be available. Later features add their service in default_services().
 */
final class Plugin {

	/**
	 * The shared instance used by the running plugin.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Services keyed by class name.
	 *
	 * @var array<string, Service>
	 */
	private $services = array();

	/**
	 * Whether boot() has run.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * The shared instance, with the plugin's own services added.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			foreach ( self::$instance->default_services() as $service ) {
				self::$instance->add( $service );
			}
		}

		return self::$instance;
	}

	/**
	 * Adds a service. After boot its hooks are registered straight away.
	 *
	 * @param Service $service The service.
	 * @return Plugin
	 */
	public function add( Service $service ): Plugin {
		$this->services[ get_class( $service ) ] = $service;

		if ( $this->booted ) {
			$service->register_hooks();
		}

		return $this;
	}

	/**
	 * Returns a service by class name.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return Service|null
	 */
	public function get( string $class_name ): ?Service {
		return isset( $this->services[ $class_name ] ) ? $this->services[ $class_name ] : null;
	}

	/**
	 * Registers the hooks of every service. Runs once.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		foreach ( $this->services as $service ) {
			$service->register_hooks();
		}

		/**
		 * Fires after Checkout FieldKit has registered its services.
		 *
		 * @since 0.1.0
		 *
		 * @param Plugin $plugin The plugin; call add() to register another service.
		 */
		do_action( 'cfk_loaded', $this );
	}

	/**
	 * Whether boot() has run.
	 *
	 * @return bool
	 */
	public function is_booted(): bool {
		return $this->booted;
	}

	/**
	 * The services the plugin itself provides. Each feature adds its service here.
	 *
	 * @return Service[]
	 */
	private function default_services(): array {
		return array(
			new Compatibility( PLUGIN_FILE ),
		);
	}
}
