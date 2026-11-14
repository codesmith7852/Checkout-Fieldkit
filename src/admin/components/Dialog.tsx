/**
 * Dialog: WordPress's Modal (focus trap, Escape, focus return, aria-modal)
 * with our look. Actions go in a footer, primary action last.
 */
import { Modal } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import type { ComponentProps, ReactNode, RefObject } from 'react';
import { cx } from './cx';

type ModalProps = ComponentProps< typeof Modal >;

export interface DialogProps {
	title: string;
	onClose: () => void;
	children?: ReactNode;
	/** Buttons, primary last. */
	actions?: ReactNode;
	/** `alertdialog` for confirmations of something risky. */
	role?: 'dialog' | 'alertdialog';
	/** Where focus goes when the dialog opens; see Modal's focusOnMount. */
	focusOnMount?: ModalProps[ 'focusOnMount' ];
	/**
	 * The element to focus when the dialog opens, such as the safe choice of
	 * a confirmation ("Keep it on"). Overrides focusOnMount.
	 */
	initialFocusRef?: RefObject< HTMLElement >;
	/** Id of the element describing the dialog, read out when it opens. */
	describedBy?: string;
	size?: 'small' | 'medium' | 'large';
	className?: string;
}

export function Dialog( {
	title,
	onClose,
	children,
	actions,
	role = 'dialog',
	focusOnMount = 'firstContentElement',
	initialFocusRef,
	describedBy,
	size = 'medium',
	className,
}: DialogProps ) {
	useEffect( () => {
		initialFocusRef?.current?.focus();
	}, [ initialFocusRef ] );

	return (
		<Modal
			title={ title }
			onRequestClose={ onClose }
			role={ role }
			focusOnMount={ initialFocusRef ? false : focusOnMount }
			aria={ { describedby: describedBy } }
			size={ size }
			className={ cx( 'cfk-dialog', className ) }
			overlayClassName="cfk-dialog-overlay"
		>
			<div className="cfk-dialog__body">{ children }</div>
			{ actions && (
				<div className="cfk-dialog__actions">{ actions }</div>
			) }
		</Modal>
	);
}
