/**
 * The app's location (tab, checkout type, section) lives in the URL query,
 * so reloads and links land in the same place (spec §6.1).
 */
import { act, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import {
	LocationProvider,
	locationSearch,
	parseLocation,
	useLocation,
} from '../../../../src/admin/location';
import { context } from '../fixtures';

describe( 'parseLocation', () => {
	it( 'opens the Fields tab on the active checkout type’s first section', () => {
		expect( parseLocation( '?page=checkout-fieldkit', context() ) ).toEqual(
			{ tab: 'fields', checkout: 'block', section: 'contact' }
		);
		expect(
			parseLocation( '', context( { activeCheckoutType: 'classic' } ) )
		).toEqual( { tab: 'fields', checkout: 'classic', section: 'billing' } );
	} );

	it( 'opens the Block checkout when the active type is unknown, unless blocks are not supported', () => {
		expect(
			parseLocation( '', context( { activeCheckoutType: 'unknown' } ) )
				.checkout
		).toBe( 'block' );
		expect(
			parseLocation(
				'',
				context( {
					activeCheckoutType: 'unknown',
					blockSupported: false,
				} )
			).checkout
		).toBe( 'classic' );
	} );

	it( 'reads tab, checkout and section from the query', () => {
		expect(
			parseLocation(
				'?page=checkout-fieldkit&tab=fields&checkout=classic&section=shipping',
				context()
			)
		).toEqual( {
			tab: 'fields',
			checkout: 'classic',
			section: 'shipping',
		} );
		expect(
			parseLocation( '?page=checkout-fieldkit&tab=tools', context() ).tab
		).toBe( 'tools' );
	} );

	it( 'falls back to defaults for values it does not know', () => {
		expect(
			parseLocation(
				'?tab=nope&checkout=shortcode&section=billing',
				context()
			)
		).toEqual( { tab: 'fields', checkout: 'block', section: 'contact' } );
		expect(
			parseLocation( '?checkout=block&section=billing', context() )
				.section
		).toBe( 'contact' );
	} );
} );

describe( 'locationSearch', () => {
	it( 'writes checkout and section for the Fields tab, keeping other arguments', () => {
		expect(
			locationSearch( '?page=checkout-fieldkit&foo=1', {
				tab: 'fields',
				checkout: 'classic',
				section: 'shipping',
			} )
		).toBe(
			'?page=checkout-fieldkit&foo=1&tab=fields&checkout=classic&section=shipping'
		);
	} );

	it( 'writes only the tab for Settings and Tools', () => {
		expect(
			locationSearch(
				'?page=checkout-fieldkit&tab=fields&checkout=classic&section=billing',
				{ tab: 'settings', checkout: 'classic', section: 'billing' }
			)
		).toBe( '?page=checkout-fieldkit&tab=settings' );
	} );
} );

describe( 'useLocation', () => {
	const wrapper = ( { children }: { children: ReactNode } ) => (
		<LocationProvider context={ context() }>{ children }</LocationProvider>
	);

	beforeEach( () => {
		window.history.replaceState(
			null,
			'',
			'/wp-admin/admin.php?page=checkout-fieldkit'
		);
	} );

	it( 'starts from the URL', () => {
		window.history.replaceState(
			null,
			'',
			'/wp-admin/admin.php?page=checkout-fieldkit&tab=settings'
		);
		const { result } = renderHook( () => useLocation(), { wrapper } );
		expect( result.current.location.tab ).toBe( 'settings' );
	} );

	it( 'pushes a history entry when navigating', () => {
		const { result } = renderHook( () => useLocation(), { wrapper } );
		const before = window.history.length;

		act( () => result.current.navigate( { checkout: 'classic' } ) );

		expect( result.current.location ).toEqual( {
			tab: 'fields',
			checkout: 'classic',
			section: 'billing',
		} );
		expect( window.location.search ).toBe(
			'?page=checkout-fieldkit&tab=fields&checkout=classic&section=billing'
		);
		expect( window.history.length ).toBe( before + 1 );
	} );

	it( 'replaces the history entry when asked', () => {
		const { result } = renderHook( () => useLocation(), { wrapper } );
		const before = window.history.length;

		act( () =>
			result.current.navigate( { section: 'address' }, { replace: true } )
		);

		expect( window.location.search ).toBe(
			'?page=checkout-fieldkit&tab=fields&checkout=block&section=address'
		);
		expect( window.history.length ).toBe( before );
	} );

	it( 'keeps the Fields location in memory while on another tab', () => {
		const { result } = renderHook( () => useLocation(), { wrapper } );

		act( () => result.current.navigate( { section: 'additional' } ) );
		act( () => result.current.navigate( { tab: 'tools' } ) );
		expect( window.location.search ).toBe(
			'?page=checkout-fieldkit&tab=tools'
		);

		act( () => result.current.navigate( { tab: 'fields' } ) );
		expect( result.current.location.section ).toBe( 'additional' );
	} );

	it( 'follows the browser’s back and forward buttons', () => {
		const { result } = renderHook( () => useLocation(), { wrapper } );

		act( () => {
			window.history.pushState(
				null,
				'',
				'/wp-admin/admin.php?page=checkout-fieldkit&tab=settings'
			);
			window.dispatchEvent( new PopStateEvent( 'popstate' ) );
		} );

		expect( result.current.location.tab ).toBe( 'settings' );
	} );

	it( 'builds links for other locations', () => {
		const { result } = renderHook( () => useLocation(), { wrapper } );

		expect( result.current.hrefFor( { tab: 'tools' } ) ).toBe(
			'/wp-admin/admin.php?page=checkout-fieldkit&tab=tools'
		);
		expect( result.current.hrefFor( { checkout: 'classic' } ) ).toBe(
			'/wp-admin/admin.php?page=checkout-fieldkit&tab=fields&checkout=classic&section=billing'
		);
	} );
} );
