/**
 * The field editor drawer (spec §6.3, frame 3): a modal panel on the right
 * with a live preview, an error summary, the field's settings in groups,
 * and Delete field / Cancel / Apply. Edits stay in the drawer until Apply
 * writes them to the draft.
 */
import { Modal } from '@wordpress/components';
import {
	useEffect,
	useId,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import {
	Button,
	CloseIcon,
	Dialog,
	ErrorIcon,
	Notice,
	Switch,
	cx,
	useAnnounce,
} from '../components';
import { checkoutTypeLabel } from '../shell/labels';
import { useContextData, useFieldSet, type FieldRow } from '../store';
import { deepEqual } from '../store/equal';
import type { CheckoutType, Context, Field } from '../types';
import { FieldPreview } from './FieldPreview';
import {
	applicableSettings,
	namePrefix,
	optionIssues,
	prepareField,
	sectionLabel,
	slugify,
	suggestName,
	typeChoices,
	typeDefinition,
	validateField,
	type FieldError,
} from './model';
import { OptionsEditor, type EditorOption } from './OptionsEditor';

export interface FieldEditorDrawerProps {
	checkoutType: CheckoutType;
	/** Section of a new field. */
	section: string;
	/** The field to edit; null to add a new one. */
	rowId: string | null;
	onClose: () => void;
}

let lastOptionKey = 0;

function newOption(
	value = '',
	label = '',
	valueEdited = false
): EditorOption {
	lastOptionKey += 1;
	return { key: `cfk-option-${ lastOptionKey }`, value, label, valueEdited };
}

/**
 * Which keys of a field differ from another; what Apply sends to the draft.
 * @param next
 * @param previous
 */
function changedKeys( next: Field, previous: Field ): Partial< Field > {
	const changes: Partial< Field > = {};
	Object.keys( next ).forEach( ( key ) => {
		if ( ! deepEqual( next[ key ], previous[ key ] ) ) {
			changes[ key ] = next[ key ];
		}
	} );
	return changes;
}

/**
 * What a field is called in announcements.
 * @param field
 */
function fieldName( field: Field ): string {
	return field.label || field.name || __( 'New field', 'checkout-fieldkit' );
}

function newField(
	context: Context,
	checkoutType: CheckoutType,
	section: string
): Field {
	const field: Field = {
		...context.fieldDefaults[ checkoutType ],
		name: '',
		builtIn: false,
	};
	const choices = typeChoices( context, checkoutType, section );
	if ( ! choices.some( ( c ) => c.allowed && c.name === field.type ) ) {
		field.type = choices.find( ( c ) => c.allowed )?.name || field.type;
	}
	return field;
}

/** A labelled control with its help and error, wired with aria-describedby. */
interface ControlProps {
	id: string;
	label: string;
	help?: ReactNode;
	error?: ReactNode;
	className?: string;
	children: ( a11y: {
		'aria-invalid'?: true;
		'aria-describedby'?: string;
	} ) => ReactNode;
}

function Control( {
	id,
	label,
	help,
	error,
	className,
	children,
}: ControlProps ) {
	const describedBy = [ error && `${ id }-error`, help && `${ id }-help` ]
		.filter( Boolean )
		.join( ' ' );
	return (
		<div className={ cx( 'cfk-editor-control', className ) }>
			<label className="cfk-editor-control__label" htmlFor={ id }>
				{ label }
			</label>
			{ children( {
				'aria-invalid': error ? true : undefined,
				'aria-describedby': describedBy || undefined,
			} ) }
			{ error && (
				<InlineError id={ `${ id }-error` }>{ error }</InlineError>
			) }
			{ help && (
				<div className="cfk-editor-control__help" id={ `${ id }-help` }>
					{ help }
				</div>
			) }
		</div>
	);
}

function InlineError( { id, children }: { id: string; children: ReactNode } ) {
	return (
		<div className="cfk-editor-error" id={ id }>
			<ErrorIcon className="cfk-editor-error__icon" />
			<span>{ children }</span>
		</div>
	);
}

function Group( {
	title,
	children,
	className,
}: {
	title: string;
	children: ReactNode;
	className?: string;
} ) {
	return (
		<fieldset className={ cx( 'cfk-editor-group', className ) }>
			<legend className="cfk-editor-group__title">{ title }</legend>
			{ children }
		</fieldset>
	);
}

/**
 * The drawer for one field; renders nothing (and closes) when the field no longer exists.
 * @param props
 */
export function FieldEditorDrawer( props: FieldEditorDrawerProps ) {
	const fieldSet = useFieldSet( props.checkoutType );
	const row = props.rowId ? fieldSet.getRow( props.rowId ) : null;
	const missing = props.rowId !== null && row === null;
	const { onClose } = props;

	useEffect( () => {
		if ( missing ) {
			onClose();
		}
	}, [ missing, onClose ] );

	if ( missing ) {
		return null;
	}
	return <EditorForm { ...props } row={ row } />;
}

function EditorForm( {
	checkoutType,
	section: newSection,
	row,
	onClose,
}: FieldEditorDrawerProps & { row: FieldRow | null } ) {
	const context = useContextData();
	const fieldSet = useFieldSet( checkoutType );
	const announce = useAnnounce();
	const base = useId();
	const id = ( key: string ) => `${ base }-${ key }`;
	const validationId = ( name: string, index: number ) =>
		index === 0 ? id( 'validation' ) : id( `validation-${ name }` );
	const isNew = row === null;
	const section = row ? row.section : newSection;
	const original = row ? row.field : null;

	const [ working, setWorking ] = useState< Field >( () =>
		original ? { ...original } : newField( context, checkoutType, section )
	);
	const [ options, setOptions ] = useState< EditorOption[] >( () =>
		( original?.options || [] ).map( ( option ) =>
			newOption( option.value, option.label, true )
		)
	);
	const [ defaultKeys, setDefaultKeys ] = useState< string[] >( () => {
		const chosen = original?.default ? original.default.split( ',' ) : [];
		return options
			.filter( ( option ) => chosen.includes( option.value ) )
			.map( ( option ) => option.key );
	} );
	const [ classesText, setClassesText ] = useState(
		( original?.classes || [] ).join( ' ' )
	);
	const [ nameEdited, setNameEdited ] = useState( ! isNew );
	const [ touched, setTouched ] = useState< string[] >( [] );
	const [ showAll, setShowAll ] = useState( ! isNew );
	const [ attempts, setAttempts ] = useState( 0 );
	const [ confirmOptional, setConfirmOptional ] = useState( false );
	const [ dragging, setDragging ] = useState( false );
	const [ focusTarget, setFocusTarget ] = useState< string | null >( null );

	const summaryRef = useRef< HTMLDivElement >( null );
	const labelRef = useRef< HTMLInputElement & HTMLTextAreaElement >( null );
	const closeRef = useRef< HTMLButtonElement >( null );
	const requiredRef = useRef< HTMLButtonElement >( null );
	const keepRequiredRef = useRef< HTMLButtonElement >( null );

	const definition = typeDefinition( context, checkoutType, working.type );
	const isChoice = !! definition?.hasOptions;
	const isMultiple = !! definition?.multiple;
	const prefix = namePrefix( checkoutType, section );

	const otherNames = useMemo(
		() =>
			fieldSet.sections.flatMap( ( item ) =>
				item.rows
					.filter( ( other ) => other.id !== row?.id )
					.map( ( other ) => other.field.name )
			),
		[ fieldSet.sections, row?.id ]
	);
	const taken = useMemo(
		() => new Set( [ ...otherNames, ...context.reservedNames ] ),
		[ otherNames, context.reservedNames ]
	);

	const candidate: Field = {
		...working,
		options: options.map( ( { value, label } ) => ( { value, label } ) ),
		default: isChoice
			? options
					.filter( ( option ) => defaultKeys.includes( option.key ) )
					.map( ( option ) => option.value.trim() )
					.filter( ( value ) => value !== '' )
					.slice( 0, isMultiple ? undefined : 1 )
					.join( ',' )
			: working.default,
		classes: classesText.split( /[\s,]+/ ),
	};
	const prepared = prepareField( context, checkoutType, candidate, original );
	const settings = applicableSettings( context, checkoutType, prepared );
	const has = ( key: string ) => settings.includes( key );
	const errors = validateField( {
		context,
		checkoutType,
		section,
		field: prepared,
		otherNames,
	} );
	const shownErrors = errors.filter(
		( error ) => showAll || touched.includes( error.key )
	);
	const errorFor = ( key: string ) =>
		shownErrors.find( ( error ) => error.key === key );

	// Problems from the last save, while the setting they are about is unchanged.
	const serverProblems = ( row?.problems || [] ).filter(
		( problem ) =>
			! ( problem.key in prepared ) ||
			deepEqual( prepared[ problem.key ], row?.field[ problem.key ] )
	);
	const serverFor = ( key: string ) =>
		serverProblems.find(
			( problem ) =>
				problem.key === key ||
				( key === 'name' && problem.key === 'builtIn' )
		)?.message;

	const issues = optionIssues( prepared.options, prepared.type === 'select' );

	const messageFor = ( key: string ): string | undefined => {
		const error = errorFor( key );
		return error ? error.message : serverFor( key );
	};

	// A name that breaks the pattern gets an example built from what was typed.
	const nameError = ( (): string | undefined => {
		const message = messageFor( 'name' );
		const error = errorFor( 'name' );
		if (
			! error ||
			new RegExp( context.fieldNamePattern ).test( prepared.name )
		) {
			return message;
		}
		const example = suggestName( prepared.name, { prefix, taken } );
		return example
			? `${ error.message } ${ sprintf(
					/* translators: %s: a field name that would work. */
					__( 'For example: %s', 'checkout-fieldkit' ),
					example
				) }`
			: error.message;
	} )();

	// Focus: the label when it can be edited, otherwise the close button.
	useEffect( () => {
		( labelRef.current || closeRef.current )?.focus();
	}, [] );

	// After a refused Apply, take the user to the summary.
	useEffect( () => {
		if ( attempts > 0 ) {
			summaryRef.current?.focus();
		}
	}, [ attempts ] );

	useEffect( () => {
		if ( focusTarget ) {
			document.getElementById( focusTarget )?.focus();
			setFocusTarget( null );
		}
	}, [ focusTarget ] );

	const touch = ( key: string ) =>
		setTouched( ( keys ) =>
			keys.includes( key ) ? keys : [ ...keys, key ]
		);

	const update = ( changes: Partial< Field > ) =>
		setWorking( ( field ) => ( { ...field, ...changes } ) );

	const onLabelChange = ( label: string ) => {
		const changes: Partial< Field > = { label };
		if ( ! nameEdited && ! working.builtIn ) {
			changes.name = suggestName( label, { prefix, taken } );
		}
		update( changes );
	};

	const onTypeChange = ( type: string ) => {
		const next = typeDefinition( context, checkoutType, type );
		const kind = ( item: typeof next, name: string ) => {
			if ( name === 'checkbox' ) {
				return 'checkbox';
			}
			return item?.hasOptions ? 'choice' : 'text';
		};
		if ( kind( definition, working.type ) !== kind( next, type ) ) {
			update( { type, default: '' } );
			setDefaultKeys( [] );
		} else {
			update( { type } );
			if ( next?.hasOptions && ! next.multiple ) {
				setDefaultKeys( ( keys ) => keys.slice( 0, 1 ) );
			}
		}
		if ( next?.hasOptions && options.length === 0 ) {
			setOptions( [ newOption() ] );
		}
		touch( 'type' );
	};

	const changeOptions = ( next: EditorOption[] ) => {
		setOptions( next );
		touch( 'options' );
	};

	const addOption = () => {
		const option = newOption();
		changeOptions( [ ...options, option ] );
		setFocusTarget( id( `option-${ option.key }-label` ) );
	};

	const removeOption = ( key: string ) => {
		const at = options.findIndex( ( option ) => option.key === key );
		const next = options.filter( ( option ) => option.key !== key );
		changeOptions( next );
		setDefaultKeys( ( keys ) => keys.filter( ( item ) => item !== key ) );
		const neighbour = next[ at ] || next[ at - 1 ];
		setFocusTarget(
			neighbour
				? id( `option-${ neighbour.key }-value` )
				: id( 'add-option' )
		);
	};

	const editOption = ( key: string, changes: Partial< EditorOption > ) =>
		changeOptions(
			options.map( ( option ) =>
				option.key === key ? { ...option, ...changes } : option
			)
		);

	const onRequiredChange = ( required: boolean ) => {
		const essential = context.essentialFields[ checkoutType ].some(
			( item ) => item.name === working.name
		);
		if ( ! required && working.builtIn && essential ) {
			setConfirmOptional( true );
			return;
		}
		update( { required } );
	};

	const apply = () => {
		if ( errors.length > 0 ) {
			setShowAll( true );
			setAttempts( ( count ) => count + 1 );
			return;
		}
		if ( row ) {
			const changes = changedKeys( prepared, row.field );
			if ( Object.keys( changes ).length > 0 ) {
				fieldSet.updateField( row.id, changes );
				announce(
					sprintf(
						/* translators: %s: field label. */
						__(
							'Changes to “%s” added to your unsaved changes.',
							'checkout-fieldkit'
						),
						fieldName( prepared )
					)
				);
			}
		} else {
			fieldSet.addField( section, prepared );
			announce(
				sprintf(
					/* translators: %s: field label. */
					__(
						'“%s” added to your unsaved changes.',
						'checkout-fieldkit'
					),
					fieldName( prepared )
				)
			);
		}
		onClose();
	};

	const remove = () => {
		if ( ! row ) {
			return;
		}
		fieldSet.removeField( row.id );
		announce(
			sprintf(
				/* translators: %s: field label. */
				__(
					'“%s” deleted. It leaves the checkout when you save changes.',
					'checkout-fieldkit'
				),
				fieldName( row.field )
			)
		);
		onClose();
	};

	// Where each setting's input is, for the summary's links.
	const targets: Record< string, { label: string; id: string } > = {
		type: {
			label: __( 'Field type', 'checkout-fieldkit' ),
			id: id( 'type' ),
		},
		label: { label: __( 'Label', 'checkout-fieldkit' ), id: id( 'label' ) },
		name: {
			label: __( 'Field name', 'checkout-fieldkit' ),
			id: id( 'name' ),
		},
		builtIn: {
			label: __( 'Field name', 'checkout-fieldkit' ),
			id: id( 'name' ),
		},
		placeholder: {
			label: __( 'Placeholder', 'checkout-fieldkit' ),
			id: id( 'placeholder' ),
		},
		default: {
			label: isChoice
				? __( 'Selected by default', 'checkout-fieldkit' )
				: __( 'Default value', 'checkout-fieldkit' ),
			id: id( 'default' ),
		},
		options: {
			label: __( 'Options', 'checkout-fieldkit' ),
			id: ( () => {
				const at = issues.indexOf( true );
				return at >= 0 && options[ at ]
					? id( `option-${ options[ at ].key }-value` )
					: id( 'add-option' );
			} )(),
		},
		required: {
			label: __( 'Required', 'checkout-fieldkit' ),
			id: id( 'required' ),
		},
		validation: {
			label: __( 'Validation', 'checkout-fieldkit' ),
			id: id( 'validation' ),
		},
		classes: {
			label: __( 'CSS classes', 'checkout-fieldkit' ),
			id: id( 'classes' ),
		},
		headingLevel: {
			label: __( 'Heading level', 'checkout-fieldkit' ),
			id: id( 'headingLevel' ),
		},
		showInEmails: {
			label: __( 'Order emails', 'checkout-fieldkit' ),
			id: id( 'showInEmails' ),
		},
		showOnOrderPages: {
			label: __( 'Order pages', 'checkout-fieldkit' ),
			id: id( 'showOnOrderPages' ),
		},
	};

	const link = ( key: string ) => {
		const target = targets[ key ];
		if ( ! target ) {
			return key;
		}
		return (
			<a
				href={ `#${ target.id }` }
				onClick={ ( event ) => {
					const element = document.getElementById( target.id );
					if ( element ) {
						event.preventDefault();
						element.focus();
					}
				} }
			>
				{ target.label }
			</a>
		);
	};

	const subtitle = [
		checkoutTypeLabel( checkoutType ),
		sectionLabel( context, checkoutType, section ),
		working.builtIn
			? __( 'Built-in field', 'checkout-fieldkit' )
			: __( 'Custom field', 'checkout-fieldkit' ),
	].join( ' · ' );

	const title = isNew
		? __( 'Add field', 'checkout-fieldkit' )
		: __( 'Edit field', 'checkout-fieldkit' );

	const choices = typeChoices( context, checkoutType, section );
	const unavailable = choices.filter( ( choice ) => ! choice.allowed );
	const validations = context.validations[ checkoutType ];
	const implied = definition?.impliedValidation || null;
	const validationChoices = [
		...validations.filter( ( item ) => item.name !== implied ),
		...working.validation
			.filter(
				( name ) =>
					name !== implied &&
					! validations.some( ( item ) => item.name === name )
			)
			.map( ( name ) => ( { name, label: name } ) ),
	];
	const impliedLabel =
		implied &&
		( validations.find( ( item ) => item.name === implied )?.label ||
			implied );

	const renamed =
		!! row?.saved &&
		! working.builtIn &&
		definition?.collectsValue !== false &&
		prepared.name !== row.saved.name;

	const essential = context.essentialFields[ checkoutType ].find(
		( item ) => item.name === working.name
	);

	const titleId = id( 'title' );
	const summaryTitleId = id( 'summary-title' );
	const serverTitleId = id( 'server-title' );

	const defaultLabel =
		working.type === 'hidden'
			? __( 'Value', 'checkout-fieldkit' )
			: __( 'Default value', 'checkout-fieldkit' );

	const defaultControl = ( () => {
		if ( ! has( 'default' ) ) {
			return null;
		}
		if ( isChoice && isMultiple ) {
			const error = messageFor( 'default' );
			return (
				<fieldset
					className="cfk-editor-checks"
					id={ id( 'default' ) }
					tabIndex={ -1 }
					aria-describedby={
						error ? id( 'default-error' ) : undefined
					}
				>
					<legend className="cfk-editor-control__label">
						{ __( 'Selected by default', 'checkout-fieldkit' ) }
					</legend>
					{ options
						.filter( ( option ) => option.value.trim() !== '' )
						.map( ( option, index ) => (
							<label
								className="cfk-editor-check"
								key={ option.key }
								htmlFor={ id( `default-${ option.key }` ) }
							>
								<input
									id={ id( `default-${ option.key }` ) }
									type="checkbox"
									checked={ defaultKeys.includes(
										option.key
									) }
									onChange={ ( event ) => {
										const checked = event.target.checked;
										setDefaultKeys( ( keys ) =>
											checked
												? [ ...keys, option.key ]
												: keys.filter(
														( key ) =>
															key !== option.key
													)
										);
										touch( 'default' );
									} }
								/>
								<span>
									{ option.label ||
										option.value ||
										index + 1 }
								</span>
							</label>
						) ) }
					{ error && (
						<InlineError id={ id( 'default-error' ) }>
							{ error }
						</InlineError>
					) }
				</fieldset>
			);
		}
		if ( isChoice ) {
			const selected = options.find( ( option ) =>
				defaultKeys.includes( option.key )
			);
			return (
				<Control
					id={ id( 'default' ) }
					label={ __( 'Selected by default', 'checkout-fieldkit' ) }
					error={ messageFor( 'default' ) }
				>
					{ ( a11y ) => (
						<select
							id={ id( 'default' ) }
							className="cfk-input"
							value={ selected ? selected.value.trim() : '' }
							onChange={ ( event ) => {
								const value = event.target.value;
								const option = options.find(
									( item ) => item.value.trim() === value
								);
								setDefaultKeys(
									option && value ? [ option.key ] : []
								);
								touch( 'default' );
							} }
							{ ...a11y }
						>
							<option value="">
								{ __( 'None', 'checkout-fieldkit' ) }
							</option>
							{ options
								.filter(
									( option ) => option.value.trim() !== ''
								)
								.map( ( option ) => (
									<option
										key={ option.key }
										value={ option.value.trim() }
									>
										{ option.label || option.value }
									</option>
								) ) }
						</select>
					) }
				</Control>
			);
		}
		if ( working.type === 'checkbox' ) {
			return (
				<label className="cfk-editor-check" htmlFor={ id( 'default' ) }>
					<input
						id={ id( 'default' ) }
						type="checkbox"
						checked={ working.default === '1' }
						onChange={ ( event ) =>
							update( {
								default: event.target.checked ? '1' : '',
							} )
						}
					/>
					<span>
						{ __( 'Ticked by default', 'checkout-fieldkit' ) }
					</span>
				</label>
			);
		}
		const dateLike = [ 'date', 'time', 'datetime-local', 'month', 'week' ];
		return (
			<Control
				id={ id( 'default' ) }
				label={ defaultLabel }
				error={ messageFor( 'default' ) }
				help={
					working.type === 'hidden'
						? __( 'Sent with every order.', 'checkout-fieldkit' )
						: undefined
				}
			>
				{ ( a11y ) => (
					<input
						id={ id( 'default' ) }
						className="cfk-input"
						type={
							dateLike.includes( working.type )
								? working.type
								: 'text'
						}
						inputMode={
							working.type === 'number' ? 'decimal' : undefined
						}
						value={ working.default }
						onChange={ ( event ) => {
							update( { default: event.target.value } );
							touch( 'default' );
						} }
						{ ...a11y }
					/>
				) }
			</Control>
		);
	} )();

	const optionsError = messageFor( 'options' );

	return (
		<Modal
			onRequestClose={ onClose }
			__experimentalHideHeader
			aria={ { labelledby: titleId } }
			focusOnMount={ false }
			shouldCloseOnEsc={ ! dragging && ! confirmOptional }
			className="cfk-drawer"
			overlayClassName="cfk-dialog-overlay cfk-drawer-overlay"
		>
			<div className="cfk-drawer__layout">
				<div className="cfk-drawer__header">
					<div>
						<h2 className="cfk-drawer__title" id={ titleId }>
							{ title }
						</h2>
						<p className="cfk-drawer__subtitle">{ subtitle }</p>
					</div>
					<Button
						ref={ closeRef }
						variant="ghost"
						className="cfk-drawer__close"
						icon={ <CloseIcon /> }
						label={ __( 'Close', 'checkout-fieldkit' ) }
						onClick={ onClose }
					/>
				</div>

				<div className="cfk-drawer__body">
					{ ! working.enabled && (
						<Notice tone="info">
							{ __(
								'This field is turned off, so shoppers don’t see it. Turn it on in the list of fields.',
								'checkout-fieldkit'
							) }
						</Notice>
					) }
					<FieldPreview
						checkoutType={ checkoutType }
						field={ prepared }
						definition={ definition }
					/>

					{ shownErrors.length > 0 && (
						<div
							ref={ summaryRef }
							className="cfk-editor-summary"
							role="group"
							aria-labelledby={ summaryTitleId }
							tabIndex={ -1 }
						>
							<ErrorIcon className="cfk-editor-summary__icon" />
							<div>
								<p
									id={ summaryTitleId }
									className="cfk-editor-summary__title"
								>
									{ sprintf(
										/* translators: %d: number of problems. */
										_n(
											'Fix %d problem before applying:',
											'Fix %d problems before applying:',
											shownErrors.length,
											'checkout-fieldkit'
										),
										shownErrors.length
									) }
								</p>
								<ul className="cfk-editor-summary__list">
									{ shownErrors.map(
										( error: FieldError ) => (
											<li key={ error.key }>
												{ link( error.key ) }
											</li>
										)
									) }
								</ul>
							</div>
						</div>
					) }

					{ serverProblems.length > 0 && (
						<div
							className="cfk-editor-summary"
							role="group"
							aria-labelledby={ serverTitleId }
						>
							<ErrorIcon className="cfk-editor-summary__icon" />
							<div>
								<p
									id={ serverTitleId }
									className="cfk-editor-summary__title"
								>
									{ serverProblems.length === 1
										? __(
												'The last save found a problem with this field:',
												'checkout-fieldkit'
											)
										: sprintf(
												/* translators: %d: number of problems. */
												__(
													'The last save found %d problems with this field:',
													'checkout-fieldkit'
												),
												serverProblems.length
											) }
								</p>
								<ul className="cfk-editor-summary__list">
									{ serverProblems.map(
										( problem, index ) => (
											<li key={ index }>
												{ problem.key ? (
													<>
														{ link( problem.key ) }
														{ ': ' }
													</>
												) : null }
												{ problem.message }
											</li>
										)
									) }
								</ul>
							</div>
						</div>
					) }

					<Group title={ __( 'Basics', 'checkout-fieldkit' ) }>
						<div className="cfk-editor-columns">
							<Control
								id={ id( 'type' ) }
								label={ __(
									'Field type',
									'checkout-fieldkit'
								) }
								error={ messageFor( 'type' ) }
								help={
									! working.builtIn && unavailable.length > 0
										? sprintf(
												/* translators: 1: section label, 2: list of field types. */
												__(
													'Not available in the %1$s section: %2$s.',
													'checkout-fieldkit'
												),
												sectionLabel(
													context,
													checkoutType,
													section
												),
												unavailable
													.map(
														( choice ) =>
															choice.label
													)
													.join( ', ' )
											)
										: undefined
								}
							>
								{ ( a11y ) => (
									<select
										id={ id( 'type' ) }
										className="cfk-input"
										value={ working.type }
										disabled={ working.builtIn }
										onChange={ ( event ) =>
											onTypeChange( event.target.value )
										}
										{ ...a11y }
									>
										{ working.builtIn ||
										! choices.some(
											( c ) => c.name === working.type
										) ? (
											<option value={ working.type }>
												{ definition?.label ||
													working.type }
											</option>
										) : null }
										{ ! working.builtIn &&
											choices.map( ( choice ) => (
												<option
													key={ choice.name }
													value={ choice.name }
													disabled={
														! choice.allowed
													}
													title={
														choice.reason ||
														undefined
													}
												>
													{ choice.allowed
														? choice.label
														: sprintf(
																/* translators: %s: field type label. */
																__(
																	'%s (not available here)',
																	'checkout-fieldkit'
																),
																choice.label
															) }
												</option>
											) ) }
									</select>
								) }
							</Control>
							{ has( 'label' ) && (
								<Control
									id={ id( 'label' ) }
									label={ __( 'Label', 'checkout-fieldkit' ) }
									error={ messageFor( 'label' ) }
									help={ ( () => {
										if ( working.type === 'hidden' ) {
											return __(
												'Only you see this, in the admin.',
												'checkout-fieldkit'
											);
										}
										if (
											definition &&
											! definition.collectsValue
										) {
											return __(
												'The text shoppers see.',
												'checkout-fieldkit'
											);
										}
										return undefined;
									} )() }
								>
									{ ( a11y ) =>
										working.type === 'paragraph' ? (
											<textarea
												ref={ labelRef }
												id={ id( 'label' ) }
												className="cfk-input cfk-input--textarea"
												rows={ 3 }
												value={ working.label }
												onChange={ ( event ) =>
													onLabelChange(
														event.target.value
													)
												}
												{ ...a11y }
											/>
										) : (
											<input
												ref={ labelRef }
												id={ id( 'label' ) }
												type="text"
												className="cfk-input"
												value={ working.label }
												onChange={ ( event ) =>
													onLabelChange(
														event.target.value
													)
												}
												{ ...a11y }
											/>
										)
									}
								</Control>
							) }
						</div>

						<Control
							id={ id( 'name' ) }
							label={ __( 'Field name', 'checkout-fieldkit' ) }
							error={ nameError }
							help={
								working.builtIn ? (
									__(
										'WooCommerce’s own fields keep their name and type.',
										'checkout-fieldkit'
									)
								) : (
									<>
										{ __(
											'Each order stores this field’s answer under this name.',
											'checkout-fieldkit'
										) }
										{ prefix && (
											<>
												{ ' ' }
												{ sprintf(
													/* translators: %s: name prefix such as billing_. */
													__(
														'Starting the name with %s lets WooCommerce also save the answer on the customer, so it is filled in next time.',
														'checkout-fieldkit'
													),
													prefix
												) }
											</>
										) }
									</>
								)
							}
						>
							{ ( a11y ) => (
								<input
									id={ id( 'name' ) }
									type="text"
									className="cfk-input cfk-input--mono"
									value={ working.name }
									readOnly={ working.builtIn }
									spellCheck={ false }
									autoComplete="off"
									onChange={ ( event ) => {
										setNameEdited( true );
										update( { name: event.target.value } );
										touch( 'name' );
									} }
									{ ...a11y }
								/>
							) }
						</Control>
						{ renamed && (
							<Notice tone="warning">
								{ sprintf(
									/* translators: %s: the saved field name. */
									__(
										'Orders placed before the rename keep their answer under the old name “%s”, and it will no longer be shown on them.',
										'checkout-fieldkit'
									),
									row?.saved?.name || ''
								) }
							</Notice>
						) }

						{ has( 'placeholder' ) && (
							<Control
								id={ id( 'placeholder' ) }
								label={ __(
									'Placeholder',
									'checkout-fieldkit'
								) }
								error={ messageFor( 'placeholder' ) }
							>
								{ ( a11y ) => (
									<input
										id={ id( 'placeholder' ) }
										type="text"
										className="cfk-input"
										value={ working.placeholder }
										onChange={ ( event ) =>
											update( {
												placeholder: event.target.value,
											} )
										}
										{ ...a11y }
									/>
								) }
							</Control>
						) }
						{ ! isChoice && defaultControl }
						{ working.builtIn && (
							<p className="cfk-editor-note">
								{ settings.length === 0
									? sprintf(
											/* translators: %s: section label. */
											__(
												'WooCommerce controls this field. Only its position can change, within the %s section.',
												'checkout-fieldkit'
											),
											sectionLabel(
												context,
												checkoutType,
												section
											)
										)
									: __(
											'WooCommerce decides this field’s other settings.',
											'checkout-fieldkit'
										) }
							</p>
						) }
					</Group>

					{ has( 'options' ) && (
						<Group title={ __( 'Options', 'checkout-fieldkit' ) }>
							<OptionsEditor
								options={ options }
								onChange={ changeOptions }
								issues={ issues }
								errorId={
									optionsError
										? id( 'options-error' )
										: undefined
								}
								valueId={ ( key ) =>
									id( `option-${ key }-value` )
								}
								labelId={ ( key ) =>
									id( `option-${ key }-label` )
								}
								addId={ id( 'add-option' ) }
								onAdd={ addOption }
								onRemove={ removeOption }
								onValueChange={ ( key, value ) =>
									editOption( key, {
										value,
										valueEdited: true,
									} )
								}
								onLabelChange={ ( key, label ) => {
									const option = options.find(
										( item ) => item.key === key
									);
									editOption(
										key,
										option && ! option.valueEdited
											? { label, value: slugify( label ) }
											: { label }
									);
								} }
								onDraggingChange={ setDragging }
							/>
							{ optionsError && (
								<InlineError id={ id( 'options-error' ) }>
									{ optionsError }
								</InlineError>
							) }
							{ prepared.type === 'select' && (
								<p className="cfk-editor-note">
									{ __(
										'Leave the first saved value empty to use that option as a “choose one” prompt.',
										'checkout-fieldkit'
									) }
								</p>
							) }
							{ defaultControl }
						</Group>
					) }

					{ ( has( 'required' ) || has( 'validation' ) ) && (
						<Group title={ __( 'Rules', 'checkout-fieldkit' ) }>
							{ has( 'required' ) && (
								<div className="cfk-editor-switch">
									<span>
										<span
											className="cfk-editor-switch__label"
											id={ id( 'required-label' ) }
										>
											{ __(
												'Required',
												'checkout-fieldkit'
											) }
										</span>
										<span
											className="cfk-editor-control__help"
											id={ id( 'required-help' ) }
										>
											{ __(
												'Shoppers can’t place the order without answering.',
												'checkout-fieldkit'
											) }
										</span>
									</span>
									<Switch
										ref={ requiredRef }
										id={ id( 'required' ) }
										checked={ working.required }
										labelledBy={ id( 'required-label' ) }
										describedBy={ id( 'required-help' ) }
										onChange={ onRequiredChange }
									/>
								</div>
							) }
							{ has( 'validation' ) && (
								<fieldset className="cfk-editor-checks">
									<legend className="cfk-editor-control__label">
										{ __(
											'Validation',
											'checkout-fieldkit'
										) }
									</legend>
									{ impliedLabel && (
										<p className="cfk-editor-control__help">
											{ sprintf(
												/* translators: %s: validation label, such as Email address. */
												__(
													'Answers are always checked as: %s.',
													'checkout-fieldkit'
												),
												impliedLabel
											) }
										</p>
									) }
									{ validationChoices.map(
										( item, index ) => (
											<label
												className="cfk-editor-check"
												key={ item.name }
												htmlFor={ validationId(
													item.name,
													index
												) }
											>
												<input
													id={ validationId(
														item.name,
														index
													) }
													type="checkbox"
													checked={ working.validation.includes(
														item.name
													) }
													onChange={ ( event ) => {
														const checked =
															event.target
																.checked;
														update( {
															validation: checked
																? [
																		...working.validation,
																		item.name,
																	]
																: working.validation.filter(
																		(
																			name
																		) =>
																			name !==
																			item.name
																	),
														} );
													} }
												/>
												<span>{ item.label }</span>
											</label>
										)
									) }
									{ messageFor( 'validation' ) && (
										<InlineError
											id={ id( 'validation-error' ) }
										>
											{ messageFor( 'validation' ) }
										</InlineError>
									) }
								</fieldset>
							) }
						</Group>
					) }

					{ ! working.builtIn &&
						( has( 'showInEmails' ) ||
							has( 'showOnOrderPages' ) ) && (
							<Group
								title={ __(
									'Where the answer shows',
									'checkout-fieldkit'
								) }
							>
								{ has( 'showInEmails' ) && (
									<label
										className="cfk-editor-check"
										htmlFor={ id( 'showInEmails' ) }
									>
										<input
											id={ id( 'showInEmails' ) }
											type="checkbox"
											checked={ working.showInEmails }
											onChange={ ( event ) =>
												update( {
													showInEmails:
														event.target.checked,
												} )
											}
										/>
										<span>
											{ __(
												'Order emails',
												'checkout-fieldkit'
											) }
											<small>
												{ __(
													'Both the email to you and the one to the customer.',
													'checkout-fieldkit'
												) }
											</small>
										</span>
									</label>
								) }
								{ has( 'showOnOrderPages' ) && (
									<label
										className="cfk-editor-check"
										htmlFor={ id( 'showOnOrderPages' ) }
									>
										<input
											id={ id( 'showOnOrderPages' ) }
											type="checkbox"
											checked={ working.showOnOrderPages }
											onChange={ ( event ) =>
												update( {
													showOnOrderPages:
														event.target.checked,
												} )
											}
										/>
										<span>
											{ __(
												'Order pages',
												'checkout-fieldkit'
											) }
											<small>
												{ __(
													'Thank-you page, the customer’s My Account, and your admin order screen.',
													'checkout-fieldkit'
												) }
											</small>
										</span>
									</label>
								) }
							</Group>
						) }

					{ ( has( 'classes' ) || has( 'headingLevel' ) ) && (
						<Group title={ __( 'Advanced', 'checkout-fieldkit' ) }>
							{ has( 'headingLevel' ) && (
								<Control
									id={ id( 'headingLevel' ) }
									label={ __(
										'Heading level',
										'checkout-fieldkit'
									) }
									help={ __(
										'Pick the level that fits the headings around it on your checkout page.',
										'checkout-fieldkit'
									) }
								>
									{ ( a11y ) => (
										<select
											id={ id( 'headingLevel' ) }
											className="cfk-input"
											value={ working.headingLevel }
											onChange={ ( event ) =>
												update( {
													headingLevel:
														event.target.value,
												} )
											}
											{ ...a11y }
										>
											{ context.headingLevels.map(
												( level ) => (
													<option
														key={ level }
														value={ level }
													>
														{ sprintf(
															/* translators: %s: heading level number. */
															__(
																'Heading %s',
																'checkout-fieldkit'
															),
															level.replace(
																/^h/,
																''
															)
														) }
													</option>
												)
											) }
										</select>
									) }
								</Control>
							) }
							{ has( 'classes' ) && (
								<Control
									id={ id( 'classes' ) }
									label={ __(
										'CSS classes',
										'checkout-fieldkit'
									) }
									error={ messageFor( 'classes' ) }
									help={
										checkoutType === 'classic'
											? __(
													'Separate classes with spaces or commas. form-row-first and form-row-last put two fields side by side.',
													'checkout-fieldkit'
												)
											: __(
													'Separate classes with spaces or commas.',
													'checkout-fieldkit'
												)
									}
								>
									{ ( a11y ) => (
										<input
											id={ id( 'classes' ) }
											type="text"
											className="cfk-input cfk-input--mono"
											value={ classesText }
											spellCheck={ false }
											onChange={ ( event ) =>
												setClassesText(
													event.target.value
												)
											}
											{ ...a11y }
										/>
									) }
								</Control>
							) }
						</Group>
					) }
				</div>

				<div className="cfk-drawer__footer">
					{ row && ! working.builtIn && (
						<Button variant="danger" onClick={ remove }>
							{ __( 'Delete field', 'checkout-fieldkit' ) }
						</Button>
					) }
					<span className="cfk-drawer__spacer" />
					<Button onClick={ onClose }>
						{ __( 'Cancel', 'checkout-fieldkit' ) }
					</Button>
					<Button variant="primary" onClick={ apply }>
						{ __( 'Apply', 'checkout-fieldkit' ) }
					</Button>
					<p className="cfk-drawer__note">
						{ __(
							'Apply adds this to your unsaved changes. Nothing reaches the checkout until you press Save changes.',
							'checkout-fieldkit'
						) }
					</p>
				</div>
			</div>

			{ confirmOptional && essential && (
				<Dialog
					role="alertdialog"
					size="small"
					title={ sprintf(
						/* translators: %s: field label. */
						__( 'Make “%s” optional?', 'checkout-fieldkit' ),
						fieldName( working )
					) }
					initialFocusRef={ keepRequiredRef }
					onClose={ () => setConfirmOptional( false ) }
					actions={
						<>
							<Button
								onClick={ () => {
									update( { required: false } );
									setConfirmOptional( false );
								} }
							>
								{ __(
									'Make it optional',
									'checkout-fieldkit'
								) }
							</Button>
							<Button
								ref={ keepRequiredRef }
								variant="primary"
								onClick={ () => setConfirmOptional( false ) }
							>
								{ __(
									'Keep it required',
									'checkout-fieldkit'
								) }
							</Button>
						</>
					}
				>
					<p>{ essential.consequence }</p>
				</Dialog>
			) }
		</Modal>
	);
}
