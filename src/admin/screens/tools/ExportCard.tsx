/**
 * Export (spec §6.5, frame 5): tick what to include, then download the
 * server's export document under the file name the server suggests.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Card, Notice, useAnnounce } from '../../components';
import { useApi, useDraft, useFieldSet, useSettingsDraft } from '../../store';
import { errorMessage } from '../../store/errors';
import type { Resource } from '../../types';
import { exportCount, TARGETS, targetLabel } from './describe';
import { Checkbox } from './Checkbox';
import { downloadFile } from './download';
import { DownloadIcon } from './icons';

export function ExportCard() {
	const api = useApi();
	const announce = useAnnounce();
	const { dirtyResources } = useDraft();
	const classic = useFieldSet( 'classic' ).savedFieldSet;
	const block = useFieldSet( 'block' ).savedFieldSet;
	const { savedSettings } = useSettingsDraft();
	const [ chosen, setChosen ] = useState< Resource[] >( TARGETS );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );

	const nothingChosen = chosen.length === 0;
	const unsavedChosen = dirtyResources.some( ( target ) =>
		chosen.includes( target )
	);

	const toggle = ( target: Resource, checked: boolean ) =>
		setChosen( ( current ) =>
			TARGETS.filter( ( item ) =>
				item === target ? checked : current.includes( item )
			)
		);

	const download = async () => {
		if ( nothingChosen || busy ) {
			return;
		}
		setBusy( true );
		setError( null );
		try {
			const file = await api.exportSetup( chosen );
			downloadFile( file );
			announce(
				sprintf(
					/* translators: %s: file name. */
					__( 'Downloaded %s.', 'checkout-fieldkit' ),
					file.fileName
				)
			);
		} catch ( reason ) {
			setError( errorMessage( reason ) );
		} finally {
			setBusy( false );
		}
	};

	return (
		<Card
			className="cfk-tools-card"
			title={ __( 'Export', 'checkout-fieldkit' ) }
			description={ __(
				'Download your fields and settings as a file you can import on another site.',
				'checkout-fieldkit'
			) }
		>
			<div className="cfk-tools-card__body">
				<fieldset className="cfk-checks">
					<legend className="cfk-visually-hidden">
						{ __( 'What to export', 'checkout-fieldkit' ) }
					</legend>
					{ TARGETS.map( ( target ) => (
						<Checkbox
							key={ target }
							label={ targetLabel( target ) }
							hint={ exportCount(
								target,
								target === 'block' ? block : classic,
								savedSettings
							) }
							checked={ chosen.includes( target ) }
							onChange={ ( checked ) =>
								toggle( target, checked )
							}
						/>
					) ) }
				</fieldset>
				{ nothingChosen && (
					<p className="cfk-tools-card__hint">
						{ __(
							'Choose at least one thing to export.',
							'checkout-fieldkit'
						) }
					</p>
				) }
				{ unsavedChosen && (
					<p className="cfk-tools-card__hint">
						{ __(
							'The file holds what is saved. Save your changes first to include them.',
							'checkout-fieldkit'
						) }
					</p>
				) }
				{ error && (
					<Notice
						tone="error"
						role="alert"
						title={ __(
							'The file was not downloaded.',
							'checkout-fieldkit'
						) }
					>
						{ error }
					</Notice>
				) }
				<div className="cfk-tools-card__actions">
					<Button
						variant="primary"
						icon={ <DownloadIcon /> }
						isBusy={ busy }
						aria-disabled={ nothingChosen || busy || undefined }
						onClick={ download }
					>
						{ __( 'Download file', 'checkout-fieldkit' ) }
					</Button>
					<span className="cfk-tools-card__hint">
						{ __(
							'The file holds no orders or customer data.',
							'checkout-fieldkit'
						) }
					</span>
				</div>
			</div>
		</Card>
	);
}
