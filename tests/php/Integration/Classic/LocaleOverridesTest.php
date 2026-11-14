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

	public function test_order_on_keeps_our_order_in_japan() {
		$this->set_locale_overrides( array( 'order' => true ) );

		$fields = $this->billing( 'JP' );

		$this->assertSame( 10, $fields['billing_first_name']['priority'] );
		$this->assertSame( 20, $fields['billing_last_name']['priority'] );
		$this->assertSame( array( 'billing_first_name', 'billing_last_name' ), array_slice( array_keys( $fields ), 0, 2 ) );
		$this->assertLessThan( $fields['billing_postcode']['priority'], $fields['billing_city']['priority'] );
	}

	public function test_order_off_lets_japan_use_its_own_order() {
		$this->set_locale_overrides( array( 'order' => false ) );

		$fields = $this->billing( 'JP' );

		$this->assertSame( 10, $fields['billing_last_name']['priority'] );
		$this->assertSame( 20, $fields['billing_first_name']['priority'] );
		$this->assertSame( array( 'billing_last_name', 'billing_first_name' ), array_slice( array_keys( $fields ), 0, 2 ) );
		$this->assertSame( 65, $fields['billing_postcode']['priority'] );
		$this->assertSame( 30, $fields['billing_company']['priority'], 'Japan has no rule for the company field, so our position applies.' );
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
		$this->change_field( 'billing', 'billing_postcode', array( 'required' => true ) );
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
		$this->assertTrue( $fields['billing_postcode']['required'] );
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

	public function test_the_script_gets_our_address_field_settings_and_switches() {
		$this->change_field( 'billing', 'billing_city', array( 'label' => 'Suburb' ) );
		$this->change_field( 'shipping', 'shipping_state', array( 'enabled' => false ) );
		$this->set_locale_overrides( array( 'label' => false ) );

		$data = Plugin::instance()->get( CheckoutFields::class )->script_data();

		$this->assertSame(
			array(
				'label'       => false,
				'placeholder' => true,
				'order'       => true,
				'required'    => false,
				'classes'     => false,
			),
			$data['localeOverrides']
		);
		$this->assertSame(
			array(
				'label'       => 'Suburb',
				'placeholder' => '',
				'required'    => true,
				'priority'    => 70,
				'classes'     => array( 'form-row-wide', 'address-field' ),
			),
			$data['fields']['billing_city']
		);
		$this->assertArrayHasKey( 'shipping_postcode', $data['fields'] );
		$this->assertArrayNotHasKey( 'shipping_state', $data['fields'], 'Disabled fields are gone from the form.' );
		$this->assertArrayNotHasKey( 'billing_email', $data['fields'], 'Only fields WooCommerce changes per country.' );
	}
}
