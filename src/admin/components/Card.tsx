/**
 * Card: a white panel with a 10px radius. With a title it is a region named
 * by its heading.
 */
import { useId } from '@wordpress/element';
import type { ReactNode } from 'react';
import { cx } from './cx';

export interface CardProps {
	title?: ReactNode;
	/** Heading level of the title; 2 by default. */
	headingLevel?: 2 | 3 | 4;
	description?: ReactNode;
	footer?: ReactNode;
	/** Accessible name when the card has no visible title. */
	label?: string;
	tone?: 'default' | 'danger';
	className?: string;
	children?: ReactNode;
}

export function Card( {
	title,
	headingLevel = 2,
	description,
	footer,
	label,
	tone = 'default',
	className,
	children,
}: CardProps ) {
	const titleId = useId();
	const Heading = `h${ headingLevel }` as 'h2' | 'h3' | 'h4';

	return (
		<section
			className={ cx(
				'cfk-card',
				tone === 'danger' && 'cfk-card--danger',
				className
			) }
			aria-labelledby={ title ? titleId : undefined }
			aria-label={ title ? undefined : label }
		>
			{ title && (
				<div className="cfk-card__header">
					<Heading id={ titleId } className="cfk-card__title">
						{ title }
					</Heading>
					{ description && (
						<p className="cfk-card__description">{ description }</p>
					) }
				</div>
			) }
			{ children }
			{ footer && <div className="cfk-card__footer">{ footer }</div> }
		</section>
	);
}
