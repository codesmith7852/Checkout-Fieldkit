<?php
/**
 * Admin notice shown when the plugin cannot load.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit;

defined( 'ABSPATH' ) || exit;

/**
 * Tells people who manage plugins why Checkout FieldKit is not running.
 */
final class RequirementsNotice implements Service {

	/**
	 * The failed requirements.
	 *
	 * @var Requirements
	 */
	private $requirements;

	/**
	 * Constructor.
	 *
	 * @param Requirements $requirements The failed requirements.
	 */
	public function __construct( Requirements $requirements ) {
		$this->requirements = $requirements;
	}

	/**
	 * Adds the notice to the admin screens.
	 */
	public function register_hooks(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Prints the notice.
	 */
	public function render(): void {
		$problem = $this->requirements->problem();

		if ( '' === $problem || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $problem ) );
	}
}
