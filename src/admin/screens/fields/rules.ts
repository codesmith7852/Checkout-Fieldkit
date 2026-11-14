/**
 * Rules of the Fields screen that don't need React (spec §2.3, §6.2):
 * copying a field to the other checkout, essential fields, and the settings
 * WooCommerce keeps for itself on some built-in fields.
 */
import { __, sprintf } from '@wordpress/i18n';
import { checkoutTypeLabel } from '../../shell/labels';
import type { CheckoutType, Context, Field, FieldSet } from '../../types';

export function otherCheckoutType( type: CheckoutType ): CheckoutType {
	return type === 'classic' ? 'block' : 'classic';
}

/**
 * A field's name for people: its label, or its field name when it has none.
 * @param field
 */
export function displayName( field: Field ): string {
	return field.label.trim() || field.name;
}

/**
 * Where a field from `section` goes on the other checkout: the section of the
 * same name if there is one, otherwise the closest one.
 */
const CLOSEST_SECTION: Record< string, string > = {
	billing: 'address',
	shipping: 'address',
	contact: 'billing',
	address: 'billing',
	additional: 'additional',
};

/**
 * The section a field copied from `section` of `from` lands in.
 *
 * @param context GET /context.
 * @param from    The checkout type the field is on.
 * @param section Its section.
 */
export function copyTargetSection(
	context: Context,
	from: CheckoutType,
	section: string
): string | null {
	const names = context.sections[ otherCheckoutType( from ) ].map(
		( item ) => item.name
	);
	if ( names.includes( section ) ) {
		return section;
	}
	const closest = CLOSEST_SECTION[ section ];
	if ( closest && names.includes( closest ) ) {
		return closest;
	}
	return names[ 0 ] || null;
}

export type CopyCheck =
	| { ok: true; section: string; sectionLabel: string; field: Field }
	| { ok: false; reason: string };

/**
 * Whether a field can be copied to the other checkout, and if so where and as
 * what. Only custom fields can be copied; the field type must be allowed in
 * the target section and the name must be free there.
 *
 * @param context GET /context.
 * @param from    The checkout type the field is on.
 * @param section Its section.
 * @param field   The field.
 * @param target  The other checkout's draft field set.
 */
export function checkCopy(
	context: Context,
	from: CheckoutType,
	section: string,
	field: Field,
	target: FieldSet
): CopyCheck {
	const to = otherCheckoutType( from );
	const toLabel = checkoutTypeLabel( to );

	if ( field.builtIn ) {
		return {
			ok: false,
			reason: sprintf(
				/* translators: 1: field label, 2: checkout type, such as "Block checkout". */
				__(
					'%1$s is built in, and the %2$s has its own built-in fields. Only custom fields can be copied.',
					'checkout-fieldkit'
				),
				displayName( field ),
				toLabel
			),
		};
	}

	const targetSection = copyTargetSection( context, from, section );
	const sectionLabel =
		context.sections[ to ].find( ( item ) => item.name === targetSection )
			?.label || '';
	const type = context.fieldTypes[ to ].find(
		( item ) => item.name === field.type && ! item.builtInOnly
	);
	const sourceType = context.fieldTypes[ from ].find(
		( item ) => item.name === field.type
	);
	const typeLabel = sourceType?.label || field.type;

	if ( ! type || ! targetSection ) {
		return {
			ok: false,
			reason: sprintf(
				/* translators: 1: checkout type, such as "Block checkout", 2: field type, such as "Date". */
				__( 'The %1$s has no %2$s fields.', 'checkout-fieldkit' ),
				toLabel,
				typeLabel
			),
		};
	}

	if ( ! type.sections.includes( targetSection ) ) {
		return {
			ok: false,
			reason: sprintf(
				/* translators: 1: field type, such as "Textarea", 2: checkout type, 3: section, such as "Address". */
				__(
					"%1$s fields can't go in the %2$s's %3$s section.",
					'checkout-fieldkit'
				),
				typeLabel,
				toLabel,
				sectionLabel
			),
		};
	}

	if ( context.reservedNames.includes( field.name ) ) {
		return {
			ok: false,
			reason: sprintf(
				/* translators: %s: field name. */
				__( 'WooCommerce reserves the name %s.', 'checkout-fieldkit' ),
				field.name
			),
		};
	}

	const taken = Object.values( target.sections ).some( ( fields ) =>
		fields.some( ( item ) => item.name === field.name )
	);
	if ( taken ) {
		return {
			ok: false,
			reason: sprintf(
				/* translators: 1: checkout type, such as "Block checkout", 2: field name. */
				__(
					'The %1$s already has a field named %2$s.',
					'checkout-fieldkit'
				),
				toLabel,
				field.name
			),
		};
	}

	const validations = context.validations[ to ].map( ( item ) => item.name );
	return {
		ok: true,
		section: targetSection,
		sectionLabel,
		field: {
			...field,
			validation: field.validation.filter( ( name ) =>
				validations.includes( name )
			),
		},
	};
}

/**
 * What stops working without an essential field, or null when the field is
 * not essential.
 *
 * @param context GET /context.
 * @param type    Checkout type.
 * @param field   The field.
 */
export function essentialConsequence(
	context: Context,
	type: CheckoutType,
	field: Field
): string | null {
	if ( ! field.builtIn ) {
		return null;
	}
	const essential = context.essentialFields[ type ].find(
		( item ) => item.name === field.name
	);
	return essential ? essential.consequence : null;
}

/**
 * Whether WooCommerce keeps a setting of a built-in field for itself, such as
 * the Block checkout's email address, which is always on and required.
 *
 * @param context GET /context.
 * @param type    Checkout type.
 * @param field   The field.
 * @param key     The setting.
 */
export function isLocked(
	context: Context,
	type: CheckoutType,
	field: Field,
	key: 'enabled' | 'required'
): boolean {
	if ( ! field.builtIn ) {
		return false;
	}
	const editable = context.builtInEditableSettings[ type ][ field.name ];
	return Array.isArray( editable ) && ! editable.includes( key );
}

/**
 * Whether Required applies to a field's type (display fields collect nothing).
 *
 * @param context GET /context.
 * @param type    Checkout type.
 * @param field   The field.
 */
export function takesRequired(
	context: Context,
	type: CheckoutType,
	field: Field
): boolean {
	const definition = context.fieldTypes[ type ].find(
		( item ) => item.name === field.type
	);
	return ! definition || definition.settings.includes( 'required' );
}
