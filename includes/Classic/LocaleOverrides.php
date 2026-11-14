<?php
/**
 * Decides where a country's own address field rules give way to ours.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Classic;

use CheckoutFieldKit\Storage\SettingsRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Locale overrides (spec §4). WooCommerce changes some address fields per
 * country (its "country locale"): label, placeholder, priority, classes,
 * required, hidden. Only properties the store owner changed from
 * WooCommerce's default are ours: in every country when the property's switch
 * is on; when it is off, only where the country has no rule of its own.
 * Properties the store owner did not change stay as WooCommerce has them for
 * the country. A country's rule that hides a field always applies. So right
 * after activation, or after saving without edits, every country looks as
 * WooCommerce has it.
 *
 * "Changed" is defined as for the Block checkout's built-in address fields
 * (Block\BuiltInAddressFields, #6): a label that is not empty and differs, a
 * required setting that differs, and the order of a section once its built-in
 * fields are out of WooCommerce's order or a custom field comes before one of
 * them. The Classic checkout adds placeholder and classes, changed when they
 * differ.
 *
 * The base country's locale entry counts as a rule only when WooCommerce has
 * one for it; otherwise WooCommerce copies its defaults there, and those are
 * not a country rule.
 */
final class LocaleOverrides {

	/**
	 * WooCommerce field keys a country can change, and the switch for each.
	 *
	 * @var array<string, string>
	 */
	public const PROPERTIES = array(
		'label'       => 'label',
		'placeholder' => 'placeholder',
		'priority'    => 'order',
		'class'       => 'classes',
		'required'    => 'required',
	);

	/**
	 * Store settings.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository $settings Store settings.
	 */
	public function __construct( SettingsRepository $settings ) {
		$this->settings = $settings;
	}

	/**
	 * The switches: label, placeholder, order, required, classes.
	 *
	 * @return array<string, bool>
	 */
	public function switches(): array {
		return $this->settings->get()['localeOverrides'];
	}

	/**
	 * WooCommerce's address field rules for a country, keyed by unprefixed
	 * field key (city, state, ...).
	 *
	 * @param string $country Country code.
	 * @return array<string, array<string, mixed>> Empty when the country has none.
	 */
	public function country_rules( string $country ): array {
		if ( '' === $country || ! function_exists( 'WC' ) || ! WC()->countries instanceof \WC_Countries ) {
			return array();
		}

		$locale = WC()->countries->get_country_locale();
		if ( ! isset( $locale[ $country ] ) || ! is_array( $locale[ $country ] ) ) {
			return array();
		}

		if ( WC()->countries->get_base_country() === $country && isset( $locale['default'] ) && $locale[ $country ] === $locale['default'] ) {
			return array();
		}

		return $locale[ $country ];
	}

	/**
	 * The WooCommerce keys the store owner changed on a built-in field.
	 *
	 * @param array<string, mixed>      $field      Our field.
	 * @param array<string, mixed>|null $by_default The field as WooCommerce defines it by default
	 *                                              (from the default field set); null counts every
	 *                                              property as changed.
	 * @param bool                      $reordered  Whether the field's section was reordered (see reordered()).
	 * @return string[] WooCommerce keys, for example array( 'label', 'priority' ).
	 */
	public function changed( array $field, ?array $by_default, bool $reordered ): array {
		if ( null === $by_default ) {
			return array_keys( self::PROPERTIES );
		}

		$changed = array();
		if ( '' !== trim( (string) $field['label'] ) && $field['label'] !== $by_default['label'] ) {
			$changed[] = 'label';
		}
		if ( (string) $field['placeholder'] !== (string) $by_default['placeholder'] ) {
			$changed[] = 'placeholder';
		}
		if ( $reordered ) {
			$changed[] = 'priority';
		}
		if ( array_values( (array) $field['classes'] ) !== array_values( (array) $by_default['classes'] ) ) {
			$changed[] = 'class';
		}
		if ( (bool) $field['required'] !== (bool) $by_default['required'] ) {
			$changed[] = 'required';
		}

		return $changed;
	}

	/**
	 * Whether the store owner reordered a section: its built-in fields are
	 * not in WooCommerce's order, or a custom field comes before one of them.
	 *
	 * @param array<int, array<string, mixed>> $fields   Our fields of the section, in order.
	 * @param string[]                         $defaults WooCommerce's built-in names of the section, in order.
	 * @return bool
	 */
	public static function reordered( array $fields, array $defaults ): bool {
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
	 * The switch names of changed WooCommerce keys, for the checkout script.
	 *
	 * @param string[] $changed WooCommerce keys changed (see changed()).
	 * @return string[] Switch names, for example array( 'label', 'order' ).
	 */
	public function changed_switches( array $changed ): array {
		$names = array();
		foreach ( self::PROPERTIES as $key => $switch ) {
			if ( in_array( $key, $changed, true ) ) {
				$names[] = $switch;
			}
		}

		return $names;
	}

	/**
	 * The WooCommerce keys whose value stays as WooCommerce has it for the
	 * country: every key the store owner did not change, and changed keys the
	 * country has a rule for while their switch is off.
	 *
	 * @param array<string, mixed> $rule     The country's rule for the field; empty when it has none.
	 * @param array<string, bool>  $switches Switches, as switches() returns them.
	 * @param string[]             $changed  WooCommerce keys changed (see changed()).
	 * @return string[] WooCommerce keys, for example array( 'label' ).
	 */
	public function kept( array $rule, array $switches, array $changed ): array {
		$kept = array();
		foreach ( self::PROPERTIES as $key => $switch ) {
			if ( ! in_array( $key, $changed, true ) || ( array_key_exists( $key, $rule ) && empty( $switches[ $switch ] ) ) ) {
				$kept[] = $key;
			}
		}

		return $kept;
	}
}
