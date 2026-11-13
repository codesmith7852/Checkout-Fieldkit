<?php
/**
 * Exports and imports the plugin's setup.
 *
 * @package CheckoutFieldKit
 */

namespace CheckoutFieldKit\Tools;

use CheckoutFieldKit\Domain;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The export document and its import (spec §6.5, §7). The REST routes and the
 * admin's Tools screen call this; it holds no state of its own.
 *
 * An export document is
 *
 *     {plugin: "checkout-fieldkit", format: 1, exportedAt: "<ISO 8601, UTC>",
 *      classic?: <field set>, block?: <field set>, settings?: <settings>}
 *
 * with each target present only when chosen. It holds no order or customer data.
 *
 * Importing replaces each target the document contains and keeps the others.
 * Everything is validated first; when anything is wrong, nothing changes.
 * Built-in fields in an imported field set are fitted to this store's
 * WooCommerce first (see FieldNormalizer::reconcile_built_ins()), so a file
 * from a store that shows different address fields still imports.
 *
 * Problems are plain arrays: {target, section, index, key, message}, where
 * target is 'classic', 'block', 'settings' or '' for the document itself, and
 * the rest are as FieldSetValidator describes ('' and null where they do not apply).
 */
final class ImportExport {

	/**
	 * Value of the document's `plugin` key.
	 */
	public const PLUGIN = 'checkout-fieldkit';

	/**
	 * Document format this version writes and reads.
	 */
	public const FORMAT = 1;

	/**
	 * Targets of a document, in order.
	 *
	 * @var string[]
	 */
	public const TARGETS = array( 'classic', 'block', 'settings' );

	/**
	 * Error code of a refused import or preview.
	 */
	public const ERROR_CODE = 'checkout_fieldkit_invalid_import';

	/**
	 * Domain objects.
	 *
	 * @var Domain
	 */
	private $domain;

	/**
	 * Constructor.
	 *
	 * @param Domain $domain Domain objects.
	 */
	public function __construct( Domain $domain ) {
		$this->domain = $domain;
	}

	/**
	 * Builds an export document of the current setup.
	 *
	 * @param string[] $targets Targets to include, from TARGETS; others are ignored.
	 * @return array<string, mixed>
	 */
	public function export( array $targets = self::TARGETS ): array {
		$document = array(
			'plugin'     => self::PLUGIN,
			'format'     => self::FORMAT,
			'exportedAt' => gmdate( 'c' ),
		);

		foreach ( self::TARGETS as $target ) {
			if ( in_array( $target, $targets, true ) ) {
				$document[ $target ] = 'settings' === $target ? $this->domain->settings()->get() : $this->domain->field_sets( $target )->get();
			}
		}

		return $document;
	}

	/**
	 * Suggested file name of an export: checkout-fieldkit-<site host>-<YYYY-MM-DD>.json.
	 *
	 * @param int|null $timestamp Time of the export; now when null.
	 * @return string
	 */
	public function file_name( ?int $timestamp = null ): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return sanitize_file_name( sprintf( 'checkout-fieldkit-%s-%s.json', $host, gmdate( 'Y-m-d', null === $timestamp ? time() : $timestamp ) ) );
	}

	/**
	 * Every problem with an import document.
	 *
	 * @param mixed $document Decoded JSON document.
	 * @return array<int, array{target: string, section: string, index: int|null, key: string, message: string}> Empty when it can be imported.
	 */
	public function validate( $document ): array {
		if ( ! is_array( $document ) ) {
			return array( self::problem( '', '', __( 'This is not a Checkout FieldKit export file.', 'checkout-fieldkit' ) ) );
		}
		if ( ! isset( $document['plugin'] ) || self::PLUGIN !== $document['plugin'] ) {
			return array( self::problem( '', 'plugin', __( 'This file was not exported by Checkout FieldKit.', 'checkout-fieldkit' ) ) );
		}
		if ( ! isset( $document['format'] ) || ! is_numeric( $document['format'] ) || (int) $document['format'] < 1 ) {
			return array( self::problem( '', 'format', __( 'This export file is damaged: its format is missing.', 'checkout-fieldkit' ) ) );
		}
		if ( (int) $document['format'] > self::FORMAT ) {
			return array( self::problem( '', 'format', __( 'This file was exported by a newer version of Checkout FieldKit. Update the plugin to import it.', 'checkout-fieldkit' ) ) );
		}
		if ( ! $this->targets_in( $document ) ) {
			return array( self::problem( '', '', __( 'This export file contains nothing to import.', 'checkout-fieldkit' ) ) );
		}

		$errors = array();
		foreach ( $this->targets_in( $document ) as $target ) {
			$found = 'settings' === $target
				? $this->domain->settings()->validate( $document[ $target ] )
				: $this->domain->field_sets( $target )->validate( $this->fitted( $target, $document[ $target ] ) );

			foreach ( $found as $error ) {
				$errors[] = array(
					'target'  => $target,
					'section' => isset( $error['section'] ) ? $error['section'] : '',
					'index'   => isset( $error['index'] ) ? $error['index'] : null,
					'key'     => $error['key'],
					'message' => $error['message'],
				);
			}
		}

		return $errors;
	}

	/**
	 * What importing a document would change, per target.
	 *
	 * For a field set, `added` and `removed` list field names that are new or
	 * gone, and `changed` those whose settings, section or position among the
	 * remaining fields differ. For settings, `changed` lists the locale
	 * overrides that differ. Targets absent from the document are kept.
	 *
	 * @param mixed $document Decoded JSON document.
	 * @return array{targets: array<int, array{target: string, action: string, added: string[], removed: string[], changed: string[]}>}|WP_Error
	 *         WP_Error 'checkout_fieldkit_invalid_import' with data {status: 422, errors: [...]} when invalid.
	 */
	public function preview( $document ) {
		$errors = $this->validate( $document );
		if ( $errors ) {
			return self::refusal( $errors );
		}

		$targets = array();
		foreach ( self::TARGETS as $target ) {
			$entry = array(
				'target'  => $target,
				'action'  => 'keep',
				'added'   => array(),
				'removed' => array(),
				'changed' => array(),
			);

			if ( array_key_exists( $target, $document ) ) {
				$entry['action'] = 'replace';
				$entry           = array_merge(
					$entry,
					'settings' === $target ? $this->settings_diff( $document[ $target ] ) : $this->field_set_diff( $target, $document[ $target ] )
				);
			}

			$targets[] = $entry;
		}

		return array( 'targets' => $targets );
	}

	/**
	 * Imports a document: validates every target, then replaces the targets
	 * it contains. Nothing changes when anything is wrong.
	 *
	 * @param mixed $document Decoded JSON document.
	 * @return true|WP_Error WP_Error 'checkout_fieldkit_invalid_import' with data {status: 422, errors: [...]} when invalid.
	 */
	public function import( $document ) {
		$errors = $this->validate( $document );
		if ( $errors ) {
			return self::refusal( $errors );
		}

		foreach ( $this->targets_in( $document ) as $target ) {
			$result = 'settings' === $target
				? $this->domain->settings()->save( $document[ $target ] )
				: $this->domain->field_sets( $target )->save( $this->fitted( $target, $document[ $target ] ) );

			if ( is_wp_error( $result ) ) {
				return $result; // Validated above, so only an extension changing rules mid-request gets here.
			}
		}

		return true;
	}

	/**
	 * The targets a document contains, in order.
	 *
	 * @param array<string, mixed> $document Document.
	 * @return string[]
	 */
	private function targets_in( array $document ): array {
		return array_values(
			array_filter(
				self::TARGETS,
				static function ( $target ) use ( $document ) {
					return array_key_exists( $target, $document );
				}
			)
		);
	}

	/**
	 * An imported field set with its built-in fields fitted to this store.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param mixed  $field_set     Field set from the document.
	 * @return mixed
	 */
	private function fitted( string $checkout_type, $field_set ) {
		if ( is_array( $field_set ) && isset( $field_set['sections'] ) && is_array( $field_set['sections'] ) ) {
			$field_set['sections'] = $this->domain->normalizer()->reconcile_built_ins( $checkout_type, $field_set['sections'] );
		}

		return $field_set;
	}

	/**
	 * Differences between the current field set and an imported one.
	 *
	 * @param string $checkout_type CheckoutType::CLASSIC or CheckoutType::BLOCK.
	 * @param mixed  $field_set     Valid field set from the document.
	 * @return array{added: string[], removed: string[], changed: string[]}
	 */
	private function field_set_diff( string $checkout_type, $field_set ): array {
		$fitted   = $this->fitted( $checkout_type, $field_set );
		$current  = self::index( $this->domain->field_sets( $checkout_type )->get()['sections'] );
		$incoming = self::index( $this->domain->field_sets( $checkout_type )->normalize( $fitted )['sections'] );

		$changed = array();
		foreach ( $incoming as $name => $entry ) {
			if ( ! isset( $current[ $name ] ) ) {
				continue;
			}
			$before = $current[ $name ];
			if ( $before['section'] !== $entry['section'] || $before['field'] !== $entry['field'] || self::common_position( $current, $incoming, $name ) ) {
				$changed[] = (string) $name;
			}
		}

		return array(
			'added'   => array_map( 'strval', array_keys( array_diff_key( $incoming, $current ) ) ),
			'removed' => array_map( 'strval', array_keys( array_diff_key( $current, $incoming ) ) ),
			'changed' => $changed,
		);
	}

	/**
	 * Whether a field's position among the fields both sets share in its
	 * section differs.
	 *
	 * @param array<string, array<string, mixed>> $current  Current fields by name.
	 * @param array<string, array<string, mixed>> $incoming Imported fields by name.
	 * @param string                              $name     Field name, present in both.
	 * @return bool
	 */
	private static function common_position( array $current, array $incoming, string $name ): bool {
		$section  = $incoming[ $name ]['section'];
		$position = static function ( array $fields, array $others ) use ( $section, $name ) {
			$names = array();
			foreach ( $fields as $other => $entry ) {
				if ( $entry['section'] === $section && isset( $others[ $other ] ) && $others[ $other ]['section'] === $section ) {
					$names[] = (string) $other;
				}
			}
			return array_search( $name, $names, true );
		};

		return $position( $current, $incoming ) !== $position( $incoming, $current );
	}

	/**
	 * Fields of a normalised field set keyed by name, in order, with their section.
	 *
	 * @param array<string, array<int, array<string, mixed>>> $sections Normalised sections.
	 * @return array<string, array{section: string, field: array<string, mixed>}>
	 */
	private static function index( array $sections ): array {
		$fields = array();
		foreach ( $sections as $section => $list ) {
			foreach ( $list as $field ) {
				$fields[ $field['name'] ] = array(
					'section' => (string) $section,
					'field'   => $field,
				);
			}
		}

		return $fields;
	}

	/**
	 * Differences between the current settings and imported ones.
	 *
	 * @param mixed $settings Valid settings from the document.
	 * @return array{added: string[], removed: string[], changed: string[]}
	 */
	private function settings_diff( $settings ): array {
		$current  = $this->domain->settings()->get()['localeOverrides'];
		$incoming = $this->domain->settings()->normalize( $settings )['localeOverrides'];

		return array(
			'added'   => array(),
			'removed' => array(),
			'changed' => array_map( 'strval', array_keys( array_diff_assoc( $incoming, $current ) ) ),
		);
	}

	/**
	 * A problem with the document itself.
	 *
	 * @param string $target  Target, or '' for the document.
	 * @param string $key     Key.
	 * @param string $message Message.
	 * @return array{target: string, section: string, index: null, key: string, message: string}
	 */
	private static function problem( string $target, string $key, string $message ): array {
		return array(
			'target'  => $target,
			'section' => '',
			'index'   => null,
			'key'     => $key,
			'message' => $message,
		);
	}

	/**
	 * The error returned for an invalid document.
	 *
	 * @param array<int, array<string, mixed>> $errors Problems.
	 * @return WP_Error
	 */
	private static function refusal( array $errors ): WP_Error {
		return new WP_Error(
			self::ERROR_CODE,
			__( 'Nothing was imported because the file has problems.', 'checkout-fieldkit' ),
			array(
				'status' => 422,
				'errors' => $errors,
			)
		);
	}
}
