/**
 * Import (spec §6.5, frame 5): choose an export file, see what the server
 * says it would change, optionally download a backup, then replace. After
 * the import the store takes the imported data as saved.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { ChangeEvent } from 'react';
import type { ApiError } from '../../api';
import {
	Button,
	Card,
	Dialog,
	Notice,
	Tag,
	useAnnounce,
} from '../../components';
import { useApi, useDraft, useFieldSet, useSettingsDraft } from '../../store';
import { errorMessage } from '../../store/errors';
import type {
	FieldSet,
	ImportError,
	ImportPreview,
	Settings,
} from '../../types';
import {
	importProblem,
	list,
	previewSummary,
	TARGETS,
	targetLabel,
} from './describe';
import { Checkbox } from './Checkbox';
import { downloadFile, readText } from './download';
import { FileIcon } from './icons';

interface Problem {
	title: string;
	details: string[];
}

interface Chosen {
	name: string;
	document: Record< string, unknown >;
	preview: ImportPreview;
}

function isImportRefusal(
	error: unknown
): error is ApiError & { data: { errors: ImportError[] } } {
	const data = ( error as ApiError | null )?.data;
	return !! data && data.status === 422 && Array.isArray( data.errors );
}

export function ImportCard() {
	const api = useApi();
	const announce = useAnnounce();
	const { dirtyResources, replaceSaved } = useDraft();
	const saved = {
		classic: useFieldSet( 'classic' ).savedFieldSet,
		block: useFieldSet( 'block' ).savedFieldSet,
		settings: useSettingsDraft().savedSettings,
	};
	const inputRef = useRef< HTMLInputElement >( null );
	const keepEditingRef = useRef< HTMLButtonElement >( null );
	const chooseRef = useRef< HTMLButtonElement >( null );
	const doneRef = useRef< HTMLDivElement >( null );
	// Where focus goes once the controls it was on have gone.
	const [ focusTo, setFocusTo ] = useState< 'choose' | 'done' | null >(
		null
	);

	useEffect( () => {
		if ( focusTo === 'choose' ) {
			chooseRef.current?.focus();
		} else if ( focusTo === 'done' ) {
			doneRef.current?.focus();
		}
		if ( focusTo ) {
			setFocusTo( null );
		}
	}, [ focusTo ] );

	const [ chosen, setChosen ] = useState< Chosen | null >( null );
	const [ checking, setChecking ] = useState< string | null >( null );
	const [ importing, setImporting ] = useState( false );
	const [ backup, setBackup ] = useState( true );
	const [ confirming, setConfirming ] = useState( false );
	const [ problem, setProblem ] = useState< Problem | null >( null );
	const [ done, setDone ] = useState< string | null >( null );

	const replaced = chosen
		? chosen.preview.targets
				.filter( ( entry ) => entry.action === 'replace' )
				.map( ( entry ) => entry.target )
		: [];
	const overwritten = dirtyResources.filter( ( target ) =>
		replaced.includes( target )
	);

	const start = () => {
		setChosen( null );
		setProblem( null );
		setDone( null );
	};

	const onFile = async ( event: ChangeEvent< HTMLInputElement > ) => {
		const file = event.target.files?.[ 0 ];
		// Let the same file be chosen again after a fix.
		event.target.value = '';
		if ( ! file ) {
			return;
		}
		start();
		setChecking( file.name );

		try {
			let document: Record< string, unknown >;
			try {
				document = JSON.parse( await readText( file ) );
			} catch {
				setProblem( {
					title: sprintf(
						/* translators: %s: file name. */
						__(
							'%s is not a valid JSON file, so it can’t be a Checkout FieldKit export.',
							'checkout-fieldkit'
						),
						file.name
					),
					details: [
						__( 'Nothing was changed.', 'checkout-fieldkit' ),
					],
				} );
				return;
			}

			try {
				const preview = await api.previewImport( document );
				setChosen( { name: file.name, document, preview } );
			} catch ( reason ) {
				setProblem(
					isImportRefusal( reason )
						? {
								title: sprintf(
									/* translators: %s: file name. */
									__(
										'%s can’t be imported. Nothing was changed.',
										'checkout-fieldkit'
									),
									file.name
								),
								details:
									reason.data.errors.map( importProblem ),
							}
						: {
								title: __(
									'The file could not be checked. Nothing was changed.',
									'checkout-fieldkit'
								),
								details: [ errorMessage( reason ) ],
							}
				);
			}
		} finally {
			setChecking( null );
		}
	};

	const runImport = async () => {
		if ( ! chosen || importing ) {
			return;
		}
		setConfirming( false );
		setImporting( true );
		setProblem( null );

		try {
			if ( backup ) {
				try {
					downloadFile( await api.exportSetup( TARGETS ) );
				} catch ( reason ) {
					setProblem( {
						title: __(
							'The copy of your current setup could not be downloaded, so nothing was imported.',
							'checkout-fieldkit'
						),
						details: [ errorMessage( reason ) ],
					} );
					return;
				}
			}

			let result;
			try {
				result = await api.importSetup( chosen.document );
			} catch ( reason ) {
				setProblem( {
					title: __( 'Nothing was imported.', 'checkout-fieldkit' ),
					details: isImportRefusal( reason )
						? reason.data.errors.map( importProblem )
						: [ errorMessage( reason ) ],
				} );
				return;
			}

			const imported = TARGETS.filter( ( target ) =>
				result.imported.includes( target )
			);
			imported.forEach( ( target ) => {
				const value = result[ target ];
				if ( ! value ) {
					return;
				}
				if ( target === 'settings' ) {
					replaceSaved( target, value as Settings );
				} else {
					replaceSaved( target, value as FieldSet );
				}
			} );

			const message = sprintf(
				/* translators: %s: list of what was replaced, such as "Classic checkout fields, Settings". */
				__( 'Import finished. Replaced: %s.', 'checkout-fieldkit' ),
				list( imported.map( targetLabel ) )
			);
			setChosen( null );
			setDone( message );
			setFocusTo( 'done' );
			announce( message );
		} finally {
			setImporting( false );
		}
	};

	const onImport = () => {
		if ( overwritten.length > 0 ) {
			setConfirming( true );
		} else {
			runImport();
		}
	};

	const busy = checking !== null || importing;
	const unsavedNames = list( overwritten.map( targetLabel ) );

	return (
		<Card
			className="cfk-tools-card"
			title={ __( 'Import', 'checkout-fieldkit' ) }
			description={ __(
				'Replace this site’s setup with one from an exported file. Check what will change before you confirm.',
				'checkout-fieldkit'
			) }
		>
			<div className="cfk-tools-card__body">
				<input
					ref={ inputRef }
					type="file"
					accept=".json,application/json"
					className="cfk-visually-hidden"
					tabIndex={ -1 }
					aria-hidden="true"
					aria-label={ __( 'Import file', 'checkout-fieldkit' ) }
					onChange={ onFile }
				/>

				{ done && (
					<Notice ref={ doneRef } tone="success" tabIndex={ -1 }>
						{ done }
					</Notice>
				) }

				{ chosen ? (
					<div className="cfk-file">
						<FileIcon className="cfk-file__icon" />
						<span className="cfk-file__name">{ chosen.name }</span>
						<Button
							disabled={ busy }
							onClick={ () => inputRef.current?.click() }
						>
							{ __( 'Choose another file', 'checkout-fieldkit' ) }
						</Button>
					</div>
				) : (
					<div className="cfk-tools-card__actions">
						<Button
							ref={ chooseRef }
							isBusy={ checking !== null }
							aria-disabled={ busy || undefined }
							onClick={ () =>
								! busy && inputRef.current?.click()
							}
						>
							{ __( 'Choose a file…', 'checkout-fieldkit' ) }
						</Button>
						<span className="cfk-tools-card__hint">
							{ checking
								? sprintf(
										/* translators: %s: file name. */
										__(
											'Checking %s…',
											'checkout-fieldkit'
										),
										checking
									)
								: __(
										'A .json file exported from Checkout FieldKit.',
										'checkout-fieldkit'
									) }
						</span>
					</div>
				) }

				{ problem && (
					<Notice tone="error" role="alert" title={ problem.title }>
						{ problem.details.length === 1 ? (
							<p>{ problem.details[ 0 ] }</p>
						) : (
							<ul>
								{ problem.details.map( ( detail, index ) => (
									<li key={ index }>{ detail }</li>
								) ) }
							</ul>
						) }
					</Notice>
				) }

				{ chosen && (
					<>
						<ul
							className="cfk-import-plan"
							aria-label={ __(
								'What the import will change',
								'checkout-fieldkit'
							) }
						>
							{ chosen.preview.targets.map( ( entry ) => (
								<li
									key={ entry.target }
									className="cfk-import-plan__row"
								>
									<Tag
										tone={
											entry.action === 'replace'
												? 'warning'
												: 'neutral'
										}
										className="cfk-import-plan__action"
									>
										{ entry.action === 'replace'
											? __(
													'Replace',
													'checkout-fieldkit'
												)
											: __(
													'Keep',
													'checkout-fieldkit'
												) }
									</Tag>
									<span className="cfk-import-plan__text">
										<strong>
											{ targetLabel( entry.target ) }
										</strong>
										<span className="cfk-import-plan__summary">
											{ previewSummary(
												entry,
												saved[ entry.target ],
												chosen.document[ entry.target ]
											) }
										</span>
									</span>
								</li>
							) ) }
						</ul>

						{ overwritten.length > 0 && (
							<Notice tone="warning">
								{ sprintf(
									/* translators: %s: list of what has unsaved changes, such as "Settings". */
									__(
										'You have unsaved changes to %s. Importing replaces them with the file’s version.',
										'checkout-fieldkit'
									),
									unsavedNames
								) }
							</Notice>
						) }

						<Checkbox
							label={ __(
								'Download a copy of my current setup first',
								'checkout-fieldkit'
							) }
							checked={ backup }
							onChange={ setBackup }
						/>

						<div className="cfk-tools-card__actions">
							<Button
								variant="primary"
								isBusy={ importing }
								aria-disabled={ busy || undefined }
								onClick={ () => ! busy && onImport() }
							>
								{ __(
									'Import and replace',
									'checkout-fieldkit'
								) }
							</Button>
							<Button
								disabled={ importing }
								onClick={ () => {
									start();
									setFocusTo( 'choose' );
								} }
							>
								{ __( 'Cancel', 'checkout-fieldkit' ) }
							</Button>
						</div>
					</>
				) }
			</div>

			{ confirming && (
				<Dialog
					role="alertdialog"
					title={ __(
						'Replace your unsaved changes?',
						'checkout-fieldkit'
					) }
					onClose={ () => setConfirming( false ) }
					initialFocusRef={ keepEditingRef }
					size="small"
					actions={
						<>
							<Button
								ref={ keepEditingRef }
								onClick={ () => setConfirming( false ) }
							>
								{ __( 'Keep editing', 'checkout-fieldkit' ) }
							</Button>
							<Button variant="danger" onClick={ runImport }>
								{ __(
									'Discard them and import',
									'checkout-fieldkit'
								) }
							</Button>
						</>
					}
				>
					<p>
						{ sprintf(
							/* translators: %s: list of what has unsaved changes, such as "Settings". */
							__(
								'Importing replaces your unsaved changes to %s with the file’s version. They can’t be brought back.',
								'checkout-fieldkit'
							),
							unsavedNames
						) }
					</p>
				</Dialog>
			) }
		</Card>
	);
}
