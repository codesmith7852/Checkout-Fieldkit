<?php
/**
 * The plugin's domain objects, created once and shared.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit;

use CheckoutFieldKit\Environment\ActiveCheckoutType;
use CheckoutFieldKit\Fields\DefaultFields;
use CheckoutFieldKit\Fields\EssentialFields;
use CheckoutFieldKit\Fields\FieldNormalizer;
use CheckoutFieldKit\Fields\FieldSetValidator;
use CheckoutFieldKit\Fields\FieldTypes;
use CheckoutFieldKit\Fields\Sections;
use CheckoutFieldKit\Storage\FieldSetRepository;
use CheckoutFieldKit\Storage\SettingsRepository;
use CheckoutFieldKit\Tools\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the field model, defaults and storage on first use and hands out
 * the same instances afterwards. Features get it from Plugin::domain():
 *
 *     $domain = Plugin::instance()->domain();
 *     $set    = $domain->field_sets( 'classic' )->get();
 *
 * Nothing here adds hooks, so a fresh Domain is safe to create in tests.
 */
final class Domain {

	/**
	 * Created objects keyed by name.
	 *
	 * @var array<string, object>
	 */
	private $instances = array();

	/**
	 * Section lists of both checkout types.
	 *
	 * @return Sections
	 */
	public function sections(): Sections {
		return $this->once(
			'sections',
			static function () {
				return new Sections();
			}
		);
	}

	/**
	 * Field type registry.
	 *
	 * @return FieldTypes
	 */
	public function field_types(): FieldTypes {
		return $this->once(
			'field_types',
			function () {
				return new FieldTypes( $this->sections() );
			}
		);
	}

	/**
	 * WooCommerce's built-in fields of both checkout types.
	 *
	 * @return DefaultFields
	 */
	public function defaults(): DefaultFields {
		return $this->once(
			'defaults',
			static function () {
				return new DefaultFields();
			}
		);
	}

	/**
	 * Essential fields and what stops working without them.
	 *
	 * @return EssentialFields
	 */
	public function essential_fields(): EssentialFields {
		return $this->once(
			'essential_fields',
			static function () {
				return new EssentialFields();
			}
		);
	}

	/**
	 * Field normaliser.
	 *
	 * @return FieldNormalizer
	 */
	public function normalizer(): FieldNormalizer {
		return $this->once(
			'normalizer',
			function () {
				return new FieldNormalizer( $this->field_types(), $this->defaults() );
			}
		);
	}

	/**
	 * Field set rules.
	 *
	 * @return FieldSetValidator
	 */
	public function validator(): FieldSetValidator {
		return $this->once(
			'validator',
			function () {
				return new FieldSetValidator( $this->field_types(), $this->defaults(), $this->normalizer() );
			}
		);
	}

	/**
	 * The stored field set of a checkout type.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return FieldSetRepository
	 * @throws \InvalidArgumentException For an unknown checkout type.
	 */
	public function field_sets( string $checkout_type ): FieldSetRepository {
		return $this->once(
			'field_sets_' . $checkout_type,
			function () use ( $checkout_type ) {
				return new FieldSetRepository( $checkout_type, $this->defaults(), $this->normalizer(), $this->validator() );
			}
		);
	}

	/**
	 * The stored store-wide settings.
	 *
	 * @return SettingsRepository
	 */
	public function settings(): SettingsRepository {
		return $this->once(
			'settings',
			static function () {
				return new SettingsRepository();
			}
		);
	}

	/**
	 * Export, import and its preview.
	 *
	 * @return ImportExport
	 */
	public function import_export(): ImportExport {
		return $this->once(
			'import_export',
			function () {
				return new ImportExport( $this );
			}
		);
	}

	/**
	 * Which checkout type the store's Checkout page uses (spec §7).
	 *
	 * @return ActiveCheckoutType
	 */
	public function active_checkout_type(): ActiveCheckoutType {
		return $this->once(
			'active_checkout_type',
			static function () {
				return new ActiveCheckoutType();
			}
		);
	}

	/**
	 * Creates an object on first request and returns the same one afterwards.
	 *
	 * @param string   $name    Instance name.
	 * @param callable $factory Creates the instance.
	 * @return mixed
	 */
	private function once( string $name, callable $factory ) {
		if ( ! isset( $this->instances[ $name ] ) ) {
			$this->instances[ $name ] = $factory();
		}

		return $this->instances[ $name ];
	}
}
