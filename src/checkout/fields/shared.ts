/**
 * Small helpers shared by the field components.
 */
import { __, sprintf } from '@wordpress/i18n';
import type { CheckoutField, FieldValue } from '../types';
import type { ValidationContext } from '../validation';

/** Props every value field gets. */
export interface FieldProps< V extends FieldValue = string > {
	field: CheckoutField;
	value: V;
	onChange: ( value: V ) => void;
	context: ValidationContext;
}

/**
 * The id of a field's input; also its id in WooCommerce's validation store.
 *
 * @param field Field.
 */
export function fieldId( field: CheckoutField ): string {
	return `checkout-fieldkit-${ field.name }`;
}

/**
 * The label shown with the input: the field's label (its name when empty),
 * with "(optional)" when the field is not required, as WooCommerce's own
 * checkout fields do.
 *
 * @param field Field.
 */
export function inputLabel( field: CheckoutField ): string {
	const label = field.label.trim() !== '' ? field.label : field.name;

	return field.required
		? label
		: /* translators: %s: field label. */
			sprintf( __( '%s (optional)', 'checkout-fieldkit' ), label );
}

/**
 * The value a field starts with: its default, when that is valid for it.
 *
 * @param field Field.
 */
export function initialValue( field: CheckoutField ): FieldValue {
	const value = field.default ?? '';
	if ( field.type === 'checkbox' ) {
		return value === '1';
	}
	if ( field.type === 'select' || field.type === 'radio' ) {
		return ( field.options ?? [] ).some(
			( option ) => option.value === value
		)
			? value
			: '';
	}

	return value;
}

/**
 * Joins class names, leaving out empty ones.
 *
 * @param names Class names.
 */
export function classNames(
	...names: Array< string | false | undefined >
): string {
	return names.filter( Boolean ).join( ' ' );
}
