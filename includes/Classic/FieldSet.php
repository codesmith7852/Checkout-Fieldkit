<?php
/**
 * The classic field set as the checkout reads it.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Classic;

use CheckoutFieldKit\Storage\FieldSetRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only access to the stored (or default) classic field set: the fields of
 * a section in display order, a field by name, and the WooCommerce priority
 * of a position.
 */
final class FieldSet {

	/**
	 * The classic field set repository.
	 *
	 * @var FieldSetRepository
	 */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param FieldSetRepository $repository The classic field set repository.
	 */
	public function __construct( FieldSetRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * The WooCommerce priority of a position in a section: (index + 1) × 10.
	 *
	 * @param int $index Position in the section, from 0.
	 * @return int
	 */
	public static function priority( int $index ): int {
		return ( $index + 1 ) * 10;
	}

	/**
	 * Every field of a section, disabled ones included, keyed by position.
	 *
	 * @param string $section Section name: billing, shipping or additional.
	 * @return array<int, array<string, mixed>>
	 */
	public function section( string $section ): array {
		$sections = $this->repository->get()['sections'];

		return isset( $sections[ $section ] ) ? $sections[ $section ] : array();
	}

	/**
	 * A field by name, with its section and position.
	 *
	 * @param string $name Field name.
	 * @return array{section: string, index: int, field: array<string, mixed>}|null Null when the set has no such field.
	 */
	public function find( string $name ): ?array {
		foreach ( $this->repository->get()['sections'] as $section => $fields ) {
			foreach ( $fields as $index => $field ) {
				if ( $field['name'] === $name ) {
					return array(
						'section' => (string) $section,
						'index'   => (int) $index,
						'field'   => $field,
					);
				}
			}
		}

		return null;
	}
}
