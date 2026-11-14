/**
 * The title row of a screen: the page's h1, an intro line and optional
 * controls on the right. The shell moves focus to the h1 when the tab changes.
 */
import type { ReactNode } from 'react';

export interface ScreenHeaderProps {
	title: string;
	description?: ReactNode;
	/** Controls shown at the end of the row, such as the checkout type switch. */
	actions?: ReactNode;
}

export function ScreenHeader( {
	title,
	description,
	actions,
}: ScreenHeaderProps ) {
	return (
		<div className="cfk-screen-header">
			<div className="cfk-screen-header__text">
				<h1 className="cfk-screen-header__title" tabIndex={ -1 }>
					{ title }
				</h1>
				{ description && (
					<p className="cfk-screen-header__description">
						{ description }
					</p>
				) }
			</div>
			{ actions && (
				<div className="cfk-screen-header__actions">{ actions }</div>
			) }
		</div>
	);
}
