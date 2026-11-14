/**
 * A checkbox: WooCommerce's own checkbox control, with its error under it.
 */
import { CheckboxControl } from '@woocommerce/blocks-components';
import { problem } from '../validation';
import FieldError, { errorIdFor } from './FieldError';
import { fieldId, inputLabel, type FieldProps } from './shared';
import useFieldValidation from './useFieldValidation';

export default function CheckboxField( {
	field,
	value,
	onChange,
	context,
}: FieldProps< boolean > ) {
	const id = fieldId( field );
	const { shownError, reveal } = useFieldValidation(
		id,
		problem( field, value, context )
	);

	return (
		<div className="cfk-checkout-checkbox">
			<CheckboxControl
				id={ id }
				label={ inputLabel( field ) }
				checked={ value }
				required={ !! field.required }
				hasError={ !! shownError }
				aria-describedby={ shownError ? errorIdFor( id ) : undefined }
				onChange={ ( checked ) => {
					onChange( checked );
					reveal();
				} }
			/>
			<FieldError id={ errorIdFor( id ) } message={ shownError } />
		</div>
	);
}
