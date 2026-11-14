/**
 * The draft store's hooks: editing, dirty tracking, discard and save, including
 * 422 problems mapped back to fields and network errors.
 */
import { act, renderHook, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import type { Api } from '../../../../src/admin/api';
import { AnnouncerProvider } from '../../../../src/admin/components/LiveRegion';
import {
	DraftProvider,
	useContextData,
	useDraft,
	useFieldSet,
	useSettingsDraft,
} from '../../../../src/admin/store';
import type { FieldSet, Settings } from '../../../../src/admin/types';
import { initialData } from '../fixtures';

function fakeApi( overrides: Partial< Api > = {} ): jest.Mocked< Api > {
	return {
		getContext: jest.fn(),
		getFieldSet: jest.fn(),
		getSettings: jest.fn(),
		putFieldSet: jest.fn( async ( _type, fieldSet: FieldSet ) =>
			JSON.parse( JSON.stringify( fieldSet ) )
		),
		putSettings: jest.fn( async ( value: Settings ) =>
			JSON.parse( JSON.stringify( value ) )
		),
		...overrides,
	} as jest.Mocked< Api >;
}

function setup( api: jest.Mocked< Api > = fakeApi() ) {
	const wrapper = ( { children }: { children: ReactNode } ) => (
		<AnnouncerProvider>
			<DraftProvider initial={ initialData() } api={ api }>
				{ children }
			</DraftProvider>
		</AnnouncerProvider>
	);
	const { result } = renderHook(
		() => ( {
			draft: useDraft(),
			classic: useFieldSet( 'classic' ),
			block: useFieldSet( 'block' ),
			settings: useSettingsDraft(),
			context: useContextData(),
		} ),
		{ wrapper }
	);
	return { result, api };
}

function invalid( code: string, errors: unknown[] ) {
	return {
		code,
		message:
			'The fields were not saved because some of them have problems.',
		data: { status: 422, errors },
	};
}

describe( 'DraftProvider', () => {
	it( 'exposes the context and the field set rows by section, clean', () => {
		const { result } = setup();

		expect( result.current.context.activeCheckoutType ).toBe( 'block' );
		expect(
			result.current.classic.sections.map( ( section ) => [
				section.name,
				section.label,
				section.rows.length,
			] )
		).toEqual( [
			[ 'billing', 'Billing', 3 ],
			[ 'shipping', 'Shipping', 1 ],
			[ 'additional', 'Additional', 1 ],
		] );
		const [ first ] = result.current.classic.sections[ 0 ].rows;
		expect( first ).toMatchObject( {
			section: 'billing',
			index: 0,
			field: { name: 'billing_first_name' },
			problems: [],
			isNew: false,
		} );
		expect( result.current.draft ).toMatchObject( {
			isDirty: false,
			changeCount: 0,
			isSaving: false,
		} );
	} );

	it( 'tracks changes from the field actions and counts them', () => {
		const { result } = setup();
		const [ first, email, vat ] = result.current.classic.sections[ 0 ].rows;

		act( () => result.current.classic.setEnabled( first.id, false ) );
		act( () => result.current.classic.setRequired( vat.id, true ) );
		act( () =>
			result.current.classic.updateField( email.id, { label: 'Email' } )
		);

		expect( result.current.draft.changeCount ).toBe( 3 );
		expect( result.current.draft.isDirty ).toBe( true );
		expect( result.current.draft.dirtyResources ).toEqual( [ 'classic' ] );
		expect( result.current.classic.changeCount ).toBe( 3 );
		expect( result.current.block.changeCount ).toBe( 0 );
		expect( result.current.classic.getRow( first.id )?.field.enabled ).toBe(
			false
		);
	} );

	it( 'adds a field with the context defaults, removes and moves fields', () => {
		const { result } = setup();
		let id = '';
		act( () => {
			id = result.current.block.addField( 'additional', {
				name: 'gift_note',
				label: 'Gift note',
			} );
		} );

		const added = result.current.block.getRow( id );
		expect( added ).toMatchObject( {
			section: 'additional',
			index: 0,
			isNew: true,
			field: {
				name: 'gift_note',
				label: 'Gift note',
				type: 'text',
				enabled: true,
				showInEmails: true,
			},
		} );

		act( () => result.current.block.moveField( id, 'contact', 0 ) );
		expect( result.current.block.getRow( id ) ).toMatchObject( {
			section: 'contact',
			index: 0,
		} );

		act( () => result.current.block.removeField( id ) );
		expect( result.current.block.getRow( id ) ).toBeNull();
		expect( result.current.draft.isDirty ).toBe( false );
	} );

	it( 'puts a removed field back under its old id, as if never removed (undo)', () => {
		const { result } = setup();
		const vat = result.current.classic.sections[ 0 ].rows[ 2 ];

		act( () => result.current.classic.removeField( vat.id ) );
		expect( result.current.draft.changeCount ).toBe( 1 );

		let id = '';
		act( () => {
			id = result.current.classic.addField(
				vat.section,
				vat.field,
				vat.index,
				vat.id
			);
		} );

		expect( id ).toBe( vat.id );
		expect( result.current.classic.getRow( vat.id ) ).toMatchObject( {
			index: 2,
			isNew: false,
		} );
		expect( result.current.draft.changeCount ).toBe( 0 );
	} );

	it( 'updates settings and discards everything', () => {
		const { result } = setup();
		const [ first ] = result.current.classic.sections[ 0 ].rows;

		act( () =>
			result.current.settings.update( {
				localeOverrides: { required: true },
			} )
		);
		act( () => result.current.classic.setEnabled( first.id, false ) );
		expect(
			result.current.settings.settings.localeOverrides.required
		).toBe( true );
		expect( result.current.settings.changeCount ).toBe( 1 );
		expect( result.current.draft.changeCount ).toBe( 2 );

		act( () => result.current.draft.discard() );

		expect( result.current.draft.changeCount ).toBe( 0 );
		expect(
			result.current.settings.settings.localeOverrides.required
		).toBe( false );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Unsaved changes discarded.'
		);
	} );

	it( 'saves nothing when nothing changed', async () => {
		const { result, api } = setup();

		let outcome;
		await act( async () => {
			outcome = await result.current.draft.save();
		} );

		expect( outcome ).toMatchObject( { status: 'nothing' } );
		expect( api.putFieldSet ).not.toHaveBeenCalled();
		expect( api.putSettings ).not.toHaveBeenCalled();
	} );

	it( 'saves only the changed resources and takes the server’s copy', async () => {
		const api = fakeApi( {
			putFieldSet: jest.fn( async ( _type, fieldSet: FieldSet ) => {
				const saved = JSON.parse( JSON.stringify( fieldSet ) );
				saved.sections.address[ 1 ].label = 'Company name';
				return saved;
			} ),
		} );
		const { result } = setup( api );
		const [ , company ] = result.current.block.sections[ 1 ].rows;

		act( () => result.current.block.setEnabled( company.id, true ) );
		act( () =>
			result.current.settings.update( {
				localeOverrides: { label: false },
			} )
		);

		let outcome;
		await act( async () => {
			outcome = await result.current.draft.save();
		} );

		expect( api.putFieldSet ).toHaveBeenCalledTimes( 1 );
		expect( api.putFieldSet ).toHaveBeenCalledWith(
			'block',
			expect.objectContaining( {
				sections: expect.objectContaining( {
					address: [
						expect.objectContaining( { name: 'country' } ),
						expect.objectContaining( {
							name: 'company',
							enabled: true,
						} ),
					],
				} ),
			} )
		);
		expect( api.putSettings ).toHaveBeenCalledWith(
			expect.objectContaining( {
				localeOverrides: expect.objectContaining( { label: false } ),
			} )
		);
		expect( outcome ).toMatchObject( {
			status: 'saved',
			saved: [ 'block', 'settings' ],
		} );
		expect( result.current.draft.isDirty ).toBe( false );
		expect( result.current.draft.isSaving ).toBe( false );
		expect( result.current.block.getRow( company.id )?.field.label ).toBe(
			'Company name'
		);
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Changes saved.'
		);
	} );

	it( 'is saving while the requests run', async () => {
		let finish: ( value: FieldSet ) => void = () => {};
		const api = fakeApi( {
			putFieldSet: jest.fn(
				() =>
					new Promise< FieldSet >( ( resolve ) => {
						finish = resolve;
					} )
			),
		} );
		const { result } = setup( api );
		const [ first ] = result.current.classic.sections[ 0 ].rows;
		act( () => result.current.classic.setEnabled( first.id, false ) );

		let pending: Promise< unknown > = Promise.resolve();
		act( () => {
			pending = result.current.draft.save();
		} );
		expect( result.current.draft.isSaving ).toBe( true );

		await act( async () => {
			finish( result.current.classic.fieldSet );
			await pending;
		} );
		expect( result.current.draft.isSaving ).toBe( false );
	} );

	it( 'maps 422 field set problems back to the fields and keeps the draft', async () => {
		const api = fakeApi( {
			putFieldSet: jest.fn( async () => {
				throw invalid( 'checkout_fieldkit_invalid_field_set', [
					{
						section: 'billing',
						index: 2,
						key: 'name',
						message: 'Bad name.',
					},
					{
						section: 'shipping',
						index: null,
						key: 'name',
						message: 'Shipping city cannot be removed.',
					},
				] );
			} ),
		} );
		const { result } = setup( api );
		const [ , , vat ] = result.current.classic.sections[ 0 ].rows;
		act( () =>
			result.current.classic.updateField( vat.id, { name: 'Bad Name' } )
		);

		let outcome;
		await act( async () => {
			outcome = await result.current.draft.save();
		} );

		expect( outcome ).toMatchObject( { status: 'invalid', saved: [] } );
		expect( result.current.draft.problems ).toEqual( [
			{
				resource: 'classic',
				section: 'billing',
				fieldId: vat.id,
				key: 'name',
				message: 'Bad name.',
			},
			{
				resource: 'classic',
				section: 'shipping',
				fieldId: null,
				key: 'name',
				message: 'Shipping city cannot be removed.',
			},
		] );
		expect( result.current.classic.getRow( vat.id )?.problems ).toEqual( [
			expect.objectContaining( { key: 'name', message: 'Bad name.' } ),
		] );
		expect( result.current.classic.problems ).toHaveLength( 2 );
		expect( result.current.draft.saveError ).toBeNull();
		expect( result.current.draft.isDirty ).toBe( true );
		expect( result.current.classic.getRow( vat.id )?.field.name ).toBe(
			'Bad Name'
		);
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent(
			'Your changes were not saved. Fix 2 problems and save again.'
		);

		// Editing the key clears its problem.
		act( () =>
			result.current.classic.updateField( vat.id, {
				name: 'billing_vat',
			} )
		);
		expect( result.current.classic.getRow( vat.id )?.problems ).toEqual(
			[]
		);
	} );

	it( 'saves what it can and maps settings problems when one request fails', async () => {
		const api = fakeApi( {
			putSettings: jest.fn( async () => {
				throw invalid( 'checkout_fieldkit_invalid_settings', [
					{
						key: 'localeOverrides.label',
						message: 'Must be on or off.',
					},
				] );
			} ),
		} );
		const { result } = setup( api );
		const [ first ] = result.current.classic.sections[ 0 ].rows;
		act( () => result.current.classic.setEnabled( first.id, false ) );
		act( () =>
			result.current.settings.update( {
				localeOverrides: { label: false },
			} )
		);

		let outcome;
		await act( async () => {
			outcome = await result.current.draft.save();
		} );

		expect( outcome ).toMatchObject( {
			status: 'invalid',
			saved: [ 'classic' ],
		} );
		expect( result.current.classic.changeCount ).toBe( 0 );
		expect( result.current.settings.changeCount ).toBe( 1 );
		expect( result.current.settings.problems ).toEqual( [
			{
				resource: 'settings',
				section: null,
				fieldId: null,
				key: 'localeOverrides.label',
				message: 'Must be on or off.',
			},
		] );
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent(
			'Some changes were saved. Fix 1 problem and save again.'
		);
	} );

	it( 'keeps every change when the server cannot be reached', async () => {
		const api = fakeApi( {
			putFieldSet: jest.fn( async () => {
				throw {
					code: 'fetch_error',
					message: 'You are probably offline.',
				};
			} ),
		} );
		const { result } = setup( api );
		const [ first ] = result.current.classic.sections[ 0 ].rows;
		act( () => result.current.classic.setEnabled( first.id, false ) );

		let outcome;
		await act( async () => {
			outcome = await result.current.draft.save();
		} );

		expect( outcome ).toMatchObject( { status: 'failed', saved: [] } );
		expect( result.current.draft.saveError ).toBe(
			'The server could not be reached. Check your connection and try again.'
		);
		expect( result.current.draft.problems ).toEqual( [] );
		expect( result.current.draft.changeCount ).toBe( 1 );
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent(
			'Your changes were not saved. The server could not be reached. Check your connection and try again.'
		);
	} );

	it( 'shows the server’s message for other errors', async () => {
		const api = fakeApi( {
			putSettings: jest.fn( async () => {
				throw {
					code: 'rest_cookie_invalid_nonce',
					message: 'Cookie check failed',
					data: { status: 403 },
				};
			} ),
		} );
		const { result } = setup( api );
		act( () =>
			result.current.settings.update( {
				localeOverrides: { label: false },
			} )
		);

		await act( async () => {
			await result.current.draft.save();
		} );

		expect( result.current.draft.saveError ).toBe( 'Cookie check failed' );
	} );

	it( 'replaces saved data after an import or reset', () => {
		const { result } = setup();
		const [ first ] = result.current.classic.sections[ 0 ].rows;
		act( () => result.current.classic.setEnabled( first.id, false ) );

		const reset = initialData().settings;
		reset.localeOverrides.classes = true;
		act( () => result.current.draft.replaceSaved( 'settings', reset ) );

		expect( result.current.settings.savedSettings ).toEqual( reset );
		expect( result.current.settings.settings ).toEqual( reset );
		expect( result.current.draft.changeCount ).toBe( 1 );
	} );
} );
