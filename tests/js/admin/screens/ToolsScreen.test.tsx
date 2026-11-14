/**
 * Tools screen (spec §6.5, frame 5): Export with choices and a download under
 * the server's file name; Import with a client-side JSON check, the server's
 * preview, an optional backup download, a warning about unsaved changes, and
 * the store updated afterwards; Reset per target with a confirmation dialog.
 */
import { screen, waitFor, within } from '@testing-library/react';
import type { Api } from '../../../../src/admin/api';
import type {
	FieldSet,
	ImportPreview,
	Settings,
} from '../../../../src/admin/types';
import { classicFieldSet, field, initialData, settings } from '../fixtures';
import {
	fakeApi,
	header,
	politeAnnouncements,
	renderApp,
} from './settingsToolsHarness';

const FILE_NAME = 'checkout-fieldkit-shop.example-2026-11-14.json';

let downloads: Array< { name: string; blob: Blob } >;
let lastBlob: Blob | null;

beforeEach( () => {
	downloads = [];
	lastBlob = null;
	URL.createObjectURL = jest.fn( ( blob: Blob ) => {
		lastBlob = blob;
		return 'blob:https://shop.example/1';
	} );
	URL.revokeObjectURL = jest.fn();
	jest.spyOn( HTMLAnchorElement.prototype, 'click' ).mockImplementation(
		function ( this: HTMLAnchorElement ) {
			downloads.push( { name: this.download, blob: lastBlob as Blob } );
		}
	);
} );

afterEach( () => {
	jest.restoreAllMocks();
} );

const card = ( name: string ) => screen.getByRole( 'region', { name } );

/* ------------------------------------------------------------------ */
/* Export                                                              */
/* ------------------------------------------------------------------ */

describe( 'Tools → Export', () => {
	const exportCard = () => card( 'Export' );

	it( 'offers the three targets, ticked, with counts', () => {
		renderApp( 'tools' );

		const boxes = within( exportCard() ).getAllByRole( 'checkbox' );
		expect( boxes ).toHaveLength( 3 );
		boxes.forEach( ( box ) => expect( box ).toBeChecked() );
		expect(
			within( exportCard() ).getByRole( 'checkbox', {
				name: 'Classic checkout fields 5 fields',
			} )
		).toBeVisible();
		expect(
			within( exportCard() ).getByRole( 'checkbox', {
				name: 'Block checkout fields 3 fields',
			} )
		).toBeVisible();
		expect(
			within( exportCard() ).getByRole( 'checkbox', {
				name: 'Settings 5 switches',
			} )
		).toBeVisible();
		expect( exportCard() ).toHaveTextContent(
			'The file holds no orders or customer data.'
		);
	} );

	it( 'requests the ticked targets and downloads the file under the server’s name', async () => {
		const { api, user } = renderApp( 'tools' );

		await user.click(
			within( exportCard() ).getByRole( 'checkbox', {
				name: /Block checkout fields/,
			} )
		);
		await user.click(
			within( exportCard() ).getByRole( 'button', {
				name: 'Download file',
			} )
		);

		await waitFor( () => expect( downloads ).toHaveLength( 1 ) );
		expect( api.exportSetup ).toHaveBeenCalledWith( [
			'classic',
			'settings',
		] );
		expect( downloads[ 0 ].name ).toBe( FILE_NAME );
		expect( downloads[ 0 ].blob.type ).toBe( 'application/json' );
		expect( downloads[ 0 ].blob.size ).toBe(
			'{"plugin":"checkout-fieldkit","format":1}'.length
		);
		expect( politeAnnouncements() ).toContain( FILE_NAME );
	} );

	it( 'needs at least one target', async () => {
		const { api, user } = renderApp( 'tools' );

		for ( const box of within( exportCard() ).getAllByRole( 'checkbox' ) ) {
			await user.click( box );
		}
		const download = within( exportCard() ).getByRole( 'button', {
			name: 'Download file',
		} );
		expect( download ).toHaveAttribute( 'aria-disabled', 'true' );
		await user.click( download );

		expect( api.exportSetup ).not.toHaveBeenCalled();
		expect( exportCard() ).toHaveTextContent(
			'Choose at least one thing to export.'
		);
	} );

	it( 'says when the export fails', async () => {
		const api = fakeApi();
		api.exportSetup.mockRejectedValue( {
			code: 'fetch_error',
			message: 'You are probably offline.',
		} );
		const { user } = renderApp( 'tools', { api } );

		await user.click(
			within( exportCard() ).getByRole( 'button', {
				name: 'Download file',
			} )
		);

		expect(
			await within( exportCard() ).findByRole( 'alert' )
		).toHaveTextContent( /The server could not be reached/ );
		expect( downloads ).toHaveLength( 0 );
	} );
} );

/* ------------------------------------------------------------------ */
/* Import                                                              */
/* ------------------------------------------------------------------ */

const importedClassic = (): FieldSet => ( {
	version: 1,
	sections: {
		billing: [
			field( {
				name: 'billing_first_name',
				label: 'Given name',
				builtIn: true,
			} ),
			field( { name: 'gift_message', label: 'Gift message' } ),
			field( { name: 'gift_wrap', label: 'Gift wrap' } ),
		],
		shipping: [],
		additional: [],
	},
} );

const importedSettings = (): Settings => {
	const value = settings();
	value.localeOverrides.classes = true;
	return value;
};

const exportDocument = () => ( {
	plugin: 'checkout-fieldkit',
	format: 1,
	exportedAt: '2026-11-12T10:00:00+00:00',
	classic: importedClassic(),
	settings: importedSettings(),
} );

const preview = (): ImportPreview => ( {
	targets: [
		{
			target: 'classic',
			action: 'replace',
			added: [ 'gift_message', 'gift_wrap' ],
			removed: [ 'billing_vat' ],
			changed: [ 'billing_first_name' ],
		},
		{
			target: 'block',
			action: 'keep',
			added: [],
			removed: [],
			changed: [],
		},
		{
			target: 'settings',
			action: 'replace',
			added: [],
			removed: [],
			changed: [ 'classes' ],
		},
	],
} );

function importApi( overrides: Partial< Api > = {} ) {
	const api = fakeApi( initialData(), overrides );
	if ( ! overrides.previewImport ) {
		api.previewImport.mockResolvedValue( preview() );
	}
	if ( ! overrides.importSetup ) {
		api.importSetup.mockResolvedValue( {
			imported: [ 'classic', 'settings' ],
			classic: importedClassic(),
			settings: importedSettings(),
		} );
	}
	return api;
}

const importCard = () => card( 'Import' );
const fileInput = () =>
	within( importCard() ).getByLabelText( 'Import file' ) as HTMLInputElement;
const jsonFile = ( contents: string, name = 'setup.json' ) =>
	new File( [ contents ], name, { type: 'application/json' } );
const plan = () =>
	within( importCard() ).getByRole( 'list', {
		name: 'What the import will change',
	} );

describe( 'Tools → Import', () => {
	it( 'refuses a file that is not JSON without asking the server', async () => {
		const { api, user } = renderApp( 'tools', { api: importApi() } );

		await user.upload( fileInput(), jsonFile( '{"plugin": ', 'bad.json' ) );

		expect(
			await within( importCard() ).findByRole( 'alert' )
		).toHaveTextContent( /bad\.json is not a valid JSON file/ );
		expect( importCard() ).toHaveTextContent( 'Nothing was changed.' );
		expect( api.previewImport ).not.toHaveBeenCalled();
	} );

	it( 'shows the server’s preview: Replace with names, or Keep', async () => {
		const { api, user } = renderApp( 'tools', { api: importApi() } );

		await user.upload(
			fileInput(),
			jsonFile( JSON.stringify( exportDocument() ), 'shop.json' )
		);

		const rows = within( await waitFor( plan ) ).getAllByRole( 'listitem' );
		expect( api.previewImport ).toHaveBeenCalledWith( exportDocument() );
		expect( importCard() ).toHaveTextContent( 'shop.json' );
		expect( rows ).toHaveLength( 3 );
		expect( rows[ 0 ] ).toHaveTextContent(
			'ReplaceClassic checkout fieldsNew: Gift message, Gift wrap. Removed: VAT number. Changed: First name.'
		);
		expect( rows[ 1 ] ).toHaveTextContent(
			'KeepBlock checkout fieldsNot in the file, so nothing changes.'
		);
		expect( rows[ 2 ] ).toHaveTextContent(
			'ReplaceSettingsKeep my CSS classes turns on.'
		);
		expect(
			within( importCard() ).getByRole( 'checkbox', {
				name: 'Download a copy of my current setup first',
			} )
		).toBeChecked();
		expect(
			within( importCard() ).getByRole( 'button', {
				name: 'Import and replace',
			} )
		).toBeVisible();
	} );

	it( 'says a target in the file is the same as now', async () => {
		const same = preview();
		same.targets[ 0 ] = {
			...same.targets[ 0 ],
			added: [],
			removed: [],
			changed: [],
		};
		same.targets[ 2 ] = { ...same.targets[ 2 ], changed: [] };
		const { user } = renderApp( 'tools', {
			api: importApi( { previewImport: jest.fn( async () => same ) } ),
		} );

		await user.upload(
			fileInput(),
			jsonFile( JSON.stringify( exportDocument() ) )
		);

		const rows = within( await waitFor( plan ) ).getAllByRole( 'listitem' );
		expect( rows[ 0 ] ).toHaveTextContent( 'Same fields as now.' );
		expect( rows[ 2 ] ).toHaveTextContent( 'Same settings as now.' );
	} );

	it( 'explains an invalid file with the server’s messages', async () => {
		const api = importApi( {
			previewImport: jest.fn().mockRejectedValue( {
				code: 'checkout_fieldkit_invalid_import',
				message: 'Nothing was imported because the file has problems.',
				data: {
					status: 422,
					errors: [
						{
							target: '',
							section: '',
							index: null,
							key: 'plugin',
							message:
								'This file was not exported by Checkout FieldKit.',
						},
						{
							target: 'block',
							section: 'additional',
							index: 0,
							key: 'name',
							message: 'The field name is not valid.',
						},
					],
				},
			} ),
		} );
		const { user } = renderApp( 'tools', { api } );

		await user.upload(
			fileInput(),
			jsonFile( '{"plugin":"other"}', 'other.json' )
		);

		const alert = await within( importCard() ).findByRole( 'alert' );
		expect( alert ).toHaveTextContent(
			/other\.json can’t be imported\. Nothing was changed\./
		);
		expect( alert ).toHaveTextContent(
			'This file was not exported by Checkout FieldKit.'
		);
		expect( alert ).toHaveTextContent(
			'Block checkout fields: The field name is not valid.'
		);
		expect(
			within( importCard() ).queryByRole( 'button', {
				name: 'Import and replace',
			} )
		).toBeNull();
		expect( api.importSetup ).not.toHaveBeenCalled();
	} );

	it( 'downloads a backup first, imports, and takes the imported data as saved', async () => {
		const { api, user } = renderApp( 'tools', { api: importApi() } );
		expect( card( 'Reset' ) ).toHaveTextContent(
			'deletes your 1 custom field'
		);

		await user.upload(
			fileInput(),
			jsonFile( JSON.stringify( exportDocument() ) )
		);
		await user.click(
			await within( importCard() ).findByRole( 'button', {
				name: 'Import and replace',
			} )
		);

		await waitFor( () =>
			expect( politeAnnouncements() ).toMatch(
				/Import finished\. Replaced: Classic checkout fields, Settings\./
			)
		);
		expect( api.exportSetup ).toHaveBeenCalledWith( [
			'classic',
			'block',
			'settings',
		] );
		expect( downloads.map( ( item ) => item.name ) ).toEqual( [
			FILE_NAME,
		] );
		expect( api.exportSetup.mock.invocationCallOrder[ 0 ] ).toBeLessThan(
			api.importSetup.mock.invocationCallOrder[ 0 ]
		);
		expect( api.importSetup ).toHaveBeenCalledWith( exportDocument() );

		// The store now holds the imported data as saved.
		expect( card( 'Reset' ) ).toHaveTextContent(
			'deletes your 2 custom fields'
		);
		expect( header() ).not.toHaveTextContent( /unsaved/ );
		expect(
			within( card( 'Export' ) ).getByRole( 'checkbox', {
				name: 'Classic checkout fields 3 fields',
			} )
		).toBeVisible();
		expect( importCard() ).toHaveTextContent(
			/Import finished\. Replaced: Classic checkout fields, Settings\./
		);
		expect( within( importCard() ).queryByRole( 'list' ) ).toBeNull();
		expect(
			within( importCard() )
				.getByText( /^Import finished\./ )
				.closest( '.cfk-notice' )
		).toHaveFocus();
	} );

	it( 'imports without a backup when that is unticked', async () => {
		const { api, user } = renderApp( 'tools', { api: importApi() } );

		await user.upload(
			fileInput(),
			jsonFile( JSON.stringify( exportDocument() ) )
		);
		await user.click(
			await within( importCard() ).findByRole( 'checkbox', {
				name: 'Download a copy of my current setup first',
			} )
		);
		await user.click(
			within( importCard() ).getByRole( 'button', {
				name: 'Import and replace',
			} )
		);

		await waitFor( () => expect( api.importSetup ).toHaveBeenCalled() );
		expect( api.exportSetup ).not.toHaveBeenCalled();
		expect( downloads ).toHaveLength( 0 );
	} );

	it( 'imports nothing when the backup cannot be downloaded', async () => {
		const api = importApi( {
			exportSetup: jest
				.fn()
				.mockRejectedValue( { code: 'fetch_error', message: '' } ),
		} );
		const { user } = renderApp( 'tools', { api } );

		await user.upload(
			fileInput(),
			jsonFile( JSON.stringify( exportDocument() ) )
		);
		await user.click(
			await within( importCard() ).findByRole( 'button', {
				name: 'Import and replace',
			} )
		);

		expect(
			await within( importCard() ).findByRole( 'alert' )
		).toHaveTextContent(
			/The copy of your current setup could not be downloaded, so nothing was imported\./
		);
		expect( api.importSetup ).not.toHaveBeenCalled();
		expect( plan() ).toBeVisible();
	} );

	it( 'Cancel drops the chosen file and its preview', async () => {
		const { user } = renderApp( 'tools', { api: importApi() } );

		await user.upload(
			fileInput(),
			jsonFile( JSON.stringify( exportDocument() ) )
		);
		await waitFor( plan );
		await user.click(
			within( importCard() ).getByRole( 'button', { name: 'Cancel' } )
		);

		expect( within( importCard() ).queryByRole( 'list' ) ).toBeNull();
		expect( importCard() ).not.toHaveTextContent( 'setup.json' );
		expect(
			within( importCard() ).getByRole( 'button', {
				name: 'Choose a file…',
			} )
		).toHaveFocus();
	} );

	it( 'warns about unsaved changes the import would overwrite, and asks first', async () => {
		const api = importApi();
		const { user } = renderApp( 'settings', { api } );
		await user.click(
			screen.getByRole( 'switch', { name: 'Keep my labels' } )
		);
		await user.click(
			within( header() ).getByRole( 'link', { name: 'Tools' } )
		);

		await user.upload(
			fileInput(),
			jsonFile( JSON.stringify( exportDocument() ) )
		);
		await waitFor( plan );
		expect( importCard() ).toHaveTextContent(
			'You have unsaved changes to Settings. Importing replaces them with the file’s version.'
		);

		const importButton = within( importCard() ).getByRole( 'button', {
			name: 'Import and replace',
		} );
		await user.click( importButton );
		let dialog = screen.getByRole( 'alertdialog', {
			name: 'Replace your unsaved changes?',
		} );
		expect(
			within( dialog ).getByRole( 'button', { name: 'Keep editing' } )
		).toHaveFocus();
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Keep editing' } )
		);
		expect( screen.queryByRole( 'alertdialog' ) ).toBeNull();
		expect( api.importSetup ).not.toHaveBeenCalled();
		expect( header() ).toHaveTextContent( '1 unsaved change' );

		await user.click( importButton );
		dialog = screen.getByRole( 'alertdialog' );
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Discard them and import',
			} )
		);

		await waitFor( () => expect( api.importSetup ).toHaveBeenCalled() );
		await waitFor( () =>
			expect( header() ).not.toHaveTextContent( /unsaved/ )
		);
	} );

	it( 'does not warn about unsaved changes to a target the file keeps', async () => {
		const api = importApi( {
			previewImport: jest.fn( async () => {
				const value = preview();
				value.targets[ 2 ] = {
					target: 'settings',
					action: 'keep',
					added: [],
					removed: [],
					changed: [],
				};
				return value;
			} ),
			importSetup: jest.fn( async () => ( {
				imported: [ 'classic' as const ],
				classic: importedClassic(),
			} ) ),
		} );
		const { user } = renderApp( 'settings', { api } );
		await user.click(
			screen.getByRole( 'switch', { name: 'Keep my labels' } )
		);
		await user.click(
			within( header() ).getByRole( 'link', { name: 'Tools' } )
		);

		await user.upload(
			fileInput(),
			jsonFile( JSON.stringify( exportDocument() ) )
		);
		await waitFor( plan );
		expect( importCard() ).not.toHaveTextContent( /unsaved changes/ );
		await user.click(
			within( importCard() ).getByRole( 'button', {
				name: 'Import and replace',
			} )
		);

		await waitFor( () => expect( api.importSetup ).toHaveBeenCalled() );
		expect( screen.queryByRole( 'alertdialog' ) ).toBeNull();
		await waitFor( () =>
			expect( politeAnnouncements() ).toMatch( /Import finished/ )
		);
		expect( header() ).toHaveTextContent( '1 unsaved change' );
	} );
} );

/* ------------------------------------------------------------------ */
/* Reset                                                               */
/* ------------------------------------------------------------------ */

describe( 'Tools → Reset', () => {
	const resetCard = () => card( 'Reset' );

	function resetApi() {
		const defaults = classicFieldSet();
		defaults.sections.billing = defaults.sections.billing.filter(
			( item ) => item.builtIn
		);
		return fakeApi( initialData(), {
			reset: jest.fn( async ( target ) => {
				if ( target === 'settings' ) {
					return { reset: target, settings: settings() };
				}
				return { reset: target, [ target ]: defaults };
			} ),
		} );
	}

	it( 'states each consequence from the saved fields', () => {
		renderApp( 'tools' );

		expect( resetCard() ).toHaveTextContent(
			'Classic checkout fieldsRestores WooCommerce’s built-in fields, undoes your changes to them and deletes your 1 custom field.'
		);
		expect( resetCard() ).toHaveTextContent(
			'Block checkout fieldsRestores WooCommerce’s built-in fields and undoes your changes to them. You have no custom fields.'
		);
		expect( resetCard() ).toHaveTextContent(
			'SettingsTurns the five address field switches back to their defaults.'
		);
	} );

	it( 'asks before resetting, with Cancel focused', async () => {
		const { api, user } = renderApp( 'tools', { api: resetApi() } );

		await user.click(
			within( resetCard() ).getByRole( 'button', {
				name: 'Reset Classic checkout…',
			} )
		);

		const dialog = screen.getByRole( 'alertdialog', {
			name: 'Reset Classic checkout fields?',
		} );
		expect( dialog ).toHaveTextContent(
			'deletes your 1 custom field: VAT number.'
		);
		expect( dialog ).toHaveTextContent(
			'Orders keep the answers already saved on them.'
		);
		expect(
			within( dialog ).getByRole( 'button', { name: 'Cancel' } )
		).toHaveFocus();

		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Cancel' } )
		);
		expect( screen.queryByRole( 'alertdialog' ) ).toBeNull();
		expect( api.reset ).not.toHaveBeenCalled();
	} );

	it( 'resets a field set on confirmation and takes the defaults as saved', async () => {
		const { api, user } = renderApp( 'tools', { api: resetApi() } );

		await user.click(
			within( resetCard() ).getByRole( 'button', {
				name: 'Reset Classic checkout…',
			} )
		);
		await user.click(
			within( screen.getByRole( 'alertdialog' ) ).getByRole( 'button', {
				name: 'Reset Classic checkout',
			} )
		);

		await waitFor( () =>
			expect( screen.queryByRole( 'alertdialog' ) ).toBeNull()
		);
		expect( api.reset ).toHaveBeenCalledWith( 'classic' );
		expect( resetCard() ).toHaveTextContent(
			'Classic checkout fieldsRestores WooCommerce’s built-in fields and undoes your changes to them. You have no custom fields.'
		);
		expect( politeAnnouncements() ).toBe(
			'Classic checkout fields reset to WooCommerce’s defaults.'
		);
	} );

	it( 'resets the settings, replacing unsaved switches, and says so first', async () => {
		const { api, user } = renderApp( 'settings', { api: resetApi() } );
		await user.click(
			screen.getByRole( 'switch', { name: 'Keep my CSS classes' } )
		);
		await user.click(
			within( header() ).getByRole( 'link', { name: 'Tools' } )
		);

		await user.click(
			within( resetCard() ).getByRole( 'button', {
				name: 'Reset settings…',
			} )
		);
		const dialog = screen.getByRole( 'alertdialog', {
			name: 'Reset settings?',
		} );
		expect( dialog ).toHaveTextContent(
			'Your unsaved changes here are lost too.'
		);
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Reset settings' } )
		);

		await waitFor( () =>
			expect( api.reset ).toHaveBeenCalledWith( 'settings' )
		);
		await waitFor( () =>
			expect( header() ).not.toHaveTextContent( /unsaved/ )
		);
	} );

	it( 'keeps the dialog open and says why when the reset fails', async () => {
		const api = fakeApi( initialData(), {
			reset: jest.fn().mockRejectedValue( {
				code: 'rest_cookie_invalid_nonce',
				message: 'Cookie check failed',
			} ),
		} );
		const { user } = renderApp( 'tools', { api } );

		await user.click(
			within( resetCard() ).getByRole( 'button', {
				name: 'Reset Block checkout…',
			} )
		);
		await user.click(
			within( screen.getByRole( 'alertdialog' ) ).getByRole( 'button', {
				name: 'Reset Block checkout',
			} )
		);

		expect(
			await within( screen.getByRole( 'alertdialog' ) ).findByRole(
				'alert'
			)
		).toHaveTextContent( 'Nothing was reset. Cookie check failed' );
	} );
} );
