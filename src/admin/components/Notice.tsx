/**
 * Notice: a message in the page, such as "shoppers don't see these fields"
 * or a save error. It does not announce itself; pass `role` ("status" or
 * "alert") when it should, or use the live region. With a `role` and a
 * `title`, the title names the notice, so a focused notice (`tabIndex={ -1 }`)
 * reads its title first.
 */
import { forwardRef, useId } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { AriaRole, ReactNode } from 'react';
import { Button } from './Button';
import { cx } from './cx';
import {
	CheckIcon,
	CloseIcon,
	ErrorIcon,
	InfoIcon,
	WarningIcon,
} from './icons';

export type NoticeTone = 'info' | 'warning' | 'error' | 'success';

export interface NoticeProps {
	tone?: NoticeTone;
	title?: ReactNode;
	children?: ReactNode;
	/** Buttons or links shown under the text. */
	actions?: ReactNode;
	/** Shows a close button when given. */
	onDismiss?: () => void;
	role?: AriaRole;
	className?: string;
	id?: string;
	tabIndex?: number;
}

const ICONS = {
	info: InfoIcon,
	warning: WarningIcon,
	error: ErrorIcon,
	success: CheckIcon,
};

export const Notice = forwardRef< HTMLDivElement, NoticeProps >(
	function NoticeRender(
		{
			tone = 'info',
			title,
			children,
			actions,
			onDismiss,
			role,
			className,
			id,
			tabIndex,
		},
		ref
	) {
		const titleId = useId();
		const Icon = ICONS[ tone ];
		return (
			<div
				ref={ ref }
				className={ cx(
					'cfk-notice',
					`cfk-notice--${ tone }`,
					className
				) }
				role={ role }
				aria-labelledby={ role && title ? titleId : undefined }
				id={ id }
				tabIndex={ tabIndex }
			>
				<Icon className="cfk-notice__icon" />
				<div className="cfk-notice__body">
					{ title && (
						<p className="cfk-notice__title" id={ titleId }>
							{ title }
						</p>
					) }
					{ children && (
						<div className="cfk-notice__content">{ children }</div>
					) }
					{ actions && (
						<div className="cfk-notice__actions">{ actions }</div>
					) }
				</div>
				{ onDismiss && (
					<Button
						variant="ghost"
						size="small"
						className="cfk-notice__dismiss"
						label={ __(
							'Dismiss this notice',
							'checkout-fieldkit'
						) }
						icon={ <CloseIcon /> }
						onClick={ onDismiss }
					/>
				) }
			</div>
		);
	}
);
