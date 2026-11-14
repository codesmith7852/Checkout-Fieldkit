/**
 * The REST client sends the routes and methods of spec §7.
 */
import { createApi } from '../../../src/admin/api';
import { blockFieldSet, settings } from './fixtures';

describe( 'createApi', () => {
	it( 'reads and writes the routes under checkout-fieldkit/v1', async () => {
		const fetcher = jest.fn().mockResolvedValue( {} );
		const api = createApi( fetcher );
		const fieldSet = blockFieldSet();
		const value = settings();

		await api.getContext();
		await api.getFieldSet( 'classic' );
		await api.getSettings();
		await api.putFieldSet( 'block', fieldSet );
		await api.putSettings( value );

		expect( fetcher.mock.calls.map( ( [ options ] ) => options ) ).toEqual(
			[
				{ path: '/checkout-fieldkit/v1/context' },
				{ path: '/checkout-fieldkit/v1/field-sets/classic' },
				{ path: '/checkout-fieldkit/v1/settings' },
				{
					path: '/checkout-fieldkit/v1/field-sets/block',
					method: 'PUT',
					data: fieldSet,
				},
				{
					path: '/checkout-fieldkit/v1/settings',
					method: 'PUT',
					data: value,
				},
			]
		);
	} );

	it( 'uses another namespace when given one', async () => {
		const fetcher = jest.fn().mockResolvedValue( {} );
		await createApi( fetcher, 'other/v2' ).getContext();
		expect( fetcher ).toHaveBeenCalledWith( { path: '/other/v2/context' } );
	} );
} );
