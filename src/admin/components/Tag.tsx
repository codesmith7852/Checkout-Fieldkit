/**
 * Tag (square corners, such as Custom or Essential) and Pill (round, such as
 * In use).
 */
import type { HTMLAttributes } from 'react';
import { cx } from './cx';

export type TagTone = 'neutral' | 'accent' | 'warning' | 'success' | 'danger';

export interface TagProps extends HTMLAttributes< HTMLSpanElement > {
	tone?: TagTone;
}

export function Tag( { tone = 'neutral', className, ...props }: TagProps ) {
	return (
		<span
			className={ cx( 'cfk-tag', `cfk-tag--${ tone }`, className ) }
			{ ...props }
		/>
	);
}

export function Pill( { className, ...props }: TagProps ) {
	return <Tag className={ cx( 'cfk-tag--pill', className ) } { ...props } />;
}
