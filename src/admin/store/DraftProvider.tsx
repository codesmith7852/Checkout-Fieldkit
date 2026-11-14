/**
 * The draft store as React context, and the hooks every screen uses.
 *
 *     useDraft()           Change count, save, discard, problems.
 *     useFieldSet( type )  One field set's rows by section, and actions on fields.
 *     useSettingsDraft()   The settings, and update().
 *     useContextData()     GET /context.
 */
import {
	createContext,
	useCallback,
	useContext,
	useMemo,
	useReducer,
	useRef,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import type { Api } from '../api';
import { useAnnounce } from '../components/LiveRegion';
import type {
	CheckoutType,
	Context,
	Field,
	FieldSet,
	InitialData,
	Resource,
	Settings,
	StoredData,
} from '../types';
import {
	changeCountFor,
	createInitialState,
	draftReducer,
	locateField,
	newFieldId,
	RESOURCES,
	type DraftAction,
	type DraftState,
	type FieldIds,
	type Problem,
	type SettingsChanges,
} from './reducer';
import { errorMessage, problemsFromError } from './errors';

export interface SaveOutcome {
	/**
	 * `nothing`: no changes; `saved`: every change saved; `invalid`: the
	 * server refused some data (see problems); `failed`: a request failed for
	 * another reason (see error).
	 */
	status: 'nothing' | 'saved' | 'invalid' | 'failed';
	/** Resources that were saved. */
	saved: Resource[];
	problems: Problem[];
	error: string | null;
}

interface Store {
	state: DraftState;
	dispatch: ( action: DraftAction ) => void;
	save: () => Promise< SaveOutcome >;
}

const StoreContext = createContext< Store | null >( null );

function useStore(): Store {
	const store = useContext( StoreContext );
	if ( ! store ) {
		throw new Error( 'Draft hooks must be used inside <DraftProvider>.' );
	}
	return store;
}

function announcement( outcome: SaveOutcome ): string {
	const partly = outcome.saved.length > 0;
	const count = outcome.problems.length;
	const error = outcome.error || '';

	if ( outcome.status === 'failed' && partly ) {
		return sprintf(
			/* translators: %s: why saving failed. */
			__(
				'Some changes were saved, others were not. %s',
				'checkout-fieldkit'
			),
			error
		);
	}
	if ( outcome.status === 'failed' ) {
		return sprintf(
			/* translators: %s: why saving failed. */
			__( 'Your changes were not saved. %s', 'checkout-fieldkit' ),
			error
		);
	}
	if ( partly ) {
		return sprintf(
			/* translators: %d: number of problems. */
			_n(
				'Some changes were saved. Fix %d problem and save again.',
				'Some changes were saved. Fix %d problems and save again.',
				count,
				'checkout-fieldkit'
			),
			count
		);
	}
	return sprintf(
		/* translators: %d: number of problems. */
		_n(
			'Your changes were not saved. Fix %d problem and save again.',
			'Your changes were not saved. Fix %d problems and save again.',
			count,
			'checkout-fieldkit'
		),
		count
	);
}

export interface DraftProviderProps {
	initial: InitialData;
	api: Api;
	children: ReactNode;
}

/**
 * Holds the draft for the app below it.
 *
 * @param props          Props.
 * @param props.initial  Context, both field sets and the settings, as loaded.
 * @param props.api      REST client.
 * @param props.children The app.
 */
export function DraftProvider( {
	initial,
	api,
	children,
}: DraftProviderProps ) {
	const [ state, dispatch ] = useReducer(
		draftReducer,
		initial,
		createInitialState
	);
	const announce = useAnnounce();
	const latest = useRef( state );
	latest.current = state;
	const inFlight = useRef( false );

	const save = useCallback( async (): Promise< SaveOutcome > => {
		const current = latest.current;
		const resources = RESOURCES.filter(
			( resource ) => changeCountFor( current, resource ) > 0
		);
		if ( inFlight.current || resources.length === 0 ) {
			return { status: 'nothing', saved: [], problems: [], error: null };
		}

		inFlight.current = true;
		dispatch( { type: 'SAVE_STARTED' } );

		const sent: Partial< StoredData > = {};
		const sentIds: Partial< FieldIds > = {};
		const requests = resources.map( ( resource ) => {
			if ( resource === 'settings' ) {
				sent.settings = current.draft.settings;
				return api.putSettings( current.draft.settings );
			}
			sent[ resource ] = current.draft[ resource ];
			sentIds[ resource ] = current.draftIds[ resource ];
			return api.putFieldSet( resource, current.draft[ resource ] );
		} );
		const results = await Promise.allSettled< FieldSet | Settings >(
			requests
		);

		const saved: Partial< StoredData > = {};
		const savedResources: Resource[] = [];
		const problems: Problem[] = [];
		let error: string | null = null;

		results.forEach( ( result, position ) => {
			const resource = resources[ position ];
			if ( result.status === 'fulfilled' ) {
				if ( resource === 'settings' ) {
					saved.settings = result.value as Settings;
				} else {
					saved[ resource ] = result.value as FieldSet;
				}
				savedResources.push( resource );
				return;
			}
			const mapped = problemsFromError(
				resource,
				result.reason,
				resource === 'settings' ? undefined : sentIds[ resource ]
			);
			if ( mapped ) {
				problems.push( ...mapped );
			} else if ( error === null ) {
				error = errorMessage( result.reason );
			}
		} );

		dispatch( {
			type: 'SAVE_FINISHED',
			sent,
			sentIds,
			saved,
			problems,
			error,
		} );
		inFlight.current = false;

		let status: SaveOutcome[ 'status' ] = 'saved';
		if ( error !== null ) {
			status = 'failed';
		} else if ( problems.length > 0 ) {
			status = 'invalid';
		}
		const outcome: SaveOutcome = {
			status,
			saved: savedResources,
			problems,
			error,
		};

		if ( status === 'saved' ) {
			announce( __( 'Changes saved.', 'checkout-fieldkit' ) );
		} else {
			announce( announcement( outcome ), 'assertive' );
		}

		return outcome;
	}, [ api, announce ] );

	const store = useMemo(
		() => ( { state, dispatch, save } ),
		[ state, save ]
	);

	return (
		<StoreContext.Provider value={ store }>
			{ children }
		</StoreContext.Provider>
	);
}

export interface DraftApi {
	/** Unsaved changes across both field sets and the settings. */
	changeCount: number;
	isDirty: boolean;
	isSaving: boolean;
	/** Resources with unsaved changes. */
	dirtyResources: Resource[];
	/** Problems from the last save, pinned to fields where possible. */
	problems: Problem[];
	/** Why the last save failed, when it was not a validation problem. */
	saveError: string | null;
	/** Writes every changed resource (PUT, in parallel). Never loses the draft. */
	save: () => Promise< SaveOutcome >;
	/** Throws away every unsaved change. */
	discard: () => void;
	/** Takes new saved data from the server (after an import or reset) as saved and draft. */
	replaceSaved: {
		( resource: CheckoutType, value: FieldSet ): void;
		( resource: 'settings', value: Settings ): void;
	};
}

/** The draft as a whole: what the header and the Tools screen need. */
export function useDraft(): DraftApi {
	const { state, dispatch, save } = useStore();
	const announce = useAnnounce();

	const counts = useMemo(
		() =>
			RESOURCES.map( ( resource ) => ( {
				resource,
				count: changeCountFor( state, resource ),
			} ) ),
		[ state ]
	);

	const discard = useCallback( () => {
		dispatch( { type: 'DISCARD' } );
		announce( __( 'Unsaved changes discarded.', 'checkout-fieldkit' ) );
	}, [ dispatch, announce ] );

	const replaceSaved = useCallback(
		( resource: Resource, value: FieldSet | Settings ) => {
			if ( resource === 'settings' ) {
				dispatch( {
					type: 'REPLACE_SAVED',
					resource,
					value: value as Settings,
				} );
			} else {
				dispatch( {
					type: 'REPLACE_SAVED',
					resource,
					value: value as FieldSet,
				} );
			}
		},
		[ dispatch ]
	) as DraftApi[ 'replaceSaved' ];

	const changeCount = counts.reduce( ( sum, item ) => sum + item.count, 0 );

	return {
		changeCount,
		isDirty: changeCount > 0,
		isSaving: state.saving,
		dirtyResources: counts
			.filter( ( item ) => item.count > 0 )
			.map( ( item ) => item.resource ),
		problems: state.problems,
		saveError: state.saveError,
		save,
		discard,
		replaceSaved,
	};
}

/** One field in a field set, as screens render it. */
export interface FieldRow {
	/** Client-side id: stable while the field is edited, renamed or moved. */
	id: string;
	field: Field;
	section: string;
	index: number;
	/** The saved version of this field; null for a field added in this draft. */
	saved: Field | null;
	isNew: boolean;
	/** Problems from the last save about this field. */
	problems: Problem[];
}

export interface SectionRows {
	name: string;
	label: string;
	rows: FieldRow[];
}

export interface FieldSetApi {
	checkoutType: CheckoutType;
	/** The draft field set. */
	fieldSet: FieldSet;
	savedFieldSet: FieldSet;
	/** Every section in display order (from the context), with its rows. */
	sections: SectionRows[];
	getRow: ( id: string ) => FieldRow | null;
	changeCount: number;
	/** Problems from the last save in this field set, including whole-section ones. */
	problems: Problem[];
	updateField: ( id: string, changes: Partial< Field > ) => void;
	updateFields: ( ids: string[], changes: Partial< Field > ) => void;
	setEnabled: ( ids: string | string[], enabled: boolean ) => void;
	setRequired: ( ids: string | string[], required: boolean ) => void;
	/**
	 * Adds a field, filled in from the context's field defaults, at `index`
	 * (default: the end). Returns its id. Pass the `id` of a field removed
	 * earlier to put it back as it was (undo); otherwise a new id is made.
	 */
	addField: (
		section: string,
		field?: Partial< Field >,
		index?: number,
		id?: string
	) => string;
	removeField: ( id: string ) => void;
	removeFields: ( ids: string[] ) => void;
	/** Moves a field to `index` in `section` (counted without the field itself). */
	moveField: ( id: string, section: string, index: number ) => void;
}

/**
 * One field set of the draft, and the actions on its fields.
 *
 * @param checkoutType Classic or block.
 */
export function useFieldSet( checkoutType: CheckoutType ): FieldSetApi {
	const { state, dispatch } = useStore();
	const fieldSet = state.draft[ checkoutType ];
	const ids = state.draftIds[ checkoutType ];
	const savedFieldSet = state.saved[ checkoutType ];
	const savedIds = state.savedIds[ checkoutType ];
	const contextSections = state.context.sections[ checkoutType ];
	const allProblems = state.problems;

	const problems = useMemo(
		() =>
			allProblems.filter(
				( problem ) => problem.resource === checkoutType
			),
		[ allProblems, checkoutType ]
	);

	const sections = useMemo( () => {
		const savedById = new Map< string, Field >();
		Object.keys( savedIds ).forEach( ( section ) => {
			savedIds[ section ].forEach( ( id, index ) => {
				const field = savedFieldSet.sections[ section ]?.[ index ];
				if ( field ) {
					savedById.set( id, field );
				}
			} );
		} );

		const known = contextSections.map( ( section ) => section.name );
		const extra = Object.keys( fieldSet.sections )
			.filter( ( name ) => ! known.includes( name ) )
			.map( ( name ) => ( { name, label: name } ) );

		return [ ...contextSections, ...extra ].map(
			( { name, label } ): SectionRows => ( {
				name,
				label,
				rows: ( fieldSet.sections[ name ] || [] ).map(
					( field, index ) => {
						const id = ( ids[ name ] || [] )[ index ];
						const saved = savedById.get( id ) || null;
						return {
							id,
							field,
							section: name,
							index,
							saved,
							isNew: saved === null,
							problems: problems.filter(
								( problem ) => problem.fieldId === id
							),
						};
					}
				),
			} )
		);
	}, [ fieldSet, ids, savedFieldSet, savedIds, contextSections, problems ] );

	const getRow = useCallback(
		( id: string ) => {
			const at = locateField( ids, id );
			if ( ! at ) {
				return null;
			}
			const section = sections.find(
				( item ) => item.name === at.section
			);
			return section?.rows[ at.index ] || null;
		},
		[ ids, sections ]
	);

	const fieldDefaults = state.context.fieldDefaults[ checkoutType ];

	const actions = useMemo( () => {
		const updateFields = ( targets: string[], changes: Partial< Field > ) =>
			dispatch( {
				type: 'UPDATE_FIELDS',
				checkoutType,
				ids: targets,
				changes,
			} );
		const list = ( targets: string | string[] ) =>
			Array.isArray( targets ) ? targets : [ targets ];
		const removeFields = ( targets: string[] ) =>
			dispatch( { type: 'REMOVE_FIELDS', checkoutType, ids: targets } );

		return {
			updateField: ( id: string, changes: Partial< Field > ) =>
				updateFields( [ id ], changes ),
			updateFields,
			setEnabled: ( targets: string | string[], enabled: boolean ) =>
				updateFields( list( targets ), { enabled } ),
			setRequired: ( targets: string | string[], required: boolean ) =>
				updateFields( list( targets ), { required } ),
			addField: (
				section: string,
				field: Partial< Field > = {},
				index?: number,
				reuseId?: string
			) => {
				const id = reuseId || newFieldId();
				dispatch( {
					type: 'ADD_FIELD',
					checkoutType,
					section,
					id,
					index,
					field: { ...fieldDefaults, ...field } as Field,
				} );
				return id;
			},
			removeField: ( id: string ) => removeFields( [ id ] ),
			removeFields,
			moveField: ( id: string, section: string, index: number ) =>
				dispatch( {
					type: 'MOVE_FIELD',
					checkoutType,
					id,
					section,
					index,
				} ),
		};
	}, [ dispatch, checkoutType, fieldDefaults ] );

	return {
		checkoutType,
		fieldSet,
		savedFieldSet,
		sections,
		getRow,
		changeCount: changeCountFor( state, checkoutType ),
		problems,
		...actions,
	};
}

export interface SettingsDraftApi {
	settings: Settings;
	savedSettings: Settings;
	/** The settings before any save, for "Restore defaults". */
	defaultSettings: Settings;
	changeCount: number;
	problems: Problem[];
	/** Merges changes into the draft settings; `localeOverrides` is merged switch by switch. */
	update: ( changes: SettingsChanges ) => void;
}

/** The settings in the draft. */
export function useSettingsDraft(): SettingsDraftApi {
	const { state, dispatch } = useStore();
	const allProblems = state.problems;
	const problems = useMemo(
		() =>
			allProblems.filter(
				( problem ) => problem.resource === 'settings'
			),
		[ allProblems ]
	);
	const update = useCallback(
		( changes: SettingsChanges ) =>
			dispatch( { type: 'UPDATE_SETTINGS', changes } ),
		[ dispatch ]
	);

	return {
		settings: state.draft.settings,
		savedSettings: state.saved.settings,
		defaultSettings: state.context.defaultSettings,
		changeCount: changeCountFor( state, 'settings' ),
		problems,
		update,
	};
}

/** GET /context: sections, field types, essential fields and the rest. */
export function useContextData(): Context {
	return useStore().state.context;
}
