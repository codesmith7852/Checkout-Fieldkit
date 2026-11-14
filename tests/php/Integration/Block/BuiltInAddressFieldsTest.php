<?php
/**
 * Tests for WooCommerce's own address fields on the Block checkout.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Block;

use Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\Blocks\Package;
use CheckoutFieldKit\Block\AddressFields;
use CheckoutFieldKit\Block\BuiltInAddressFields;
use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Environment\BlockSupport;
use CheckoutFieldKit\Fields\DefaultFields;
use CheckoutFieldKit\Plugin;
use CheckoutFieldKit\Tests\Support\StoreApiCheckout;
use CheckoutFieldKit\Tests\TestCase;
use ReflectionProperty;

/**
 * Spec §5, first bullet: built-in address fields get our label, required,
 * enabled (hidden) and order by adjusting WooCommerce's default address field
 * data and country locale data, only when the store uses the Block checkout.
 * Locale overrides (spec §6.4) decide whether a country's own value or ours
 * wins for what the store owner changed.
 */
class BuiltInAddressFieldsTest extends TestCase {

	use StoreApiCheckout;

	/**
	 * The service under test, with its hooks added.
	 *
	 * @var BuiltInAddressFields
	 */
	private $service;

	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_default_country', 'GB' );
		update_option( 'woocommerce_allowed_countries', 'all' );
		update_option( 'woocommerce_checkout_phone_field', 'optional' );
		update_option( 'woocommerce_checkout_company_field', 'optional' );
		update_option( 'woocommerce_checkout_address_2_field', 'optional' );
		$this->use_checkout_page( '<!-- wp:woocommerce/checkout --><div class="wp-block-woocommerce-checkout"></div><!-- /wp:woocommerce/checkout -->' );
		$this->forget_locale();
		$this->forget_default_fields_data();

		// The running plugin's copy keeps WooCommerce's defaults from earlier tests; test a fresh one.
		$this->remove_hooks_of( Plugin::instance()->get( BuiltInAddressFields::class ) );
		$this->service = new BuiltInAddressFields( new Domain() );
		$this->service->register_hooks();
	}

	/**
	 * Removes the hooks a BuiltInAddressFields instance added. The test case restores them afterwards.
	 *
	 * @param BuiltInAddressFields $service Service.
	 */
	private function remove_hooks_of( $service ) {
		remove_filter( 'woocommerce_default_address_fields', array( $service, 'filter_default_address_fields' ), 1000 );
		remove_filter( 'woocommerce_get_country_locale', array( $service, 'filter_country_locale' ), 1000 );
		remove_action( 'woocommerce_blocks_checkout_enqueue_data', array( $service, 'add_default_fields_data' ), 5 );
		remove_action( 'woocommerce_blocks_cart_enqueue_data', array( $service, 'add_default_fields_data' ), 5 );
	}

	public function tear_down() {
		$controller = Package::container()->get( CheckoutFields::class );
		foreach ( array_keys( $controller->get_additional_fields() ) as $id ) {
			if ( 0 === strpos( $id, 'checkout-fieldkit/' ) ) {
				$controller->deregister_checkout_field( $id );
			}
		}
		$this->forget_locale();
		$this->forget_default_fields_data();
		parent::tear_down();
	}

	/**
	 * Makes WooCommerce build its country locale again on next use.
	 */
	private function forget_locale() {
		WC()->countries->locale = array();
	}

	/**
	 * The data WooCommerce's block scripts get, from the shared registry.
	 *
	 * @return array<string, mixed>
	 */
	private function asset_data() {
		$property = new ReflectionProperty( AssetDataRegistry::class, 'data' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		return $property->getValue( Package::container()->get( AssetDataRegistry::class ) );
	}

	/**
	 * Removes `defaultFields` from the shared asset data registry, which lives for the whole run.
	 */
	private function forget_default_fields_data() {
		$registry = Package::container()->get( AssetDataRegistry::class );
		$property = new ReflectionProperty( AssetDataRegistry::class, 'data' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$data = $property->getValue( $registry );
		unset( $data['defaultFields'], $data['addressFieldsLocations'] );
		$property->setValue( $registry, $data );
	}

	/**
	 * Makes a page with this content the store's Checkout page.
	 *
	 * @param string $content Page content.
	 */
	private function use_checkout_page( $content ) {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);
		update_option( 'woocommerce_checkout_page_id', $page_id );
		$this->forget_locale();
	}

	/**
	 * Saves the Block field set after changing its Address section.
	 *
	 * @param callable $edit Receives the address fields keyed by name and returns them, in the new order.
	 */
	private function save_address( callable $edit ) {
		$repository = ( new Domain() )->field_sets( 'block' );
		$set        = $repository->get();
		$fields     = array_column( $set['sections']['address'], null, 'name' );

		$set['sections']['address'] = array_values( $edit( $fields ) );

		$this->assertTrue( $repository->save( $set ) );
		$this->forget_locale();
	}

	/**
	 * Saves locale overrides.
	 *
	 * @param array<string, bool> $overrides Overrides to change.
	 */
	private function save_overrides( array $overrides ) {
		$settings                 = ( new Domain() )->settings();
		$value                    = $settings->get();
		$value['localeOverrides'] = array_merge( $value['localeOverrides'], $overrides );
		$this->assertTrue( $settings->save( $value ) );
		$this->forget_locale();
	}

	/**
	 * Moves a field to a position.
	 *
	 * @param array<string, array<string, mixed>> $fields   Fields keyed by name.
	 * @param string                              $name     Field to move.
	 * @param int                                 $position New position, from 0.
	 * @return array<string, array<string, mixed>>
	 */
	private function move( array $fields, $name, $position ) {
		$field = $fields[ $name ];
		unset( $fields[ $name ] );

		return array_merge( array_slice( $fields, 0, $position, true ), array( $name => $field ), array_slice( $fields, $position, null, true ) );
	}

	/**
	 * A typical set of changes: a label, required on and off, a field turned off.
	 */
	private function save_typical_changes() {
		$this->save_address(
			function ( $fields ) {
				$fields['city']['label']          = 'Town';
				$fields['first_name']['required'] = false;
				$fields['phone']['required']      = true;
				$fields['company']['enabled']     = false;
				return $fields;
			}
		);
	}

	public function test_the_plugin_registers_the_service() {
		$this->assertInstanceOf( BuiltInAddressFields::class, Plugin::instance()->get( BuiltInAddressFields::class ) );
	}

	public function test_default_address_fields_get_our_label_required_and_enabled() {
		$this->save_typical_changes();

		$fields = WC()->countries->get_default_address_fields();

		$this->assertSame( 'Town', $fields['city']['label'] );
		$this->assertFalse( $fields['first_name']['required'] );
		$this->assertTrue( $fields['phone']['required'] );
		$this->assertArrayNotHasKey( 'company', $fields, 'Turned off.' );
		$this->assertSame( 'Street address', $fields['address_1']['label'], 'What the store owner did not change stays as WooCommerce has it.' );
		$this->assertSame( 50, $fields['address_1']['priority'], 'The order did not change.' );
	}

	public function test_a_changed_order_becomes_the_priority() {
		$this->save_address(
			function ( $fields ) {
				return $this->move( $fields, 'postcode', 1 ); // Right after the country.
			}
		);

		$fields = WC()->countries->get_default_address_fields();

		$this->assertSame( 10, $fields['country']['priority'] );
		$this->assertSame( 20, $fields['postcode']['priority'] );
		$this->assertSame( 30, $fields['first_name']['priority'] );
		$this->assertSame( 'postcode', array_keys( $fields )[1] );
	}

	public function test_a_field_woocommerce_hides_by_setting_can_be_turned_on() {
		update_option( 'woocommerce_checkout_phone_field', 'hidden' );
		$this->save_address(
			function ( $fields ) {
				$fields['phone']['enabled']  = true;
				$fields['phone']['required'] = true;
				return $fields;
			}
		);

		$phone = WC()->countries->get_default_address_fields()['phone'];

		$this->assertTrue( $phone['required'] );
		$this->assertSame( 'tel', $phone['type'] );
		$this->assertSame( array( 'phone' ), $phone['validate'] );
		$this->assertSame( 'Phone', $phone['label'] );
	}

	public function test_the_country_is_never_turned_off_or_made_optional() {
		$this->save_address(
			function ( $fields ) {
				$fields['country']['label']    = 'Land';
				$fields['country']['enabled']  = false;
				$fields['country']['required'] = false;
				return $fields;
			}
		);

		$fields = WC()->countries->get_default_address_fields();
		$this->assertSame( 'Land', $fields['country']['label'] );
		$this->assertTrue( $fields['country']['required'] );

		$this->service->add_default_fields_data();
		$country = $this->asset_data()['defaultFields']['country'];
		$this->assertSame( 'Land', $country['label'] );
		$this->assertTrue( $country['required'] );
		$this->assertFalse( $country['hidden'] );
	}

	public function test_nothing_changes_before_the_block_field_set_is_saved() {
		$before = WC()->countries->get_default_address_fields();
		$this->remove_hooks_of( $this->service );
		$this->forget_locale();

		$this->assertSame( WC()->countries->get_default_address_fields(), $before );
	}

	public function test_nothing_changes_when_the_store_uses_the_classic_checkout() {
		$this->save_typical_changes();
		$this->use_checkout_page( '[woocommerce_checkout]' );

		$fields = WC()->countries->get_default_address_fields();

		$this->assertSame( 'Town / City', $fields['city']['label'] );
		$this->assertTrue( $fields['first_name']['required'] );
		$this->assertArrayHasKey( 'company', $fields );
		$this->assertSame( 'County', WC()->countries->get_country_locale()['GB']['state']['label'] );
	}

	public function test_nothing_changes_without_block_support() {
		$this->save_typical_changes();
		$this->remove_hooks_of( $this->service );
		( new BuiltInAddressFields( new Domain(), new BlockSupport( '8.8.0', true ) ) )->register_hooks();

		$this->assertSame( 'Town / City', WC()->countries->get_default_address_fields()['city']['label'] );
	}

	public function test_defaults_never_include_our_changes() {
		$this->save_typical_changes();

		$defaults = new DefaultFields();

		$this->assertSame( 'Town / City', $defaults->get( 'classic', 'billing_city' )['label'] );
		$this->assertTrue( $defaults->get( 'classic', 'billing_first_name' )['required'] );
		$this->assertNotNull( $defaults->get( 'classic', 'billing_company' ) );
		$this->assertSame( 'City', $defaults->get( 'block', 'city' )['label'] );
	}

	public function test_a_turned_off_field_is_hidden_in_every_country() {
		$this->save_address(
			function ( $fields ) {
				$fields['state']['enabled'] = false;
				return $fields;
			}
		);

		$locale = WC()->countries->get_country_locale();

		foreach ( array( 'GB', 'US', 'JP', 'IE' ) as $country ) {
			$this->assertTrue( $locale[ $country ]['state']['hidden'], $country );
			$this->assertFalse( $locale[ $country ]['state']['required'], $country );
		}
		$this->assertArrayNotHasKey( 'state', $locale['default'] );
	}

	public function test_our_labels_replace_country_labels_when_kept() {
		$this->save_address(
			function ( $fields ) {
				$fields['state']['label'] = 'Region';
				return $fields;
			}
		);

		$locale = WC()->countries->get_country_locale();

		$this->assertArrayNotHasKey( 'label', $locale['GB']['state'] );
		$this->assertArrayNotHasKey( 'label', $locale['US']['state'] );
		$this->assertSame( 'Region', $locale['default']['state']['label'] );
		$this->assertSame( 'ZIP Code', $locale['US']['postcode']['label'], 'Unchanged fields keep the country label.' );
		$this->assertSame( 'Region', WC()->countries->get_address_fields( 'US', 'billing_' )['billing_state']['label'] );
	}

	public function test_country_labels_win_when_labels_are_not_kept() {
		$this->save_overrides( array( 'label' => false ) );
		$this->save_address(
			function ( $fields ) {
				$fields['state']['label'] = 'Region';
				return $fields;
			}
		);

		$locale = WC()->countries->get_country_locale();

		$this->assertSame( 'County', $locale['GB']['state']['label'] );
		$this->assertSame( 'Region', $locale['default']['state']['label'] );
	}

	public function test_our_order_replaces_country_order_when_kept() {
		$this->save_address(
			function ( $fields ) {
				return $this->move( $fields, 'postcode', 1 );
			}
		);

		$locale = WC()->countries->get_country_locale();

		$this->assertArrayNotHasKey( 'priority', $locale['JP']['postcode'] );
		$this->assertArrayNotHasKey( 'priority', $locale['JP']['city'] );
	}

	public function test_country_order_wins_when_order_is_not_kept() {
		$this->save_overrides( array( 'order' => false ) );
		$this->save_address(
			function ( $fields ) {
				return $this->move( $fields, 'postcode', 1 );
			}
		);

		$this->assertSame( 65, WC()->countries->get_country_locale()['JP']['postcode']['priority'] );
	}

	public function test_the_country_rule_for_required_wins_by_default() {
		$this->save_address(
			function ( $fields ) {
				$fields['postcode']['required'] = false;
				return $fields;
			}
		);

		$locale = WC()->countries->get_country_locale();

		$this->assertTrue( $locale['IE']['postcode']['required'] );
		$this->assertFalse( $locale['default']['postcode']['required'] );
	}

	public function test_our_required_wins_when_kept_except_where_the_country_hides_the_field() {
		$this->save_overrides( array( 'required' => true ) );
		$this->save_address(
			function ( $fields ) {
				$fields['postcode']['required'] = false;
				$fields['state']['required']    = false;
				return $fields;
			}
		);

		$locale = WC()->countries->get_country_locale();

		$this->assertFalse( $locale['IE']['postcode']['required'] );
		$this->assertTrue( $locale['AF']['state']['hidden'], 'A country that hides a field still hides it.' );
	}

	public function test_block_default_fields_data_gets_our_changes() {
		$this->save_address(
			function ( $fields ) {
				$fields['city']['label']          = 'Town';
				$fields['first_name']['required'] = false;
				$fields['company']['enabled']     = false;
				$fields                           = $this->move( $fields, 'postcode', 1 );
				$fields['vat']                    = array(
					'name'  => 'vat',
					'type'  => 'text',
					'label' => 'VAT',
				);
				return $this->move( $fields, 'vat', 2 );
			}
		);
		( new AddressFields( new Domain() ) )->register_fields();

		do_action( 'woocommerce_blocks_checkout_enqueue_data' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's hook.
		$data = $this->asset_data()['defaultFields'];

		$this->assertSame( 'Town', $data['city']['label'] );
		$this->assertSame( 'Town (optional)', $data['city']['optionalLabel'] );
		$this->assertFalse( $data['first_name']['required'] );
		$this->assertTrue( $data['company']['hidden'] );
		$this->assertFalse( $data['company']['required'] );
		$this->assertSame( 10, $data['country']['index'] );
		$this->assertSame( 20, $data['postcode']['index'] );
		$this->assertSame( 30, $data['checkout-fieldkit/vat']['index'] );
		$this->assertSame( 40, $data['first_name']['index'] );
		$this->assertSame( 'Email address', $data['email']['label'], 'Email is never changed.' );
		$this->assertSame( 0, $data['email']['index'] );
		$this->assertSame( 'email', $data['email']['type'] );
	}

	public function test_block_default_fields_data_is_woocommerces_own_without_changes() {
		do_action( 'woocommerce_blocks_checkout_enqueue_data' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's hook.

		$controller = Package::container()->get( CheckoutFields::class );
		$this->assertSame(
			wp_json_encode( array_merge( $controller->get_core_fields(), $controller->get_additional_fields() ) ),
			wp_json_encode( $this->asset_data()['defaultFields'] )
		);
	}

	public function test_the_cart_gets_the_same_default_fields_data() {
		$this->save_typical_changes();

		do_action( 'woocommerce_blocks_cart_enqueue_data' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's hook.

		$this->assertSame( 'Town', $this->asset_data()['defaultFields']['city']['label'] );
	}

	public function test_a_store_api_checkout_accepts_a_turned_off_field_left_empty() {
		$this->save_address(
			function ( $fields ) {
				$fields['city']['enabled'] = false;
				return $fields;
			}
		);

		$response = $this->store_api_checkout( $this->billing_address( array( 'city' => '' ) ) );

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
	}

	public function test_a_store_api_checkout_requires_a_field_made_required() {
		$this->save_address(
			function ( $fields ) {
				$fields['company']['required'] = true;
				$fields['company']['label']    = 'Business';
				return $fields;
			}
		);

		$response = $this->store_api_checkout( $this->billing_address( array( 'company' => '' ) ) );

		$this->assertSame( 400, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertStringContainsString( 'Business is required', wp_json_encode( $response->get_data() ) );
	}

	public function test_a_store_api_checkout_accepts_a_field_made_optional() {
		$this->save_address(
			function ( $fields ) {
				$fields['last_name']['required'] = false;
				return $fields;
			}
		);

		$response = $this->store_api_checkout( $this->billing_address( array( 'last_name' => '' ) ) );

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
	}
}
