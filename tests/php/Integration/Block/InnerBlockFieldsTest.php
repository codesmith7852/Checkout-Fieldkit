<?php
/**
 * Tests for the fields our checkout inner blocks show.
 *
 * @package CheckoutFieldKit\Tests
 */

namespace CheckoutFieldKit\Tests\Integration\Block;

use CheckoutFieldKit\Block\InnerBlockFields;
use CheckoutFieldKit\Domain;
use CheckoutFieldKit\Tests\TestCase;

/**
 * Spec §5, second bullet: the Contact and Additional inner blocks render
 * every enabled custom field of their section, in the stored order.
 */
class InnerBlockFieldsTest extends TestCase {

	/**
	 * Saves the default Block field set with fields appended to sections.
	 *
	 * @param array<string, array<int, array<string, mixed>>> $sections Fields by section.
	 * @return InnerBlockFields
	 */
	private function with_fields( array $sections ) {
		$domain     = new Domain();
		$repository = $domain->field_sets( 'block' );
		$set        = $repository->get();
		foreach ( $sections as $section => $fields ) {
			foreach ( $fields as $field ) {
				$set['sections'][ $section ][] = $field;
			}
		}
		$this->assertTrue( $repository->save( $set ), 'The field set saves.' );

		return new InnerBlockFields( $domain->field_sets( 'block' ), $domain->field_types() );
	}

	public function test_lists_enabled_custom_fields_of_contact_and_additional_in_order() {
		$fields = $this->with_fields(
			array(
				'contact'    => array(
					array(
						'name'  => 'newsletter',
						'type'  => 'checkbox',
						'label' => 'Send me news',
					),
					array(
						'name'    => 'old',
						'type'    => 'text',
						'label'   => 'Old',
						'enabled' => false,
					),
				),
				'address'    => array(
					array(
						'name'  => 'vat',
						'type'  => 'text',
						'label' => 'VAT',
					),
				),
				'additional' => array(
					array(
						'name'  => 'gift_note',
						'type'  => 'textarea',
						'label' => 'Gift note',
					),
					array(
						'name'  => 'intro',
						'type'  => 'heading',
						'label' => 'About you',
					),
				),
			)
		);

		$this->assertSame( array( 'newsletter' ), array_column( $fields->fields( 'contact' ), 'name' ), 'Built-in email and disabled fields are left out.' );
		$this->assertSame( array( 'gift_note', 'intro' ), array_column( $fields->fields( 'additional' ), 'name' ) );
		$this->assertSame( array( 'newsletter', 'gift_note', 'intro' ), array_column( $fields->fields(), 'name' ) );
		$this->assertSame( array( 'newsletter', 'gift_note' ), array_column( $fields->value_fields(), 'name' ), 'Display fields collect no value.' );
		$this->assertSame( 'contact', $fields->section_of( 'newsletter' ) );
		$this->assertSame( 'additional', $fields->section_of( 'gift_note' ) );
		$this->assertNull( $fields->section_of( 'vat' ) );
	}

	public function test_sections_without_custom_fields_are_empty() {
		$fields = $this->with_fields( array() );

		$this->assertSame( array(), $fields->fields( 'contact' ) );
		$this->assertSame(
			array(
				'contact'    => array(),
				'additional' => array(),
			),
			$fields->script_data()['sections']
		);
	}

	public function test_script_data_gives_the_frontend_only_what_it_needs_per_type() {
		$fields = $this->with_fields(
			array(
				'contact'    => array(
					array(
						'name'             => 'website',
						'type'             => 'url',
						'label'            => 'Website',
						'placeholder'      => 'https://',
						'required'         => true,
						'default'          => 'https://example.org',
						'validation'       => array( 'url' ),
						'classes'          => array( 'wide' ),
						'showInEmails'     => false,
						'showOnOrderPages' => false,
					),
				),
				'additional' => array(
					array(
						'name'         => 'title',
						'type'         => 'heading',
						'label'        => 'More',
						'headingLevel' => 'h4',
					),
					array(
						'name'  => 'note',
						'type'  => 'paragraph',
						'label' => 'Tell us more.',
					),
					array(
						'name'    => 'size',
						'type'    => 'select',
						'label'   => 'Size',
						'default' => 'm',
						'options' => array(
							array(
								'value' => '',
								'label' => 'Pick a size',
							),
							array(
								'value' => 'm',
								'label' => 'Medium',
							),
						),
					),
					array(
						'name'    => 'agree',
						'type'    => 'checkbox',
						'label'   => 'I agree',
						'default' => '1',
					),
				),
			)
		);

		$data = $fields->script_data();

		$this->assertSame( 'checkout-fieldkit', $data['namespace'] );
		$this->assertSame(
			array(
				'name'        => 'website',
				'type'        => 'url',
				'label'       => 'Website',
				'classes'     => array( 'wide' ),
				'required'    => true,
				'default'     => 'https://example.org',
				'placeholder' => 'https://',
				'validation'  => array( 'url' ),
			),
			$data['sections']['contact'][0],
			'No display flags or other admin-only keys.'
		);
		$this->assertSame(
			array(
				'name'         => 'title',
				'type'         => 'heading',
				'label'        => 'More',
				'classes'      => array(),
				'headingLevel' => 'h4',
			),
			$data['sections']['additional'][0]
		);
		$this->assertSame(
			array(
				'name'    => 'note',
				'type'    => 'paragraph',
				'label'   => 'Tell us more.',
				'classes' => array(),
			),
			$data['sections']['additional'][1]
		);
		$this->assertSame(
			array(
				array(
					'value' => '',
					'label' => 'Pick a size',
				),
				array(
					'value' => 'm',
					'label' => 'Medium',
				),
			),
			$data['sections']['additional'][2]['options']
		);
		$this->assertSame( 'm', $data['sections']['additional'][2]['default'] );
		$this->assertArrayNotHasKey( 'validation', $data['sections']['additional'][2], 'Validation does not apply to selects.' );
		$this->assertSame( '1', $data['sections']['additional'][3]['default'] );
		$this->assertArrayNotHasKey( 'placeholder', $data['sections']['additional'][3] );
	}
}
