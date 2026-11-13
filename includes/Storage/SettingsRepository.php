<?php
/**
 * Reads and writes the store-wide settings.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Storage;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The store-wide settings, stored in the autoloaded option
 * checkout_fieldkit_settings (spec §3):
 *
 *     {version: 1, localeOverrides: {label, placeholder, order, required, classes}}
 *
 * Every locale override is a bool: true keeps the plugin's setting for
 * address fields in every country (spec §4). Until the first save, reading
 * returns the defaults and nothing is written. Reset deletes the option.
 */
final class SettingsRepository {

	/**
	 * Schema version of stored settings.
	 */
	public const VERSION = 1;

	/**
	 * Option name.
	 */
	public const OPTION = 'checkout_fieldkit_settings';

	/**
	 * Error code of a refused save.
	 */
	public const ERROR_CODE = 'checkout_fieldkit_invalid_settings';

	/**
	 * Locale overrides and their defaults, in the order the admin shows them.
	 *
	 * @var array<string, bool>
	 */
	public const LOCALE_OVERRIDES = array(
		'label'       => true,
		'placeholder' => true,
		'order'       => true,
		'required'    => false,
		'classes'     => false,
	);

	/**
	 * Schema migrations.
	 *
	 * @var Migrator
	 */
	private $migrator;

	/**
	 * Constructor.
	 *
	 * @param Migrator|null $migrator Schema migrations; defaults to self::migrator().
	 */
	public function __construct( ?Migrator $migrator = null ) {
		$this->migrator = null === $migrator ? self::migrator() : $migrator;
	}

	/**
	 * The migrations of stored settings. When the stored shape changes, raise
	 * VERSION and add the step from the previous version here.
	 *
	 * @return Migrator
	 */
	public static function migrator(): Migrator {
		return new Migrator( self::VERSION );
	}

	/**
	 * Name of the option the settings are stored in.
	 *
	 * @return string
	 */
	public function option_name(): string {
		return self::OPTION;
	}

	/**
	 * Whether settings have been saved (and not reset since).
	 *
	 * @return bool
	 */
	public function is_stored(): bool {
		return is_array( get_option( self::OPTION ) );
	}

	/**
	 * The default settings.
	 *
	 * @return array{version: int, localeOverrides: array<string, bool>}
	 */
	public function defaults(): array {
		return array(
			'version'         => $this->migrator->current_version(),
			'localeOverrides' => self::LOCALE_OVERRIDES,
		);
	}

	/**
	 * The saved settings, or the defaults when none are saved. Values that are
	 * missing or not booleans read as their defaults.
	 *
	 * @return array{version: int, localeOverrides: array<string, bool>}
	 */
	public function get(): array {
		$raw = get_option( self::OPTION );

		return is_array( $raw ) ? $this->build( $this->migrator->migrate( $raw ) ) : $this->defaults();
	}

	/**
	 * Every problem with settings, as save() would report them.
	 *
	 * @param mixed $settings Settings as given: {version?, localeOverrides?}.
	 * @return array<int, array{key: string, message: string}> Empty when valid. Keys of
	 *         locale overrides are written 'localeOverrides.label' and so on.
	 */
	public function validate( $settings ): array {
		if ( ! is_array( $settings ) ) {
			return array( self::problem( '', __( 'Settings must be an object.', 'checkout-fieldkit' ) ) );
		}

		if ( $this->migrator->is_newer( $settings ) ) {
			return array( self::problem( 'version', __( 'These settings were saved by a newer version of Checkout FieldKit. Update the plugin to use them.', 'checkout-fieldkit' ) ) );
		}

		$settings = $this->migrator->migrate( $settings );
		if ( ! isset( $settings['localeOverrides'] ) ) {
			return array();
		}
		if ( ! is_array( $settings['localeOverrides'] ) ) {
			return array( self::problem( 'localeOverrides', __( 'Locale overrides must be an object of on/off switches.', 'checkout-fieldkit' ) ) );
		}

		$errors = array();
		foreach ( array_keys( self::LOCALE_OVERRIDES ) as $key ) {
			if ( array_key_exists( $key, $settings['localeOverrides'] ) && ! is_bool( $settings['localeOverrides'][ $key ] ) ) {
				$errors[] = self::problem( 'localeOverrides.' . $key, __( 'This switch must be on (true) or off (false).', 'checkout-fieldkit' ) );
			}
		}

		return $errors;
	}

	/**
	 * Settings as save() would store them: migrated, unknown keys dropped,
	 * missing or non-boolean values replaced by their defaults.
	 *
	 * @param array<string, mixed> $settings Settings as given.
	 * @return array{version: int, localeOverrides: array<string, bool>}
	 */
	public function normalize( array $settings ): array {
		return $this->build( $this->migrator->migrate( $settings ) );
	}

	/**
	 * Replaces the stored settings. Unknown keys are dropped; missing ones
	 * take their defaults. Nothing is stored when anything is wrong.
	 *
	 * @param mixed $settings Settings as given: {version?, localeOverrides?}.
	 * @return true|WP_Error True when saved. Otherwise a WP_Error with code
	 *                       'checkout_fieldkit_invalid_settings' and data {status: 422, errors: [{key, message}]}.
	 */
	public function save( $settings ) {
		$errors = $this->validate( $settings );
		if ( $errors ) {
			return new WP_Error(
				self::ERROR_CODE,
				__( 'The settings were not saved because some of them have problems.', 'checkout-fieldkit' ),
				array(
					'status' => 422,
					'errors' => $errors,
				)
			);
		}

		update_option( self::OPTION, $this->normalize( $settings ), true );

		return true;
	}

	/**
	 * Deletes the stored settings, so the defaults apply again.
	 */
	public function reset(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Well-formed settings from a migrated value.
	 *
	 * @param array<string, mixed> $value Migrated value.
	 * @return array{version: int, localeOverrides: array<string, bool>}
	 */
	private function build( array $value ): array {
		$settings  = $this->defaults();
		$overrides = isset( $value['localeOverrides'] ) && is_array( $value['localeOverrides'] ) ? $value['localeOverrides'] : array();
		foreach ( $overrides as $key => $enabled ) {
			if ( array_key_exists( $key, self::LOCALE_OVERRIDES ) && is_bool( $enabled ) ) {
				$settings['localeOverrides'][ $key ] = $enabled;
			}
		}

		return $settings;
	}

	/**
	 * A problem with one setting.
	 *
	 * @param string $key     Setting key.
	 * @param string $message Message.
	 * @return array{key: string, message: string}
	 */
	private static function problem( string $key, string $message ): array {
		return array(
			'key'     => $key,
			'message' => $message,
		);
	}
}
