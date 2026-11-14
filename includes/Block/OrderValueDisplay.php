<?php
/**
 * Shows Block checkout inner-block values after the order is placed.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Block;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Orders\FieldValues;
use CheckoutFieldKit\Orders\ValueDisplay;
use CheckoutFieldKit\Service;

defined( 'ABSPATH' ) || exit;

/**
 * The display locations of spec §4.2 for orders placed on the Block checkout
 * (spec §5, third bullet): Orders\ValueDisplay, registered for the Block
 * field set. It shows the values we saved as order meta `<name>`, that is the
 * Contact and Additional fields, on the admin order screen under the general
 * details. Address fields are stored and shown by WooCommerce itself under
 * other keys, so they never appear twice.
 *
 * A service of its own because the plugin keeps one service per class, and
 * the classic field set already has its ValueDisplay.
 */
final class OrderValueDisplay implements Service {

	/**
	 * Field set and types.
	 *
	 * @var Domain
	 */
	private $domain;

	/**
	 * Constructor.
	 *
	 * @param Domain $domain Field set and types.
	 */
	public function __construct( Domain $domain ) {
		$this->domain = $domain;
	}

	/**
	 * Adds the display hooks for the Block field set.
	 */
	public function register_hooks(): void {
		( new ValueDisplay( $this->domain->field_sets( CheckoutType::BLOCK ), new FieldValues( $this->domain->field_types() ) ) )->register_hooks();
	}
}
