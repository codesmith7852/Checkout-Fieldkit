<?php
/**
 * Tests for showing inner-block values after a Block checkout order.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Block;

use CheckoutFieldKit\Block\OrderValueDisplay;
use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Plugin;
use CheckoutFieldKit\Tests\Support\StoreApiCheckout;
use CheckoutFieldKit\Tests\TestCase;
use WC_Meta_Box_Order_Data;
use WC_Order;

/**
 * Spec §5, third bullet: values of the Contact and Additional inner blocks
 * are shown as in §4.2, in emails (showInEmails), on the thank-you and My
 * Account order pages and on the admin order screen under the general
 * details (showOnOrderPages). Address fields are shown by WooCommerce itself.
 */
class OrderValueDisplayTest extends TestCase {

	use StoreApiCheckout;

	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_default_country', 'GB' );
	}

	public function tear_down() {
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Rebuild Store API routes with the next schema.
		parent::tear_down();
	}

	/**
	 * Saves Contact, Address and Additional fields and places a Block
	 * checkout order with values for them.
	 *
	 * @return WC_Order
	 */
	private function place_block_order() {
		$repository = ( new Domain() )->field_sets( 'block' );
		$set        = $repository->get();

		$set['sections']['contact'][]    = array(
			'name'  => 'nickname',
			'type'  => 'text',
			'label' => 'Nickname',
		);
		$set['sections']['contact'][]    = array(
			'name'         => 'pages_only',
			'type'         => 'text',
			'label'        => 'Pages only',
			'showInEmails' => false,
		);
		$set['sections']['address'][]    = array(
			'name'  => 'vat',
			'type'  => 'text',
			'label' => 'VAT number',
		);
		$set['sections']['additional'][] = array(
			'name'  => 'gift_note',
			'type'  => 'textarea',
			'label' => 'Gift note',
		);
		$set['sections']['additional'][] = array(
			'name'    => 'wrap',
			'type'    => 'radio',
			'label'   => 'Wrapping',
			'options' => array(
				array(
					'value' => 'plain',
					'label' => 'Plain paper',
				),
				array(
					'value' => 'gold',
					'label' => 'Gold & silver',
				),
			),
		);
		$set['sections']['additional'][] = array(
			'name'             => 'emails_only',
			'type'             => 'checkbox',
			'label'            => 'Emails only',
			'showOnOrderPages' => false,
		);
		$this->assertTrue( $repository->save( $set ) );
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Routes with the new schema.

		$response = $this->store_api_checkout(
			$this->billing_address(),
			array(
				'extensions' => array(
					'checkout-fieldkit' => array(
						'nickname'    => 'Ada',
						'pages_only'  => 'P-1',
						'gift_note'   => "Happy birthday,\nlove",
						'wrap'        => 'gold',
						'emails_only' => true,
					),
				),
			)
		);
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );

		$order = wc_get_order( $response->get_data()['order_id'] );
		// Where WooCommerce keeps an Address field's value; it shows that one itself.
		$order->update_meta_data( '_wc_billing/checkout-fieldkit/vat', 'GB-VAT-1' );
		$order->save();

		return wc_get_order( $order->get_id() );
	}

	/**
	 * An email's content for an order.
	 *
	 * @param string   $email_class WC_Email class name.
	 * @param WC_Order $order       Order.
	 * @param string   $type        'html' or 'plain'.
	 * @return string
	 */
	private function email_content( $email_class, WC_Order $order, $type ) {
		$instance = new \ReflectionProperty( \WC_Emails::class, 'instance' );
		if ( PHP_VERSION_ID < 80100 ) {
			$instance->setAccessible( true );
		}
		$instance->setValue( null, null );

		$email             = WC()->mailer()->emails[ $email_class ];
		$email->object     = $order;
		$email->email_type = $type;

		return $email->get_content();
	}

	/**
	 * Output of an order page action.
	 *
	 * @param string   $action 'woocommerce_thankyou' or 'woocommerce_view_order'.
	 * @param WC_Order $order  Order.
	 * @return string
	 */
	private function order_page( $action, WC_Order $order ) {
		ob_start();
		do_action( $action, $order->get_id() );

		return ob_get_clean();
	}

	/**
	 * Our values table in an order page's output.
	 *
	 * @param string $html Page output.
	 * @return string
	 */
	private function our_table( $html ) {
		$this->assertSame( 1, preg_match( '#<section class="cfk-order-values">.*?</section>#s', $html, $match ), 'One values table.' );

		return $match[0];
	}

	public function test_the_plugin_registers_the_service() {
		$this->assertInstanceOf( OrderValueDisplay::class, Plugin::instance()->get( OrderValueDisplay::class ) );
	}

	/**
	 * @dataProvider data_order_storage
	 *
	 * @param bool $hpos Whether orders use HPOS.
	 */
	public function test_the_thank_you_and_my_account_pages_show_the_values( $hpos ) {
		$this->use_hpos( $hpos );
		$order = $this->place_block_order();

		foreach ( array( 'woocommerce_thankyou', 'woocommerce_view_order' ) as $action ) {
			$table = $this->our_table( $this->order_page( $action, $order ) );

			$this->assertStringContainsString( '<tr><th scope="row">Nickname:</th><td>Ada</td></tr>', $table, $action );
			$this->assertStringContainsString( '<tr><th scope="row">Pages only:</th><td>P-1</td></tr>', $table, $action );
			$this->assertStringContainsString( "<tr><th scope=\"row\">Gift note:</th><td>Happy birthday,<br />\nlove</td></tr>", $table, $action );
			$this->assertStringContainsString( '<tr><th scope="row">Wrapping:</th><td>Gold &amp; silver</td></tr>', $table, $action );
			$this->assertStringNotContainsString( 'Emails only', $table, 'showOnOrderPages off.' );
			$this->assertStringNotContainsString( 'VAT', $table, 'Address fields are left to WooCommerce.' );
		}
	}

	/**
	 * @dataProvider data_emails
	 *
	 * @param string $email_class WC_Email class name.
	 */
	public function test_emails_list_the_values( $email_class ) {
		$order = $this->place_block_order();

		$html  = $this->email_content( $email_class, $order, 'html' );
		$plain = $this->email_content( $email_class, $order, 'plain' );

		$this->assertStringContainsString( '<strong>Nickname:</strong> Ada</p>', $html );
		$this->assertStringContainsString( "<strong>Gift note:</strong> Happy birthday,<br />\nlove</p>", $html );
		$this->assertStringContainsString( '<strong>Wrapping:</strong> Gold &amp; silver</p>', $html );
		$this->assertStringContainsString( '<strong>Emails only:</strong> Yes</p>', $html );
		$this->assertStringNotContainsString( 'Pages only', $html, 'showInEmails off.' );
		$this->assertStringNotContainsString( '<strong>VAT number:</strong>', $html, 'Not in our order meta list.' );

		$this->assertStringContainsString( 'Nickname: Ada', $plain );
		$this->assertStringContainsString( 'Wrapping: Gold & silver', $plain );
		$this->assertStringNotContainsString( 'Pages only', $plain );
	}

	public function data_emails() {
		return array(
			'admin new order'     => array( 'WC_Email_New_Order' ),
			'customer processing' => array( 'WC_Email_Customer_Processing_Order' ),
		);
	}

	public function test_the_admin_order_screen_shows_the_values_under_the_general_details() {
		$order = $this->place_block_order();
		include_once WC_ABSPATH . 'includes/admin/wc-meta-box-functions.php';
		include_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php';
		$this->log_in_as( 'administrator' );

		// WooCommerce keeps the order shown in the global $theorder; start and end without one.
		unset( $GLOBALS['theorder'] );
		ob_start();
		WC_Meta_Box_Order_Data::output( $order );
		$html = ob_get_clean();
		unset( $GLOBALS['theorder'] );

		$this->assertSame( 1, preg_match( '#<div class="cfk-admin-order-values cfk-admin-order-values--general">(.*?)</div>#s', $html, $general ), 'One block of values.' );
		$this->assertStringContainsString( '<p><strong>Nickname:</strong> Ada</p>', $general[1] );
		$this->assertStringContainsString( '<p><strong>Wrapping:</strong> Gold &amp; silver</p>', $general[1] );
		$this->assertStringNotContainsString( 'Emails only', $general[1] );
		$this->assertStringNotContainsString( 'VAT', $general[1] );
		$this->assertStringNotContainsString( 'cfk-admin-order-values--billing', $html );
	}

	public function test_classic_orders_are_left_to_the_classic_field_set() {
		$this->place_block_order();
		$order = $this->create_order();
		$order->update_meta_data( 'nickname', 'Classic Ada' );
		$order->save();

		$this->assertStringNotContainsString( 'Classic Ada', $this->order_page( 'woocommerce_thankyou', $order ) );
	}
}
