<?php
/**
 * Tests for showing classic checkout values after the order is placed.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Orders;

use CheckoutFieldKit\Plugin;
use CheckoutFieldKit\Tests\Support\ClassicOrders;
use CheckoutFieldKit\Tests\TestCase;
use WC_Meta_Box_Order_Data;
use WC_Order;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Tests fire WooCommerce's hooks.

/**
 * Spec §4.2: custom field values in emails (showInEmails), on the thank-you
 * page, the My Account order view and the admin order screen
 * (showOnOrderPages), for fields still in the field set.
 */
class ValueDisplayTest extends TestCase {

	use ClassicOrders;

	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_default_country', 'GB' );
		WC()->countries->locale = array();
	}

	public function tear_down() {
		$_POST                  = array();
		WC()->countries->locale = array();
		parent::tear_down();
	}

	/**
	 * Places an order with values in every section.
	 *
	 * @return WC_Order
	 */
	private function place_order_with_values() {
		$this->save_classic_fields();

		return $this->place_classic_order(
			array(
				'billing_vat'               => 'GB 123',
				'billing_newsletter'        => '1',
				'billing_topics'            => array( 'web', 'friend' ),
				'invoice_ref'               => 'INV-9',
				'ship_to_different_address' => '1',
				'shipping_first_name'       => 'Charles',
				'shipping_last_name'        => 'Babbage',
				'shipping_address_1'        => '2 Engine Row',
				'shipping_city'             => 'London',
				'shipping_postcode'         => 'SW1A 2AA',
				'shipping_country'          => 'GB',
				'shipping_gate_code'        => '4321',
				'delivery_floor'            => '0',
				'gift_message'              => "Happy birthday,\nlove from Ada & Bob",
				'heard_from'                => 'web',
				'interests'                 => array( 'post', 'friend' ),
				'gift_wrap'                 => '1',
				'guests'                    => '0',
				'internal_code'             => 'X-1',
				'email_only'                => 'E-1',
			)
		);
	}

	/**
	 * An email's content for an order.
	 *
	 * @param string   $email_class WC_Email class name, for example 'WC_Email_New_Order'.
	 * @param WC_Order $order       Order.
	 * @param string   $type        'html' or 'plain'.
	 * @return string
	 */
	private function email_content( $email_class, WC_Order $order, $type ) {
		// WC_Emails adds its template hooks once, when created; the test suite
		// removes hooks added during earlier tests, so start a fresh one.
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
	 * The thank-you page's output for an order.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function thank_you_page( WC_Order $order ) {
		ob_start();
		do_action( 'woocommerce_thankyou', $order->get_id() );

		return ob_get_clean();
	}

	/**
	 * The My Account order view's output for an order.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function my_account_order( WC_Order $order ) {
		ob_start();
		do_action( 'woocommerce_view_order', $order->get_id() );

		return ob_get_clean();
	}

	/**
	 * The admin order screen's order data box for an order.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function admin_order_data( WC_Order $order ) {
		include_once WC_ABSPATH . 'includes/admin/wc-meta-box-functions.php';
		include_once WC_ABSPATH . 'includes/admin/wc-admin-functions.php';
		$this->log_in_as( 'administrator' );

		ob_start();
		WC_Meta_Box_Order_Data::output( $order );

		return ob_get_clean();
	}

	/**
	 * @dataProvider data_emails
	 *
	 * @param string $email_class WC_Email class name.
	 * @param bool   $hpos        Whether HPOS is on.
	 */
	public function test_html_emails_list_values_in_the_order_meta( $email_class, $hpos ) {
		$this->use_hpos( $hpos );
		$order = $this->place_order_with_values();

		$html = $this->email_content( $email_class, $order, 'html' );

		$this->assertStringContainsString( '<strong>VAT number:</strong> GB 123</p>', $html );
		$this->assertStringContainsString( '<strong>Newsletter:</strong> Yes</p>', $html );
		$this->assertStringContainsString( '<strong>Topics:</strong> A friend &amp; family, Web &quot;shop&quot;</p>', $html );
		$this->assertStringContainsString( '<strong>Invoice reference:</strong> INV-9</p>', $html );
		$this->assertStringContainsString( '<strong>Gate code:</strong> 4321</p>', $html );
		$this->assertStringContainsString( '<strong>Floor:</strong> <span>0</span></p>', $html, 'WooCommerce skips a value of "0" unless it is wrapped.' );
		$this->assertStringContainsString( "<strong>Gift message:</strong> Happy birthday,<br />\nlove from Ada &amp; Bob</p>", $html );
		$this->assertStringContainsString( '<strong>Heard from:</strong> Web &quot;shop&quot;</p>', $html );
		$this->assertStringContainsString( '<strong>Interests:</strong> By post, A friend &amp; family</p>', $html );
		$this->assertStringContainsString( '<strong>Gift wrap:</strong> Yes</p>', $html );
		$this->assertStringContainsString( '<strong>Guests:</strong> <span>0</span></p>', $html );
		$this->assertStringContainsString( '<strong>Email only:</strong> E-1</p>', $html );
		$this->assertStringNotContainsString( 'Internal code', $html, 'showInEmails off.' );
		$this->assertStringNotContainsString( 'X-1', $html );
	}

	/**
	 * @dataProvider data_emails
	 *
	 * @param string $email_class WC_Email class name.
	 * @param bool   $hpos        Whether HPOS is on.
	 */
	public function test_plain_text_emails_list_values_in_the_order_meta( $email_class, $hpos ) {
		$this->use_hpos( $hpos );
		$order = $this->place_order_with_values();

		$plain = $this->email_content( $email_class, $order, 'plain' );

		$this->assertStringContainsString( "VAT number: GB 123\n", $plain );
		$this->assertStringContainsString( "Newsletter: Yes\n", $plain );
		$this->assertStringContainsString( 'Topics: A friend & family, Web "shop"' . "\n", $plain );
		$this->assertStringContainsString( "Floor: 0\n", $plain );
		$this->assertStringContainsString( "Gift message: Happy birthday,\nlove from Ada & Bob\n", $plain );
		$this->assertStringContainsString( 'Heard from: Web "shop"' . "\n", $plain );
		$this->assertStringContainsString( "Gift wrap: Yes\n", $plain );
		$this->assertStringContainsString( "Email only: E-1\n", $plain );
		$this->assertStringNotContainsString( 'Internal code', $plain, 'showInEmails off.' );
		$this->assertStringNotContainsString( '<br', $plain );
	}

	public function data_emails() {
		return array(
			'admin new order, HPOS on'            => array( 'WC_Email_New_Order', true ),
			'admin new order, HPOS off'           => array( 'WC_Email_New_Order', false ),
			'customer processing order, HPOS on'  => array( 'WC_Email_Customer_Processing_Order', true ),
			'customer processing order, HPOS off' => array( 'WC_Email_Customer_Processing_Order', false ),
		);
	}

	/**
	 * @dataProvider data_order_storage
	 *
	 * @param bool $hpos Whether HPOS is on.
	 */
	public function test_the_thank_you_page_shows_a_table_after_the_order_details( $hpos ) {
		$this->use_hpos( $hpos );
		$order = $this->place_order_with_values();

		$html = $this->thank_you_page( $order );

		$this->assert_order_page_table( $html );
	}

	/**
	 * @dataProvider data_order_storage
	 *
	 * @param bool $hpos Whether HPOS is on.
	 */
	public function test_the_my_account_order_view_shows_a_table_after_the_order_details( $hpos ) {
		$this->use_hpos( $hpos );
		$order = $this->place_order_with_values();

		$html = $this->my_account_order( $order );

		$this->assert_order_page_table( $html );
	}

	/**
	 * Checks the values table of the thank-you page and My Account.
	 *
	 * @param string $html Page output.
	 */
	private function assert_order_page_table( $html ) {
		$table_at   = strpos( $html, 'cfk-order-values' );
		$details_at = strpos( $html, 'woocommerce-table--order-details' );
		$this->assertNotFalse( $table_at, 'The values table is shown.' );
		$this->assertGreaterThan( $details_at, $table_at, 'After the order details table.' );
		$this->assertSame( 1, substr_count( $html, '<table class="woocommerce-table woocommerce-table--cfk-order-values shop_table cfk-order-values__table">' ) );

		$this->assertStringContainsString( '<th scope="row">VAT number:</th><td>GB 123</td>', $html );
		$this->assertStringContainsString( '<th scope="row">Topics:</th><td>A friend &amp; family, Web &quot;shop&quot;</td>', $html );
		$this->assertStringContainsString( '<th scope="row">Gate code:</th><td>4321</td>', $html );
		$this->assertStringContainsString( '<th scope="row">Floor:</th><td>0</td>', $html );
		$this->assertStringContainsString( "<th scope=\"row\">Gift message:</th><td>Happy birthday,<br />\nlove from Ada &amp; Bob</td>", $html );
		$this->assertStringContainsString( '<th scope="row">Heard from:</th><td>Web &quot;shop&quot;</td>', $html );
		$this->assertStringContainsString( '<th scope="row">Gift wrap:</th><td>Yes</td>', $html );
		$this->assertStringContainsString( '<th scope="row">Internal code:</th><td>X-1</td>', $html );
		$this->assertStringNotContainsString( 'Email only', $html, 'showOnOrderPages off.' );

		$this->assertLessThan( strpos( $html, 'Gate code' ), strpos( $html, 'VAT number' ), 'In field set order.' );
		$this->assertLessThan( strpos( $html, 'Gift message' ), strpos( $html, 'Gate code' ), 'In field set order.' );
	}

	/**
	 * @dataProvider data_order_storage
	 *
	 * @param bool $hpos Whether HPOS is on.
	 */
	public function test_the_admin_order_screen_shows_values_by_section( $hpos ) {
		$this->use_hpos( $hpos );
		$order = $this->place_order_with_values();

		$html = $this->admin_order_data( $order );

		$billing  = $this->between( $html, 'order_data_column_billing', 'order_data_column_shipping' );
		$shipping = $this->between( $html, 'order_data_column_shipping', '<div class="clear"></div>' );
		$general  = $this->between( $html, 'order_data_column_general', 'order_data_column_billing' );

		$this->assertStringContainsString( '<p><strong>VAT number:</strong> GB 123</p>', $billing );
		$this->assertStringContainsString( '<p><strong>Newsletter:</strong> Yes</p>', $billing );
		$this->assertStringContainsString( '<p><strong>Invoice reference:</strong> INV-9</p>', $billing );
		$this->assertStringNotContainsString( 'Gate code', $billing );

		$this->assertStringContainsString( '<p><strong>Gate code:</strong> 4321</p>', $shipping );
		$this->assertStringContainsString( '<p><strong>Floor:</strong> 0</p>', $shipping );
		$this->assertStringNotContainsString( 'VAT number', $shipping );

		$this->assertStringContainsString( "<p><strong>Gift message:</strong> Happy birthday,<br />\nlove from Ada &amp; Bob</p>", $general );
		$this->assertStringContainsString( '<p><strong>Interests:</strong> By post, A friend &amp; family</p>', $general );
		$this->assertStringContainsString( '<p><strong>Internal code:</strong> X-1</p>', $general );
		$this->assertStringNotContainsString( 'Email only', $html, 'showOnOrderPages off.' );
		$this->assertStringNotContainsString( 'VAT number', $general );
	}

	/**
	 * The part of a string between two markers.
	 *
	 * @param string $haystack String.
	 * @param string $from     Start marker.
	 * @param string $to       End marker, searched after the start.
	 * @return string
	 */
	private function between( $haystack, $from, $to ) {
		$start = strpos( $haystack, $from );
		$this->assertNotFalse( $start, "Marker $from" );
		$end = strpos( $haystack, $to, $start + strlen( $from ) );
		$this->assertNotFalse( $end, "Marker $to" );

		return substr( $haystack, $start, $end - $start );
	}

	public function test_nothing_is_shown_with_both_flags_off() {
		$this->save_classic_fields(
			array(
				'additional' => array(
					array(
						'name'             => 'quiet',
						'type'             => 'text',
						'label'            => 'Quiet',
						'showInEmails'     => false,
						'showOnOrderPages' => false,
					),
				),
			)
		);
		$order = $this->place_classic_order( array( 'quiet' => 'shh' ) );
		$this->assertSame( 'shh', $order->get_meta( 'quiet' ), 'Still saved.' );

		$this->assertStringNotContainsString( 'Quiet', $this->email_content( 'WC_Email_New_Order', $order, 'html' ) );
		$this->assertStringNotContainsString( 'Quiet', $this->email_content( 'WC_Email_Customer_Processing_Order', $order, 'plain' ) );
		$this->assertStringNotContainsString( 'cfk-order-values', $this->thank_you_page( $order ) );
		$this->assertStringNotContainsString( 'cfk-order-values', $this->my_account_order( $order ) );
		$this->assertStringNotContainsString( 'cfk-admin-order-values', $this->admin_order_data( $order ) );
	}

	public function test_values_of_fields_no_longer_in_the_field_set_are_not_shown() {
		$order = $this->place_order_with_values();

		$repository = Plugin::instance()->domain()->field_sets( 'classic' );
		$set        = $repository->get();
		foreach ( $set['sections'] as $section => $fields ) {
			$set['sections'][ $section ] = array_values(
				array_filter(
					$fields,
					static function ( $field ) {
						return ! in_array( $field['name'], array( 'heard_from', 'billing_vat' ), true );
					}
				)
			);
		}
		$this->assertTrue( $repository->save( $set ) );

		$this->assertSame( 'web', $order->get_meta( 'heard_from' ), 'The value stays on the order.' );
		foreach ( array(
			$this->email_content( 'WC_Email_New_Order', $order, 'html' ),
			$this->thank_you_page( $order ),
			$this->my_account_order( $order ),
			$this->admin_order_data( $order ),
		) as $html ) {
			$this->assertStringNotContainsString( 'Heard from', $html );
			$this->assertStringNotContainsString( 'VAT number', $html );
			$this->assertStringContainsString( 'Gift wrap', $html );
		}
	}

	public function test_a_disabled_field_still_in_the_field_set_keeps_showing_old_answers() {
		$order = $this->place_order_with_values();

		$repository = Plugin::instance()->domain()->field_sets( 'classic' );
		$set        = $repository->get();
		foreach ( $set['sections']['additional'] as $index => $field ) {
			if ( 'gift_wrap' === $field['name'] ) {
				$set['sections']['additional'][ $index ]['enabled'] = false;
			}
		}
		$this->assertTrue( $repository->save( $set ) );

		$this->assertStringContainsString( '<th scope="row">Gift wrap:</th><td>Yes</td>', $this->thank_you_page( $order ) );
	}

	public function test_block_checkout_orders_are_left_to_the_block_checkout() {
		$this->save_classic_fields();
		$order = $this->create_order(
			array(
				'created_via' => 'store-api',
				'meta'        => array( 'nickname' => 'Countess' ),
			)
		);

		$this->assertStringNotContainsString( 'Countess', $this->email_content( 'WC_Email_New_Order', $order, 'html' ) );
		$this->assertStringNotContainsString( 'cfk-order-values', $this->thank_you_page( $order ) );
		$this->assertStringNotContainsString( 'Countess', $this->admin_order_data( $order ) );
	}

	public function test_the_display_value_filter_changes_every_location() {
		$order = $this->place_order_with_values();
		add_filter(
			'checkout_fieldkit_display_value',
			static function ( $formatted, $value, $field ) {
				return 'guests' === $field['name'] ? 'none' : $formatted;
			},
			10,
			3
		);

		$this->assertStringContainsString( '<strong>Guests:</strong> none</p>', $this->email_content( 'WC_Email_New_Order', $order, 'html' ) );
		$this->assertStringContainsString( "Guests: none\n", $this->email_content( 'WC_Email_New_Order', $order, 'plain' ) );
		$this->assertStringContainsString( '<th scope="row">Guests:</th><td>none</td>', $this->thank_you_page( $order ) );
		$this->assertStringContainsString( '<p><strong>Guests:</strong> none</p>', $this->admin_order_data( $order ) );
	}
}
