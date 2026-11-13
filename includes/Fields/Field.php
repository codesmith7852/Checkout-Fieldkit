<?php
/**
 * The keys of a field and their defaults.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A field is a plain associative array (spec §2.2). This class names its keys
 * and their default values; FieldNormalizer turns any input into that shape.
 */
final class Field {

	/**
	 * Every key of a field with its default value, in a fixed order.
	 *
	 * @var array<string, mixed>
	 */
	public const DEFAULTS = array(
		'name'             => '',
		'type'             => 'text',
		'builtIn'          => false,
		'enabled'          => true,
		'required'         => false,
		'label'            => '',
		'placeholder'      => '',
		'default'          => '',
		'options'          => array(),
		'validation'       => array(),
		'classes'          => array(),
		'headingLevel'     => 'h3',
		'showInEmails'     => true,
		'showOnOrderPages' => true,
	);

	/**
	 * Heading levels a heading field may use.
	 *
	 * @var string[]
	 */
	public const HEADING_LEVELS = array( 'h2', 'h3', 'h4', 'h5', 'h6' );

	/**
	 * The keys of a field on a checkout type and their defaults, including
	 * keys added by extensions.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @return array<string, mixed>
	 */
	public static function defaults( string $checkout_type ): array {
		/**
		 * Filters the keys a field has and their default values.
		 *
		 * Add a key here to keep it when a field set is saved: keys not listed
		 * are dropped. A value whose PHP type differs from the default's is
		 * replaced by the default; check anything finer with
		 * checkout_fieldkit_validate_field. The plugin's own keys cannot be
		 * removed or changed.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, mixed> $defaults      Field keys and their default values.
		 * @param string               $checkout_type 'classic' or 'block'.
		 */
		$filtered = apply_filters( 'checkout_fieldkit_field_defaults', self::DEFAULTS, $checkout_type );

		// The plugin's own keys come first, in their order and with their defaults.
		return self::DEFAULTS + ( is_array( $filtered ) ? $filtered : array() );
	}
}
