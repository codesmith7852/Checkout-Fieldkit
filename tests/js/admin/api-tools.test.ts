/**
 * The REST client's Tools routes (spec §7): export with the server's file
 * name, import preview, import and reset.
 */
import { createApi, fileNameFrom } from '../../../src/admin/api';

function response( body: string, headers: Record< string, string > = {} ) {
	return {
		headers: {
			get: ( name: string ) => headers[ name.toLowerCase() ] ?? null,
		},
		text: async () => body,
	};
}

describe( 'createApi tools routes', () => {
	it( 'exports the chosen targets, unparsed, with the file name from Content-Disposition', async () => {
		const body = '{"plugin":"checkout-fieldkit","format":1}';
		const fetcher = jest.fn().mockResolvedValue(
			response( body, {
				'content-disposition':
					'attachment; filename="checkout-fieldkit-shop.example-2026-11-14.json"',
			} )
		);

		const file = await createApi( fetcher ).exportSetup( [
			'classic',
			'settings',
		] );

		expect( fetcher ).toHaveBeenCalledWith( {
			path: '/checkout-fieldkit/v1/export?include=classic%2Csettings',
			parse: false,
		} );
		expect( file ).toEqual( {
			fileName: 'checkout-fieldkit-shop.example-2026-11-14.json',
			contents: body,
		} );
	} );

	it( 'rejects a failed export with the server’s error body', async () => {
		const error = {
			code: 'rest_invalid_param',
			message: 'Invalid parameter(s): include',
		};
		const fetcher = jest.fn().mockRejectedValue( {
			status: 400,
			json: async () => error,
		} );

		await expect(
			createApi( fetcher ).exportSetup( [ 'classic' ] )
		).rejects.toEqual( error );
	} );

	it( 'posts documents to import/preview and import, and targets to reset', async () => {
		const fetcher = jest.fn().mockResolvedValue( {} );
		const api = createApi( fetcher );
		const document = { plugin: 'checkout-fieldkit', format: 1 };

		await api.previewImport( document );
		await api.importSetup( document );
		await api.reset( 'block' );

		expect( fetcher.mock.calls.map( ( [ options ] ) => options ) ).toEqual(
			[
				{
					path: '/checkout-fieldkit/v1/import/preview',
					method: 'POST',
					data: document,
				},
				{
					path: '/checkout-fieldkit/v1/import',
					method: 'POST',
					data: document,
				},
				{
					path: '/checkout-fieldkit/v1/reset',
					method: 'POST',
					data: { target: 'block' },
				},
			]
		);
	} );
} );

describe( 'fileNameFrom', () => {
	const now = new Date( 2026, 10, 14, 9, 30 );

	it( 'reads quoted and unquoted file names', () => {
		expect( fileNameFrom( 'attachment; filename="a b.json"', now ) ).toBe(
			'a b.json'
		);
		expect( fileNameFrom( 'attachment; filename=setup.json', now ) ).toBe(
			'setup.json'
		);
	} );

	it( 'keeps only the base name', () => {
		expect(
			fileNameFrom( 'attachment; filename="../../evil.json"', now )
		).toBe( 'evil.json' );
	} );

	it( 'falls back to checkout-fieldkit-<host>-<date>.json', () => {
		expect( fileNameFrom( null, now ) ).toBe(
			`checkout-fieldkit-${ window.location.hostname }-2026-11-14.json`
		);
	} );
} );
