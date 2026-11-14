<?php
/**
 * Our Contact and Additional inner blocks in WooCommerce's Checkout block.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Block;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Environment\BlockSupport;
use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Puts the two inner blocks of spec §5 into the Block checkout:
 *
 * - `checkout-fieldkit/contact-fields`, inside WooCommerce's Contact
 *   information block, after its own fields;
 * - `checkout-fieldkit/additional-fields`, in the checkout form right after
 *   WooCommerce's Additional information block (so before the order notes).
 *
 * How they reach Checkout pages that were never edited:
 *
 * - Contact: the block type is locked against removal (`lock.remove` by
 *   default) and its parent is an inner block area of WooCommerce, which makes
 *   it a "forced" block: WooCommerce's checkout renders it inside the Contact
 *   information block when the page does not contain it, and the editor adds
 *   it there when the page is opened.
 * - Additional: WooCommerce would put a forced block of the checkout form's
 *   area at its end, after Place order, so it is not forced in the editor.
 *   Instead, add_additional_block() adds its placeholder to the rendered
 *   Checkout block (as WooCommerce itself adds blocks missing from older
 *   pages) unless the page already has it, and the editor script inserts it at
 *   the same place when the page is opened.
 *
 * Also registers the scripts with the Checkout block (CheckoutIntegration),
 * the block types on the server (so WooCommerce adds the `data-block-name`
 * its frontend needs when a page saved with them is rendered), and enqueues
 * our style with the Checkout block's scripts.
 *
 * Nothing is hooked when the Block checkout features are not available.
 */
final class CheckoutBlocks implements Service {

	/**
	 * Name of the Contact inner block.
	 */
	public const CONTACT_BLOCK = 'checkout-fieldkit/contact-fields';

	/**
	 * Name of the Additional inner block.
	 */
	public const ADDITIONAL_BLOCK = 'checkout-fieldkit/additional-fields';

	/**
	 * Where the Additional block goes when a page lacks it: after the first of
	 * these blocks found ('after'), or else before the first found ('before').
	 */
	private const ADDITIONAL_PLACES = array(
		array( 'after', 'woocommerce/checkout-additional-information-block' ),
		array( 'before', 'woocommerce/checkout-order-note-block' ),
		array( 'before', 'woocommerce/checkout-terms-block' ),
		array( 'before', 'woocommerce/checkout-actions-block' ),
	);

	/**
	 * Field set, types and environment.
	 *
	 * @var Domain
	 */
	private $domain;

	/**
	 * The plugin's main file.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Whether Block features can run; null to ask the domain.
	 *
	 * @var BlockSupport|null
	 */
	private $support;

	/**
	 * Constructor.
	 *
	 * @param Domain            $domain      Field set, types and environment.
	 * @param string            $plugin_file The plugin's main file (scripts are in the build folder next to it).
	 * @param BlockSupport|null $support     Whether Block features can run; defaults to the domain's.
	 */
	public function __construct( Domain $domain, string $plugin_file, ?BlockSupport $support = null ) {
		$this->domain      = $domain;
		$this->plugin_file = $plugin_file;
		$this->support     = $support;
	}

	/**
	 * Hooks into the Checkout block's registration and rendering.
	 */
	public function register_hooks(): void {
		if ( ! $this->support()->is_available() ) {
			return;
		}

		add_action( 'woocommerce_blocks_checkout_block_registration', array( $this, 'register_integration' ) );
		add_action( 'init', array( $this, 'register_block_types' ), 5 );
		add_filter( 'render_block_woocommerce/checkout', array( $this, 'add_additional_block' ) );
		add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_after', array( $this, 'enqueue_style' ) );
	}

	/**
	 * Registers our scripts and data with the Checkout block.
	 *
	 * @param \Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry $registry The Checkout block's integrations.
	 */
	public function register_integration( $registry ): void {
		if ( ! is_object( $registry ) || ! method_exists( $registry, 'register' ) ) {
			return;
		}

		$registry->register( new CheckoutIntegration( $this->fields(), $this->plugin_file ) );
	}

	/**
	 * Registers the two block types from their block.json (the built copy, or
	 * the source when the plugin has not been built).
	 */
	public function register_block_types(): void {
		foreach ( array( 'contact-fields', 'additional-fields' ) as $block ) {
			$built = dirname( $this->plugin_file ) . '/build/checkout/blocks/' . $block;
			$dir   = is_readable( $built . '/block.json' ) ? $built : dirname( $this->plugin_file ) . '/src/checkout/blocks/' . $block;
			if ( is_readable( $dir . '/block.json' ) && ! \WP_Block_Type_Registry::get_instance()->is_registered( 'checkout-fieldkit/' . $block ) ) {
				register_block_type( $dir );
			}
		}
	}

	/**
	 * Adds the Additional block to a rendered Checkout block that lacks it.
	 *
	 * @param string|mixed $content Rendered Checkout block.
	 * @return string|mixed
	 */
	public function add_additional_block( $content ) {
		if ( ! is_string( $content ) || false !== strpos( $content, 'data-block-name="' . self::ADDITIONAL_BLOCK . '"' ) ) {
			return $content;
		}

		$placeholder = '<div data-block-name="' . self::ADDITIONAL_BLOCK . '" class="wp-block-checkout-fieldkit-additional-fields"></div>';

		foreach ( self::ADDITIONAL_PLACES as list( $where, $block ) ) {
			$element = '<div[^<>]*\bdata-block-name="' . preg_quote( $block, '#' ) . '"[^<>]*>';
			if ( 'after' === $where ) {
				$result = preg_replace( '#' . $element . '\s*</div>#', '$0' . $placeholder, $content, 1, $count );
			} else {
				// The opening tag of the block, from the "<div" that starts it.
				$result = preg_replace( '#' . $element . '#', $placeholder . '$0', $content, 1, $count );
			}

			if ( $count > 0 && is_string( $result ) ) {
				return $result;
			}
		}

		return $content;
	}

	/**
	 * Enqueues our style along with the Checkout block's scripts.
	 */
	public function enqueue_style(): void {
		if ( wp_style_is( CheckoutIntegration::FRONTEND_HANDLE, 'registered' ) ) {
			wp_enqueue_style( CheckoutIntegration::FRONTEND_HANDLE );
		}
	}

	/**
	 * The fields of our inner blocks.
	 *
	 * @return InnerBlockFields
	 */
	private function fields(): InnerBlockFields {
		return new InnerBlockFields( $this->domain->field_sets( CheckoutType::BLOCK ), $this->domain->field_types() );
	}

	/**
	 * Whether Block features can run.
	 *
	 * @return BlockSupport
	 */
	private function support(): BlockSupport {
		return null !== $this->support ? $this->support : $this->domain->block_support();
	}
}
