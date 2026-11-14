/**
 * Radio buttons in a group named by the field label, in the markup of
 * WooCommerce's checkout radio controls.
 */
import { problem } from '../validation';
import FieldError, { errorIdFor } from './FieldError';
import { classNames, fieldId, inputLabel, type FieldProps } from './shared';
import useFieldValidation from './useFieldValidation';

export default function RadioField( {
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
	const options = ( field.options ?? [] ).filter(
		( option ) => option.value !== ''
	);

	return (
		<div
			className={ classNames(
				'cfk-checkout-radio',
				shownError && 'has-error'
			) }
		>
			<div
				id={ id }
				role="radiogroup"
				aria-labelledby={ `${ id }-label` }
				aria-required={ !! field.required }
				aria-invalid={ !! shownError }
				aria-describedby={ shownError ? errorIdFor( id ) : undefined }
				className="wc-block-components-radio-control"
			>
				<p id={ `${ id }-label` } className="cfk-checkout-field__label">
					{ inputLabel( field ) }
				</p>
				{ options.map( ( option ) => {
					const optionId = `${ id }-${ option.value }`;
					const checked = value === option.value;

					return (
						// The option's text is inside the label, in WooCommerce's layout.
						// eslint-disable-next-line jsx-a11y/label-has-associated-control
						<label
							key={ option.value }
							htmlFor={ optionId }
							className={ classNames(
								'wc-block-components-radio-control__option',
								checked &&
									'wc-block-components-radio-control__option-checked'
							) }
						>
							<input
								id={ optionId }
								type="radio"
								name={ id }
								value={ option.value }
								checked={ checked }
								className="wc-block-components-radio-control__input"
								onChange={ () => {
									onChange( option.value );
									reveal();
								} }
								onBlur={ reveal }
							/>
							<div className="wc-block-components-radio-control__option-layout">
								<div className="wc-block-components-radio-control__label-group">
									<span className="wc-block-components-radio-control__label">
										{ option.label }
									</span>
								</div>
							</div>
						</label>
					);
				} ) }
			</div>
			<FieldError id={ errorIdFor( id ) } message={ shownError } />
		</div>
	);
}
