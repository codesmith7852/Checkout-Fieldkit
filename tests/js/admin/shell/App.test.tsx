/**
 * The app shell (spec §6.1): header states, tabs synced with the URL, save
 * and discard from the header, the problem summary, the leave-page warning
 * and loading data that was not preloaded.
 */
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import App, { Providers } from '../../../../src/admin/App';
import type { Api } from '../../../../src/admin/api';
import { Shell } from '../../../../src/admin/shell/Shell';
import { useFieldSet, useSettingsDraft } from '../../../../src/admin/store';
import type {
	AdminSettings,
	FieldSet,
	InitialData,
	Settings,
} from '../../../../src/admin/types';
import { initialData } from '../fixtures';

function fakeApi( overrides: Partial< Api > = {} ): jest.Mocked< Api > {
	const data = initialData();
	return {
		getContext: jest.fn( async () => data.context ),
		getFieldSet: jest.fn( async ( type ) => data[ type ] ),
		getSettings: jest.fn( async () => data.settings ),
		putFieldSet: jest.fn( async ( _type, fieldSet: FieldSet ) =>
			JSON.parse( JSON.stringify( fieldSet ) )
		),
		putSettings: jest.fn( async ( value: Settings ) =>
			JSON.parse( JSON.stringify( value ) )
		),
		...overrides,
	} as jest.Mocked< Api >;
}

function adminSettings( data: InitialData = initialData() ): AdminSettings {
	return {
		restNamespace: 'checkout-fieldkit/v1',
		preloaded: {
			context: data.context,
			fieldSets: { classic: data.classic, block: data.block },
			settings: data.settings,
		},
	};
}

/** Stands in for the later screens: buttons that edit the draft. */
function Editor() {
	const classic = useFieldSet( 'classic' );
	const { update } = useSettingsDraft();
	const [ first, , vat ] = classic.sections[ 0 ].rows;
	return (
		<div>
			<button
				type="button"
				onClick={ () =>
					classic.setEnabled( first.id, ! first.field.enabled )
				}
			>
				Toggle first name
			</button>
			<button
				type="button"
				onClick={ () =>
					classic.updateField( vat.id, { name: 'Bad Name' } )
				}
			>
				Break VAT name
			</button>
			<button
				type="button"
				onClick={ () =>
					update( { localeOverrides: { label: false } } )
				}
			>
				Turn off labels
			</button>
		</div>
	);
}

function renderShell( api: jest.Mocked< Api > = fakeApi() ) {
	render(
		<Providers settings={ adminSettings() } api={ api }>
			<Shell />
			<Editor />
		</Providers>
	);
	return { api, user: userEvent.setup() };
}

const header = () => screen.getByRole( 'banner' );
// The app's polite live region (the Fields screen's drag-and-drop adds its own).
const announcer = () =>
	screen
		.getAllByRole( 'status' )
		.find( ( region ) =>
			region.classList.contains( 'cfk-visually-hidden' )
		);
const saveButton = () =>
	within( header() ).getByRole( 'button', { name: /Save changes|Saving/ } );

beforeEach( () => {
	window.history.replaceState(
		null,
		'',
		'/wp-admin/admin.php?page=checkout-fieldkit'
	);
} );

describe( 'App header', () => {
	it( 'shows the name, the tabs and "All changes saved" when clean', () => {
		render( <App settings={ adminSettings() } api={ fakeApi() } /> );

		expect(
			within( header() ).getByText( 'Checkout FieldKit' )
		).toBeVisible();
		const nav = within( header() ).getByRole( 'navigation', {
			name: 'Checkout FieldKit',
		} );
		const links = within( nav ).getAllByRole( 'link' );
		expect( links.map( ( link ) => link.textContent ) ).toEqual( [
			'Fields',
			'Settings',
			'Tools',
		] );
		expect( links[ 0 ] ).toHaveAttribute( 'aria-current', 'page' );
		expect( links[ 1 ] ).toHaveAttribute(
			'href',
			'/wp-admin/admin.php?page=checkout-fieldkit&tab=settings'
		);

		expect(
			within( header() ).getByText( 'All changes saved' )
		).toBeVisible();
		expect( saveButton() ).toHaveAttribute( 'aria-disabled', 'true' );
		expect(
			within( header() ).queryByRole( 'button', { name: 'Discard' } )
		).not.toBeInTheDocument();
	} );

	it( 'counts unsaved changes and offers Discard and Save changes', async () => {
		const { user } = renderShell();

		await user.click(
			screen.getByRole( 'button', { name: 'Toggle first name' } )
		);
		expect(
			within( header() ).getByText( '1 unsaved change' )
		).toBeVisible();

		await user.click(
			screen.getByRole( 'button', { name: 'Turn off labels' } )
		);
		expect(
			within( header() ).getByText( '2 unsaved changes' )
		).toBeVisible();
		expect(
			within( header() ).queryByText( 'All changes saved' )
		).not.toBeInTheDocument();
		expect( saveButton() ).not.toHaveAttribute( 'aria-disabled' );
		expect(
			within( header() ).getByRole( 'button', { name: 'Discard' } )
		).toBeVisible();
	} );

	it( 'saves from the header and announces it', async () => {
		const { user, api } = renderShell();
		await user.click(
			screen.getByRole( 'button', { name: 'Toggle first name' } )
		);

		await user.click( saveButton() );

		await waitFor( () =>
			expect(
				within( header() ).getByText( 'All changes saved' )
			).toBeVisible()
		);
		expect( api.putFieldSet ).toHaveBeenCalledTimes( 1 );
		expect( api.putSettings ).not.toHaveBeenCalled();
		expect( announcer() ).toHaveTextContent( 'Changes saved.' );
		expect( saveButton() ).toHaveFocus();
	} );

	it( 'does nothing when Save changes is pressed with nothing to save', async () => {
		const { user, api } = renderShell();
		await user.click( saveButton() );
		expect( api.putFieldSet ).not.toHaveBeenCalled();
		expect( api.putSettings ).not.toHaveBeenCalled();
	} );

	it( 'shows "Saving…" while the save runs', async () => {
		let finish: ( value: FieldSet ) => void = () => {};
		const api = fakeApi( {
			putFieldSet: jest.fn(
				() =>
					new Promise< FieldSet >( ( resolve ) => {
						finish = resolve;
					} )
			),
		} );
		const { user } = renderShell( api );
		await user.click(
			screen.getByRole( 'button', { name: 'Toggle first name' } )
		);
		await user.click( saveButton() );

		expect( saveButton() ).toHaveTextContent( 'Saving…' );
		expect( saveButton() ).toHaveAttribute( 'aria-disabled', 'true' );
		expect(
			within( header() ).getByRole( 'button', { name: 'Discard' } )
		).toBeDisabled();

		await act( async () => finish( initialData().classic ) );
	} );

	it( 'confirms before discarding, and Keep editing keeps the changes', async () => {
		const { user } = renderShell();
		await user.click(
			screen.getByRole( 'button', { name: 'Toggle first name' } )
		);

		await user.click(
			within( header() ).getByRole( 'button', { name: 'Discard' } )
		);
		const dialog = screen.getByRole( 'alertdialog', {
			name: 'Discard unsaved changes?',
		} );
		expect( dialog ).toHaveTextContent(
			'Your 1 unsaved change will be lost.'
		);
		expect(
			within( dialog ).getByRole( 'button', { name: 'Keep editing' } )
		).toHaveFocus();

		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Keep editing' } )
		);
		await waitFor( () =>
			expect(
				screen.queryByRole( 'alertdialog' )
			).not.toBeInTheDocument()
		);
		expect(
			within( header() ).getByText( '1 unsaved change' )
		).toBeVisible();

		await user.click(
			within( header() ).getByRole( 'button', { name: 'Discard' } )
		);
		await user.click(
			within( screen.getByRole( 'alertdialog' ) ).getByRole( 'button', {
				name: 'Discard changes',
			} )
		);
		await waitFor( () =>
			expect(
				within( header() ).getByText( 'All changes saved' )
			).toBeVisible()
		);
		expect( announcer() ).toHaveTextContent( 'Unsaved changes discarded.' );
	} );
} );

describe( 'Save problems', () => {
	it( 'lists 422 problems in a focused summary that links to each field', async () => {
		const api = fakeApi( {
			putFieldSet: jest.fn( async () => {
				throw {
					code: 'checkout_fieldkit_invalid_field_set',
					message: 'Not saved.',
					data: {
						status: 422,
						errors: [
							{
								section: 'billing',
								index: 2,
								key: 'name',
								message:
									'The field name must start with a lower-case letter.',
							},
						],
					},
				};
			} ),
		} );
		const { user } = renderShell( api );
		await user.click(
			screen.getByRole( 'button', { name: 'Break VAT name' } )
		);
		await user.click( saveButton() );

		const summary = await screen.findByRole( 'group', {
			name: 'Fix 1 problem, then save again',
		} );
		expect( summary ).toHaveFocus();
		expect( summary ).toHaveTextContent(
			'The field name must start with a lower-case letter.'
		);
		expect(
			within( header() ).getByText( '1 unsaved change' )
		).toBeVisible();

		const link = within( summary ).getByRole( 'link', {
			name: 'Classic checkout · Billing · VAT number',
		} );
		await user.click( link );
		expect( window.location.search ).toBe(
			'?page=checkout-fieldkit&tab=fields&checkout=classic&section=billing'
		);
	} );

	it( 'shows a network error with Try again, keeping the changes', async () => {
		const putSettings = jest
			.fn()
			.mockRejectedValueOnce( {
				code: 'fetch_error',
				message: 'You are probably offline.',
			} )
			.mockImplementation( async ( value: Settings ) => value );
		const { user, api } = renderShell( fakeApi( { putSettings } ) );
		await user.click(
			screen.getByRole( 'button', { name: 'Turn off labels' } )
		);
		await user.click( saveButton() );

		const summary = await screen.findByRole( 'group', {
			name: 'Your changes were not saved',
		} );
		expect( summary ).toHaveTextContent(
			'The server could not be reached. Check your connection and try again.'
		);
		expect(
			within( header() ).getByText( '1 unsaved change' )
		).toBeVisible();

		await user.click(
			within( summary ).getByRole( 'button', { name: 'Try again' } )
		);
		await waitFor( () =>
			expect(
				within( header() ).getByText( 'All changes saved' )
			).toBeVisible()
		);
		expect( api.putSettings ).toHaveBeenCalledTimes( 2 );
		expect(
			screen.queryByRole( 'group', { name: /not saved/ } )
		).toBeNull();
	} );
} );

describe( 'Navigation', () => {
	it( 'switches tabs, updates the URL and moves focus to the screen title', async () => {
		const user = userEvent.setup();
		render( <App settings={ adminSettings() } api={ fakeApi() } /> );

		await user.click( screen.getByRole( 'link', { name: 'Settings' } ) );

		expect( window.location.search ).toBe(
			'?page=checkout-fieldkit&tab=settings'
		);
		const title = screen.getByRole( 'heading', {
			level: 1,
			name: 'Settings',
		} );
		expect( title ).toHaveFocus();
		expect(
			screen.getByRole( 'link', { name: 'Settings' } )
		).toHaveAttribute( 'aria-current', 'page' );

		await user.click( screen.getByRole( 'link', { name: 'Tools' } ) );
		expect(
			screen.getByRole( 'heading', { level: 1, name: 'Tools' } )
		).toHaveFocus();
	} );

	it( 'opens on the location in the URL', () => {
		window.history.replaceState(
			null,
			'',
			'/wp-admin/admin.php?page=checkout-fieldkit&tab=fields&checkout=classic&section=shipping'
		);
		render( <App settings={ adminSettings() } api={ fakeApi() } /> );

		expect(
			screen.getByRole( 'heading', { level: 1, name: 'Checkout fields' } )
		).toBeVisible();
		expect(
			screen.getByRole( 'link', { name: /Classic checkout/ } )
		).toHaveAttribute( 'aria-current', 'page' );
		expect(
			screen.getByRole( 'tab', { name: /Shipping/ } )
		).toHaveAttribute( 'aria-selected', 'true' );
	} );

	it( 'opens the Fields screen on the active checkout type, marked In use', async () => {
		const user = userEvent.setup();
		render( <App settings={ adminSettings() } api={ fakeApi() } /> );

		const block = screen.getByRole( 'link', { name: /Block checkout/ } );
		expect( block ).toHaveAttribute( 'aria-current', 'page' );
		expect( block ).toHaveTextContent( 'In use' );
		expect(
			screen.getByRole( 'link', { name: /Classic checkout/ } )
		).toHaveTextContent( 'Not in use' );
		expect(
			screen.getAllByRole( 'tab' ).map( ( tab ) => tab.textContent )
		).toEqual( [ 'Contact1', 'Address2', 'Additional0' ] );

		await user.click(
			screen.getByRole( 'link', { name: /Classic checkout/ } )
		);
		expect( window.location.search ).toBe(
			'?page=checkout-fieldkit&tab=fields&checkout=classic&section=billing'
		);

		await user.click( screen.getByRole( 'tab', { name: /Additional/ } ) );
		expect( window.location.search ).toBe(
			'?page=checkout-fieldkit&tab=fields&checkout=classic&section=additional'
		);
	} );

	it( 'hides the save controls on Tools unless there are unsaved changes', async () => {
		window.history.replaceState(
			null,
			'',
			'/wp-admin/admin.php?page=checkout-fieldkit&tab=tools'
		);
		const { user } = renderShell();
		expect(
			within( header() ).queryByRole( 'button', { name: 'Save changes' } )
		).not.toBeInTheDocument();

		await user.click(
			screen.getByRole( 'button', { name: 'Toggle first name' } )
		);
		expect( saveButton() ).toBeVisible();
	} );
} );

describe( 'Leaving the page', () => {
	function leave() {
		const event = new Event( 'beforeunload', { cancelable: true } );
		window.dispatchEvent( event );
		return event;
	}

	it( 'asks for confirmation only while there are unsaved changes', async () => {
		const { user } = renderShell();
		expect( leave().defaultPrevented ).toBe( false );

		await user.click(
			screen.getByRole( 'button', { name: 'Toggle first name' } )
		);
		expect( leave().defaultPrevented ).toBe( true );

		await user.click(
			screen.getByRole( 'button', { name: 'Toggle first name' } )
		);
		expect( leave().defaultPrevented ).toBe( false );
	} );
} );

describe( 'Loading', () => {
	it( 'fetches whatever was not preloaded', async () => {
		const api = fakeApi();
		const settings = adminSettings();
		settings.preloaded = { ...settings.preloaded, context: null };
		render( <App settings={ settings } api={ api } /> );

		expect( screen.getByText( 'Loading checkout fields…' ) ).toBeVisible();
		expect(
			await screen.findByRole( 'heading', { name: 'Checkout fields' } )
		).toBeVisible();
		expect( api.getContext ).toHaveBeenCalledTimes( 1 );
		expect( api.getFieldSet ).not.toHaveBeenCalled();
	} );

	it( 'says when loading failed and tries again', async () => {
		const user = userEvent.setup();
		const data = initialData();
		const api = fakeApi( {
			getSettings: jest
				.fn()
				.mockRejectedValueOnce( {
					code: 'rest_forbidden',
					message: 'Sorry, you are not allowed to do that.',
				} )
				.mockResolvedValue( data.settings ),
		} );
		render( <App settings={ {} } api={ api } /> );

		expect(
			await screen.findByText( 'Sorry, you are not allowed to do that.' )
		).toBeVisible();
		await user.click( screen.getByRole( 'button', { name: 'Try again' } ) );

		expect(
			await screen.findByRole( 'heading', { name: 'Checkout fields' } )
		).toBeVisible();
	} );
} );
