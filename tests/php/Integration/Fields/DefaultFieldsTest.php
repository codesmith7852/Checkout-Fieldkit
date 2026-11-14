<?php
/**
 * Tests for the built-in fields of each checkout type.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Fields;

use CheckoutFieldKit\Fields\DefaultFields;
use CheckoutFieldKit\Fields\Field;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Spec §2.3: classic built-ins come from WooCommerce's address fields for the
 * base country plus order notes; block built-ins are WooCommerce's core
 * block fields. Defaults never include the plugin's own changes.
 *
 * Our own checkout-field filters (added by later features) must return their
 * input unchanged while DefaultFields::is_computing() is true. The last tests
 * stand in for them with filters that follow that contract.
 */
class DefaultFieldsTest extends TestCase {

	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_default_country', 'GB' );
		update_option( 'woocommerce_checkout_phone_field', 'optional' );
		update_option( 'woocommerce_checkout_company_field', 'optional' );
		update_option( 'woocommerce_checkout_address_2_field', 'optional' );
		WC()->countries->locale = array();
	}

	public function tear_down() {
		parent::tear_down();
		WC()->countries->locale = array(); // Built for this test's base country and filters.
	}

	public function test_classic_defaults_are_woocommerces_address_fields_for_the_base_country() {
		$set = ( new DefaultFields() )->field_set( 'classic' );

		$this->assertSame( 1, $set['version'] );
		$this->assertSame( array( 'billing', 'shipping', 'additional' ), array_keys( $set['sections'] ) );
		$this->assertSame(
			array_keys( WC()->countries->get_address_fields( 'GB', 'billing_' ) ),
			array_column( $set['sections']['billing'], 'name' )
		);
		$this->assertSame(
			array_keys( WC()->countries->get_address_fields( 'GB', 'shipping_' ) ),
			array_column( $set['sections']['shipping'], 'name' )
		);
		$this->assertSame( array( 'order_comments' ), array_column( $set['sections']['additional'], 'name' ) );
	}

	public function test_classic_billing_holds_the_expected_fields() {
		$names = array_column( ( new DefaultFields() )->field_set( 'classic' )['sections']['billing'], 'name' );

		foreach ( array( 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_country', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_phone', 'billing_email' ) as $name ) {
			$this->assertContains( $name, $names );
		}
	}

	public function test_classic_built_ins_keep_woocommerces_settings() {
		$defaults = new DefaultFields();

		$first = $defaults->get( 'classic', 'billing_first_name' );
		$this->assertSame( array_keys( Field::DEFAULTS ), array_keys( $first ) );
		$this->assertSame( 'text', $first['type'] );
		$this->assertTrue( $first['builtIn'] );
		$this->assertTrue( $first['enabled'] );
		$this->assertTrue( $first['required'] );
		$this->assertSame( 'First name', $first['label'] );
		$this->assertSame( array( 'form-row-first' ), $first['classes'] );

		$this->assertSame( 'country', $defaults->get( 'classic', 'billing_country' )['type'] );
		$this->assertSame( 'state', $defaults->get( 'classic', 'shipping_state' )['type'] );
		$this->assertSame( 'email', $defaults->get( 'classic', 'billing_email' )['type'] );
		$this->assertSame( array( 'email' ), $defaults->get( 'classic', 'billing_email' )['validation'] );
		$this->assertSame( 'tel', $defaults->get( 'classic', 'billing_phone' )['type'] );
		$this->assertFalse( $defaults->get( 'classic', 'billing_phone' )['required'] );
		$this->assertSame( array( 'postcode' ), $defaults->get( 'classic', 'shipping_postcode' )['validation'] );
		$this->assertSame( 'House number and street name', $defaults->get( 'classic', 'billing_address_1' )['placeholder'] );

		$notes = $defaults->get( 'classic', 'order_comments' );
		$this->assertSame( 'textarea', $notes['type'] );
		$this->assertSame( 'Order notes', $notes['label'] );
		$this->assertFalse( $notes['required'] );
		$this->assertNotSame( '', $notes['placeholder'] );
		$this->assertSame( 'additional', $defaults->section_of( 'classic', 'order_comments' ) );
	}

	public function test_classic_defaults_follow_the_base_countrys_locale() {
		update_option( 'woocommerce_default_country', 'US:CA' );

		$this->assertSame( 'ZIP Code', ( new DefaultFields() )->get( 'classic', 'billing_postcode' )['label'] );
	}

	public function test_fields_woocommerce_hides_by_setting_are_not_classic_built_ins() {
		update_option( 'woocommerce_checkout_phone_field', 'hidden' );

		$defaults = new DefaultFields();

		$this->assertNull( $defaults->get( 'classic', 'billing_phone' ) );
		$this->assertFalse( $defaults->get( 'block', 'phone' )['enabled'], 'On Block it stays, turned off.' );
	}

	public function test_block_defaults() {
		$set = ( new DefaultFields() )->field_set( 'block' );

		$this->assertSame( 1, $set['version'] );
		$this->assertSame( array( 'contact', 'address', 'additional' ), array_keys( $set['sections'] ) );
		$this->assertSame( array( 'email' ), array_column( $set['sections']['contact'], 'name' ) );
		$this->assertSame(
			array( 'country', 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone' ),
			array_column( $set['sections']['address'], 'name' )
		);
		$this->assertSame( array(), $set['sections']['additional'] );
	}

	public function test_block_built_ins_keep_woocommerces_settings() {
		update_option( 'woocommerce_checkout_company_field', 'hidden' );
		update_option( 'woocommerce_checkout_phone_field', 'required' );
		$defaults = new DefaultFields();

		$email = $defaults->get( 'block', 'email' );
		$this->assertSame( 'email', $email['type'] );
		$this->assertTrue( $email['builtIn'] );
		$this->assertTrue( $email['required'] );
		$this->assertTrue( $email['enabled'] );
		$this->assertSame( 'Email address', $email['label'] );

		$this->assertSame( 'country', $defaults->get( 'block', 'country' )['type'] );
		$this->assertSame( 'state', $defaults->get( 'block', 'state' )['type'] );
		$this->assertSame( 'text', $defaults->get( 'block', 'city' )['type'] );
		$this->assertSame( 'tel', $defaults->get( 'block', 'phone' )['type'] );
		$this->assertTrue( $defaults->get( 'block', 'phone' )['required'] );
		$this->assertFalse( $defaults->get( 'block', 'company' )['enabled'] );
		$this->assertSame( 'address', $defaults->section_of( 'block', 'postcode' ) );
	}

	public function test_unknown_names_and_checkout_types() {
		$defaults = new DefaultFields();

		$this->assertNull( $defaults->get( 'classic', 'gift_note' ) );
		$this->assertNull( $defaults->get( 'block', 'billing_email' ) );
		$this->assertNull( $defaults->section_of( 'classic', 'email' ) );
		$this->assertSame( array(), $defaults->field_set( 'nope' )['sections'] );
	}

	public function test_names() {
		$defaults = new DefaultFields();

		$this->assertContains( 'billing_email', $defaults->names( 'classic' ) );
		$this->assertContains( 'order_comments', $defaults->names( 'classic' ) );
		$this->assertContains( 'email', $defaults->names( 'block' ) );
		$this->assertCount( 11, $defaults->names( 'block' ) );
	}

	public function test_editable_settings_of_built_in_fields() {
		$defaults = new DefaultFields();

		$this->assertSame( array( 'label', 'placeholder', 'default', 'required', 'enabled', 'validation', 'classes' ), $defaults->editable_settings( 'classic', 'billing_city' ) );
		$this->assertSame( array( 'label', 'required', 'enabled' ), $defaults->editable_settings( 'block', 'city' ) );
		$this->assertSame( array(), $defaults->editable_settings( 'block', 'email' ), 'Block email is locked: only its position is ours.' );
	}

	public function test_reserved_names_cover_both_checkout_types_and_woocommerces_own_inputs() {
		update_option( 'woocommerce_checkout_phone_field', 'hidden' );
		$reserved = ( new DefaultFields() )->reserved_names();

		foreach ( array( 'billing_phone', 'shipping_phone', 'billing_email', 'shipping_country', 'email', 'address_1', 'phone', 'order_comments', 'account_username', 'account_password', 'createaccount', 'ship_to_different_address', 'terms', 'payment_method', 'shipping_method' ) as $name ) {
			$this->assertContains( $name, $reserved );
		}
		$this->assertNotContains( 'gift_note', $reserved );
	}

	public function test_a_field_another_plugin_adds_to_woocommerces_address_fields_is_built_in() {
		add_filter(
			'woocommerce_billing_fields',
			static function ( $fields ) {
				$fields['billing_vat'] = array(
					'label'    => 'VAT number',
					'priority' => 120,
				);
				return $fields;
			}
		);

		$this->assertSame( 'VAT number', ( new DefaultFields() )->get( 'classic', 'billing_vat' )['label'] );
	}

	public function test_reading_defaults_does_not_apply_our_own_field_filters_and_does_not_recurse() {
		$defaults = new DefaultFields();
		$calls    = 0;
		$applied  = 0;
		add_filter(
			'woocommerce_billing_fields',
			static function ( $fields ) use ( $defaults, &$calls, &$applied ) {
				++$calls;
				if ( DefaultFields::is_computing() ) {
					return $fields;
				}
				++$applied;
				// Like the classic checkout filter: start from the defaults, apply our changes.
				$ours                                  = $defaults->get( 'classic', 'billing_first_name' );
				$fields['billing_first_name']['label'] = 'Ours: ' . $ours['label'];
				return $fields;
			},
			PHP_INT_MAX
		);

		$label = WC()->countries->get_address_fields( 'GB', 'billing_' )['billing_first_name']['label'];

		$this->assertSame( 'Ours: First name', $label, 'The filter applies on the checkout.' );
		$this->assertSame( 1, $applied, 'Our filter ran once, and reading defaults inside it did not run it again.' );
		$this->assertSame( 2, $calls );
		$this->assertSame( 'First name', $defaults->get( 'classic', 'billing_first_name' )['label'], 'Defaults do not include our changes.' );
	}

	public function test_is_computing_is_true_only_while_defaults_are_read() {
		$seen = array();
		add_filter(
			'woocommerce_shipping_fields',
			static function ( $fields ) use ( &$seen ) {
				$seen[] = DefaultFields::is_computing();
				return $fields;
			}
		);

		( new DefaultFields() )->field_set( 'classic' );

		$this->assertSame( array( true ), $seen );
		$this->assertFalse( DefaultFields::is_computing() );
	}

	public function test_our_locale_filters_are_left_out_of_defaults_but_woocommerces_locale_cache_keeps_them() {
		add_filter(
			'woocommerce_get_country_locale',
			static function ( $locale ) {
				if ( DefaultFields::is_computing() ) {
					return $locale;
				}
				$locale['GB']['postcode']['label'] = 'Ours';
				return $locale;
			}
		);
		WC()->countries->get_country_locale(); // WooCommerce builds and caches the locale with our filter.

		$label = ( new DefaultFields() )->get( 'classic', 'billing_postcode' )['label'];

		$this->assertNotSame( 'Ours', $label );
		$this->assertSame( 'Ours', WC()->countries->get_country_locale()['GB']['postcode']['label'], 'Reading defaults does not leave a locale without our changes cached.' );
	}
}
