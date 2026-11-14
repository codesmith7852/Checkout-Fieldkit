/**
 * Settings screen (spec §6.4, frame 4): the five locale-override switches,
 * Changed tags against the saved value, Restore defaults, and saving through
 * the header's Save changes.
 */
import { screen, waitFor, within } from '@testing-library/react';
import { initialData, settings } from '../fixtures';
import {
	fakeApi,
	header,
	politeAnnouncements,
	renderApp,
} from './settingsToolsHarness';

const NAMES = [
	'Keep my labels',
	'Keep my placeholders',
	'Keep my field order',
	'Keep my required settings',
	'Keep my CSS classes',
];

const card = () =>
	screen.getByRole( 'region', {
		name: 'Address fields in different countries',
	} );
const toggle = ( name: string ) =>
	within( card() ).getByRole( 'switch', { name } );

describe( 'Settings screen', () => {
	it( 'explains the overrides with examples and shows the five switches as saved', () => {
		renderApp( 'settings' );

		expect( card() ).toHaveTextContent( /County/ );
		expect( card() ).toHaveTextContent( /PIN Code/ );
		expect( card() ).toHaveTextContent( /Japan/ );
		expect( card() ).toHaveTextContent(
			/only where you changed .* from WooCommerce’s default/
		);

		expect( within( card() ).getAllByRole( 'switch' ) ).toHaveLength( 5 );
		NAMES.forEach( ( name ) => expect( toggle( name ) ).toBeVisible() );
		expect(
			NAMES.map( ( name ) =>
				toggle( name ).getAttribute( 'aria-checked' )
			)
		).toEqual( [ 'true', 'true', 'true', 'false', 'false' ] );
		expect( toggle( 'Keep my labels' ) ).toHaveAccessibleDescription(
			/instead of names like County or PIN Code/
		);
		expect( within( card() ).queryByText( 'Changed' ) ).toBeNull();
	} );

	it( 'tags a switch that differs from the saved value and counts it as unsaved', async () => {
		const { user } = renderApp( 'settings' );

		await user.click( toggle( 'Keep my required settings' ) );

		expect( toggle( 'Keep my required settings' ) ).toHaveAttribute(
			'aria-checked',
			'true'
		);
		expect( within( card() ).getAllByText( 'Changed' ) ).toHaveLength( 1 );
		expect(
			toggle( 'Keep my required settings' )
		).toHaveAccessibleDescription( /Changed, not saved yet/ );
		expect( header() ).toHaveTextContent( '1 unsaved change' );

		// Switching it back is no change at all.
		await user.click( toggle( 'Keep my required settings' ) );
		expect( within( card() ).queryByText( 'Changed' ) ).toBeNull();
		expect( header() ).toHaveTextContent( 'All changes saved' );
	} );

	it( 'saves through the header and clears the tags', async () => {
		const { api, user } = renderApp( 'settings' );

		await user.click( toggle( 'Keep my labels' ) );
		await user.click( toggle( 'Keep my CSS classes' ) );
		expect( within( card() ).getAllByText( 'Changed' ) ).toHaveLength( 2 );

		await user.click(
			within( header() ).getByRole( 'button', { name: 'Save changes' } )
		);

		await waitFor( () =>
			expect( header() ).toHaveTextContent( 'All changes saved' )
		);
		expect( api.putSettings ).toHaveBeenCalledWith( {
			version: 1,
			localeOverrides: {
				label: false,
				placeholder: true,
				order: true,
				required: false,
				classes: true,
			},
		} );
		expect( within( card() ).queryByText( 'Changed' ) ).toBeNull();
	} );

	it( 'restores the defaults into the draft, to be saved', async () => {
		const saved = settings();
		saved.localeOverrides.label = false;
		saved.localeOverrides.classes = true;
		const data = initialData( { settings: saved } );
		const { api, user } = renderApp( 'settings', {
			data,
			api: fakeApi( data ),
		} );

		await user.click(
			within( card() ).getByRole( 'button', { name: 'Restore defaults' } )
		);

		expect(
			NAMES.map( ( name ) =>
				toggle( name ).getAttribute( 'aria-checked' )
			)
		).toEqual( [ 'true', 'true', 'true', 'false', 'false' ] );
		expect( within( card() ).getAllByText( 'Changed' ) ).toHaveLength( 2 );
		expect( header() ).toHaveTextContent( '2 unsaved changes' );
		expect( politeAnnouncements() ).toMatch(
			/Defaults restored\. Save changes to keep them\./
		);
		expect( api.putSettings ).not.toHaveBeenCalled();
	} );

	it( 'disables Restore defaults when the draft already has the defaults', async () => {
		const { user } = renderApp( 'settings' );
		const restore = within( card() ).getByRole( 'button', {
			name: 'Restore defaults',
		} );

		expect( restore ).toHaveAttribute( 'aria-disabled', 'true' );
		await user.click( restore );
		expect( politeAnnouncements() ).toBe( '' );

		await user.click( toggle( 'Keep my labels' ) );
		expect( restore ).not.toHaveAttribute( 'aria-disabled' );
	} );

	it( 'shows a problem from the server next to its switch', async () => {
		const api = fakeApi();
		api.putSettings.mockRejectedValue( {
			code: 'checkout_fieldkit_invalid_settings',
			message: 'The settings were not saved.',
			data: {
				status: 422,
				errors: [
					{
						key: 'localeOverrides.order',
						message: 'This setting must be on or off.',
					},
				],
			},
		} );
		const { user } = renderApp( 'settings', { api } );

		await user.click( toggle( 'Keep my field order' ) );
		await user.click(
			within( header() ).getByRole( 'button', { name: 'Save changes' } )
		);

		await waitFor( () =>
			expect(
				toggle( 'Keep my field order' )
			).toHaveAccessibleDescription( /This setting must be on or off\./ )
		);
	} );
} );
