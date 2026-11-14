<?php
/**
 * Tests for the locale override switches on the classic checkout.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Classic;

use CheckoutFieldKit\Classic\CheckoutFields;
use CheckoutFieldKit\Plugin;

/**
 * Spec §4, locale overrides: for each switch that is on, WooCommerce's
 * per-country value for address fields gives way to ours in every country;
 * when it is off the country's rule wins where the country has one. Country
 * rules that hide a field always apply. The store is in Great Britain.
 */
class LocaleOverridesTest extends ClassicTestCase {

	/**
	 * Billing fields for a country.
	 *
	 * @param string $country Country code.
	 * @return array
	 */
	private function billing( $country ) {
		return WC()->countries->get_address_fields( $country, 'billing_' );
	}

	public function test_label_on_keeps_our_label_in_india() {
		$this->change_field( 'billing', 'billing_postcode', array( 'label' => 'Postal area' ) );
		$this->set_locale_overrides( array( 'label' => true ) );

		$this->assertSame( 'Postal area', $this->billing( 'IN' )['billing_postcode']['label'] );
	}

	public function test_label_off_lets_india_use_its_own_label() {
		$this->change_field( 'billing', 'billing_postcode', array( 'label' => 'Postal area' ) );
		$this->change_field( 'billing', 'billing_city', array( 'label' => 'Suburb' ) );
		$this->set_locale_overrides( array( 'label' => false ) );

		$fields = $this->billing( 'IN' );

		$this->assertSame( 'PIN Code', $fields['billing_postcode']['label'] );
		$this->assertSame( 'Suburb', $fields['billing_city']['label'], 'India has no rule for the city label, so ours applies.' );
	}

	/**
	 * WooCommerce 11.3 has no country with its own placeholder, so the test
	 * gives India one, as a plugin or a later WooCommerce could.
	 *
	 * @param bool   $on       Switch.
	 * @param string $expected Placeholder.
	 *
	 * @dataProvider data_placeholder
	 */
	public function test_placeholder_switch_with_a_country_placeholder_rule( $on, $expected ) {
		$rule = static function ( $locale ) {
			$locale['IN']['address_1']['placeholder'] = 'Door number and street';
			return $locale;
		};
		add_filter( 'woocommerce_get_country_locale', $rule );
		WC()->countries->locale = array();
		$this->change_field( 'billing', 'billing_address_1', array( 'placeholder' => 'Street and number' ) );
		$this->set_locale_overrides( array( 'placeholder' => $on ) );

		$fields = $this->billing( 'IN' );
		remove_filter( 'woocommerce_get_country_locale', $rule );

		$this->assertSame( $expected, $fields['billing_address_1']['placeholder'] );
	}

	public function data_placeholder() {
		return array(
			'on'  => array( true, 'Street and number' ),
			'off' => array( false, 'Door number and street' ),
		);
	}

	public function test_order_on_keeps_our_order_in_japan_once_we_reordered() {
		$this->move_to_top( 'billing', 'billing_email' );
		$this->set_locale_overrides( array( 'order' => true ) );

		$fields = $this->billing( 'JP' );

		$this->assertSame( 10, $fields['billing_email']['priority'] );
		$this->assertSame( 20, $fields['billing_first_name']['priority'] );
		$this->assertSame( 30, $fields['billing_last_name']['priority'] );
		$this->assertSame( array( 'billing_email', 'billing_first_name', 'billing_last_name' ), array_slice( array_keys( $fields ), 0, 3 ) );
		$this->assertLessThan( $fields['billing_postcode']['priority'], $fields['billing_city']['priority'] );
	}

	/**
	 * Moves a field to the top of its section.
	 *
	 * @param string $section Section name.
	 * @param string $name    Field name.
	 */
	private function move_to_top( $section, $name ) {
		$this->change_fields(
			function ( &$sections ) use ( $section, $name ) {
				foreach ( $sections[ $section ] as $index => $field ) {
					if ( $field['name'] === $name ) {
						unset( $sections[ $section ][ $index ] );
						array_unshift( $sections[ $section ], $field );
						return;
					}
				}
			}
		);
	}

	/**
	 * A switch only makes our value win where the store owner changed it from
	 * WooCommerce's default. Untouched properties keep following the country,
	 * so right after activation, or after saving without edits, nothing about
	 * other countries changes.
	 */
	public function test_right_after_activation_india_keeps_its_own_labels() {
		$this->set_locale_overrides(
			array(
				'label'       => true,
				'placeholder' => true,
				'order'       => true,
				'required'    => true,
				'classes'     => true,
			)
		);

		$this->assertSame( 'PIN Code', $this->billing( 'IN' )['billing_postcode']['label'] );
		$this->assertSame( 'State', $this->billing( 'IN' )['billing_state']['label'] );
	}

	public function test_saving_without_edits_changes_nothing_in_other_countries() {
		$this->change_fields(
			static function () {
			}
		);
		$this->set_locale_overrides(
			array(
				'label'       => true,
				'placeholder' => true,
				'order'       => true,
				'required'    => true,
				'classes'     => true,
			)
		);
		$this->assertTrue( $this->domain()->field_sets( 'classic' )->is_stored() );

		foreach ( array( 'IN', 'JP', 'US', 'AE' ) as $country ) {
			$this->assertSame( $this->woocommerce_fields( $country ), $this->billing( $country ), $country );
		}
	}

	public function test_an_untouched_property_follows_the_country_even_with_its_switch_on() {
		$this->change_field( 'billing', 'billing_postcode', array( 'label' => 'Postal area' ) );
		$this->set_locale_overrides(
			array(
				'label'    => true,
				'order'    => true,
				'required' => true,
				'classes'  => true,
			)
		);

		$jp = $this->billing( 'JP' );
		$gb = $this->billing( 'GB' );

		$this->assertSame( 'Postal area', $this->billing( 'IN' )['billing_postcode']['label'], 'Changed: ours.' );
		$this->assertSame( 'Prefecture', $jp['billing_state']['label'], 'Untouched label: Japan\'s.' );
		$this->assertSame( 10, $jp['billing_last_name']['priority'], 'Untouched order: Japan\'s.' );
		$this->assertSame( array( 'form-row-first' ), $jp['billing_last_name']['class'], 'Untouched classes: Japan\'s.' );
		$this->assertFalse( $gb['billing_state']['required'], 'Untouched required: Great Britain\'s.' );
	}

	/**
	 * WooCommerce's billing fields for a country without our filter.
	 *
	 * @param string $country Country code.
	 * @return array
	 */
	private function woocommerce_fields( $country ) {
		$service = Plugin::instance()->get( CheckoutFields::class );
		remove_filter( 'woocommerce_billing_fields', array( $service, 'filter_billing_fields' ), CheckoutFields::PRIORITY );
		$fields = $this->billing( $country );
		add_filter( 'woocommerce_billing_fields', array( $service, 'filter_billing_fields' ), CheckoutFields::PRIORITY, 2 );

		return $fields;
	}

	public function test_order_off_lets_japan_use_its_own_order() {
		$this->set_locale_overrides( array( 'order' => false ) );

		$fields = $this->billing( 'JP' );

		$this->assertSame( 10, $fields['billing_last_name']['priority'] );
		$this->assertSame( 20, $fields['billing_first_name']['priority'] );
		$this->assertSame( array( 'billing_last_name', 'billing_first_name' ), array_slice( array_keys( $fields ), 0, 2 ) );
		$this->assertSame( 65, $fields['billing_postcode']['priority'] );
		$this->assertSame( 30, $fields['billing_company']['priority'], 'Untouched: as WooCommerce has it.' );
	}

	public function test_classes_on_keep_our_classes_in_japan() {
		$this->change_field( 'billing', 'billing_last_name', array( 'classes' => array( 'form-row-last', 'family' ) ) );
		$this->set_locale_overrides( array( 'classes' => true ) );

		$this->assertSame( array( 'form-row-last', 'family' ), $this->billing( 'JP' )['billing_last_name']['class'] );
	}

	public function test_classes_off_let_japan_use_its_own_classes() {
		$this->change_field( 'billing', 'billing_last_name', array( 'classes' => array( 'form-row-last', 'family' ) ) );
		$this->change_field( 'billing', 'billing_company', array( 'classes' => array( 'form-row-first' ) ) );
		$this->set_locale_overrides( array( 'classes' => false ) );

		$fields = $this->billing( 'JP' );

		$this->assertSame( array( 'form-row-first' ), $fields['billing_last_name']['class'] );
		$this->assertSame( array( 'form-row-first' ), $fields['billing_company']['class'], 'Japan has no rule for the company field.' );
	}

	public function test_required_on_makes_our_required_setting_win_in_great_britain() {
		$this->change_field( 'billing', 'billing_state', array( 'required' => true ) );
		$this->set_locale_overrides( array( 'required' => true ) );

		$this->assertTrue( $this->billing( 'GB' )['billing_state']['required'] );
		$this->assertTrue( $this->billing( 'IN' )['billing_state']['required'] );
	}

	public function test_required_off_lets_the_country_rule_win() {
		$this->change_field( 'billing', 'billing_state', array( 'required' => true ) );
		$this->change_field( 'billing', 'billing_postcode', array( 'required' => false ) );
		$this->set_locale_overrides( array( 'required' => false ) );

		$this->assertFalse( $this->billing( 'GB' )['billing_state']['required'], 'Great Britain makes the county optional.' );
		$this->assertTrue( $this->billing( 'IN' )['billing_state']['required'], 'India has no rule for required, so ours applies.' );
		$this->assertFalse( $this->billing( 'IN' )['billing_postcode']['required'] );
	}

	public function test_a_country_rule_that_hides_a_field_always_applies() {
		$this->change_field(
			'billing',
			'billing_postcode',
			array(
				'label'    => 'Postal area',
				'required' => false,
			)
		);
		$this->set_locale_overrides(
			array(
				'label'       => true,
				'placeholder' => true,
				'order'       => true,
				'required'    => true,
				'classes'     => true,
			)
		);

		$fields = $this->billing( 'AE' );

		$this->assertTrue( $fields['billing_postcode']['hidden'] );
		$this->assertSame( 'Postal area', $fields['billing_postcode']['label'] );
	}

	public function test_a_base_country_without_rules_of_its_own_does_not_count_as_having_rules() {
		update_option( 'woocommerce_default_country', 'MX' );
		WC()->countries->locale = array();
		$this->change_field( 'billing', 'billing_city', array( 'label' => 'Ciudad' ) );
		$this->set_locale_overrides( array( 'label' => false ) );

		$this->assertSame( 'Ciudad', $this->billing( 'MX' )['billing_city']['label'], 'WooCommerce copies its defaults to the base country; they are not a Mexican rule.' );
	}

	public function test_the_shipping_form_follows_the_same_switches() {
		$this->change_field( 'shipping', 'shipping_postcode', array( 'label' => 'Delivery code' ) );
		$this->set_locale_overrides( array( 'label' => false ) );

		$this->assertSame( 'PIN Code', WC()->countries->get_address_fields( 'IN', 'shipping_' )['shipping_postcode']['label'] );

		$this->set_locale_overrides( array( 'label' => true ) );

		$this->assertSame( 'Delivery code', WC()->countries->get_address_fields( 'IN', 'shipping_' )['shipping_postcode']['label'] );
	}

	public function test_the_script_gets_our_address_field_settings_and_the_switches_that_apply_to_each() {
		$this->change_field( 'billing', 'billing_city', array( 'label' => 'Suburb' ) );
		$this->change_field( 'shipping', 'shipping_state', array( 'enabled' => false ) );
		$this->set_locale_overrides(
			array(
				'label'       => true,
				'placeholder' => true,
				'order'       => true,
				'required'    => true,
				'classes'     => true,
			)
		);

		$data = Plugin::instance()->get( CheckoutFields::class )->script_data();

		$this->assertSame(
			array(
				'label'       => 'Suburb',
				'placeholder' => '',
				'required'    => true,
				'priority'    => 70,
				'classes'     => array( 'form-row-wide', 'address-field' ),
				'changed'     => array( 'label' ),
			),
			$data['fields']['billing_city'],
			'Only the label was changed.'
		);
		$this->assertSame( array(), $data['fields']['billing_postcode']['changed'] );
		$this->assertArrayHasKey( 'shipping_postcode', $data['fields'] );
		$this->assertArrayNotHasKey( 'shipping_state', $data['fields'], 'Disabled fields are gone from the form.' );
		$this->assertArrayNotHasKey( 'billing_email', $data['fields'], 'Only fields WooCommerce changes per country.' );
		$this->assertTrue( $data['localeOverrides']['label'] );
	}

	public function test_a_reordered_section_lets_the_order_switch_apply_to_every_field_of_it() {
		$this->move_to_top( 'billing', 'billing_email' );
		$this->set_locale_overrides( array( 'order' => true ) );

		$data = Plugin::instance()->get( CheckoutFields::class )->script_data();

		$this->assertContains( 'order', $data['fields']['billing_city']['changed'] );
		$this->assertNotContains( 'order', $data['fields']['shipping_city']['changed'], 'Shipping keeps WooCommerce\'s order.' );
	}

	public function test_a_custom_field_placed_before_a_built_in_one_counts_as_reordering() {
		$this->change_fields(
			function ( &$sections ) {
				array_unshift( $sections['billing'], $this->custom( 'billing_vat', 'text' ) );
			}
		);
		$this->set_locale_overrides( array( 'order' => true ) );

		$this->assertSame( 30, $this->billing( 'JP' )['billing_last_name']['priority'], 'Ours, not Japan\'s 10.' );
	}
}
