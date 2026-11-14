/**
 * Button: primary, secondary, ghost or danger, in two sizes.
 */
import { forwardRef } from '@wordpress/element';
import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cx } from './cx';

export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';

export interface ButtonProps extends ButtonHTMLAttributes< HTMLButtonElement > {
	variant?: ButtonVariant;
	size?: 'default' | 'small';
	/** An icon before the text; decorative. */
	icon?: ReactNode;
	/** Accessible name; required when the button shows only an icon. */
	label?: string;
	/** Shows the button as working (aria-busy). */
	isBusy?: boolean;
}

export const Button = forwardRef< HTMLButtonElement, ButtonProps >(
	function ButtonRender(
		{
			variant = 'secondary',
			size = 'default',
			icon,
			label,
			isBusy = false,
			className,
			children,
			type = 'button',
			...props
		},
		ref
	) {
		const iconOnly = !! icon && ! children;
		return (
			<button
				ref={ ref }

				type={ type }
				className={ cx(
					'cfk-button',
					`cfk-button--${ variant }`,
					size === 'small' && 'cfk-button--small',
					iconOnly && 'cfk-button--icon-only',
					isBusy && 'is-busy',
					className
				) }
				aria-label={ label }
				aria-busy={ isBusy || undefined }
				{ ...props }
			>
				{ icon && (
					<span className="cfk-button__icon" aria-hidden="true">
						{ icon }
					</span>
				) }
				{ children }
			</button>
		);
	}
);
