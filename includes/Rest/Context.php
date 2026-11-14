<?php
/**
 * What the admin app needs to know that is not stored data.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Rest;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Fields\CheckoutPage;
use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Fields\Field;
use CheckoutFieldKit\Fields\FieldSetValidator;

defined( 'ABSPATH' ) || exit;

/**
 * The body of GET /context (spec §7): the store's checkout, and the field
 * model the admin mirrors (sections, field types, validations, essential
 * fields and the rules for new fields). Every key is listed in to_array().
 */
final class Context {

	/**
	 * Lowest WooCommerce version with the block checkout features the plugin
	 * uses (additional checkout fields API), spec §5.
	 */
	public const BLOCK_MINIMUM_WOOCOMMERCE_VERSION = '8.9';

	/**
	 * Domain objects.
	 *
	 * @var Domain
	 */
	private $domain;

	/**
	 * The store's Checkout page.
	 *
	 * @var CheckoutPage
	 */
	private $checkout_page;

	/**
	 * Constructor.
	 *
	 * @param Domain       $domain        Domain objects.
	 * @param CheckoutPage $checkout_page The store's Checkout page.
	 */
	public function __construct( Domain $domain, CheckoutPage $checkout_page ) {
		$this->domain        = $domain;
		$this->checkout_page = $checkout_page;
	}

	/**
	 * Whether a WooCommerce version has the block checkout features the plugin needs.
	 *
	 * @param string $woocommerce_version WooCommerce version.
	 * @return bool
	 */
	public static function block_supported( string $woocommerce_version ): bool {
		return version_compare( $woocommerce_version, self::BLOCK_MINIMUM_WOOCOMMERCE_VERSION, '>=' );
	}

	/**
	 * The context, ready to serialise.
	 *
	 * Keys:
	 *
	 *     activeCheckoutType       'classic'|'block'|'unknown'  What the Checkout page uses.
	 *     checkoutPageUrl          string|null                  Null without a usable Checkout page.
	 *     blockSupported           bool                         WooCommerce 8.9 or newer.
	 *     wooCommerceVersion       string
	 *     sections                 {classic: [{name, label}], block: [...]}, in display order.
	 *     fieldTypes               {classic: [type definition], block: [...]}, see FieldTypes.
	 *     validations              {classic: [{name, label}], block: [...]}
	 *     essentialFields          {classic: [{name, consequence}], block: [...]}
	 *     builtInEditableSettings  {classic: {field name: [field key]}, block: {...}}; position is always editable.
	 *     reservedNames            string[]  Names a new custom field may not use.
	 *     fieldNamePattern         string    Rule for field names, as a JavaScript RegExp source.
	 *     fieldDefaults            {classic: {field key: default}, block: {...}}, for a new field.
	 *     headingLevels            string[]
	 *     defaultSettings          The settings before any save (GET /settings shape).
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$woocommerce_version = defined( 'WC_VERSION' ) ? (string) WC_VERSION : '';

		$editable = array();
		$defaults = array();
		foreach ( CheckoutType::all() as $checkout_type ) {
			$editable[ $checkout_type ] = array();
			foreach ( $this->domain->defaults()->names( $checkout_type ) as $name ) {
				$editable[ $checkout_type ][ $name ] = $this->domain->defaults()->editable_settings( $checkout_type, $name );
			}
			$defaults[ $checkout_type ] = Field::defaults( $checkout_type );
		}

		$validations = array();
		foreach ( CheckoutType::all() as $checkout_type ) {
			$validations[ $checkout_type ] = $this->domain->field_types()->validations( $checkout_type );
		}

		return array(
			'activeCheckoutType'      => $this->checkout_page->active_checkout_type(),
			'checkoutPageUrl'         => $this->checkout_page->url(),
			'blockSupported'          => self::block_supported( $woocommerce_version ),
			'wooCommerceVersion'      => $woocommerce_version,
			'sections'                => $this->domain->sections()->describe(),
			'fieldTypes'              => $this->domain->field_types()->describe(),
			'validations'             => $validations,
			'essentialFields'         => $this->domain->essential_fields()->describe(),
			'builtInEditableSettings' => $editable,
			'reservedNames'           => $this->domain->defaults()->reserved_names(),
			'fieldNamePattern'        => trim( FieldSetValidator::NAME_PATTERN, '/' ),
			'fieldDefaults'           => $defaults,
			'headingLevels'           => Field::HEADING_LEVELS,
			'defaultSettings'         => $this->domain->settings()->defaults(),
		);
	}
}
