/**
 * The field editor's rules, as plain functions: which settings apply to a
 * field (spec §2.4), which types a section allows, the suggested field name,
 * preparing an edited field for the draft (as FieldNormalizer.php would), and
 * the client mirror of the field rules of spec §2.5 (FieldSetValidator.php),
 * with the server's own messages.
 */
import { __, sprintf } from '@wordpress/i18n';
import type {
	CheckoutType,
	Context,
	Field,
	FieldOption,
	FieldTypeDefinition,
} from '../types';

/** Longest field name (spec §2.2). */
export const NAME_MAX_LENGTH = 60;

/** Settings that are cleared when they do not apply to a custom field's type (FieldNormalizer::TYPE_SETTINGS). */
const TYPE_SETTINGS = [
	'placeholder',
	'default',
	'required',
	'options',
	'validation',
	'headingLevel',
	'showInEmails',
	'showOnOrderPages',
];

/** Settings the editor never changes on a built-in field. */
const LOCKED_BUILT_IN_KEYS = [ 'name', 'type', 'builtIn' ];

/** A problem with one setting of the field being edited. */
export interface FieldError {
	/** Field key, such as `name` or `options`. */
	key: string;
	message: string;
}

/**
 * The definition of a field type.
 *
 * @param context      GET /context.
 * @param checkoutType Classic or block.
 * @param type         Type name.
 */
export function typeDefinition(
	context: Context,
	checkoutType: CheckoutType,
	type: string
): FieldTypeDefinition | null {
	return (
		context.fieldTypes[ checkoutType ].find(
			( definition ) => definition.name === type
		) || null
	);
}

/**
 * A section's label.
 *
 * @param context      GET /context.
 * @param checkoutType Classic or block.
 * @param section      Section name.
 */
export function sectionLabel(
	context: Context,
	checkoutType: CheckoutType,
	section: string
): string {
	return (
		context.sections[ checkoutType ].find(
			( item ) => item.name === section
		)?.label || section
	);
}

/**
 * The settings the editor offers for a field, in editor order: for a custom
 * field those of its type; for a built-in field those of its type that are
 * ours to change (`builtInEditableSettings`). Name, type, enabled and
 * position are not settings here.
 *
 * @param context      GET /context.
 * @param checkoutType Classic or block.
 * @param field        The field.
 */
export function applicableSettings(
	context: Context,
	checkoutType: CheckoutType,
	field: Field
): string[] {
	const definition = typeDefinition( context, checkoutType, field.type );
	const typeSettings = definition ? definition.settings : [ 'label' ];

	if ( ! field.builtIn ) {
		return typeSettings.slice();
	}

	const editable = (
		context.builtInEditableSettings[ checkoutType ][ field.name ] || []
	).filter( ( key ) => key !== 'enabled' );

	return definition
		? typeSettings.filter( ( key ) => editable.includes( key ) )
		: editable;
}

/** A type a custom field can have, and whether the section allows it. */
export interface TypeChoice {
	name: string;
	label: string;
	allowed: boolean;
	/** Why the section does not allow the type; empty when it does. */
	reason: string;
}

function notInSectionMessage( typeLabel: string, section: string ): string {
	return sprintf(
		/* translators: 1: field type label, 2: section label. */
		__(
			'%1$s fields cannot be used in the %2$s section.',
			'checkout-fieldkit'
		),
		typeLabel,
		section
	);
}

/**
 * Every type a custom field can have on a checkout type, in offer order,
 * with whether the section allows it (rule 5 of spec §2.5).
 *
 * @param context      GET /context.
 * @param checkoutType Classic or block.
 * @param section      Section name.
 */
export function typeChoices(
	context: Context,
	checkoutType: CheckoutType,
	section: string
): TypeChoice[] {
	const label = sectionLabel( context, checkoutType, section );
	return context.fieldTypes[ checkoutType ]
		.filter( ( definition ) => ! definition.builtInOnly )
		.map( ( definition ) => {
			const allowed = definition.sections.includes( section );
			return {
				name: definition.name,
				label: definition.label,
				allowed,
				reason: allowed
					? ''
					: notInSectionMessage( definition.label, label ),
			};
		} );
}

/**
 * The prefix suggested for new custom fields of a section (spec §4.1):
 * keeping it lets WooCommerce save the answer on the customer too.
 *
 * @param checkoutType Classic or block.
 * @param section      Section name.
 */
export function namePrefix(
	checkoutType: CheckoutType,
	section: string
): string {
	if (
		checkoutType === 'classic' &&
		( section === 'billing' || section === 'shipping' )
	) {
		return `${ section }_`;
	}
	return '';
}

const SPECIAL_LETTERS: Record< string, string > = {
	ß: 'ss',
	æ: 'ae',
	œ: 'oe',
	ø: 'o',
	đ: 'd',
	ð: 'd',
	ł: 'l',
	þ: 'th',
};

/**
 * Lower-case letters, digits and single underscores from any text.
 *
 * @param text Text such as a label.
 */
export function slugify( text: string ): string {
	return text
		.toLowerCase()
		.normalize( 'NFD' )
		.replace( /[̀-ͯ]/g, '' )
		.replace( /[ßæœøđðłþ]/g, ( letter ) => SPECIAL_LETTERS[ letter ] )
		.replace( /[^a-z0-9]+/g, '_' )
		.replace( /^_+|_+$/g, '' );
}

function trimName( name: string, length: number ): string {
	return name.slice( 0, length ).replace( /_+$/, '' );
}

export interface SuggestNameOptions {
	/** Section prefix, such as `billing_`. */
	prefix?: string;
	/** Names already in use or reserved. */
	taken?: Set< string >;
}

/**
 * A field name for a label: lower-case words joined by underscores, with the
 * section's prefix, starting with a letter, at most 60 characters, and not
 * one of `taken` (a number is added). Empty when the label has no letters or
 * digits.
 *
 * @param label          The field's label.
 * @param options        Prefix and names to avoid.
 * @param options.prefix
 * @param options.taken
 */
export function suggestName(
	label: string,
	{ prefix = '', taken = new Set() }: SuggestNameOptions = {}
): string {
	let base = slugify( label );
	if ( prefix && base.startsWith( prefix ) ) {
		base = base.slice( prefix.length );
	}
	if ( base === '' ) {
		return '';
	}
	if ( ! prefix && /^[0-9]/.test( base ) ) {
		base = `field_${ base }`;
	}

	const name = trimName( prefix + base, NAME_MAX_LENGTH );
	if ( ! taken.has( name ) ) {
		return name;
	}
	for ( let number = 2; ; number++ ) {
		const suffix = `_${ number }`;
		const candidate =
			trimName( name, NAME_MAX_LENGTH - suffix.length ) + suffix;
		if ( ! taken.has( candidate ) ) {
			return candidate;
		}
	}
}

/**
 * The CSS classes typed in a text box: separated by spaces or commas, each
 * cleaned as WordPress's sanitize_html_class() does, without repeats.
 *
 * @param text What was typed.
 */
export function parseClasses( text: string ): string[] {
	const classes: string[] = [];
	text.split( /[\s,]+/ ).forEach( ( item ) => {
		const clean = item
			.replace( /%[a-fA-F0-9][a-fA-F0-9]/g, '' )
			.replace( /[^A-Za-z0-9_-]/g, '' );
		if ( clean !== '' && ! classes.includes( clean ) ) {
			classes.push( clean );
		}
	} );
	return classes;
}

function isTicked( value: string ): boolean {
	return [ '1', 'true', 'yes', 'on' ].includes( value.trim().toLowerCase() );
}

function cleanDefault(
	value: string,
	type: string,
	definition: FieldTypeDefinition | null
): string {
	if ( type === 'checkbox' ) {
		return isTicked( value ) ? '1' : '';
	}
	if ( definition?.multiple ) {
		const values: string[] = [];
		value.split( ',' ).forEach( ( item ) => {
			const clean = item.trim();
			if ( clean !== '' && ! values.includes( clean ) ) {
				values.push( clean );
			}
		} );
		return values.join( ',' );
	}
	return value.trim();
}

/**
 * An edited field as it goes into the draft, shaped as the server would
 * normalise it: text trimmed, settings that do not apply to a custom field's
 * type back at their defaults, and a built-in field's locked settings (and
 * its name, type and builtIn) as they were.
 *
 * @param context      GET /context.
 * @param checkoutType Classic or block.
 * @param field        The edited field.
 * @param original     For a built-in field: the field before editing.
 */
export function prepareField(
	context: Context,
	checkoutType: CheckoutType,
	field: Field,
	original?: Field | null
): Field {
	const definition = typeDefinition( context, checkoutType, field.type );
	const defaults = context.fieldDefaults[ checkoutType ];
	const result: Field = {
		...field,
		name: field.name.trim(),
		label: field.label.trim(),
		placeholder: field.placeholder.trim(),
		default: cleanDefault( field.default, field.type, definition ),
		options: field.options.map( ( option ): FieldOption => ( {
			value: option.value.trim(),
			label: option.label.trim(),
		} ) ),
		validation: Array.from( new Set( field.validation ) ),
		classes: parseClasses( field.classes.join( ' ' ) ),
	};

	if ( field.builtIn && original ) {
		const editable =
			context.builtInEditableSettings[ checkoutType ][ original.name ] ||
			[];
		Object.keys( result ).forEach( ( key ) => {
			if (
				LOCKED_BUILT_IN_KEYS.includes( key ) ||
				( key !== 'enabled' && ! editable.includes( key ) )
			) {
				result[ key ] = original[ key ];
			}
		} );
		return result;
	}

	if ( definition ) {
		TYPE_SETTINGS.forEach( ( key ) => {
			if ( ! definition.settings.includes( key ) && key in defaults ) {
				result[ key ] = defaults[ key ];
			}
		} );
	}

	return result;
}

/** What validateField() needs to know. */
export interface ValidationInput {
	context: Context;
	checkoutType: CheckoutType;
	section: string;
	/** The field as prepareField() gives it. */
	field: Field;
	/** Names of every other field in the field set. */
	otherNames: string[];
}

/**
 * Whether a string is a number as PHP's is_numeric() sees it.
 *
 * @param value Text.
 */
export function isNumeric( value: string ): boolean {
	return /^\s*[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?\s*$/.test( value );
}

/**
 * Which options have a problem: an empty saved value (other than a
 * dropdown's first option) or a value another option has too.
 *
 * @param options  The options.
 * @param isSelect Whether the field is a dropdown.
 */
export function optionIssues(
	options: FieldOption[],
	isSelect: boolean
): boolean[] {
	const values = options.map( ( option ) => option.value.trim() );
	return values.map( ( value, position ) => {
		if ( value === '' ) {
			return ! ( isSelect && position === 0 );
		}
		return values.indexOf( value ) !== values.lastIndexOf( value );
	} );
}

function optionsError(
	options: FieldOption[],
	isSelect: boolean
): FieldError | null {
	const values = options.map( ( option ) => option.value );
	const empty = values.some(
		( value, position ) => value === '' && ! ( isSelect && position === 0 )
	);
	if ( empty ) {
		return {
			key: 'options',
			message: isSelect
				? __(
						'Every option needs a saved value. Only the first option of a dropdown may be empty, as a prompt.',
						'checkout-fieldkit'
					)
				: __(
						'Every option needs a saved value.',
						'checkout-fieldkit'
					),
		};
	}
	if ( new Set( values ).size !== values.length ) {
		return {
			key: 'options',
			message: __(
				'Two options have the same saved value. Each option needs its own.',
				'checkout-fieldkit'
			),
		};
	}
	if ( ! values.some( ( value ) => value !== '' ) ) {
		return {
			key: 'options',
			message: __( 'Add at least one option.', 'checkout-fieldkit' ),
		};
	}
	return null;
}

function defaultError(
	field: Field,
	definition: FieldTypeDefinition
): FieldError | null {
	if ( field.default === '' ) {
		return null;
	}
	if ( definition.hasOptions ) {
		const values = field.options.map( ( option ) => option.value );
		const chosen = definition.multiple
			? field.default.split( ',' )
			: [ field.default ];
		if ( chosen.some( ( value ) => ! values.includes( value ) ) ) {
			return {
				key: 'default',
				message: __(
					'The default must be one of the option values.',
					'checkout-fieldkit'
				),
			};
		}
	}
	if ( field.type === 'number' && ! isNumeric( field.default ) ) {
		return {
			key: 'default',
			message: __(
				'The default of a number field must be a number.',
				'checkout-fieldkit'
			),
		};
	}
	return null;
}

/**
 * The problems the server would find with this field (rules 1 and 3–5 of
 * spec §2.5; rule 2 holds because the editor never changes a built-in
 * field's name or type). At most one problem per key, in the server's order.
 *
 * @param input What to check; see ValidationInput.
 */
export function validateField( input: ValidationInput ): FieldError[] {
	const { context, checkoutType, section, field, otherNames } = input;
	const errors: FieldError[] = [];

	if ( ! new RegExp( context.fieldNamePattern ).test( field.name ) ) {
		errors.push( {
			key: 'name',
			message: __(
				'The field name must start with a lower-case letter and use only lower-case letters, digits and underscores, up to 60 characters.',
				'checkout-fieldkit'
			),
		} );
	} else if ( otherNames.includes( field.name ) ) {
		errors.push( {
			key: 'name',
			message: __(
				'Another field already uses this name. Field names must be unique.',
				'checkout-fieldkit'
			),
		} );
	}

	if ( field.builtIn ) {
		return errors;
	}

	if ( errors.length === 0 && context.reservedNames.includes( field.name ) ) {
		errors.push( {
			key: 'name',
			message: __(
				'WooCommerce uses this name for one of its own fields. Choose another name.',
				'checkout-fieldkit'
			),
		} );
	}

	const definition = typeDefinition( context, checkoutType, field.type );
	if ( ! definition || definition.builtInOnly ) {
		errors.push( {
			key: 'type',
			message: sprintf(
				/* translators: %s: field type. */
				__(
					'"%s" is not a field type this checkout supports.',
					'checkout-fieldkit'
				),
				field.type
			),
		} );
		return errors;
	}
	if ( ! definition.sections.includes( section ) ) {
		errors.push( {
			key: 'type',
			message: notInSectionMessage(
				definition.label,
				sectionLabel( context, checkoutType, section )
			),
		} );
	}

	const problem = defaultError( field, definition );
	if ( problem ) {
		errors.push( problem );
	}

	if ( definition.hasOptions ) {
		const options = optionsError( field.options, field.type === 'select' );
		if ( options ) {
			errors.push( options );
		}
	}

	return errors;
}
