/**
 * Text for screen readers only.
 */
import type { HTMLAttributes } from 'react';
import { cx } from './cx';

export function VisuallyHidden( {
	className,
	...props
}: HTMLAttributes< HTMLSpanElement > ) {
	return (
		<span
			className={ cx( 'cfk-visually-hidden', className ) }
			{ ...props }
		/>
	);
}
