/**
 * Switch: an on/off button with role="switch" and aria-checked (spec §6.6).
 * Name it with `label`, or with `labelledBy` pointing at visible text.
 */
import { forwardRef } from '@wordpress/element';
import { cx } from './cx';

export interface SwitchProps {
	checked: boolean;
	onChange: ( checked: boolean ) => void;
	/** Accessible name, when no visible text names the switch. */
	label?: string;
	/** Id of the element whose text names the switch. */
	labelledBy?: string;
	/** Id(s) of elements that describe the switch. */
	describedBy?: string;
	disabled?: boolean;
	size?: 'default' | 'small';
	id?: string;
	className?: string;
}

export const Switch = forwardRef< HTMLButtonElement, SwitchProps >(
	function SwitchRender(
		{
			checked,
			onChange,
			label,
			labelledBy,
			describedBy,
			disabled = false,
			size = 'default',
			id,
			className,
		},
		ref
	) {
		return (
			<button
				ref={ ref }
				id={ id }
				type="button"
				role="switch"
				aria-checked={ checked }
				aria-label={ labelledBy ? undefined : label }
				aria-labelledby={ labelledBy }
				aria-describedby={ describedBy }
				disabled={ disabled }
				className={ cx(
					'cfk-switch',
					size === 'small' && 'cfk-switch--small',
					checked && 'is-checked',
					className
				) }
				onClick={ () => onChange( ! checked ) }
			>
				<span className="cfk-switch__thumb" aria-hidden="true" />
			</button>
		);
	}
);
