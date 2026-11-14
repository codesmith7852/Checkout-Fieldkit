<?php
/**
 * Renders the classic custom field types WooCommerce cannot render well.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Classic;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Fields\CheckoutType;
use CheckoutFieldKit\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §4. Custom fields of these types are rendered by the plugin through
 * woocommerce_form_field_{type}, inside WooCommerce's wrapper markup (a
 * .form-row with id "<id>_field" and data-priority), with WooCommerce's
 * required/optional marker, a label for every control, and aria-describedby
 * for a description:
 *
 * - multiselect (posts name[]), checkboxgroup (posts name[]) and radio, which
 *   WooCommerce either cannot render or renders without a group label;
 * - checkbox, so a description describes the input rather than its label;
 * - date, time, datetime-local, month and week, without the placeholder
 *   attribute HTML does not allow on them, and url;
 * - hidden, without a visible label (its label is for the admin only);
 * - heading (at its headingLevel) and paragraph, which collect nothing.
 *
 * Groups and display fields use a <div> wrapper, as a <p> cannot hold them.
 * Fields of other plugins are left to WooCommerce. Every field of the classic
 * field set, built-in or custom, then goes through
 * checkout_fieldkit_render_field_classic.
 */
final class FieldRenderer implements Service {

	/**
	 * Types the plugin renders for custom fields.
	 *
	 * @var string[]
	 */
	public const TYPES = array( 'multiselect', 'checkboxgroup', 'radio', 'checkbox', 'date', 'time', 'datetime-local', 'month', 'week', 'url', 'hidden', 'heading', 'paragraph' );

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
	 * Constructor.
	 *
	 * @param Domain $domain Domain objects.
	 */
	public function __construct( Domain $domain ) {
		$this->domain = $domain;
	}

	/**
	 * Adds the per-type renderers and the general render filter.
	 */
	public function register_hooks(): void {
		foreach ( self::TYPES as $type ) {
			add_filter( 'woocommerce_form_field_' . $type, array( $this, 'render_custom_field' ), 10, 4 );
		}
		add_filter( 'woocommerce_form_field', array( $this, 'filter_field_html' ), 10, 4 );
	}

	/**
	 * Renders a custom field of one of TYPES; anything else is returned as given.
	 *
	 * @param mixed $html  WooCommerce's HTML.
	 * @param mixed $key   Field key.
	 * @param mixed $args  Field arguments after WooCommerce's processing.
	 * @param mixed $value Field value.
	 * @return mixed
	 */
	public function render_custom_field( $html, $key, $args, $value ) {
		if ( ! is_string( $key ) || ! is_array( $args ) || ! isset( $args['type'] ) ) {
			return $html;
		}

		$found = $this->field_set()->find( $key );
		if ( null === $found || $found['field']['builtIn'] || $found['field']['type'] !== $args['type'] ) {
			return $html;
		}

		return $this->render( $found['field'], $key, $args, $value );
	}

	/**
	 * Passes the HTML of every field of the classic field set through
	 * checkout_fieldkit_render_field_classic.
	 *
	 * @param mixed $html  Field HTML.
	 * @param mixed $key   Field key.
	 * @param mixed $args  Field arguments.
	 * @param mixed $value Field value.
	 * @return mixed
	 */
	public function filter_field_html( $html, $key, $args, $value ) {
		if ( ! is_string( $key ) ) {
			return $html;
		}

		$found = $this->field_set()->find( $key );
		if ( null === $found ) {
			return $html;
		}

		/**
		 * Filters the HTML of a field of the classic field set on the classic
		 * checkout and My Account's address form.
		 *
		 * Runs for built-in and custom fields after WooCommerce or the plugin
		 * rendered them. A type added through checkout_fieldkit_field_types
		 * arrives with empty HTML and can be rendered here.
		 *
		 * @since 0.1.0
		 *
		 * @param string               $html  Field HTML.
		 * @param array<string, mixed> $field The field, as stored in the classic field set.
		 * @param array<string, mixed> $args  Arguments of woocommerce_form_field() after WooCommerce's processing.
		 * @param mixed                $value The value the field is shown with.
		 */
		return apply_filters( 'checkout_fieldkit_render_field_classic', $html, $found['field'], is_array( $args ) ? $args : array(), $value );
	}

	/**
	 * HTML of a custom field.
	 *
	 * @param array<string, mixed> $field Our field.
	 * @param string               $key   Field key (its input name).
	 * @param array<string, mixed> $args  Field arguments after WooCommerce's processing.
	 * @param mixed                $value Field value.
	 * @return string
	 */
	private function render( array $field, string $key, array $args, $value ): string {
		$args = $this->complete( $args, $key );

		switch ( $args['type'] ) {
			case 'heading':
				$level = in_array( $field['headingLevel'], array( 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $field['headingLevel'] : 'h3';
				return $this->wrap( 'div', $args, sprintf( '<%1$s class="cfk-heading">%2$s</%1$s>', $level, esc_html( $args['label'] ) ) );

			case 'paragraph':
				return $this->wrap( 'div', $args, '<p class="cfk-paragraph">' . nl2br( esc_html( $args['label'] ) ) . '</p>' );

			case 'hidden':
				return $this->wrap(
					'div',
					$args,
					sprintf(
						'<input type="hidden" class="input-hidden %1$s" name="%2$s" id="%3$s" value="%4$s" />',
						esc_attr( implode( ' ', $args['input_class'] ) ),
						esc_attr( $key ),
						esc_attr( $args['id'] ),
						esc_attr( self::scalar( $value ) )
					),
					' hidden="hidden" style="display:none"'
				);

			case 'radio':
			case 'checkboxgroup':
				return $this->render_group( $key, $args, $value );

			case 'multiselect':
				return $this->render_multiselect( $key, $args, $value );

			case 'checkbox':
				return $this->render_checkbox( $key, $args, $value );

			default:
				return $this->render_input( $key, $args, $value );
		}
	}

	/**
	 * A date-type or url input.
	 *
	 * @param string               $key   Field key.
	 * @param array<string, mixed> $args  Field arguments.
	 * @param mixed                $value Field value.
	 * @return string
	 */
	private function render_input( string $key, array $args, $value ): string {
		$placeholder = 'url' === $args['type'] && '' !== (string) $args['placeholder']
			? ' placeholder="' . esc_attr( $args['placeholder'] ) . '"'
			: '';

		$input = sprintf(
			'<input type="%1$s" class="input-text %2$s" name="%3$s" id="%4$s"%5$s value="%6$s"%7$s />',
			esc_attr( $args['type'] ),
			esc_attr( implode( ' ', $args['input_class'] ) ),
			esc_attr( $key ),
			esc_attr( $args['id'] ),
			$placeholder,
			esc_attr( self::scalar( $value ) ),
			self::attributes( $args['custom_attributes'] )
		);

		return $this->wrap( 'p', $args, $this->label( $args ) . '<span class="woocommerce-input-wrapper">' . $input . $this->description( $args ) . '</span>' );
	}

	/**
	 * A single checkbox; the label wraps the input, as WooCommerce does it.
	 *
	 * @param string               $key   Field key.
	 * @param array<string, mixed> $args  Field arguments.
	 * @param mixed                $value Field value.
	 * @return string
	 */
	private function render_checkbox( string $key, array $args, $value ): string {
		$attributes = $args['custom_attributes'];
		if ( $args['required'] ) {
			$attributes['aria-required'] = 'true';
		}

		$unchecked = null === $args['unchecked_value']
			? ''
			: sprintf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( $key ), esc_attr( (string) $args['unchecked_value'] ) );

		$checkbox = sprintf(
			'<input type="checkbox" class="input-checkbox %1$s" name="%2$s" id="%3$s" value="%4$s"%5$s%6$s />',
			esc_attr( implode( ' ', $args['input_class'] ) ),
			esc_attr( $key ),
			esc_attr( $args['id'] ),
			esc_attr( (string) $args['checked_value'] ),
			checked( self::scalar( $value ), (string) $args['checked_value'], false ),
			self::attributes( $attributes )
		);

		$label = sprintf(
			'<label class="checkbox %1$s">%2$s%3$s %4$s%5$s</label>',
			esc_attr( implode( ' ', $args['label_class'] ) ),
			$unchecked,
			$checkbox,
			esc_html( $args['label'] ),
			$this->marker( $args )
		);

		return $this->wrap( 'p', $args, '<span class="woocommerce-input-wrapper">' . $label . $this->description( $args ) . '</span>' );
	}

	/**
	 * A multi-select dropdown, posting name[].
	 *
	 * @param string               $key   Field key.
	 * @param array<string, mixed> $args  Field arguments.
	 * @param mixed                $value Field value.
	 * @return string
	 */
	private function render_multiselect( string $key, array $args, $value ): string {
		$selected = self::values( $value );
		$options  = '';
		foreach ( (array) $args['options'] as $option_value => $option_label ) {
			$option_value = (string) $option_value;
			if ( '' === $option_value ) {
				continue;
			}
			$options .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $option_value ),
				in_array( $option_value, $selected, true ) ? ' selected="selected"' : '',
				esc_html( (string) $option_label )
			);
		}

		$select = sprintf(
			'<select name="%1$s[]" id="%2$s" class="select cfk-enhanced-select %3$s" multiple="multiple" data-placeholder="%4$s"%5$s>%6$s</select>',
			esc_attr( $key ),
			esc_attr( $args['id'] ),
			esc_attr( implode( ' ', $args['input_class'] ) ),
			esc_attr( (string) $args['placeholder'] ),
			self::attributes( $args['custom_attributes'] ),
			$options
		);

		return $this->wrap( 'p', $args, $this->label( $args ) . '<span class="woocommerce-input-wrapper">' . $select . $this->description( $args ) . '</span>' );
	}

	/**
	 * Radio buttons or a checkbox group: a fieldset whose legend is the field
	 * label, each option with its own label. Required state and description
	 * belong to the group, not to each option.
	 *
	 * @param string               $key   Field key.
	 * @param array<string, mixed> $args  Field arguments.
	 * @param mixed                $value Field value.
	 * @return string
	 */
	private function render_group( string $key, array $args, $value ): string {
		$is_radio   = 'radio' === $args['type'];
		$attributes = $args['custom_attributes'];
		unset( $attributes['aria-required'], $attributes['aria-describedby'] );

		$group_attributes = array();
		if ( $is_radio ) {
			$group_attributes['role'] = 'radiogroup';
			if ( $args['required'] ) {
				$group_attributes['aria-required'] = 'true';
			}
		}
		if ( '' !== (string) $args['description'] ) {
			$group_attributes['aria-describedby'] = $args['id'] . '-description';
		}

		$selected = $is_radio ? array( self::scalar( $value ) ) : self::values( $value );
		$choices  = '';
		$position = 0;
		foreach ( (array) $args['options'] as $option_value => $option_label ) {
			$option_value = (string) $option_value;
			if ( '' === $option_value ) {
				continue;
			}
			$id       = $args['id'] . '_' . $position;
			$choices .= sprintf(
				'<span class="cfk-choice"><input type="%1$s" class="%2$s %3$s" name="%4$s" id="%5$s" value="%6$s"%7$s%8$s /> <label for="%5$s" class="%9$s">%10$s</label></span>',
				$is_radio ? 'radio' : 'checkbox',
				$is_radio ? 'input-radio' : 'input-checkbox',
				esc_attr( implode( ' ', $args['input_class'] ) ),
				esc_attr( $is_radio ? $key : $key . '[]' ),
				esc_attr( $id ),
				esc_attr( $option_value ),
				in_array( $option_value, $selected, true ) ? ' checked="checked"' : '',
				self::attributes( $attributes ),
				$is_radio ? 'radio' : 'checkbox',
				esc_html( (string) $option_label )
			);
			++$position;
		}

		// Assistive technology gets the required state from aria-required on a
		// radio group; a checkbox group has no such attribute, so it says so.
		$spoken = ! $is_radio && $args['required']
			? ' <span class="screen-reader-text">' . esc_html__( '(required)', 'checkout-fieldkit' ) . '</span>'
			: '';

		$fieldset = sprintf(
			'<fieldset class="cfk-fieldset"%1$s><legend class="cfk-legend %2$s">%3$s%4$s%5$s</legend><span class="woocommerce-input-wrapper">%6$s%7$s</span></fieldset>',
			self::attributes( $group_attributes ),
			esc_attr( implode( ' ', $args['label_class'] ) ),
			esc_html( $args['label'] ),
			$this->marker( $args ),
			$spoken,
			$choices,
			$this->description( $args )
		);

		if ( ! $is_radio ) {
			// WooCommerce's checkout script would check each box on its own and
			// flag the unticked ones; the server checks the group as a whole.
			$args['class'] = array_values( array_diff( $args['class'], array( 'validate-required' ) ) );
		}

		return $this->wrap( 'div', $args, $fieldset );
	}

	/**
	 * WooCommerce's wrapper around a field.
	 *
	 * @param string               $tag   'p' for one control, 'div' for groups and display fields.
	 * @param array<string, mixed> $args  Field arguments.
	 * @param string               $inner Content.
	 * @param string               $extra Extra attributes, escaped, with a leading space.
	 * @return string
	 */
	private function wrap( string $tag, array $args, string $inner, string $extra = '' ): string {
		$classes = array_merge( array( 'form-row' ), $args['class'], array( 'cfk-field-' . $args['type'] ) );

		return sprintf(
			'<%1$s class="%2$s" id="%3$s_field" data-priority="%4$s"%5$s>%6$s</%1$s>',
			$tag,
			esc_attr( implode( ' ', array_unique( array_filter( array_map( 'strval', $classes ) ) ) ) ),
			esc_attr( $args['id'] ),
			esc_attr( (string) $args['priority'] ),
			$extra,
			$inner
		);
	}

	/**
	 * The field label with WooCommerce's marker, or nothing without a label.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return string
	 */
	private function label( array $args ): string {
		if ( '' === (string) $args['label'] ) {
			return '';
		}

		return sprintf(
			'<label for="%1$s" class="%2$s">%3$s%4$s</label>',
			esc_attr( $args['id'] ),
			esc_attr( implode( ' ', $args['label_class'] ) ),
			esc_html( $args['label'] ),
			$this->marker( $args )
		);
	}

	/**
	 * WooCommerce's required or optional marker.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return string
	 */
	private function marker( array $args ): string {
		if ( $args['required'] ) {
			return '&nbsp;<span class="required" aria-hidden="true">*</span>';
		}

		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- WooCommerce's own marker text, translated by WooCommerce.
		return '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'woocommerce' ) . ')</span>';
	}

	/**
	 * The description, as WooCommerce marks it up.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return string
	 */
	private function description( array $args ): string {
		if ( '' === (string) $args['description'] ) {
			return '';
		}

		return sprintf(
			'<span class="description" id="%1$s-description" aria-hidden="true">%2$s</span>',
			esc_attr( $args['id'] ),
			wp_kses_post( (string) $args['description'] )
		);
	}

	/**
	 * Field arguments with every key this class reads.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @param string               $key  Field key.
	 * @return array<string, mixed>
	 */
	private function complete( array $args, string $key ): array {
		$args = wp_parse_args(
			$args,
			array(
				'label'             => '',
				'description'       => '',
				'placeholder'       => '',
				'required'          => false,
				'id'                => $key,
				'class'             => array(),
				'label_class'       => array(),
				'input_class'       => array(),
				'options'           => array(),
				'custom_attributes' => array(),
				'priority'          => '',
				'unchecked_value'   => null,
				'checked_value'     => '1',
			)
		);

		foreach ( array( 'class', 'label_class', 'input_class' ) as $list ) {
			$args[ $list ] = array_values( array_filter( array_map( 'strval', (array) $args[ $list ] ), 'strlen' ) );
		}
		$args['custom_attributes'] = (array) $args['custom_attributes'];
		$args['label']             = (string) $args['label'];

		return $args;
	}

	/**
	 * Attributes as HTML, each with a leading space.
	 *
	 * @param array<string, mixed> $attributes Name => value.
	 * @return string
	 */
	private static function attributes( array $attributes ): string {
		$html = '';
		foreach ( $attributes as $name => $value ) {
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				$html .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
			}
		}

		return $html;
	}

	/**
	 * A single value as a string.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function scalar( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * The values of a multi-value field: a list after a failed submit, or a
	 * comma-separated string from a default or saved value.
	 *
	 * @param mixed $value Value.
	 * @return string[]
	 */
	private static function values( $value ): array {
		$values = is_array( $value ) ? $value : explode( ',', self::scalar( $value ) );

		return array_values( array_filter( array_map( 'trim', array_map( 'strval', array_filter( $values, 'is_scalar' ) ) ), 'strlen' ) );
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
}
