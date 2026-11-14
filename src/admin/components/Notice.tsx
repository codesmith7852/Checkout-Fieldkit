/**
 * Notice: a message in the page, such as "shoppers don't see these fields"
 * or a save error. It does not announce itself; pass `role` ("status" or
 * "alert") when it should, or use the live region.
 */
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

export function Notice( {
	tone = 'info',
	title,
	children,
	actions,
	onDismiss,
	role,
	className,
	id,
	tabIndex,
}: NoticeProps ) {
	const Icon = ICONS[ tone ];
	return (
		<div
			className={ cx( 'cfk-notice', `cfk-notice--${ tone }`, className ) }
			role={ role }
			id={ id }
			tabIndex={ tabIndex }
		>
			<Icon className="cfk-notice__icon" />
			<div className="cfk-notice__body">
				{ title && <p className="cfk-notice__title">{ title }</p> }
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
					label={ __( 'Dismiss this notice', 'checkout-fieldkit' ) }
					icon={ <CloseIcon /> }
					onClick={ onDismiss }
				/>
			) }
		</div>
	);
}
