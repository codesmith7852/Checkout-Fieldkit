/**
 * A dropdown in the markup of WooCommerce's checkout selects, so its styles apply.
 */
import { problem } from '../validation';
import FieldError, { errorIdFor } from './FieldError';
import { classNames, fieldId, inputLabel, type FieldProps } from './shared';
import useFieldValidation from './useFieldValidation';

export default function SelectField( {
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
	const options = field.options ?? [];
	// Without an empty first option, offer one so nothing is chosen for the shopper.
	const prompt =
		options.length > 0 && options[ 0 ].value === ''
			? []
			: [ { value: '', label: field.placeholder || '' } ];

	return (
		<div
			className={ classNames(
				'cfk-checkout-select',
				shownError && 'has-error'
			) }
		>
			<div className="wc-blocks-components-select">
				<div className="wc-blocks-components-select__container">
					<label
						htmlFor={ id }
						className="wc-blocks-components-select__label"
					>
						{ inputLabel( field ) }
					</label>
					<select
						id={ id }
						className="wc-blocks-components-select__select"
						value={ value }
						required={ !! field.required }
						aria-invalid={ !! shownError }
						aria-describedby={
							shownError ? errorIdFor( id ) : undefined
						}
						onChange={ ( event ) => {
							onChange( event.target.value );
							reveal();
						} }
						onBlur={ reveal }
					>
						{ [ ...prompt, ...options ].map( ( option ) => (
							<option key={ option.value } value={ option.value }>
								{ option.label }
							</option>
						) ) }
					</select>
					<svg
						className="wc-blocks-components-select__expand"
						viewBox="0 0 24 24"
						width="24"
						height="24"
						aria-hidden="true"
						focusable="false"
					>
						<path
							d="M7 10l5 5 5-5"
							fill="none"
							stroke="currentColor"
							strokeWidth="1.5"
						/>
					</svg>
				</div>
			</div>
			<FieldError id={ errorIdFor( id ) } message={ shownError } />
		</div>
	);
}
