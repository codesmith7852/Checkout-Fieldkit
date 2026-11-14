<?php
/**
 * Applies the classic field set to WooCommerce's checkout and address forms.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Classic;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Fields\DefaultFields;
use CheckoutFieldKit\Service;
use const CheckoutFieldKit\PLUGIN_FILE;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §4. Late filters on woocommerce_billing_fields and
 * woocommerce_shipping_fields (which also feed My Account → Addresses) and on
 * woocommerce_checkout_fields (for Additional, WooCommerce's "order" fields):
 *
 * - Built-in fields keep WooCommerce's definition with our settings on top,
 *   subject to the locale override switches (LocaleOverrides).
 * - On a store whose Checkout page uses the Block checkout, Billing and
 *   Shipping (and so My Account → Addresses) are left to WooCommerce
 *   (AddressScope).
 * - Custom fields are added as definitions of their own type.
 * - Disabled fields are removed; position becomes priority (index + 1) × 10.
 * - Fields WooCommerce or other plugins add that are not in the field set
 *   are left alone.
 * - When every Additional field is disabled, woocommerce_enable_order_notes_field
 *   is false, so the "Additional information" heading goes too.
 *
 * On the checkout and the address form it also loads a small script that
 * applies the same locale rules when the shopper changes country, and the
 * styles of the fields the plugin renders (FieldRenderer).
 */
final class CheckoutFields implements Service {

	/**
	 * Priority of the field filters: late, so our settings win.
	 */
	public const PRIORITY = 1000;

	/**
	 * Handle of the script and the style sheet.
	 */
	public const HANDLE = 'checkout-fieldkit-classic';

	/**
	 * Domain objects.
	 *
	 * @var Domain
	 */
	private $domain;

	/**
	 * Created on first use.
	 *
	 * @var FieldSet|null
	 */
	private $field_set = null;

	/**
	 * Created on first use.
	 *
	 * @var WooCommerceFields|null
	 */
	private $mapper = null;

	/**
	 * Created on first use.
	 *
	 * @var LocaleOverrides|null
	 */
	private $locale = null;

	/**
	 * Constructor.
	 *
	 * @param Domain $domain Domain objects.
	 */
	public function __construct( Domain $domain ) {
		$this->domain = $domain;
	}

	/**
	 * Adds the field filters and the asset loader.
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_billing_fields', array( $this, 'filter_billing_fields' ), self::PRIORITY, 2 );
		add_filter( 'woocommerce_shipping_fields', array( $this, 'filter_shipping_fields' ), self::PRIORITY, 2 );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'filter_checkout_fields' ), self::PRIORITY );
		add_filter( 'woocommerce_enable_order_notes_field', array( $this, 'filter_order_notes_enabled' ), self::PRIORITY );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
	}

	/**
	 * Applies the Billing section.
	 *
	 * @param mixed  $fields  WooCommerce's billing fields, keyed by name.
	 * @param string $country Country the fields are for.
	 * @return mixed
	 */
	public function filter_billing_fields( $fields, $country = '' ) {
		return $this->apply_address_section( 'billing', $fields, is_string( $country ) ? $country : '' );
	}

	/**
	 * Applies the Shipping section.
	 *
	 * @param mixed  $fields  WooCommerce's shipping fields, keyed by name.
	 * @param string $country Country the fields are for.
	 * @return mixed
	 */
	public function filter_shipping_fields( $fields, $country = '' ) {
		return $this->apply_address_section( 'shipping', $fields, is_string( $country ) ? $country : '' );
	}

	/**
	 * Applies the Additional section to WooCommerce's "order" fields. Billing
	 * and shipping have been applied already, through get_address_fields().
	 *
	 * @param mixed $fields Checkout fields keyed by fieldset.
	 * @return mixed
	 */
	public function filter_checkout_fields( $fields ) {
		if ( DefaultFields::is_computing() || ! is_array( $fields ) ) {
			return $fields;
		}

		$order           = isset( $fields['order'] ) && is_array( $fields['order'] ) ? $fields['order'] : array();
		$fields['order'] = $this->apply( 'additional', $order, array() );

		return $fields;
	}

	/**
	 * Turns WooCommerce's order notes field off when every Additional field
	 * is disabled, which also hides the "Additional information" heading.
	 * Admin screens are left alone, so the admin can still see and edit a
	 * customer's note.
	 *
	 * @param mixed $enabled Whether WooCommerce shows order notes.
	 * @return mixed
	 */
	public function filter_order_notes_enabled( $enabled ) {
		if ( DefaultFields::is_computing() || ( is_admin() && ! wp_doing_ajax() ) ) {
			return $enabled;
		}

		foreach ( $this->field_set()->section( 'additional' ) as $field ) {
			if ( $field['enabled'] ) {
				return $enabled;
			}
		}

		return false;
	}

	/**
	 * What the classic checkout script needs: the locale override switches,
	 * and our settings for each enabled built-in address field WooCommerce
	 * changes per country (its country locale field selectors), keyed by name,
	 * with the switch names of the properties the store owner changed.
	 *
	 * @return array{localeOverrides: array<string, bool>, fields: array<string, array<string, mixed>>}
	 */
	public function script_data(): array {
		$keys   = function_exists( 'WC' ) && WC()->countries instanceof \WC_Countries
			? array_keys( (array) WC()->countries->get_country_locale_field_selectors() )
			: array();
		$fields = array();

		foreach ( array( 'billing', 'shipping' ) as $section ) {
			$changed = $this->changed( $section );
			foreach ( $this->field_set()->section( $section ) as $index => $field ) {
				$key = self::unprefixed( $section, $field['name'] );
				if ( ! $field['builtIn'] || ! $field['enabled'] || ! in_array( $key, $keys, true ) ) {
					continue;
				}
				$fields[ $field['name'] ] = array(
					'label'       => $field['label'],
					'placeholder' => $field['placeholder'],
					'required'    => $field['required'],
					'priority'    => FieldSet::priority( $index ),
					'classes'     => $field['classes'],
					'changed'     => $this->locale()->changed_switches( isset( $changed[ $field['name'] ] ) ? $changed[ $field['name'] ] : array() ),
				);
			}
		}

		return array(
			'localeOverrides' => $this->locale()->switches(),
			'fields'          => $fields,
		);
	}

	/**
	 * Loads the classic checkout script and styles on the checkout and on
	 * My Account's address form, and nowhere else. Needs `npm run build`.
	 */
	public function enqueue_assets(): void {
		if ( ! self::is_form_page() || ! $this->scope()->applies() ) {
			return;
		}

		$dir        = plugin_dir_path( PLUGIN_FILE ) . 'build/';
		$asset_file = $dir . 'classic-checkout.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$asset   = include $asset_file;
		$version = is_array( $asset ) && isset( $asset['version'] ) ? (string) $asset['version'] : null;
		$deps    = is_array( $asset ) && isset( $asset['dependencies'] ) ? (array) $asset['dependencies'] : array();
		$deps[]  = 'jquery';
		foreach ( array( 'wc-address-i18n', 'selectWoo' ) as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				$deps[] = $handle;
			}
		}

		wp_enqueue_script( self::HANDLE, plugins_url( 'build/classic-checkout.js', PLUGIN_FILE ), array_values( array_unique( $deps ) ), $version, true );
		wp_add_inline_script( self::HANDLE, 'window.checkoutFieldkitClassic = ' . wp_json_encode( $this->script_data() ) . ';', 'before' );

		if ( is_readable( $dir . 'classic-checkout.css' ) ) {
			wp_enqueue_style( self::HANDLE, plugins_url( 'build/classic-checkout.css', PLUGIN_FILE ), array(), $version );
		}
	}

	/**
	 * Whether the current page shows the classic checkout form or My
	 * Account's address form.
	 *
	 * @return bool
	 */
	private static function is_form_page(): bool {
		if ( ! function_exists( 'is_checkout' ) ) {
			return false;
		}

		if ( is_checkout() && ! is_order_received_page() && ! is_checkout_pay_page() ) {
			return true;
		}

		return is_account_page() && is_wc_endpoint_url( 'edit-address' );
	}

	/**
	 * Applies the Billing or Shipping section, with the country's locale rules
	 * where the switches let them stay.
	 *
	 * @param string $section Section name.
	 * @param mixed  $fields  WooCommerce's fields, keyed by name.
	 * @param string $country Country the fields are for.
	 * @return mixed
	 */
	private function apply_address_section( string $section, $fields, string $country ) {
		if ( DefaultFields::is_computing() || ! is_array( $fields ) || ! $this->scope()->applies() ) {
			return $fields;
		}

		$rules    = $this->locale()->country_rules( $country );
		$switches = $this->locale()->switches();
		$changed  = $this->changed( $section );
		$keep     = array();
		foreach ( $this->field_set()->section( $section ) as $field ) {
			$key = self::unprefixed( $section, $field['name'] );
			if ( $field['builtIn'] ) {
				$rule                   = isset( $rules[ $key ] ) && is_array( $rules[ $key ] ) ? $rules[ $key ] : array();
				$keep[ $field['name'] ] = $this->locale()->kept( $rule, $switches, isset( $changed[ $field['name'] ] ) ? $changed[ $field['name'] ] : array() );
			}
		}

		return $this->apply( $section, $fields, $keep );
	}

	/**
	 * Rebuilds WooCommerce's fields of a section from the field set.
	 *
	 * @param string                  $section Section name.
	 * @param array<string, mixed>    $fields  WooCommerce's fields, keyed by name.
	 * @param array<string, string[]> $keep    Per built-in field, WooCommerce keys to leave as they are.
	 * @return array<string, mixed>
	 */
	private function apply( string $section, array $fields, array $keep ): array {
		$ours   = $this->field_set()->section( $section );
		$names  = array_column( $ours, 'name' );
		$result = array();

		foreach ( $fields as $name => $definition ) {
			if ( ! in_array( (string) $name, $names, true ) ) {
				$result[ $name ] = $definition;
			}
		}

		foreach ( $ours as $index => $field ) {
			$name = $field['name'];
			if ( ! $field['enabled'] ) {
				continue;
			}

			if ( ! $field['builtIn'] ) {
				$result[ $name ] = $this->mapper()->custom( $field, FieldSet::priority( $index ) );
			} elseif ( isset( $fields[ $name ] ) && is_array( $fields[ $name ] ) ) {
				$result[ $name ] = $this->mapper()->overlay( $fields[ $name ], $field, FieldSet::priority( $index ), isset( $keep[ $name ] ) ? $keep[ $name ] : array() );
			}
		}

		if ( function_exists( 'wc_checkout_fields_uasort_comparison' ) ) {
			uasort( $result, 'wc_checkout_fields_uasort_comparison' );
		}

		return $result;
	}

	/**
	 * What the store owner changed on each built-in field of a section,
	 * compared with the default field set (see LocaleOverrides::changed()).
	 *
	 * @param string $section Section name.
	 * @return array<string, string[]> WooCommerce keys changed, keyed by field name.
	 */
	private function changed( string $section ): array {
		$ours      = $this->field_set()->section( $section );
		$defaults  = $this->domain->field_sets( CheckoutType::CLASSIC )->defaults()['sections'];
		$defaults  = array_column( isset( $defaults[ $section ] ) ? $defaults[ $section ] : array(), null, 'name' );
		$reordered = LocaleOverrides::reordered( $ours, array_keys( $defaults ) );

		$changed = array();
		foreach ( $ours as $field ) {
			if ( $field['builtIn'] ) {
				$changed[ $field['name'] ] = $this->locale()->changed( $field, isset( $defaults[ $field['name'] ] ) ? $defaults[ $field['name'] ] : null, $reordered );
			}
		}

		return $changed;
	}

	/**
	 * Where the Billing and Shipping sections apply.
	 *
	 * @return AddressScope
	 */
	private function scope(): AddressScope {
		return new AddressScope( $this->domain->active_checkout_type() );
	}

	/**
	 * A field name without its section prefix, as WooCommerce's country
	 * locale keys it (billing_city → city).
	 *
	 * @param string $section Section name.
	 * @param string $name    Field name.
	 * @return string
	 */
	private static function unprefixed( string $section, string $name ): string {
		$prefix = $section . '_';

		return 0 === strpos( $name, $prefix ) ? substr( $name, strlen( $prefix ) ) : $name;
	}

	/**
	 * The classic field set.
	 *
	 * @return FieldSet
	 */
	private function field_set(): FieldSet {
		if ( null === $this->field_set ) {
			$this->field_set = new FieldSet( $this->domain->field_sets( CheckoutType::CLASSIC ) );
		}

		return $this->field_set;
	}

	/**
	 * The field mapper.
	 *
	 * @return WooCommerceFields
	 */
	private function mapper(): WooCommerceFields {
		if ( null === $this->mapper ) {
			$this->mapper = new WooCommerceFields( $this->domain->field_types() );
		}

		return $this->mapper;
	}

	/**
	 * The locale rules.
	 *
	 * @return LocaleOverrides
	 */
	private function locale(): LocaleOverrides {
		if ( null === $this->locale ) {
			$this->locale = new LocaleOverrides( $this->domain->settings() );
		}

		return $this->locale;
	}
}
