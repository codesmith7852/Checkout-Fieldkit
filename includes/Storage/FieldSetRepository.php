<?php
/**
 * Reads and writes the field set of one checkout type.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Storage;

use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Fields\DefaultFields;
use CheckoutFieldKit\Fields\FieldNormalizer;
use CheckoutFieldKit\Fields\FieldSetValidator;
use InvalidArgumentException;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The field set of one checkout type, stored in its own autoloaded option
 * (spec §3): checkout_fieldkit_field_set_classic or checkout_fieldkit_field_set_block.
 *
 * A field set is {version: int, sections: {section: [field, ...]}} with every
 * section of the checkout type in display order and every field normalised
 * (all keys of Field::defaults()). Until the first save, reading returns the
 * built-in defaults and nothing is written. Saving validates first and stores
 * nothing when anything is wrong. Reset deletes the option.
 */
final class FieldSetRepository {

	/**
	 * Schema version of stored field sets.
	 */
	public const VERSION = 1;

	/**
	 * Option name prefix; the checkout type follows.
	 */
	public const OPTION_PREFIX = 'checkout_fieldkit_field_set_';

	/**
	 * Error code of a refused save.
	 */
	public const ERROR_CODE = 'checkout_fieldkit_invalid_field_set';

	/**
	 * Checkout type.
	 *
	 * @var string
	 */
	private $checkout_type;

	/**
	 * Built-in fields.
	 *
	 * @var DefaultFields
	 */
	private $defaults;

	/**
	 * Field normaliser.
	 *
	 * @var FieldNormalizer
	 */
	private $normalizer;

	/**
	 * Field set rules.
	 *
	 * @var FieldSetValidator
	 */
	private $validator;

	/**
	 * Schema migrations.
	 *
	 * @var Migrator
	 */
	private $migrator;

	/**
	 * Last read: language, stored value and the field set made from it.
	 *
	 * @var array{locale: string, raw: mixed, set: array<string, mixed>}|null
	 */
	private $cache = null;

	/**
	 * Default field sets keyed by language.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $defaults_cache = array();

	/**
	 * Constructor.
	 *
	 * @param string            $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param DefaultFields     $defaults      Built-in fields.
	 * @param FieldNormalizer   $normalizer    Field normaliser.
	 * @param FieldSetValidator $validator     Field set rules.
	 * @param Migrator|null     $migrator      Schema migrations; defaults to self::migrator().
	 * @throws InvalidArgumentException For an unknown checkout type.
	 */
	public function __construct( string $checkout_type, DefaultFields $defaults, FieldNormalizer $normalizer, FieldSetValidator $validator, ?Migrator $migrator = null ) {
		if ( ! CheckoutType::is_valid( $checkout_type ) ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'Unknown checkout type "%s".', $checkout_type ) ) );
		}

		$this->checkout_type = $checkout_type;
		$this->defaults      = $defaults;
		$this->normalizer    = $normalizer;
		$this->validator     = $validator;
		$this->migrator      = null === $migrator ? self::migrator() : $migrator;
	}

	/**
	 * The migrations of stored field sets. When the stored shape changes,
	 * raise VERSION and add the step from the previous version here.
	 *
	 * @return Migrator
	 */
	public static function migrator(): Migrator {
		return new Migrator( self::VERSION );
	}

	/**
	 * The checkout type of this field set.
	 *
	 * @return string
	 */
	public function checkout_type(): string {
		return $this->checkout_type;
	}

	/**
	 * Name of the option the field set is stored in.
	 *
	 * @return string
	 */
	public function option_name(): string {
		return self::OPTION_PREFIX . $this->checkout_type;
	}

	/**
	 * Whether a field set has been saved (and not reset since).
	 *
	 * @return bool
	 */
	public function is_stored(): bool {
		return is_array( get_option( $this->option_name() ) );
	}

	/**
	 * The saved field set, or the defaults when none is saved. A stored set
	 * from an older version is migrated in memory, and its built-in fields are
	 * brought in line with WooCommerce's current ones; it is written back on
	 * the next save.
	 *
	 * @return array{version: int, sections: array<string, array<int, array<string, mixed>>>}
	 */
	public function get(): array {
		$raw = get_option( $this->option_name() );
		if ( ! is_array( $raw ) ) {
			return $this->defaults();
		}

		$locale = determine_locale();
		if ( null !== $this->cache && $locale === $this->cache['locale'] && $raw === $this->cache['raw'] ) {
			return $this->cache['set'];
		}

		$value    = $this->migrator->migrate( $raw );
		$sections = isset( $value['sections'] ) && is_array( $value['sections'] ) ? $value['sections'] : array();
		$set      = $this->build( $this->normalizer->reconcile_built_ins( $this->checkout_type, $sections ) );

		$this->cache = array(
			'locale' => $locale,
			'raw'    => $raw,
			'set'    => $set,
		);

		return $set;
	}

	/**
	 * The built-in default field set, as get() returns it before any save.
	 *
	 * @return array{version: int, sections: array<string, array<int, array<string, mixed>>>}
	 */
	public function defaults(): array {
		$locale = determine_locale();
		if ( ! isset( $this->defaults_cache[ $locale ] ) ) {
			$this->defaults_cache[ $locale ] = $this->build( $this->defaults->field_set( $this->checkout_type )['sections'] );
		}

		return $this->defaults_cache[ $locale ];
	}

	/**
	 * Every problem with a field set, as save() would report them. A set
	 * without `version` counts as version 1; older versions are migrated
	 * before the rules are checked; newer versions are refused.
	 *
	 * @param mixed $field_set Field set as given: {version?, sections}.
	 * @return array<int, array{section: string, index: int|null, key: string, message: string}> Empty when valid.
	 */
	public function validate( $field_set ): array {
		if ( is_array( $field_set ) && $this->migrator->is_newer( $field_set ) ) {
			return array(
				array(
					'section' => '',
					'index'   => null,
					'key'     => 'version',
					'message' => __( 'These fields were saved by a newer version of Checkout FieldKit. Update the plugin to use them.', 'checkout-fieldkit' ),
				),
			);
		}

		return $this->validator->validate( $this->checkout_type, is_array( $field_set ) ? $this->migrator->migrate( $field_set ) : $field_set );
	}

	/**
	 * Replaces the stored field set. Nothing is stored when the set has any
	 * problem.
	 *
	 * @param mixed $field_set Field set as given: {version?, sections}. Unknown field keys are dropped.
	 * @return true|WP_Error True when saved. Otherwise a WP_Error with code
	 *                       'checkout_fieldkit_invalid_field_set' and data {status: 422, errors: [...]}
	 *                       listing every problem (see FieldSetValidator).
	 */
	public function save( $field_set ) {
		$errors = $this->validate( $field_set );
		if ( $errors ) {
			return new WP_Error(
				self::ERROR_CODE,
				__( 'The fields were not saved because some of them have problems.', 'checkout-fieldkit' ),
				array(
					'status' => 422,
					'errors' => $errors,
				)
			);
		}

		$set    = $this->build( $this->migrator->migrate( $field_set )['sections'] );
		$stored = array(
			'version'  => $set['version'],
			'sections' => array(),
		);
		foreach ( $set['sections'] as $section => $fields ) {
			$stored['sections'][ $section ] = array();
			foreach ( $fields as $field ) {
				$stored['sections'][ $section ][] = $this->normalizer->compact( $field, $this->checkout_type );
			}
		}

		update_option( $this->option_name(), $stored, true );
		$this->cache = null;

		$this->announce( $set );

		return true;
	}

	/**
	 * Deletes the stored field set, so the built-in defaults apply again.
	 */
	public function reset(): void {
		delete_option( $this->option_name() );
		$this->cache = null;

		$this->announce( $this->defaults() );
	}

	/**
	 * A normalised field set from sections.
	 *
	 * @param array<string, mixed> $sections Fields keyed by section name.
	 * @return array{version: int, sections: array<string, array<int, array<string, mixed>>>}
	 */
	private function build( array $sections ): array {
		return array(
			'version'  => $this->migrator->current_version(),
			'sections' => $this->normalizer->normalize_set( $this->checkout_type, $sections ),
		);
	}

	/**
	 * Tells listeners the field set changed.
	 *
	 * @param array<string, mixed> $set The field set now in effect.
	 */
	private function announce( array $set ): void {
		/**
		 * Fires after a field set was saved or reset.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $set           The field set now in effect: {version, sections}.
		 *                                            After a reset, the built-in defaults.
		 * @param string               $checkout_type 'classic' or 'block'.
		 */
		do_action( 'checkout_fieldkit_field_set_saved', $set, $this->checkout_type );
	}
}
