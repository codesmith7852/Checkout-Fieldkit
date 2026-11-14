/**
 * The draft (GLOSSARY): a saved copy and a draft copy of both field sets and
 * the settings. Screens change only the draft; Save changes writes the
 * resources that differ, Discard copies the saved data back.
 *
 * Every field carries a client-side id, kept beside the data (`draftIds`
 * mirrors `draft.<type>.sections` index for index), so a field keeps its
 * identity while it is renamed, moved or edited, and a 422 problem found at a
 * section and index can be pinned to the field it was about. Ids never reach
 * the server.
 */
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
import { deepEqual } from './equal';

/** Field ids per section, in the same order as the section's fields. */
export type SectionIds = Record< string, string[] >;

export type FieldIds = Record< CheckoutType, SectionIds >;

/** A problem the server found when saving, pinned to a field when it is about one. */
export interface Problem {
	resource: Resource;
	/** Section name for field set problems; null for settings. */
	section: string | null;
	/** The field the problem is about; null for whole-section and settings problems. */
	fieldId: string | null;
	/** Field key, such as `name`, or a settings path such as `localeOverrides.label`. */
	key: string;
	message: string;
}

export interface DraftState {
	context: Context;
	saved: StoredData;
	savedIds: FieldIds;
	draft: StoredData;
	draftIds: FieldIds;
	saving: boolean;
	problems: Problem[];
	/** Why the last save failed when it was not a validation problem (network, permissions). */
	saveError: string | null;
}

/** Partial settings; nested objects such as `localeOverrides` are merged, not replaced. */
export interface SettingsChanges {
	localeOverrides?: Record< string, boolean >;
	[ key: string ]: unknown;
}

export type DraftAction =
	| {
			type: 'UPDATE_FIELDS';
			checkoutType: CheckoutType;
			ids: string[];
			changes: Partial< Field >;
	  }
	| {
			type: 'ADD_FIELD';
			checkoutType: CheckoutType;
			section: string;
			field: Field;
			id: string;
			index?: number;
	  }
	| { type: 'REMOVE_FIELDS'; checkoutType: CheckoutType; ids: string[] }
	| {
			type: 'MOVE_FIELD';
			checkoutType: CheckoutType;
			id: string;
			section: string;
			index: number;
	  }
	| { type: 'UPDATE_SETTINGS'; changes: SettingsChanges }
	| { type: 'DISCARD' }
	| { type: 'SAVE_STARTED' }
	| {
			type: 'SAVE_FINISHED';
			/** The draft values that were sent, by resource. */
			sent: Partial< StoredData >;
			/** The field ids of the field sets that were sent. */
			sentIds: Partial< FieldIds >;
			/** What the server saved, for the resources that succeeded. */
			saved: Partial< StoredData >;
			problems: Problem[];
			error: string | null;
	  }
	| {
			type: 'REPLACE_SAVED';
			resource: CheckoutType;
			value: FieldSet;
	  }
	| {
			type: 'REPLACE_SAVED';
			resource: 'settings';
			value: Settings;
	  };

export const CHECKOUT_TYPES: CheckoutType[] = [ 'classic', 'block' ];

export const RESOURCES: Resource[] = [ 'classic', 'block', 'settings' ];

let lastId = 0;

/** A new client-side field id. */
export function newFieldId(): string {
	lastId += 1;
	return `cfk-field-${ lastId }`;
}

/**
 * New ids for every field of a field set.
 *
 * @param fieldSet Field set.
 */
export function idsFor( fieldSet: FieldSet ): SectionIds {
	const ids: SectionIds = {};
	Object.keys( fieldSet.sections ).forEach( ( section ) => {
		ids[ section ] = fieldSet.sections[ section ].map( () => newFieldId() );
	} );
	return ids;
}

/**
 * The state for freshly loaded data: nothing changed yet.
 *
 * @param data Context, both field sets and the settings.
 */
export function createInitialState( data: InitialData ): DraftState {
	const stored: StoredData = {
		classic: data.classic,
		block: data.block,
		settings: data.settings,
	};
	const ids: FieldIds = {
		classic: idsFor( data.classic ),
		block: idsFor( data.block ),
	};

	return {
		context: data.context,
		saved: stored,
		savedIds: ids,
		draft: stored,
		draftIds: ids,
		saving: false,
		problems: [],
		saveError: null,
	};
}

/**
 * Where a field is in a field set.
 *
 * @param ids Field ids by section.
 * @param id  Field id.
 */
export function locateField(
	ids: SectionIds,
	id: string
): { section: string; index: number } | null {
	for ( const section of Object.keys( ids ) ) {
		const index = ids[ section ].indexOf( id );
		if ( index !== -1 ) {
			return { section, index };
		}
	}
	return null;
}

function clamp( index: number | undefined, length: number ): number {
	if ( index === undefined || index > length ) {
		return length;
	}
	return Math.max( 0, index );
}

function insertAt< T >( list: T[], index: number, item: T ): T[] {
	const next = list.slice();
	next.splice( index, 0, item );
	return next;
}

function isPlainObject( value: unknown ): value is Record< string, unknown > {
	return (
		typeof value === 'object' && value !== null && ! Array.isArray( value )
	);
}

function withFieldSet(
	state: DraftState,
	type: CheckoutType,
	fieldSet: FieldSet,
	ids: SectionIds
): DraftState {
	return {
		...state,
		draft: { ...state.draft, [ type ]: fieldSet },
		draftIds: { ...state.draftIds, [ type ]: ids },
	};
}

function updateFields(
	state: DraftState,
	type: CheckoutType,
	targets: string[],
	changes: Partial< Field >
): DraftState {
	const wanted = new Set( targets );
	const fieldSet = state.draft[ type ];
	const ids = state.draftIds[ type ];
	const sections: FieldSet[ 'sections' ] = {};

	Object.keys( fieldSet.sections ).forEach( ( section ) => {
		const sectionIds = ids[ section ] || [];
		sections[ section ] = fieldSet.sections[ section ].map(
			( field, index ) =>
				wanted.has( sectionIds[ index ] )
					? { ...field, ...changes }
					: field
		);
	} );

	const changedKeys = Object.keys( changes );
	const next = withFieldSet( state, type, { ...fieldSet, sections }, ids );

	return {
		...next,
		problems: state.problems.filter(
			( problem ) =>
				! (
					problem.resource === type &&
					problem.fieldId !== null &&
					wanted.has( problem.fieldId ) &&
					changedKeys.includes( problem.key )
				)
		),
	};
}

function addField(
	state: DraftState,
	type: CheckoutType,
	section: string,
	field: Field,
	id: string,
	index?: number
): DraftState {
	const fieldSet = state.draft[ type ];
	const fields = fieldSet.sections[ section ] || [];
	const ids = state.draftIds[ type ][ section ] || [];
	const at = clamp( index, fields.length );

	return withFieldSet(
		state,
		type,
		{
			...fieldSet,
			sections: {
				...fieldSet.sections,
				[ section ]: insertAt( fields, at, field ),
			},
		},
		{ ...state.draftIds[ type ], [ section ]: insertAt( ids, at, id ) }
	);
}

function removeFields(
	state: DraftState,
	type: CheckoutType,
	targets: string[]
): DraftState {
	const unwanted = new Set( targets );
	const fieldSet = state.draft[ type ];
	const ids = state.draftIds[ type ];
	const sections: FieldSet[ 'sections' ] = {};
	const nextIds: SectionIds = {};

	Object.keys( fieldSet.sections ).forEach( ( section ) => {
		const sectionIds = ids[ section ] || [];
		sections[ section ] = fieldSet.sections[ section ].filter(
			( _field, index ) => ! unwanted.has( sectionIds[ index ] )
		);
		nextIds[ section ] = sectionIds.filter(
			( id ) => ! unwanted.has( id )
		);
	} );

	const next = withFieldSet(
		state,
		type,
		{ ...fieldSet, sections },
		nextIds
	);

	return {
		...next,
		problems: state.problems.filter(
			( problem ) =>
				problem.fieldId === null || ! unwanted.has( problem.fieldId )
		),
	};
}

function moveField(
	state: DraftState,
	type: CheckoutType,
	id: string,
	toSection: string,
	toIndex: number
): DraftState {
	const from = locateField( state.draftIds[ type ], id );
	if ( ! from ) {
		return state;
	}

	const fieldSet = state.draft[ type ];
	const field = fieldSet.sections[ from.section ][ from.index ];
	const removed = removeFields( state, type, [ id ] );

	return {
		...addField( removed, type, toSection, field, id, toIndex ),
		problems: state.problems,
	};
}

function updateSettings(
	state: DraftState,
	changes: SettingsChanges
): DraftState {
	const settings: Settings = { ...state.draft.settings };
	const paths: string[] = [];

	Object.keys( changes ).forEach( ( key ) => {
		const value = changes[ key ];
		const current = settings[ key ];
		if ( isPlainObject( value ) && isPlainObject( current ) ) {
			settings[ key ] = { ...current, ...value };
			Object.keys( value ).forEach( ( child ) =>
				paths.push( `${ key }.${ child }` )
			);
		} else {
			settings[ key ] = value;
			paths.push( key );
		}
	} );

	return {
		...state,
		draft: { ...state.draft, settings },
		problems: state.problems.filter(
			( problem ) =>
				problem.resource !== 'settings' ||
				! paths.some(
					( path ) =>
						problem.key === path ||
						problem.key.startsWith( `${ path }.` ) ||
						path.startsWith( `${ problem.key }.` )
				)
		),
	};
}

/**
 * Whether a field set has as many fields per section as there are ids.
 *
 * @param fieldSet Field set.
 * @param ids      Ids by section.
 */
function idsMatch( fieldSet: FieldSet, ids: SectionIds | undefined ): boolean {
	if ( ! ids ) {
		return false;
	}
	const sections = Object.keys( fieldSet.sections );
	return (
		sections.length === Object.keys( ids ).length &&
		sections.every(
			( section ) =>
				( ids[ section ] || [] ).length ===
				fieldSet.sections[ section ].length
		)
	);
}

function saveFinished(
	state: DraftState,
	action: Extract< DraftAction, { type: 'SAVE_FINISHED' } >
): DraftState {
	const saved = { ...state.saved };
	const savedIds = { ...state.savedIds };
	const draft = { ...state.draft };
	const draftIds = { ...state.draftIds };

	CHECKOUT_TYPES.forEach( ( type ) => {
		const value = action.saved[ type ];
		if ( ! value ) {
			return;
		}
		const sentIds = action.sentIds[ type ];
		const ids =
			sentIds && idsMatch( value, sentIds ) ? sentIds : idsFor( value );
		saved[ type ] = value;
		savedIds[ type ] = ids;
		// Take the server's copy unless the field set was edited during the save.
		if ( state.draft[ type ] === action.sent[ type ] ) {
			draft[ type ] = value;
			draftIds[ type ] = ids;
		}
	} );

	if ( action.saved.settings ) {
		saved.settings = action.saved.settings;
		if ( state.draft.settings === action.sent.settings ) {
			draft.settings = action.saved.settings;
		}
	}

	return {
		...state,
		saved,
		savedIds,
		draft,
		draftIds,
		saving: false,
		problems: action.problems,
		saveError: action.error,
	};
}

export function draftReducer(
	state: DraftState,
	action: DraftAction
): DraftState {
	switch ( action.type ) {
		case 'UPDATE_FIELDS':
			return updateFields(
				state,
				action.checkoutType,
				action.ids,
				action.changes
			);
		case 'ADD_FIELD':
			return addField(
				state,
				action.checkoutType,
				action.section,
				action.field,
				action.id,
				action.index
			);
		case 'REMOVE_FIELDS':
			return removeFields( state, action.checkoutType, action.ids );
		case 'MOVE_FIELD':
			return moveField(
				state,
				action.checkoutType,
				action.id,
				action.section,
				action.index
			);
		case 'UPDATE_SETTINGS':
			return updateSettings( state, action.changes );
		case 'DISCARD':
			return {
				...state,
				draft: state.saved,
				draftIds: state.savedIds,
				problems: [],
				saveError: null,
			};
		case 'SAVE_STARTED':
			return { ...state, saving: true, saveError: null };
		case 'SAVE_FINISHED':
			return saveFinished( state, action );
		case 'REPLACE_SAVED': {
			const problems = state.problems.filter(
				( problem ) => problem.resource !== action.resource
			);
			if ( action.resource === 'settings' ) {
				return {
					...state,
					saved: { ...state.saved, settings: action.value },
					draft: { ...state.draft, settings: action.value },
					problems,
				};
			}
			const ids = idsFor( action.value );
			return {
				...state,
				saved: { ...state.saved, [ action.resource ]: action.value },
				savedIds: { ...state.savedIds, [ action.resource ]: ids },
				draft: { ...state.draft, [ action.resource ]: action.value },
				draftIds: { ...state.draftIds, [ action.resource ]: ids },
				problems,
			};
		}
		default:
			return state;
	}
}

/**
 * How many fields differ between two copies of a field set: one per added,
 * removed or edited field (a field moved to another section counts as
 * edited), plus one per section whose remaining fields are in a new order.
 *
 * @param saved    Saved field set.
 * @param savedIds Its field ids.
 * @param draft    Draft field set.
 * @param draftIds Its field ids.
 */
export function countFieldSetChanges(
	saved: FieldSet,
	savedIds: SectionIds,
	draft: FieldSet,
	draftIds: SectionIds
): number {
	const index = ( fieldSet: FieldSet, ids: SectionIds ) => {
		const map = new Map< string, { section: string; field: Field } >();
		Object.keys( fieldSet.sections ).forEach( ( section ) => {
			fieldSet.sections[ section ].forEach( ( field, position ) => {
				const id = ( ids[ section ] || [] )[ position ];
				if ( id !== undefined ) {
					map.set( id, { section, field } );
				}
			} );
		} );
		return map;
	};

	const before = index( saved, savedIds );
	const after = index( draft, draftIds );
	let count = 0;

	after.forEach( ( now, id ) => {
		const then = before.get( id );
		if (
			! then ||
			then.section !== now.section ||
			! deepEqual( then.field, now.field )
		) {
			count += 1;
		}
	} );
	before.forEach( ( _then, id ) => {
		if ( ! after.has( id ) ) {
			count += 1;
		}
	} );

	const sections = new Set( [
		...Object.keys( savedIds ),
		...Object.keys( draftIds ),
	] );
	sections.forEach( ( section ) => {
		const staying = ( ids: string[] ) =>
			ids.filter(
				( id ) =>
					before.get( id )?.section === section &&
					after.get( id )?.section === section
			);
		if (
			! deepEqual(
				staying( savedIds[ section ] || [] ),
				staying( draftIds[ section ] || [] )
			)
		) {
			count += 1;
		}
	} );

	return count;
}

/**
 * How many settings differ, counting each switch inside a nested object.
 * The schema `version` is not a change.
 *
 * @param saved Saved settings.
 * @param draft Draft settings.
 * @param depth Nesting level; 0 for the settings object itself.
 */
export function countSettingsChanges(
	saved: Record< string, unknown >,
	draft: Record< string, unknown >,
	depth = 0
): number {
	const keys = new Set( [
		...Object.keys( saved ),
		...Object.keys( draft ),
	] );
	let count = 0;

	keys.forEach( ( key ) => {
		if ( depth === 0 && key === 'version' ) {
			return;
		}
		const before = saved[ key ];
		const after = draft[ key ];
		if ( isPlainObject( before ) && isPlainObject( after ) ) {
			count += countSettingsChanges( before, after, depth + 1 );
		} else if ( ! deepEqual( before, after ) ) {
			count += 1;
		}
	} );

	return count;
}

/**
 * Unsaved changes in one resource.
 *
 * @param state    Draft state.
 * @param resource Field set or settings.
 */
export function changeCountFor(
	state: DraftState,
	resource: Resource
): number {
	if ( resource === 'settings' ) {
		return state.saved.settings === state.draft.settings
			? 0
			: countSettingsChanges(
					state.saved.settings,
					state.draft.settings
				);
	}
	if (
		state.saved[ resource ] === state.draft[ resource ] &&
		state.savedIds[ resource ] === state.draftIds[ resource ]
	) {
		return 0;
	}
	return countFieldSetChanges(
		state.saved[ resource ],
		state.savedIds[ resource ],
		state.draft[ resource ],
		state.draftIds[ resource ]
	);
}

/**
 * All unsaved changes, as shown in the header.
 *
 * @param state Draft state.
 */
export function changeCount( state: DraftState ): number {
	return RESOURCES.reduce(
		( total, resource ) => total + changeCountFor( state, resource ),
		0
	);
}

/**
 * The resources Save changes has to write.
 *
 * @param state Draft state.
 */
export function dirtyResources( state: DraftState ): Resource[] {
	return RESOURCES.filter(
		( resource ) => changeCountFor( state, resource ) > 0
	);
}
