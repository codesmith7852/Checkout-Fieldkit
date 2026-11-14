/**
 * Reset (spec §6.5, frame 5): return a field set or the settings to the
 * defaults, after a confirmation that states what is lost.
 */
import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Card, Dialog, Notice, useAnnounce } from '../../components';
import { useApi, useDraft, useFieldSet } from '../../store';
import { errorMessage } from '../../store/errors';
import type { FieldSet, Resource, Settings } from '../../types';
import { resetConsequence, TARGETS, targetLabel } from './describe';

function openLabel( target: Resource ): string {
	if ( target === 'classic' ) {
		return __( 'Reset Classic checkout…', 'checkout-fieldkit' );
	}
	if ( target === 'block' ) {
		return __( 'Reset Block checkout…', 'checkout-fieldkit' );
	}
	return __( 'Reset settings…', 'checkout-fieldkit' );
}

function confirmLabel( target: Resource ): string {
	if ( target === 'classic' ) {
		return __( 'Reset Classic checkout', 'checkout-fieldkit' );
	}
	if ( target === 'block' ) {
		return __( 'Reset Block checkout', 'checkout-fieldkit' );
	}
	return __( 'Reset settings', 'checkout-fieldkit' );
}

function dialogTitle( target: Resource ): string {
	if ( target === 'classic' ) {
		return __( 'Reset Classic checkout fields?', 'checkout-fieldkit' );
	}
	if ( target === 'block' ) {
		return __( 'Reset Block checkout fields?', 'checkout-fieldkit' );
	}
	return __( 'Reset settings?', 'checkout-fieldkit' );
}

function doneMessage( target: Resource ): string {
	if ( target === 'settings' ) {
		return __( 'Settings reset to their defaults.', 'checkout-fieldkit' );
	}
	return sprintf(
		/* translators: %s: "Classic checkout fields" or "Block checkout fields". */
		__( '%s reset to WooCommerce’s defaults.', 'checkout-fieldkit' ),
		targetLabel( target )
	);
}

export function ResetCard() {
	const api = useApi();
	const announce = useAnnounce();
	const { dirtyResources, replaceSaved } = useDraft();
	const fieldSets = {
		classic: useFieldSet( 'classic' ).savedFieldSet,
		block: useFieldSet( 'block' ).savedFieldSet,
	};
	const cancelRef = useRef< HTMLButtonElement >( null );
	const [ target, setTarget ] = useState< Resource | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );

	const fieldSetOf = ( item: Resource ) =>
		item === 'settings' ? null : fieldSets[ item ];

	const open = ( item: Resource ) => {
		setError( null );
		setTarget( item );
	};

	const close = () => {
		if ( ! busy ) {
			setTarget( null );
		}
	};

	const confirm = async () => {
		if ( ! target || busy ) {
			return;
		}
		setBusy( true );
		setError( null );
		try {
			const result = await api.reset( target );
			const value = result[ target ];
			if ( target === 'settings' ) {
				if ( value ) {
					replaceSaved( target, value as Settings );
				}
			} else if ( value ) {
				replaceSaved( target, value as FieldSet );
			}
			setTarget( null );
			announce( doneMessage( target ) );
		} catch ( reason ) {
			setError(
				sprintf(
					/* translators: %s: why the reset failed. */
					__( 'Nothing was reset. %s', 'checkout-fieldkit' ),
					errorMessage( reason )
				)
			);
		} finally {
			setBusy( false );
		}
	};

	return (
		<Card
			tone="danger"
			className="cfk-tools-card"
			title={ __( 'Reset', 'checkout-fieldkit' ) }
			description={ __(
				'Go back to WooCommerce’s own fields. Orders keep the answers already saved on them.',
				'checkout-fieldkit'
			) }
		>
			{ TARGETS.map( ( item ) => (
				<div key={ item } className="cfk-reset-row">
					<div className="cfk-reset-row__text">
						<strong>{ targetLabel( item ) }</strong>
						<span className="cfk-reset-row__consequence">
							{ resetConsequence( item, fieldSetOf( item ) ) }
						</span>
					</div>
					<Button variant="danger" onClick={ () => open( item ) }>
						{ openLabel( item ) }
					</Button>
				</div>
			) ) }

			{ target && (
				<Dialog
					role="alertdialog"
					title={ dialogTitle( target ) }
					onClose={ close }
					initialFocusRef={ cancelRef }
					size="small"
					actions={
						<>
							<Button
								ref={ cancelRef }
								disabled={ busy }
								onClick={ close }
							>
								{ __( 'Cancel', 'checkout-fieldkit' ) }
							</Button>
							<Button
								variant="danger"
								isBusy={ busy }
								aria-disabled={ busy || undefined }
								onClick={ confirm }
							>
								{ confirmLabel( target ) }
							</Button>
						</>
					}
				>
					<p>
						{ resetConsequence(
							target,
							fieldSetOf( target ),
							true
						) }
					</p>
					{ target !== 'settings' && (
						<p>
							{ __(
								'Orders keep the answers already saved on them.',
								'checkout-fieldkit'
							) }
						</p>
					) }
					{ dirtyResources.includes( target ) && (
						<p>
							{ __(
								'Your unsaved changes here are lost too.',
								'checkout-fieldkit'
							) }
						</p>
					) }
					{ error && (
						<Notice tone="error" role="alert">
							{ error }
						</Notice>
					) }
				</Dialog>
			) }
		</Card>
	);
}
