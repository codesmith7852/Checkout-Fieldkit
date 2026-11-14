<?php
/**
 * Shared set-up for the classic checkout tests.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Classic;

use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Plugin;
use CheckoutFieldKit\Tests\TestCase;

/**
 * A store in Great Britain with the phone field optional, and helpers to
 * change the classic field set and the locale override switches through the
 * plugin's own repositories.
 */
abstract class ClassicTestCase extends TestCase {

	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_default_country', 'GB' );
		update_option( 'woocommerce_checkout_phone_field', 'optional' );
		update_option( 'woocommerce_allowed_countries', 'all' );
		update_option( 'woocommerce_ship_to_countries', '' );
		WC()->countries->locale = array();
	}

	public function tear_down() {
		parent::tear_down();
		WC()->countries->locale = array();
	}

	/**
	 * The domain the running plugin uses.
	 *
	 * @return Domain
	 */
	protected function domain() {
		return Plugin::instance()->domain();
	}

	/**
	 * Changes the stored classic field set and saves it.
	 *
	 * @param callable $change Receives the sections by reference.
	 */
	protected function change_fields( callable $change ) {
		$set = $this->domain()->field_sets( 'classic' )->get();
		$change( $set['sections'] );
		$result = $this->domain()->field_sets( 'classic' )->save( $set );

		$this->assertTrue( true === $result, is_wp_error( $result ) ? wp_json_encode( $result->get_error_data() ) : 'Saved.' );
	}

	/**
	 * Changes one field of a section.
	 *
	 * @param string $section Section name.
	 * @param string $name    Field name.
	 * @param array  $values  Keys to change.
	 */
	protected function change_field( $section, $name, array $values ) {
		$this->change_fields(
			function ( &$sections ) use ( $section, $name, $values ) {
				foreach ( $sections[ $section ] as $index => $field ) {
					if ( $field['name'] === $name ) {
						$sections[ $section ][ $index ] = array_merge( $field, $values );
						return;
					}
				}
				$this->fail( "No field $name in $section." );
			}
		);
	}

	/**
	 * Adds custom fields at the end of a section.
	 *
	 * @param string  $section Section name.
	 * @param array[] $fields  Fields.
	 */
	protected function add_fields( $section, array $fields ) {
		$this->change_fields(
			function ( &$sections ) use ( $section, $fields ) {
				foreach ( $fields as $field ) {
					$sections[ $section ][] = $field;
				}
			}
		);
	}

	/**
	 * Saves the locale override switches; switches not given keep their defaults.
	 *
	 * @param array<string, bool> $switches Switches.
	 */
	protected function set_locale_overrides( array $switches ) {
		$this->assertTrue( $this->domain()->settings()->save( array( 'localeOverrides' => $switches ) ) );
	}

	/**
	 * A custom field of a type, with a label from its name and, for choice
	 * types, two options.
	 *
	 * @param string $name  Field name.
	 * @param string $type  Field type.
	 * @param array  $extra Other keys.
	 * @return array
	 */
	protected function custom( $name, $type, array $extra = array() ) {
		$field = array(
			'name'  => $name,
			'type'  => $type,
			'label' => ucfirst( str_replace( '_', ' ', $name ) ),
		);
		if ( in_array( $type, array( 'select', 'multiselect', 'radio', 'checkboxgroup' ), true ) ) {
			$field['options'] = array(
				array(
					'value' => 'apple',
					'label' => 'Apple',
				),
				array(
					'value' => 'pear',
					'label' => 'Pear',
				),
			);
		}

		return array_merge( $field, $extra );
	}

	/**
	 * The twenty classic field types a custom field can have.
	 *
	 * @return array<string, array{string}>
	 */
	public function data_classic_types() {
		$types = array( 'text', 'number', 'email', 'url', 'tel', 'password', 'hidden', 'textarea', 'select', 'multiselect', 'radio', 'checkbox', 'checkboxgroup', 'date', 'time', 'datetime-local', 'month', 'week', 'heading', 'paragraph' );
		$data  = array();
		foreach ( $types as $type ) {
			$data[ $type ] = array( $type );
		}

		return $data;
	}

	/**
	 * A field name for a type, for example extra_datetime_local.
	 *
	 * @param string $type Field type.
	 * @return string
	 */
	protected function name_for( $type ) {
		return 'extra_' . str_replace( '-', '_', $type );
	}
}
