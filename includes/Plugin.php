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
	 * Field model, defaults and storage, created on first use.
	 *
	 * @var Domain|null
	 */
	private $domain = null;

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
	}

	/**
	 * The field model, defaults and storage that features share: field types,
	 * sections, built-in fields, the field set and settings repositories, and
	 * import/export. See Domain.
	 *
	 * @return Domain
	 */
	public function domain(): Domain {
		if ( null === $this->domain ) {
			$this->domain = new Domain();
		}

		return $this->domain;
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
	 * The services the plugin itself provides. Each feature adds its service
	 * here, one per line; services that need field sets or settings take
	 * $this->domain(), for example `new Rest\Routes( $this->domain() ),`.
	 *
	 * @return Service[]
	 */
	private function default_services(): array {
		return array(
			new Compatibility( PLUGIN_FILE ),
			new Block\AddressFields( $this->domain() ),
			new Block\BuiltInAddressFields( $this->domain() ),
			new Classic\OrderValueSaver( $this->domain()->field_sets( Fields\CheckoutType::CLASSIC ), new Orders\ValueSanitizer( $this->domain()->field_types() ) ),
			new Orders\ValueDisplay( $this->domain()->field_sets( Fields\CheckoutType::CLASSIC ), new Orders\FieldValues( $this->domain()->field_types() ) ),
		);
	}
}
