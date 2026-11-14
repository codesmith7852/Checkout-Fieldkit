/**
 * A native checkbox with its label, and an optional hint inside the label
 * (such as "5 fields").
 */
import { useId } from '@wordpress/element';
import type { ReactNode } from 'react';

export interface CheckboxProps {
	label: ReactNode;
	hint?: ReactNode;
	checked: boolean;
	onChange: ( checked: boolean ) => void;
	disabled?: boolean;
}

export function Checkbox( {
	label,
	hint,
	checked,
	onChange,
	disabled,
}: CheckboxProps ) {
	const id = useId();
	return (
		<div className="cfk-check">
			<input
				id={ id }
				type="checkbox"
				checked={ checked }
				disabled={ disabled }
				onChange={ ( event ) => onChange( event.target.checked ) }
			/>
			<label htmlFor={ id }>
				{ label }
				{ hint && (
					<>
						{ ' ' }
						<small className="cfk-check__hint">{ hint }</small>
					</>
				) }
			</label>
		</div>
	);
}
