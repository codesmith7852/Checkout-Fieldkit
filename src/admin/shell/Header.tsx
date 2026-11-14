/**
 * The app header (frames 1, 2): logo and name, the Fields · Settings · Tools
 * tabs, the unsaved-changes count or "All changes saved", Discard and Save
 * changes.
 *
 * Save changes stays focusable when there is nothing to save (aria-disabled
 * rather than disabled), so focus is not lost when a save finishes.
 */
import { useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { RefObject } from 'react';
import { Button, CheckIcon, Dialog, LogoMark, cx } from '../components';
import { useLocation, type Tab } from '../location';
import { useDraft } from '../store';
import { isPlainClick } from './links';

export interface HeaderProps {
	onSave: () => void;
	saveRef: RefObject< HTMLButtonElement >;
}

function tabLabel( tab: Tab ): string {
	if ( tab === 'settings' ) {
		return __( 'Settings', 'checkout-fieldkit' );
	}
	if ( tab === 'tools' ) {
		return __( 'Tools', 'checkout-fieldkit' );
	}
	return __( 'Fields', 'checkout-fieldkit' );
}

const TABS: Tab[] = [ 'fields', 'settings', 'tools' ];

export function Header( { onSave, saveRef }: HeaderProps ) {
	const { location, navigate, hrefFor } = useLocation();
	const { changeCount, isDirty, isSaving, discard } = useDraft();
	const [ confirmingDiscard, setConfirmingDiscard ] = useState( false );
	const keepEditingRef = useRef< HTMLButtonElement >( null );
	const canSave = isDirty && ! isSaving;
	// Tools acts at once and has nothing to save; show the controls there only for edits made elsewhere.
	const showSaveControls = location.tab !== 'tools' || isDirty || isSaving;

	return (
		<header className="cfk-header">
			<div className="cfk-header__brand">
				<span className="cfk-header__logo" aria-hidden="true">
					<LogoMark />
				</span>
				{ __( 'Checkout FieldKit', 'checkout-fieldkit' ) }
			</div>

			<nav
				className="cfk-header__nav"
				aria-label={ __( 'Checkout FieldKit', 'checkout-fieldkit' ) }
			>
				{ TABS.map( ( tab ) => {
					const current = location.tab === tab;
					return (
						<a
							key={ tab }
							href={ hrefFor( { tab } ) }
							className={ cx(
								'cfk-header__tab',
								current && 'is-current'
							) }
							aria-current={ current ? 'page' : undefined }
							onClick={ ( event ) => {
								if ( isPlainClick( event ) ) {
									event.preventDefault();
									navigate( { tab } );
								}
							} }
						>
							{ tabLabel( tab ) }
						</a>
					);
				} ) }
			</nav>

			<span className="cfk-header__spacer" />

			{ showSaveControls && (
				<div className="cfk-header__save">
					{ isDirty ? (
						<span className="cfk-header__unsaved">
							<span
								className="cfk-header__dot"
								aria-hidden="true"
							/>
							{ sprintf(
								/* translators: %d: number of unsaved changes. */
								_n(
									'%d unsaved change',
									'%d unsaved changes',
									changeCount,
									'checkout-fieldkit'
								),
								changeCount
							) }
						</span>
					) : (
						<span className="cfk-header__saved">
							<CheckIcon className="cfk-header__saved-icon" />
							{ __( 'All changes saved', 'checkout-fieldkit' ) }
						</span>
					) }
					{ isDirty && (
						<Button
							variant="ghost"
							disabled={ isSaving }
							onClick={ () => setConfirmingDiscard( true ) }
						>
							{ __( 'Discard', 'checkout-fieldkit' ) }
						</Button>
					) }
					<Button
						ref={ saveRef }
						variant="primary"
						aria-disabled={ canSave ? undefined : true }
						isBusy={ isSaving }
						onClick={ () => {
							if ( canSave ) {
								onSave();
							}
						} }
					>
						{ isSaving
							? __( 'Saving…', 'checkout-fieldkit' )
							: __( 'Save changes', 'checkout-fieldkit' ) }
					</Button>
				</div>
			) }

			{ confirmingDiscard && (
				<Dialog
					role="alertdialog"
					title={ __(
						'Discard unsaved changes?',
						'checkout-fieldkit'
					) }
					onClose={ () => setConfirmingDiscard( false ) }
					initialFocusRef={ keepEditingRef }
					size="small"
					actions={
						<>
							<Button
								variant="danger"
								onClick={ () => {
									setConfirmingDiscard( false );
									discard();
									saveRef.current?.focus();
								} }
							>
								{ __( 'Discard changes', 'checkout-fieldkit' ) }
							</Button>
							<Button
								ref={ keepEditingRef }
								variant="primary"
								onClick={ () => setConfirmingDiscard( false ) }
							>
								{ __( 'Keep editing', 'checkout-fieldkit' ) }
							</Button>
						</>
					}
				>
					<p>
						{ sprintf(
							/* translators: %d: number of unsaved changes. */
							_n(
								'Your %d unsaved change will be lost. The checkout stays as it was last saved.',
								'Your %d unsaved changes will be lost. The checkout stays as it was last saved.',
								changeCount,
								'checkout-fieldkit'
							),
							changeCount
						) }
					</p>
				</Dialog>
			) }
		</header>
	);
}
