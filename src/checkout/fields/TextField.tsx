/**
 * Text, number, email and URL fields: WooCommerce's own validated text input,
 * with our checks added (the same as on the server).
 */
import { ValidatedTextInput } from '@woocommerce/blocks-components';
import { useCallback } from '@wordpress/element';
import { problem, requiredMessage } from '../validation';
import { fieldId, inputLabel, type FieldProps } from './shared';

/** Input attributes by field type. */
const INPUT: Record<
	string,
	{ type: string; inputMode?: string; autoComplete?: string }
> = {
	text: { type: 'text' },
	// A text input, so decimals and signs are typed freely; checked as a number.
	number: { type: 'text', inputMode: 'decimal' },
	email: { type: 'email', autoComplete: 'email' },
	url: { type: 'url', autoComplete: 'url' },
};

export default function TextField( {
	field,
	value,
	onChange,
	context,
}: FieldProps ) {
	const input = INPUT[ field.type ] ?? INPUT.text;

	const customValidation = useCallback(
		( element: HTMLInputElement ) => {
			const message = problem( field, element.value, context );
			if ( message !== '' ) {
				element.setCustomValidity( message );
				return false;
			}

			return true;
		},
		[ field, context ]
	);

	// The browser's own checks (a required value, the email and URL formats)
	// run first; give them our messages too ('' keeps the browser's).
	const customValidityMessage = useCallback(
		( validity: ValidityState ) =>
			validity.valueMissing
				? requiredMessage( field )
				: problem( field, value, context ),
		[ field, value, context ]
	);

	return (
		<ValidatedTextInput
			id={ fieldId( field ) }
			errorId={ fieldId( field ) }
			type={ input.type }
			inputMode={ input.inputMode }
			autoComplete={ input.autoComplete ?? 'off' }
			autoCapitalize={ field.type === 'text' ? undefined : 'none' }
			label={ inputLabel( field ) }
			placeholder={ field.placeholder || undefined }
			value={ value }
			onChange={ onChange }
			required={ !! field.required }
			customValidation={ customValidation }
			customValidityMessage={ customValidityMessage }
		/>
	);
}
