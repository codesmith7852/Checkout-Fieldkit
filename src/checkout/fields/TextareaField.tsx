/**
 * A multi-line text field in WooCommerce's textarea style.
 */
import { problem } from '../validation';
import FieldError, { errorIdFor } from './FieldError';
import { classNames, fieldId, inputLabel, type FieldProps } from './shared';
import useFieldValidation from './useFieldValidation';

export default function TextareaField( {
	field,
	value,
	onChange,
	context,
}: FieldProps ) {
	const id = fieldId( field );
	const { shownError, reveal } = useFieldValidation(
		id,
		problem( field, value, context )
	);

	return (
		<div
			className={ classNames(
				'cfk-checkout-textarea',
				shownError && 'has-error'
			) }
		>
			<label className="cfk-checkout-field__label" htmlFor={ id }>
				{ inputLabel( field ) }
			</label>
			<textarea
				id={ id }
				className="wc-block-components-textarea"
				value={ value }
				placeholder={ field.placeholder || undefined }
				required={ !! field.required }
				rows={ 3 }
				aria-invalid={ !! shownError }
				aria-describedby={ shownError ? errorIdFor( id ) : undefined }
				onChange={ ( event ) => onChange( event.target.value ) }
				onBlur={ reveal }
			/>
			<FieldError id={ errorIdFor( id ) } message={ shownError } />
		</div>
	);
}
