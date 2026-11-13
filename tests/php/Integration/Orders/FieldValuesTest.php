<?php
/**
 * Tests for reading and formatting stored field values.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Orders;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Orders\FieldValues;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Spec §4.2: option labels for choice fields, Yes for a ticked checkbox,
 * line breaks kept for textarea; HTML escaped, plain text left as text.
 */
class FieldValuesTest extends TestCase {

	/**
	 * Component under test.
	 *
	 * @var FieldValues
	 */
	private $values;

	public function set_up() {
		parent::set_up();
		$this->values = new FieldValues( ( new Domain() )->field_types() );
	}

	/**
	 * A normalised custom field.
	 *
	 * @param string $name  Field name.
	 * @param string $type  Field type.
	 * @param array  $extra Other keys.
	 * @return array
	 */
	private function field( $name, $type, array $extra = array() ) {
		return array_merge(
			array(
				'name'             => $name,
				'type'             => $type,
				'builtIn'          => false,
				'label'            => ucfirst( str_replace( '_', ' ', $name ) ),
				'options'          => array(
					array(
						'value' => 'a',
						'label' => 'Apple & pear',
					),
					array(
						'value' => 'b',
						'label' => 'Banana',
					),
					array(
						'value' => 'c',
						'label' => 'Cherry',
					),
				),
				'showInEmails'     => true,
				'showOnOrderPages' => true,
			),
			$extra
		);
	}

	public function test_where_a_value_is_stored() {
		$this->assertSame( '_billing_vat', FieldValues::meta_key( 'billing_vat', 'classic' ) );
		$this->assertSame( '_shipping_gate', FieldValues::meta_key( 'shipping_gate', 'classic' ) );
		$this->assertSame( 'gift_note', FieldValues::meta_key( 'gift_note', 'classic' ) );
		$this->assertSame( 'billingvat', FieldValues::meta_key( 'billingvat', 'classic' ) );
		$this->assertSame( 'billing_vat', FieldValues::meta_key( 'billing_vat', 'block' ), 'Block fields are stored under their name.' );
	}

	/**
	 * @dataProvider data_order_storage
	 *
	 * @param bool $hpos Whether HPOS is on.
	 */
	public function test_reads_the_stored_value_from_where_it_lives( $hpos ) {
		$this->use_hpos( $hpos );
		$order = $this->create_order(
			array(
				'meta' => array(
					'_billing_vat' => 'GB123',
					'gift_note'    => "Hi\nthere",
					'zero'         => '0',
				),
			)
		);

		$this->assertSame( 'GB123', $this->values->stored( $order, $this->field( 'billing_vat', 'text' ), 'classic' ) );
		$this->assertSame( "Hi\nthere", $this->values->stored( $order, $this->field( 'gift_note', 'textarea' ), 'classic' ) );
		$this->assertSame( '0', $this->values->stored( $order, $this->field( 'zero', 'text' ), 'classic' ) );
		$this->assertSame( '', $this->values->stored( $order, $this->field( 'missing', 'text' ), 'classic' ) );
	}

	/**
	 * @dataProvider data_formats
	 *
	 * @param string $type  Field type.
	 * @param string $value Stored value.
	 * @param string $html  Expected HTML.
	 * @param string $plain Expected plain text.
	 */
	public function test_formats_by_type( $type, $value, $html, $plain ) {
		$field = $this->field( 'answer', $type );

		$this->assertSame( $html, $this->values->format( $field, $value, 'classic', FieldValues::HTML ) );
		$this->assertSame( $plain, $this->values->format( $field, $value, 'classic', FieldValues::PLAIN ) );
	}

	public function data_formats() {
		return array(
			'text is escaped for HTML'          => array( 'text', 'Fish & <chips>', 'Fish &amp; &lt;chips&gt;', 'Fish & <chips>' ),
			'"0"'                               => array( 'number', '0', '0', '0' ),
			'textarea keeps line breaks'        => array( 'textarea', "One\nTwo & three", "One<br />\nTwo &amp; three", "One\nTwo & three" ),
			'select shows the option label'     => array( 'select', 'a', 'Apple &amp; pear', 'Apple & pear' ),
			'radio shows the option label'      => array( 'radio', 'b', 'Banana', 'Banana' ),
			'an option no longer offered'       => array( 'select', 'z', 'z', 'z' ),
			'multiselect lists option labels'   => array( 'multiselect', 'a,c', 'Apple &amp; pear, Cherry', 'Apple & pear, Cherry' ),
			'checkboxgroup lists option labels' => array( 'checkboxgroup', 'b, c', 'Banana, Cherry', 'Banana, Cherry' ),
			'ticked checkbox'                   => array( 'checkbox', '1', 'Yes', 'Yes' ),
			'unticked checkbox'                 => array( 'checkbox', '', '', '' ),
			'empty'                             => array( 'text', '', '', '' ),
			'email'                             => array( 'email', 'ada@example.org', 'ada@example.org', 'ada@example.org' ),
			'date'                              => array( 'date', '2026-11-13', '2026-11-13', '2026-11-13' ),
		);
	}

	public function test_the_display_value_filter_has_the_last_word() {
		$order = $this->create_order();
		$seen  = null;
		add_filter(
			'checkout_fieldkit_display_value',
			static function ( $formatted, $value, $field, $format, $checkout_type, $filtered_order ) use ( &$seen ) {
				$seen = array( $formatted, $value, $field['name'], $format, $checkout_type, $filtered_order ? $filtered_order->get_id() : null );

				return '<em>' . $formatted . '</em>';
			},
			10,
			6
		);

		$result = $this->values->format( $this->field( 'answer', 'select' ), 'b', 'block', FieldValues::HTML, $order );

		$this->assertSame( '<em>Banana</em>', $result );
		$this->assertSame( array( 'Banana', 'b', 'answer', 'html', 'block', $order->get_id() ), $seen );
	}

	public function test_entries_list_custom_fields_with_a_value_in_the_given_order() {
		$order  = $this->create_order(
			array(
				'meta' => array(
					'_billing_vat' => 'GB<1>',
					'gift_wrap'    => '1',
					'fruit'        => 'a,b',
					'_billing_x'   => 'built-in value',
				),
			)
		);
		$fields = array(
			$this->field( 'gift_wrap', 'checkbox', array( 'label' => 'Wrap <it>' ) ),
			$this->field( 'billing_x', 'text', array( 'builtIn' => true ) ),
			$this->field( 'notes_heading', 'heading' ),
			$this->field( 'empty_one', 'text' ),
			$this->field( 'billing_vat', 'text', array( 'label' => '' ) ),
			$this->field( 'fruit', 'checkboxgroup' ),
		);

		$this->assertSame(
			array(
				array(
					'name'  => 'gift_wrap',
					'label' => 'Wrap &lt;it&gt;',
					'value' => 'Yes',
				),
				array(
					'name'  => 'billing_vat',
					'label' => 'billing_vat',
					'value' => 'GB&lt;1&gt;',
				),
				array(
					'name'  => 'fruit',
					'label' => 'Fruit',
					'value' => 'Apple &amp; pear, Banana',
				),
			),
			$this->values->entries( $order, $fields, 'classic', FieldValues::HTML )
		);

		$plain = $this->values->entries( $order, $fields, 'classic', FieldValues::PLAIN );
		$this->assertSame( array( 'Wrap <it>', 'Yes' ), array( $plain[0]['label'], $plain[0]['value'] ) );
		$this->assertSame( 'GB<1>', $plain[1]['value'] );
	}

	public function test_the_checkout_type_an_order_was_placed_on() {
		$this->assertSame( 'classic', FieldValues::checkout_type_of( $this->create_order( array( 'created_via' => 'checkout' ) ) ) );
		$this->assertSame( 'classic', FieldValues::checkout_type_of( $this->create_order( array( 'created_via' => 'admin' ) ) ) );
		$this->assertSame( 'block', FieldValues::checkout_type_of( $this->create_order( array( 'created_via' => 'store-api' ) ) ) );
	}
}
