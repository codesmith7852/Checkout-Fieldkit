<?php
/**
 * WooCommerce's own address fields on the Block checkout.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Block;

use Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\Blocks\Package;
use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Environment\BlockSupport;
use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Fields\DefaultFields;
use CheckoutFieldKit\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Applies the Block field set's label, required, enabled and order of the
 * built-in address fields (spec §5) where the Block checkout reads them:
 *
 * - `defaultFields`, the field data WooCommerce's block scripts get: our
 *   values are registered under that key before WooCommerce adds its own
 *   (the registry keeps the first value), built from the same core and
 *   additional fields WooCommerce would use;
 * - `woocommerce_default_address_fields`, which becomes the `default` country
 *   locale the Store API checks required fields against (and which the
 *   My Account address forms use);
 * - `woocommerce_get_country_locale`, the per-country rules: a turned-off
 *   field is hidden in every country; for a label, required setting or order
 *   the store owner changed, the country's own value is dropped when the
 *   matching locale override is on. Countries that hide a field still hide it.
 *
 * Only what differs from WooCommerce's own Block defaults counts as a change,
 * so saving the field set without touching a field leaves it exactly as
 * WooCommerce has it. Nothing changes until the Block field set is saved,
 * unless the store's Checkout page uses the Block checkout, or without Block
 * support. The country is never hidden or made optional: WooCommerce looks
 * up the rest of the address by it. `email` is not an address field and is
 * never changed.
 */
final class BuiltInAddressFields implements Service {

	/**
	 * Classic definitions of fields WooCommerce leaves out of its default
	 * address fields when its own setting hides them.
	 */
	private const ADDED_DEFINITIONS = array(
		'company'   => array(
			'class'        => array( 'form-row-wide' ),
			'autocomplete' => 'organization',
			'priority'     => 30,
		),
		'address_2' => array(
			'class'        => array( 'form-row-wide', 'address-field' ),
			'autocomplete' => 'address-line2',
			'priority'     => 60,
		),
		'phone'     => array(
			'type'         => 'tel',
			'class'        => array( 'form-row-wide' ),
			'validate'     => array( 'phone' ),
			'autocomplete' => 'tel',
			'priority'     => 100,
		),
	);

	/**
	 * Field set, settings and environment.
	 *
	 * @var Domain
	 */
	private $domain;

	/**
	 * Whether Block features can run; null to ask the domain.
	 *
	 * @var BlockSupport|null
	 */
	private $support;

	/**
	 * Constructor.
	 *
	 * @param Domain            $domain  Field set, settings and environment.
	 * @param BlockSupport|null $support Whether Block features can run; defaults to the domain's.
	 */
	public function __construct( Domain $domain, ?BlockSupport $support = null ) {
		$this->domain  = $domain;
		$this->support = $support;
	}

	/**
	 * Adds the filters on WooCommerce's address data, late so our values win.
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_default_address_fields', array( $this, 'filter_default_address_fields' ), 1000 );
		add_filter( 'woocommerce_get_country_locale', array( $this, 'filter_country_locale' ), 1000 );
		add_action( 'woocommerce_blocks_checkout_enqueue_data', array( $this, 'add_default_fields_data' ), 5 );
		add_action( 'woocommerce_blocks_cart_enqueue_data', array( $this, 'add_default_fields_data' ), 5 );
	}

	/**
	 * What the store owner changed about the built-in address fields, keyed
	 * by field name. Each entry has `field` (our normalised field) and the
	 * changed values among `label`, `required`, `enabled` and `index`
	 * (position × 10, only when the order changed).
	 *
	 * @return array<string, array<string, mixed>> Empty when nothing applies.
	 */
	public function changes(): array {
		if ( DefaultFields::is_computing() || ! $this->support()->is_available() ) {
			return array();
		}

		$repository = $this->domain->field_sets( CheckoutType::BLOCK );
		if ( ! $repository->is_stored() || ! $this->domain->active_checkout_type()->is_block() ) {
			return array();
		}

		$fields   = array_values( $repository->get()['sections']['address'] ?? array() );
		$defaults = array_column( $repository->defaults()['sections']['address'] ?? array(), null, 'name' );

		$changes = array();
		$ordered = $this->order_changed( $fields, array_keys( $defaults ) );
		foreach ( $fields as $position => $field ) {
			$name = (string) $field['name'];
			if ( empty( $field['builtIn'] ) || ! isset( $defaults[ $name ] ) ) {
				continue;
			}

			$default = $defaults[ $name ];
			$change  = array();
			if ( '' !== trim( (string) $field['label'] ) && $field['label'] !== $default['label'] ) {
				$change['label'] = (string) $field['label'];
			}
			if ( 'country' !== $name && (bool) $field['enabled'] !== (bool) $default['enabled'] ) {
				$change['enabled'] = (bool) $field['enabled'];
			}
			if ( 'country' !== $name && (bool) $field['required'] !== (bool) $default['required'] ) {
				$change['required'] = (bool) $field['required'];
			}
			if ( $ordered ) {
				$change['index'] = ( $position + 1 ) * 10;
			}

			if ( $change ) {
				$changes[ $name ] = array_merge( array( 'field' => $field ), $change );
			}
		}

		return $changes;
	}

	/**
	 * Applies our changes to WooCommerce's default address fields.
	 *
	 * @param mixed $fields Default address fields keyed by name.
	 * @return mixed
	 */
	public function filter_default_address_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}

		$changes = $this->changes();
		foreach ( $changes as $name => $change ) {
			$field = $change['field'];
			if ( isset( $change['enabled'] ) && ! $change['enabled'] ) {
				unset( $fields[ $name ] );
				continue;
			}

			if ( ! isset( $fields[ $name ] ) || ! is_array( $fields[ $name ] ) ) {
				if ( empty( $change['enabled'] ) || ! isset( self::ADDED_DEFINITIONS[ $name ] ) ) {
					continue;
				}
				$fields[ $name ] = array_merge(
					self::ADDED_DEFINITIONS[ $name ],
					array(
						'label'    => (string) $field['label'],
						'required' => (bool) $field['required'],
					)
				);
			}

			if ( isset( $change['label'] ) ) {
				$fields[ $name ]['label'] = $change['label'];
			}
			if ( isset( $change['required'] ) ) {
				$fields[ $name ]['required'] = $change['required'];
			}
			if ( isset( $change['index'] ) ) {
				$fields[ $name ]['priority'] = $change['index'];
			}
		}

		if ( $changes ) {
			uasort( $fields, 'wc_checkout_fields_uasort_comparison' );
		}

		return $fields;
	}

	/**
	 * Applies our changes to the per-country address rules.
	 *
	 * @param mixed $locale Address rules keyed by country code, then field name.
	 * @return mixed
	 */
	public function filter_country_locale( $locale ) {
		if ( ! is_array( $locale ) ) {
			return $locale;
		}

		$changes = $this->changes();
		if ( ! $changes ) {
			return $locale;
		}

		$overrides = $this->domain->settings()->get()['localeOverrides'];

		foreach ( $locale as $country => $rules ) {
			if ( ! is_array( $rules ) ) {
				continue;
			}

			foreach ( $changes as $name => $change ) {
				if ( ! isset( $rules[ $name ] ) || ! is_array( $rules[ $name ] ) ) {
					continue;
				}

				$rule = $rules[ $name ];
				if ( isset( $change['enabled'] ) && ! $change['enabled'] ) {
					$rule['hidden']   = true;
					$rule['required'] = false;
				} else {
					if ( isset( $change['label'] ) && ! empty( $overrides['label'] ) ) {
						unset( $rule['label'] );
					}
					if ( isset( $change['index'] ) && ! empty( $overrides['order'] ) ) {
						unset( $rule['priority'] );
					}
					if ( isset( $change['required'] ) && ! empty( $overrides['required'] ) && empty( $rule['hidden'] ) ) {
						$rule['required'] = $change['required'];
					}
				}

				$locale[ $country ][ $name ] = $rule;
			}
		}

		return $locale;
	}

	/**
	 * Gives WooCommerce's block scripts our field data as `defaultFields`
	 * before WooCommerce adds its own (the asset data registry keeps the
	 * first value of a key). Does nothing when nothing changed.
	 */
	public function add_default_fields_data(): void {
		$changes = $this->changes();
		if ( ! $changes || ! class_exists( Package::class ) || ! class_exists( AssetDataRegistry::class ) || ! class_exists( CheckoutFields::class ) ) {
			return;
		}

		$registry = Package::container()->get( AssetDataRegistry::class );
		if ( $registry->exists( 'defaultFields' ) ) {
			return;
		}

		$controller = Package::container()->get( CheckoutFields::class );
		$registry->add( 'defaultFields', array_merge( $this->core_fields( (array) $controller->get_core_fields(), $changes ), (array) $controller->get_additional_fields() ) );
	}

	/**
	 * WooCommerce's core block fields with our changes.
	 *
	 * @param array<string, array<string, mixed>> $core    Core fields keyed by name.
	 * @param array<string, array<string, mixed>> $changes See changes().
	 * @return array<string, array<string, mixed>>
	 */
	private function core_fields( array $core, array $changes ): array {
		foreach ( $changes as $name => $change ) {
			if ( ! isset( $core[ $name ] ) || ! is_array( $core[ $name ] ) ) {
				continue;
			}

			$field = $change['field'];
			if ( isset( $change['label'] ) ) {
				$core[ $name ]['label'] = $change['label'];
				/* translators: %s: field label. */
				$core[ $name ]['optionalLabel'] = sprintf( __( '%s (optional)', 'checkout-fieldkit' ), $change['label'] );
			}
			if ( isset( $change['enabled'] ) || isset( $change['required'] ) ) {
				$core[ $name ]['hidden']   = empty( $field['enabled'] );
				$core[ $name ]['required'] = ! empty( $field['enabled'] ) && ! empty( $field['required'] );
			}
			if ( isset( $change['index'] ) ) {
				$core[ $name ]['index'] = $change['index'];
			}
		}

		return $core;
	}

	/**
	 * Whether the store owner reordered the address fields: the built-in
	 * fields are not in WooCommerce's order, or a custom field comes before
	 * one of them.
	 *
	 * @param array<int, array<string, mixed>> $fields   Our address fields, in order.
	 * @param string[]                         $defaults WooCommerce's built-in names, in order.
	 * @return bool
	 */
	private function order_changed( array $fields, array $defaults ): bool {
		$built_ins = array();
		$custom    = false;
		foreach ( $fields as $field ) {
			if ( empty( $field['builtIn'] ) ) {
				$custom = true;
				continue;
			}
			if ( $custom ) {
				return true;
			}
			$built_ins[] = (string) $field['name'];
		}

		return array_values( array_intersect( $defaults, $built_ins ) ) !== $built_ins;
	}

	/**
	 * Whether Block features can run.
	 *
	 * @return BlockSupport
	 */
	private function support(): BlockSupport {
		return null !== $this->support ? $this->support : $this->domain->block_support();
	}
}
