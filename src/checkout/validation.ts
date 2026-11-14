/**
 * What is wrong with a field's value, checked in the browser with the same
 * rules and messages as on the server (Orders\ValueValidator in PHP):
 *
 * - an empty value (blank text, an unticked checkbox, a select's empty
 *   prompt) fails only when the field is required;
 * - a non-empty value gets its type's own check (number, email, url), then
 *   each chosen validation (email, phone, postcode, url), then, for select
 *   and radio, must be one of the options;
 * - headings and paragraphs are never checked.
 */
import { __, sprintf } from '@wordpress/i18n';
import type { CheckoutField, FieldValue } from './types';

/** What a check may need beyond the value. */
export interface ValidationContext {
	/** The billing country, for postcodes. */
	country?: string;
	/** WooCommerce's postcode check (`isPostcode` from its checkout package). */
	isPostcode?: ( args: { postcode: string; country: string } ) => boolean;
}

const IMPLIED: Partial< Record< CheckoutField[ 'type' ], string > > = {
	number: 'number',
	email: 'email',
	url: 'url',
};

const DISPLAY_TYPES = [ 'heading', 'paragraph' ];
const CHOICE_TYPES = [ 'select', 'radio' ];

/**
 * Whether a value counts as empty.
 *
 * @param value Field value.
 */
export function isEmptyValue( value: FieldValue | undefined | null ): boolean {
	if ( typeof value === 'boolean' ) {
		return ! value;
	}

	return value === undefined || value === null || value.trim() === '';
}

/**
 * The label a message names the field by.
 *
 * @param field Field.
 */
export function fieldLabel( field: CheckoutField ): string {
	return field.label.trim() !== '' ? field.label : field.name;
}

/**
 * The "is required" message of a field.
 *
 * @param field Field.
 */
export function requiredMessage( field: CheckoutField ): string {
	return sprintf(
		/* translators: %s: field label. */
		__( '%s is required.', 'checkout-fieldkit' ),
		fieldLabel( field )
	);
}

/**
 * What is wrong with a value.
 *
 * @param field   Field.
 * @param value   Its value.
 * @param context Billing country and WooCommerce's postcode check.
 * @return The message, or '' when the value is fine.
 */
export function problem(
	field: CheckoutField,
	value: FieldValue | undefined | null,
	context: ValidationContext = {}
): string {
	if ( DISPLAY_TYPES.includes( field.type ) ) {
		return '';
	}

	if ( isEmptyValue( value ) ) {
		return field.required ? requiredMessage( field ) : '';
	}

	if ( typeof value !== 'string' ) {
		return '';
	}

	const text = value.trim();
	const label = fieldLabel( field );
	const implied = IMPLIED[ field.type ];
	const checks = Array.from(
		new Set( [
			...( implied ? [ implied ] : [] ),
			...( field.validation ?? [] ),
		] )
	);

	for ( const check of checks ) {
		const message = checkValue( check, text, label, context );
		if ( message ) {
			return message;
		}
	}

	if (
		CHOICE_TYPES.includes( field.type ) &&
		! ( field.options ?? [] ).some(
			( option ) => option.value !== '' && option.value === text
		)
	) {
		return sprintf(
			/* translators: %s: field label. */
			__( '%s: choose one of the options.', 'checkout-fieldkit' ),
			label
		);
	}

	return '';
}

/**
 * What is wrong with a non-empty value under one validation.
 *
 * @param check   'email', 'url', 'number', 'phone' or 'postcode'.
 * @param value   Trimmed, non-empty value.
 * @param label   Field label.
 * @param context Billing country and postcode check.
 */
function checkValue(
	check: string,
	value: string,
	label: string,
	context: ValidationContext
): string {
	switch ( check ) {
		case 'email':
			return isEmail( value ) ? '' : invalidMessage( 'email', label );
		case 'url':
			return isWebAddress( value ) ? '' : invalidMessage( 'url', label );
		case 'number':
			return /^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?$/.test( value )
				? ''
				: invalidMessage( 'number', label );
		case 'phone':
			// WC_Validation::is_phone_format(): only digits, spaces and # _ - + / ( ) .
			return value.replace( /[\s#0-9_\-+/().]/g, '' ).trim() === ''
				? ''
				: invalidMessage( 'phone', label );
		case 'postcode':
			return isPostcode( value, context )
				? ''
				: invalidMessage( 'postcode', label );
	}

	return '';
}

/**
 * The message for a value that fails a validation.
 *
 * @param check 'email', 'url', 'number', 'phone' or 'postcode'.
 * @param label Field label.
 */
function invalidMessage( check: string, label: string ): string {
	switch ( check ) {
		case 'email':
			return sprintf(
				/* translators: %s: field label. */
				__( '%s is not a valid email address.', 'checkout-fieldkit' ),
				label
			);
		case 'url':
			return sprintf(
				/* translators: %s: field label. */
				__(
					'%s is not a valid web address. Start it with http:// or https://.',
					'checkout-fieldkit'
				),
				label
			);
		case 'number':
			return sprintf(
				/* translators: %s: field label. */
				__( '%s must be a number.', 'checkout-fieldkit' ),
				label
			);
		case 'phone':
			return sprintf(
				/* translators: %s: field label. */
				__( '%s is not a valid phone number.', 'checkout-fieldkit' ),
				label
			);
		default:
			return sprintf(
				/* translators: %s: field label. */
				__( '%s is not a valid postcode / ZIP.', 'checkout-fieldkit' ),
				label
			);
	}
}

/**
 * Whether a value looks like an email address (as WordPress's is_email()
 * broadly accepts: something@domain.tld, no spaces).
 *
 * @param value Value.
 */
function isEmail( value: string ): boolean {
	return /^[^\s@]+@[^\s@.]+(\.[^\s@.]+)+$/.test( value );
}

/**
 * Whether a value is an http or https address with a host.
 *
 * @param value Value.
 */
function isWebAddress( value: string ): boolean {
	if ( ! /^https?:\/\/\S+$/i.test( value ) ) {
		return false;
	}

	try {
		return new URL( value ).hostname !== '';
	} catch {
		return false;
	}
}

/**
 * Whether a value is a postcode: only letters, digits, spaces and hyphens,
 * and valid for the billing country when WooCommerce's check is available.
 *
 * @param value   Value.
 * @param context Billing country and postcode check.
 */
function isPostcode( value: string, context: ValidationContext ): boolean {
	if ( /[^\sA-Za-z0-9-]/.test( value ) ) {
		return false;
	}

	if ( context.isPostcode && context.country ) {
		return context.isPostcode( {
			postcode: value,
			country: context.country,
		} );
	}

	return true;
}
