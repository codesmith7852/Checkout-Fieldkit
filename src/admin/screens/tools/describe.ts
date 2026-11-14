/**
 * The sentences the Tools screen shows: counts for Export, what an import
 * would change, and what a reset deletes.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import type {
	Field,
	FieldSet,
	ImportError,
	ImportTargetPreview,
	Resource,
	Settings,
} from '../../types';
import { switchTitle } from '../settings/switches';

export const TARGETS: Resource[] = [ 'classic', 'block', 'settings' ];

export function targetLabel( target: Resource ): string {
	if ( target === 'classic' ) {
		return __( 'Classic checkout fields', 'checkout-fieldkit' );
	}
	if ( target === 'block' ) {
		return __( 'Block checkout fields', 'checkout-fieldkit' );
	}
	return __( 'Settings', 'checkout-fieldkit' );
}

/**
 * A list of names for a sentence, such as "Gift message, Gift wrap".
 *
 * @param items Names.
 */
export function list( items: string[] ): string {
	if ( items.length === 0 ) {
		return '';
	}
	return items.reduce( ( sentence, item ) =>
		sprintf(
			/* translators: 1: the list so far; 2: the next item. */
			__( '%1$s, %2$s', 'checkout-fieldkit' ),
			sentence,
			item
		)
	);
}

export function fieldsOf( fieldSet: FieldSet | undefined | null ): Field[] {
	const sections = fieldSet?.sections;
	if ( ! sections || typeof sections !== 'object' ) {
		return [];
	}
	return Object.values( sections ).flatMap( ( fields ) =>
		Array.isArray( fields ) ? fields : []
	);
}

export function customFieldsOf( fieldSet: FieldSet ): Field[] {
	return fieldsOf( fieldSet ).filter( ( item ) => ! item.builtIn );
}

/**
 * "5 fields" or "5 switches", for the Export choices.
 *
 * @param target   What is exported.
 * @param fieldSet The target's saved field set (ignored for settings).
 * @param settings The saved settings.
 */
export function exportCount(
	target: Resource,
	fieldSet: FieldSet,
	settings: Settings
): string {
	if ( target === 'settings' ) {
		const count = Object.keys( settings.localeOverrides || {} ).length;
		return sprintf(
			/* translators: %d: number of settings switches. */
			_n( '%d switch', '%d switches', count, 'checkout-fieldkit' ),
			count
		);
	}
	const count = fieldsOf( fieldSet ).length;
	return sprintf(
		/* translators: %d: number of fields. */
		_n( '%d field', '%d fields', count, 'checkout-fieldkit' ),
		count
	);
}

function labelsByName( ...fieldSets: Array< FieldSet | undefined | null > ) {
	const labels = new Map< string, string >();
	fieldSets.forEach( ( fieldSet ) =>
		fieldsOf( fieldSet ).forEach( ( item ) => {
			if ( item && typeof item.name === 'string' && item.label ) {
				if ( ! labels.has( item.name ) ) {
					labels.set( item.name, String( item.label ) );
				}
			}
		} )
	);
	return ( names: string[] ) =>
		list( names.map( ( name ) => labels.get( name ) || name ) );
}

/**
 * What importing changes in one target, in a sentence or two.
 *
 * @param entry    The server's preview for the target.
 * @param saved    The target as saved now.
 * @param incoming The target as it is in the file.
 */
export function previewSummary(
	entry: ImportTargetPreview,
	saved: FieldSet | Settings,
	incoming: unknown
): string {
	if ( entry.action === 'keep' ) {
		return __(
			'Not in the file, so nothing changes.',
			'checkout-fieldkit'
		);
	}

	if ( entry.target === 'settings' ) {
		if ( entry.changed.length === 0 ) {
			return __( 'Same settings as now.', 'checkout-fieldkit' );
		}
		const overrides =
			( incoming as Partial< Settings > | null )?.localeOverrides || {};
		return entry.changed
			.map( ( key ) =>
				overrides[ key ]
					? sprintf(
							/* translators: %s: a settings switch, such as "Keep my labels". */
							__( '%s turns on.', 'checkout-fieldkit' ),
							switchTitle( key )
						)
					: sprintf(
							/* translators: %s: a settings switch, such as "Keep my labels". */
							__( '%s turns off.', 'checkout-fieldkit' ),
							switchTitle( key )
						)
			)
			.join( ' ' );
	}

	const fromFile = labelsByName( incoming as FieldSet );
	const fromSaved = labelsByName( saved as FieldSet, incoming as FieldSet );
	const parts: string[] = [];
	if ( entry.added.length ) {
		parts.push(
			sprintf(
				/* translators: %s: list of field labels. */
				__( 'New: %s.', 'checkout-fieldkit' ),
				fromFile( entry.added )
			)
		);
	}
	if ( entry.removed.length ) {
		parts.push(
			sprintf(
				/* translators: %s: list of field labels. */
				__( 'Removed: %s.', 'checkout-fieldkit' ),
				fromSaved( entry.removed )
			)
		);
	}
	if ( entry.changed.length ) {
		parts.push(
			sprintf(
				/* translators: %s: list of field labels. */
				__( 'Changed: %s.', 'checkout-fieldkit' ),
				fromSaved( entry.changed )
			)
		);
	}
	return parts.length
		? parts.join( ' ' )
		: __( 'Same fields as now.', 'checkout-fieldkit' );
}

/**
 * One problem of an invalid import file, prefixed with its target.
 *
 * @param error A problem from the server's 422.
 */
export function importProblem( error: ImportError ): string {
	if ( error.target && TARGETS.includes( error.target ) ) {
		return sprintf(
			/* translators: 1: what the problem is in, such as "Block checkout fields"; 2: the problem. */
			__( '%1$s: %2$s', 'checkout-fieldkit' ),
			targetLabel( error.target ),
			error.message
		);
	}
	return error.message;
}

/**
 * What resetting a target does, as shown in the Reset card.
 *
 * @param target   What is reset.
 * @param fieldSet The target's saved field set (ignored for settings).
 * @param detailed Name the custom fields that are deleted (for the dialog).
 */
export function resetConsequence(
	target: Resource,
	fieldSet: FieldSet | null,
	detailed = false
): string {
	if ( target === 'settings' || ! fieldSet ) {
		return __(
			'Turns the five address field switches back to their defaults.',
			'checkout-fieldkit'
		);
	}
	const custom = customFieldsOf( fieldSet );
	if ( custom.length === 0 ) {
		return __(
			'Restores WooCommerce’s built-in fields and undoes your changes to them. You have no custom fields.',
			'checkout-fieldkit'
		);
	}
	if ( detailed ) {
		return sprintf(
			/* translators: 1: number of custom fields; 2: their labels. */
			_n(
				'Restores WooCommerce’s built-in fields, undoes your changes to them and deletes your %1$d custom field: %2$s.',
				'Restores WooCommerce’s built-in fields, undoes your changes to them and deletes your %1$d custom fields: %2$s.',
				custom.length,
				'checkout-fieldkit'
			),
			custom.length,
			list( custom.map( ( item ) => item.label || item.name ) )
		);
	}
	return sprintf(
		/* translators: %d: number of custom fields. */
		_n(
			'Restores WooCommerce’s built-in fields, undoes your changes to them and deletes your %d custom field.',
			'Restores WooCommerce’s built-in fields, undoes your changes to them and deletes your %d custom fields.',
			custom.length,
			'checkout-fieldkit'
		),
		custom.length
	);
}
