/**
 * The draft reducer: saved and draft copies, dirty tracking and the change count.
 */
import {
	createInitialState,
	draftReducer,
	changeCount,
	dirtyResources,
	type DraftState,
} from '../../../../src/admin/store/reducer';
import { field, initialData } from '../fixtures';

function ids( state: DraftState, type: 'classic' | 'block', section: string ) {
	return state.draftIds[ type ][ section ];
}

function labels(
	state: DraftState,
	type: 'classic' | 'block',
	section: string
) {
	return state.draft[ type ].sections[ section ].map( ( f ) => f.label );
}

describe( 'draftReducer', () => {
	let state: DraftState;

	beforeEach( () => {
		state = createInitialState( initialData() );
	} );

	it( 'starts clean, with the draft equal to the saved data', () => {
		expect( changeCount( state ) ).toBe( 0 );
		expect( dirtyResources( state ) ).toEqual( [] );
		expect( state.draft ).toEqual( state.saved );
	} );

	it( 'gives every field a unique id, the same in saved and draft', () => {
		const all = [
			...Object.values( state.draftIds.classic ).flat(),
			...Object.values( state.draftIds.block ).flat(),
		];
		expect( new Set( all ).size ).toBe( all.length );
		expect( all ).toHaveLength( 8 );
		expect( state.savedIds ).toEqual( state.draftIds );
	} );

	it( 'counts one change per updated field', () => {
		const [ first, , vat ] = ids( state, 'classic', 'billing' );
		state = draftReducer( state, {
			type: 'UPDATE_FIELDS',
			checkoutType: 'classic',
			ids: [ first ],
			changes: { label: 'Given name' },
		} );
		state = draftReducer( state, {
			type: 'UPDATE_FIELDS',
			checkoutType: 'classic',
			ids: [ vat ],
			changes: { required: true, label: 'VAT' },
		} );

		expect( labels( state, 'classic', 'billing' ) ).toEqual( [
			'Given name',
			'Email address',
			'VAT',
		] );
		expect( changeCount( state ) ).toBe( 2 );
		expect( dirtyResources( state ) ).toEqual( [ 'classic' ] );
	} );

	it( 'is clean again when a change is undone by hand', () => {
		const [ first ] = ids( state, 'classic', 'billing' );
		state = draftReducer( state, {
			type: 'UPDATE_FIELDS',
			checkoutType: 'classic',
			ids: [ first ],
			changes: { enabled: false },
		} );
		expect( changeCount( state ) ).toBe( 1 );

		state = draftReducer( state, {
			type: 'UPDATE_FIELDS',
			checkoutType: 'classic',
			ids: [ first ],
			changes: { enabled: true },
		} );
		expect( changeCount( state ) ).toBe( 0 );
		expect( dirtyResources( state ) ).toEqual( [] );
	} );

	it( 'updates several fields at once, one change each', () => {
		const address = ids( state, 'block', 'address' );
		state = draftReducer( state, {
			type: 'UPDATE_FIELDS',
			checkoutType: 'block',
			ids: address,
			changes: { enabled: false },
		} );
		// Company was already off, so only Country changed.
		expect( changeCount( state ) ).toBe( 1 );
		expect(
			state.draft.block.sections.address.map( ( f ) => f.enabled )
		).toEqual( [ false, false ] );
	} );

	it( 'adds a field at an index or at the end, with the id it is given', () => {
		state = draftReducer( state, {
			type: 'ADD_FIELD',
			checkoutType: 'block',
			section: 'additional',
			id: 'new-1',
			field: field( { name: 'gift_note', label: 'Gift note' } ),
		} );
		state = draftReducer( state, {
			type: 'ADD_FIELD',
			checkoutType: 'block',
			section: 'additional',
			id: 'new-2',
			index: 0,
			field: field( { name: 'heard', label: 'Heard about us' } ),
		} );

		expect( labels( state, 'block', 'additional' ) ).toEqual( [
			'Heard about us',
			'Gift note',
		] );
		expect( ids( state, 'block', 'additional' ) ).toEqual( [
			'new-2',
			'new-1',
		] );
		expect( changeCount( state ) ).toBe( 2 );
		expect( dirtyResources( state ) ).toEqual( [ 'block' ] );
	} );

	it( 'removes fields and counts each removal', () => {
		const [ , email, vat ] = ids( state, 'classic', 'billing' );
		state = draftReducer( state, {
			type: 'REMOVE_FIELDS',
			checkoutType: 'classic',
			ids: [ vat, email ],
		} );
		expect( labels( state, 'classic', 'billing' ) ).toEqual( [
			'First name',
		] );
		expect( changeCount( state ) ).toBe( 2 );
	} );

	it( 'moves a field within its section and counts the new order once', () => {
		const [ first, email, vat ] = ids( state, 'classic', 'billing' );
		state = draftReducer( state, {
			type: 'MOVE_FIELD',
			checkoutType: 'classic',
			id: vat,
			section: 'billing',
			index: 0,
		} );
		expect( ids( state, 'classic', 'billing' ) ).toEqual( [
			vat,
			first,
			email,
		] );
		expect( changeCount( state ) ).toBe( 1 );

		state = draftReducer( state, {
			type: 'MOVE_FIELD',
			checkoutType: 'classic',
			id: vat,
			section: 'billing',
			index: 2,
		} );
		expect( ids( state, 'classic', 'billing' ) ).toEqual( [
			first,
			email,
			vat,
		] );
		expect( changeCount( state ) ).toBe( 0 );
	} );

	it( 'moves a field to another section', () => {
		const [ , , vat ] = ids( state, 'classic', 'billing' );
		state = draftReducer( state, {
			type: 'MOVE_FIELD',
			checkoutType: 'classic',
			id: vat,
			section: 'additional',
			index: 1,
		} );
		expect( labels( state, 'classic', 'additional' ) ).toEqual( [
			'Order notes',
			'VAT number',
		] );
		expect( changeCount( state ) ).toBe( 1 );
	} );

	it( 'counts each changed setting', () => {
		state = draftReducer( state, {
			type: 'UPDATE_SETTINGS',
			changes: { localeOverrides: { label: false, classes: true } },
		} );
		expect( state.draft.settings.localeOverrides ).toEqual( {
			label: false,
			placeholder: true,
			order: true,
			required: false,
			classes: true,
		} );
		expect( changeCount( state ) ).toBe( 2 );
		expect( dirtyResources( state ) ).toEqual( [ 'settings' ] );
	} );

	it( 'discards every change', () => {
		const [ first ] = ids( state, 'classic', 'billing' );
		state = draftReducer( state, {
			type: 'UPDATE_FIELDS',
			checkoutType: 'classic',
			ids: [ first ],
			changes: { label: 'X' },
		} );
		state = draftReducer( state, {
			type: 'UPDATE_SETTINGS',
			changes: { localeOverrides: { order: false } },
		} );
		state = draftReducer( state, { type: 'DISCARD' } );

		expect( changeCount( state ) ).toBe( 0 );
		expect( state.draft ).toEqual( state.saved );
		expect( state.draftIds ).toEqual( state.savedIds );
	} );

	it( 'takes what the server saved as both saved and draft', () => {
		const [ first ] = ids( state, 'classic', 'billing' );
		state = draftReducer( state, {
			type: 'UPDATE_FIELDS',
			checkoutType: 'classic',
			ids: [ first ],
			changes: { label: '  Given name ' },
		} );
		const sent = { classic: state.draft.classic };
		const sentIds = { classic: state.draftIds.classic };
		state = draftReducer( state, { type: 'SAVE_STARTED' } );
		expect( state.saving ).toBe( true );

		const fromServer = JSON.parse( JSON.stringify( sent.classic ) );
		fromServer.sections.billing[ 0 ].label = 'Given name';
		state = draftReducer( state, {
			type: 'SAVE_FINISHED',
			sent,
			sentIds,
			saved: { classic: fromServer },
			problems: [],
			error: null,
		} );

		expect( state.saving ).toBe( false );
		expect( changeCount( state ) ).toBe( 0 );
		expect( state.saved.classic.sections.billing[ 0 ].label ).toBe(
			'Given name'
		);
		expect( ids( state, 'classic', 'billing' )[ 0 ] ).toBe( first );
	} );

	it( 'keeps edits made while a save was running', () => {
		const [ first, email ] = ids( state, 'classic', 'billing' );
		state = draftReducer( state, {
			type: 'UPDATE_FIELDS',
			checkoutType: 'classic',
			ids: [ first ],
			changes: { label: 'Given name' },
		} );
		const sent = { classic: state.draft.classic };
		const sentIds = { classic: state.draftIds.classic };
		state = draftReducer( state, { type: 'SAVE_STARTED' } );
		state = draftReducer( state, {
			type: 'UPDATE_FIELDS',
			checkoutType: 'classic',
			ids: [ email ],
			changes: { label: 'Email' },
		} );
		state = draftReducer( state, {
			type: 'SAVE_FINISHED',
			sent,
			sentIds,
			saved: { classic: sent.classic },
			problems: [],
			error: null,
		} );

		expect( labels( state, 'classic', 'billing' ) ).toEqual( [
			'Given name',
			'Email',
			'VAT number',
		] );
		expect( changeCount( state ) ).toBe( 1 );
	} );

	it( 'clears a field’s problems for the keys that were edited', () => {
		const [ , , vat ] = ids( state, 'classic', 'billing' );
		state = {
			...state,
			problems: [
				{
					resource: 'classic',
					section: 'billing',
					fieldId: vat,
					key: 'name',
					message: 'Bad name',
				},
				{
					resource: 'classic',
					section: 'billing',
					fieldId: vat,
					key: 'default',
					message: 'Bad default',
				},
			],
		};
		state = draftReducer( state, {
			type: 'UPDATE_FIELDS',
			checkoutType: 'classic',
			ids: [ vat ],
			changes: { name: 'billing_vat_number' },
		} );
		expect( state.problems.map( ( p ) => p.key ) ).toEqual( [ 'default' ] );
	} );

	it( 'replaces a saved resource, for import and reset', () => {
		const [ first ] = ids( state, 'classic', 'billing' );
		state = draftReducer( state, {
			type: 'UPDATE_FIELDS',
			checkoutType: 'classic',
			ids: [ first ],
			changes: { label: 'X' },
		} );
		const replacement = initialData().block;
		state = draftReducer( state, {
			type: 'REPLACE_SAVED',
			resource: 'classic',
			value: {
				version: 1,
				sections: {
					billing: [ field( { name: 'billing_x', label: 'X' } ) ],
					shipping: [],
					additional: [],
				},
			},
		} );
		expect( labels( state, 'classic', 'billing' ) ).toEqual( [ 'X' ] );
		expect( changeCount( state ) ).toBe( 0 );
		expect( state.saved.block ).toEqual( replacement );
	} );
} );
