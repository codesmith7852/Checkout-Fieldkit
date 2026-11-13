<?php
/**
 * Moves stored values forward to the current schema version.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Every stored value carries a `version` (ADR 0005). When a value is older
 * than the current version, the steps from its version up run in order, each
 * turning version N into version N + 1. A value without a usable version
 * counts as version 1, the first stored shape.
 *
 * To change a stored shape: raise the repository's VERSION and add the step
 * from the old version in its migrator() method.
 */
final class Migrator {

	/**
	 * Version values are migrated to.
	 *
	 * @var int
	 */
	private $current_version;

	/**
	 * Steps keyed by the version they migrate from.
	 *
	 * @var array<int, callable>
	 */
	private $steps = array();

	/**
	 * Constructor.
	 *
	 * @param int                  $current_version Version values are migrated to.
	 * @param array<int, callable> $steps           Steps keyed by the version they migrate from. Each
	 *                                              takes the value (an array) and returns it changed.
	 */
	public function __construct( int $current_version, array $steps = array() ) {
		$this->current_version = $current_version;
		foreach ( $steps as $from_version => $step ) {
			$this->add_step( (int) $from_version, $step );
		}
	}

	/**
	 * Adds the step that turns a version into the next.
	 *
	 * @param int      $from_version Version the step migrates from.
	 * @param callable $step         Takes the value (an array) and returns it changed.
	 * @return Migrator
	 */
	public function add_step( int $from_version, callable $step ): Migrator {
		$this->steps[ $from_version ] = $step;

		return $this;
	}

	/**
	 * The version values are migrated to.
	 *
	 * @return int
	 */
	public function current_version(): int {
		return $this->current_version;
	}

	/**
	 * The version of a stored value; 1 when it has none.
	 *
	 * @param array<string, mixed> $value Stored value.
	 * @return int
	 */
	public function version_of( array $value ): int {
		return isset( $value['version'] ) && is_numeric( $value['version'] ) ? (int) $value['version'] : 1;
	}

	/**
	 * Whether a value was stored by a newer version of the plugin.
	 *
	 * @param array<string, mixed> $value Stored value.
	 * @return bool
	 */
	public function is_newer( array $value ): bool {
		return $this->version_of( $value ) > $this->current_version;
	}

	/**
	 * Migrates a value to the current version. Current and newer values are
	 * returned unchanged.
	 *
	 * @param array<string, mixed> $value Stored value.
	 * @return array<string, mixed> The value, with `version` set to the current version when it was older.
	 */
	public function migrate( array $value ): array {
		$version = $this->version_of( $value );
		if ( $version >= $this->current_version ) {
			return $value;
		}

		for ( ; $version < $this->current_version; $version++ ) {
			if ( isset( $this->steps[ $version ] ) ) {
				$value = (array) call_user_func( $this->steps[ $version ], $value );
			}
		}
		$value['version'] = $this->current_version;

		return $value;
	}
}
