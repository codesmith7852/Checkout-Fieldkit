<?php
/**
 * Tests for rendering classic custom fields.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Classic;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM properties.

use CheckoutFieldKit\Classic\FieldRenderer;
use CheckoutFieldKit\Plugin;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Spec §4: every custom field renders inside WooCommerce's wrapper markup
 * (a .form-row with id "<name>_field" and data-priority), with an accessible
 * label, WooCommerce's required/optional marker, and aria-describedby for a
 * description. Types WooCommerce cannot render are rendered by the plugin.
 */
class FieldRendererTest extends ClassicTestCase {

	/**
	 * Types that show content and collect nothing.
	 *
	 * @var string[]
	 */
	private const DISPLAY = array( 'heading', 'paragraph' );

	/**
	 * Adds a custom field to Additional and renders it as the checkout does.
	 *
	 * @param array      $field Field.
	 * @param mixed|null $value Value to render with.
	 * @param array      $args  Extra arguments for woocommerce_form_field().
	 * @return string HTML.
	 */
	private function render( array $field, $value = null, array $args = array() ) {
		$this->add_fields( 'additional', array( $field ) );
		$fields = ( new \WC_Checkout() )->get_checkout_fields( 'order' );
		$this->assertArrayHasKey( $field['name'], $fields );

		return woocommerce_form_field( $field['name'], array_merge( $fields[ $field['name'] ], $args, array( 'return' => true ) ), $value );
	}

	/**
	 * Parses an HTML fragment the way a browser would, as far as libxml can.
	 *
	 * @param string $html Fragment.
	 * @return DOMXPath
	 */
	private function parse( $html ) {
		$document = new DOMDocument();
		libxml_use_internal_errors( true );
		$document->loadHTML( '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>' );
		libxml_clear_errors();

		return new DOMXPath( $document );
	}

	/**
	 * The form controls a shopper can use.
	 *
	 * @param DOMXPath $xpath Document.
	 * @param string   $scope XPath prefix.
	 * @return DOMElement[]
	 */
	private function controls( DOMXPath $xpath, $scope = '//body' ) {
		$controls = array();
		foreach ( $xpath->query( $scope . '//input[not(@type="hidden")] | ' . $scope . '//select | ' . $scope . '//textarea' ) as $node ) {
			$controls[] = $node;
		}

		return $controls;
	}

	/**
	 * Whether a control has an accessible name from a label or legend.
	 *
	 * @param DOMXPath   $xpath   Document.
	 * @param DOMElement $control Control.
	 * @return bool
	 */
	private function is_labelled( DOMXPath $xpath, DOMElement $control ) {
		$id = $control->getAttribute( 'id' );
		if ( '' !== $id && $xpath->query( '//label[@for="' . $id . '"][normalize-space(.) != ""]' )->length ) {
			return true;
		}
		if ( $xpath->query( 'ancestor::label[normalize-space(.) != ""]', $control )->length ) {
			return true;
		}
		if ( '' !== $control->getAttribute( 'aria-labelledby' ) || '' !== $control->getAttribute( 'aria-label' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @dataProvider data_classic_types
	 *
	 * @param string $type Field type.
	 */
	public function test_every_type_renders_valid_labelled_html_inside_the_woocommerce_wrapper( $type ) {
		$name     = $this->name_for( $type );
		$required = ! in_array( $type, array( 'hidden', 'heading', 'paragraph' ), true );
		$html     = $this->render( $this->custom( $name, $type, array( 'required' => $required ) ) );
		$xpath    = $this->parse( $html );

		$wrapper = $xpath->query( '//*[@id="' . $name . '_field"]' );
		$this->assertSame( 1, $wrapper->length, "One wrapper for $type: $html" );
		$wrapper = $wrapper->item( 0 );
		$this->assertContains( 'form-row', explode( ' ', $wrapper->getAttribute( 'class' ) ) );
		$this->assertSame( '20', $wrapper->getAttribute( 'data-priority' ) );

		// Everything the field outputs stays inside its wrapper once parsed, which
		// fails when a block element sits inside a <p> or tags are unbalanced.
		$this->assertSame( 1, $xpath->query( '//body/*' )->length, "Only the wrapper at the top level for $type: $html" );
		$this->assertSame( count( $this->controls( $xpath ) ), count( $this->controls( $xpath, '//*[@id="' . $name . '_field"]' ) ) );
		if ( 'p' === $wrapper->nodeName ) {
			$this->assertSame( 0, $xpath->query( './/div|.//p|.//fieldset|.//h2|.//h3|.//h4|.//h5|.//h6|.//ul', $wrapper )->length, "Only phrasing content in a <p> for $type." );
		}

		// Ids are unique.
		$ids = array();
		foreach ( $xpath->query( '//*[@id]' ) as $node ) {
			$ids[] = $node->getAttribute( 'id' );
		}
		$this->assertSame( array_unique( $ids ), $ids );

		$label = ucfirst( str_replace( '_', ' ', $name ) );
		if ( in_array( $type, self::DISPLAY, true ) ) {
			$this->assertStringContainsString( $label, $wrapper->textContent );
			$this->assertSame( 0, $xpath->query( '//span[@class="required"] | //span[@class="optional"]' )->length );
			return;
		}

		if ( 'hidden' === $type ) {
			$this->assertSame( 1, $xpath->query( '//input[@type="hidden"][@name="' . $name . '"]' )->length );
			$this->assertSame( 0, $xpath->query( '//label' )->length, 'The label of a hidden field is for the admin only.' );
			$this->assertNotSame( '', $wrapper->getAttribute( 'hidden' ) . $wrapper->getAttribute( 'style' ) );
			return;
		}

		foreach ( $this->controls( $xpath ) as $control ) {
			$this->assertTrue( $this->is_labelled( $xpath, $control ), "Every control of $type has a label: $html" );
			if ( in_array( $control->getAttribute( 'type' ), array( 'date', 'time', 'datetime-local', 'month', 'week', 'checkbox', 'radio' ), true ) ) {
				$this->assertFalse( $control->hasAttribute( 'placeholder' ), "No placeholder attribute on a $type input." );
			}
		}

		$group       = in_array( $type, array( 'radio', 'checkboxgroup' ), true );
		$name_holder = $group ? $xpath->query( '//fieldset/legend' ) : $xpath->query( '//label[contains(normalize-space(.), "' . $label . '")]' );
		$this->assertSame( 1, $name_holder->length, "The field label names the field for $type: $html" );
		$this->assertStringContainsString( $label, $name_holder->item( 0 )->textContent );
		$this->assertSame( 1, $xpath->query( './/span[@class="required"][@aria-hidden="true"]', $name_holder->item( 0 ) )->length, "Required marker for $type." );
	}

	/**
	 * @dataProvider data_classic_types
	 *
	 * @param string $type Field type.
	 */
	public function test_an_optional_field_shows_the_optional_marker( $type ) {
		if ( in_array( $type, array( 'hidden', 'heading', 'paragraph' ), true ) ) {
			$this->assertTrue( true, 'Not a field a shopper fills in.' );
			return;
		}

		$xpath = $this->parse( $this->render( $this->custom( $this->name_for( $type ), $type, array( 'required' => false ) ) ) );

		$this->assertSame( 1, $xpath->query( '//span[@class="optional"]' )->length );
		$this->assertSame( 0, $xpath->query( '//span[@class="required"]' )->length );
	}

	/**
	 * @dataProvider data_classic_types
	 *
	 * @param string $type Field type.
	 */
	public function test_a_description_is_linked_with_aria_describedby( $type ) {
		if ( in_array( $type, array( 'hidden', 'heading', 'paragraph' ), true ) ) {
			$this->assertTrue( true, 'Not a field a shopper fills in.' );
			return;
		}

		$name  = $this->name_for( $type );
		$xpath = $this->parse( $this->render( $this->custom( $name, $type ), null, array( 'description' => 'Tell us more.' ) ) );

		$description = $xpath->query( '//*[@id="' . $name . '-description"]' );
		$this->assertSame( 1, $description->length );
		$this->assertSame( 'Tell us more.', $description->item( 0 )->textContent );

		$described = $xpath->query( '//*[@aria-describedby="' . $name . '-description"]' );
		$this->assertSame( 1, $described->length );
		$expected = in_array( $type, array( 'radio', 'checkboxgroup' ), true ) ? array( 'fieldset' ) : array( 'input', 'select', 'textarea' );
		$this->assertContains( $described->item( 0 )->nodeName, $expected, 'The control itself, or its group, is described.' );
	}

	public function test_radio_buttons_are_a_labelled_group() {
		$xpath = $this->parse( $this->render( $this->custom( 'how_found', 'radio', array( 'required' => true ) ), 'pear' ) );

		$fieldset = $xpath->query( '//fieldset[@role="radiogroup"]' );
		$this->assertSame( 1, $fieldset->length );
		$this->assertSame( 'true', $fieldset->item( 0 )->getAttribute( 'aria-required' ) );
		$this->assertSame( 2, $xpath->query( '//input[@type="radio"][@name="how_found"]' )->length );
		$this->assertSame( 0, $xpath->query( '//input[@aria-required]' )->length, 'aria-required belongs to the group.' );
		$this->assertSame( 1, $xpath->query( '//input[@type="radio"][@value="pear"][@checked]' )->length );
		$this->assertSame( 1, $xpath->query( '//label[normalize-space(.)="Apple"]' )->length, 'Option labels carry no marker.' );
	}

	public function test_a_checkbox_group_posts_a_list_and_ticks_the_saved_values() {
		$html  = $this->render( $this->custom( 'toppings', 'checkboxgroup', array( 'required' => true ) ), 'apple,pear' );
		$xpath = $this->parse( $html );

		$this->assertSame( 2, $xpath->query( '//input[@type="checkbox"][@name="toppings[]"][@checked]' )->length );
		$this->assertNotContains( 'validate-required', explode( ' ', $xpath->query( '//*[@id="toppings_field"]' )->item( 0 )->getAttribute( 'class' ) ), 'WooCommerce\'s script would check each box on its own.' );
	}

	public function test_a_checkbox_group_accepts_an_array_value_after_a_failed_submit() {
		$xpath = $this->parse( $this->render( $this->custom( 'toppings', 'checkboxgroup' ), array( 'pear' ) ) );

		$this->assertSame( 1, $xpath->query( '//input[@checked]' )->length );
		$this->assertSame( 1, $xpath->query( '//input[@value="pear"][@checked]' )->length );
	}

	public function test_a_multiselect_posts_a_list_and_selects_the_default_values() {
		$this->add_fields(
			'additional',
			array(
				$this->custom(
					'fruits',
					'multiselect',
					array(
						'default'     => 'apple,pear',
						'placeholder' => 'Pick some',
					)
				),
			)
		);
		$fields = ( new \WC_Checkout() )->get_checkout_fields( 'order' );
		$xpath  = $this->parse( woocommerce_form_field( 'fruits', array_merge( $fields['fruits'], array( 'return' => true ) ) ) );

		$select = $xpath->query( '//select[@name="fruits[]"][@multiple]' );
		$this->assertSame( 1, $select->length );
		$this->assertSame( 'Pick some', $select->item( 0 )->getAttribute( 'data-placeholder' ) );
		$this->assertSame( 2, $xpath->query( '//option[@selected]' )->length );
	}

	public function test_a_heading_uses_its_heading_level() {
		$xpath = $this->parse(
			$this->render(
				$this->custom(
					'delivery_heading',
					'heading',
					array(
						'label'        => 'Delivery',
						'headingLevel' => 'h4',
					)
				)
			)
		);

		$this->assertSame( 'Delivery', $xpath->query( '//h4' )->item( 0 )->textContent );
	}

	public function test_a_paragraph_keeps_its_line_breaks_and_escapes_markup() {
		$html = $this->render( $this->custom( 'delivery_note', 'paragraph', array( 'label' => "We deliver on weekdays.\nCall us <b>first</b>." ) ) );

		$this->assertStringContainsString( 'We deliver on weekdays.<br />', $html );
		$this->assertStringNotContainsString( '<b>', $html );
	}

	public function test_a_hidden_field_posts_its_default() {
		$xpath = $this->parse( $this->render( $this->custom( 'campaign', 'hidden', array( 'default' => 'spring' ) ) ) );

		$this->assertSame( 'spring', $xpath->query( '//input[@name="campaign"]' )->item( 0 )->getAttribute( 'value' ) );
	}

	public function test_date_type_inputs_keep_their_input_type() {
		foreach ( array( 'date', 'time', 'datetime-local', 'month', 'week', 'url' ) as $type ) {
			$xpath = $this->parse( $this->render( $this->custom( $this->name_for( $type ), $type ), 'x' ) );

			$this->assertSame( 1, $xpath->query( '//input[@type="' . $type . '"][contains(@class, "input-text")][@value="x"]' )->length, $type );
		}
	}

	public function test_fields_of_other_plugins_are_left_to_woocommerce() {
		$html = woocommerce_form_field(
			'partner_site',
			array(
				'type'   => 'url',
				'label'  => 'Partner site',
				'return' => true,
			)
		);

		$this->assertStringContainsString( 'placeholder=""', $html, 'WooCommerce\'s own markup.' );
		$this->assertSame(
			'',
			woocommerce_form_field(
				'partner_list',
				array(
					'type'   => 'checkboxgroup',
					'return' => true,
				)
			)
		);
	}

	public function test_the_render_filter_can_change_any_of_our_fields() {
		$seen   = array();
		$filter = static function ( $html, $field, $args, $value ) use ( &$seen ) {
			$seen[] = array( $field['name'], $args['type'], $value );
			return $html . '<!-- changed -->';
		};
		add_filter( 'checkout_fieldkit_render_field_classic', $filter, 10, 4 );

		$custom   = $this->render( $this->custom( 'fruits', 'checkboxgroup' ), 'apple' );
		$built_in = woocommerce_form_field( 'billing_city', array_merge( WC()->countries->get_address_fields( 'GB', 'billing_' )['billing_city'], array( 'return' => true ) ), 'Leeds' );
		$other    = woocommerce_form_field( 'partner_site', array( 'return' => true ) );

		remove_filter( 'checkout_fieldkit_render_field_classic', $filter, 10 );

		$this->assertStringEndsWith( '<!-- changed -->', $custom );
		$this->assertStringEndsWith( '<!-- changed -->', $built_in );
		$this->assertStringEndsNotWith( '<!-- changed -->', $other );
		$this->assertSame(
			array(
				array( 'fruits', 'checkboxgroup', 'apple' ),
				array( 'billing_city', 'text', 'Leeds' ),
			),
			$seen
		);
	}

	public function test_the_service_is_registered() {
		$this->assertInstanceOf( FieldRenderer::class, Plugin::instance()->get( FieldRenderer::class ) );
	}

	public function test_on_a_block_store_address_fields_are_left_to_woocommerce() {
		$this->use_checkout_page( '<!-- wp:woocommerce/checkout /-->' );
		$filter = static function ( $html ) {
			return $html . '<!-- changed -->';
		};
		add_filter( 'checkout_fieldkit_render_field_classic', $filter );

		$html = woocommerce_form_field( 'billing_city', array( 'return' => true ), 'Leeds' );

		remove_filter( 'checkout_fieldkit_render_field_classic', $filter );
		$this->assertStringEndsNotWith( '<!-- changed -->', $html );
	}
}
