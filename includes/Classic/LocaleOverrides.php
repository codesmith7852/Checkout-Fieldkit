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
 * required, hidden. For each switch that is on, the country's value is
 * discarded so ours applies everywhere; when a switch is off, the country's
 * value wins where the country has one and ours applies elsewhere. A
 * country's rule that hides a field always applies.
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
	 * The WooCommerce keys whose country value stays: those the country's
	 * rule sets while their switch is off.
	 *
	 * @param array<string, mixed> $rule     The country's rule for one field.
	 * @param array<string, bool>  $switches Switches, as switches() returns them.
	 * @return string[] WooCommerce keys, for example array( 'label' ).
	 */
	public function kept( array $rule, array $switches ): array {
		$kept = array();
		foreach ( self::PROPERTIES as $key => $switch ) {
			if ( empty( $switches[ $switch ] ) && array_key_exists( $key, $rule ) ) {
				$kept[] = $key;
			}
		}

		return $kept;
	}
}
