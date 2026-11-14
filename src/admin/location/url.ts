/**
 * Reading and writing the app's location in the URL query (spec §6.1):
 * `tab`, and on the Fields tab `checkout` and `section`. Other query
 * arguments, such as WordPress's `page`, are left alone.
 */
import type { CheckoutType, Context } from '../types';

export type Tab = 'fields' | 'settings' | 'tools';

export const TABS: Tab[] = [ 'fields', 'settings', 'tools' ];

export interface AppLocation {
	tab: Tab;
	checkout: CheckoutType;
	section: string;
}

/**
 * The checkout type the Fields screen opens on: the one the store uses, or
 * the Block checkout (WooCommerce's default) when that is unknown and blocks
 * are supported.
 *
 * @param context GET /context.
 */
export function defaultCheckoutType( context: Context ): CheckoutType {
	if ( context.activeCheckoutType !== 'unknown' ) {
		return context.activeCheckoutType;
	}
	return context.blockSupported ? 'block' : 'classic';
}

function isCheckoutType( value: unknown ): value is CheckoutType {
	return value === 'classic' || value === 'block';
}

/**
 * A section that exists for the checkout type, or its first section.
 *
 * @param context  GET /context.
 * @param checkout Checkout type.
 * @param section  Wanted section.
 */
export function validSection(
	context: Context,
	checkout: CheckoutType,
	section: string | null | undefined
): string {
	const names = ( context.sections[ checkout ] || [] ).map(
		( item ) => item.name
	);
	if ( section && names.includes( section ) ) {
		return section;
	}
	return names[ 0 ] || '';
}

/**
 * The location a query string points to; unknown values fall back to defaults.
 *
 * @param search  `window.location.search`.
 * @param context GET /context.
 */
export function parseLocation( search: string, context: Context ): AppLocation {
	const params = new URLSearchParams( search );
	const tab = params.get( 'tab' );
	const checkout = params.get( 'checkout' );
	const resolvedCheckout = isCheckoutType( checkout )
		? checkout
		: defaultCheckoutType( context );

	return {
		tab: TABS.includes( tab as Tab ) ? ( tab as Tab ) : 'fields',
		checkout: resolvedCheckout,
		section: validSection(
			context,
			resolvedCheckout,
			params.get( 'section' )
		),
	};
}

/**
 * The query string for a location, keeping the other arguments of `search`.
 *
 * @param search   The current query string.
 * @param location Where to go.
 */
export function locationSearch(
	search: string,
	location: AppLocation
): string {
	const params = new URLSearchParams( search );
	params.delete( 'tab' );
	params.delete( 'checkout' );
	params.delete( 'section' );
	params.set( 'tab', location.tab );
	if ( location.tab === 'fields' ) {
		params.set( 'checkout', location.checkout );
		params.set( 'section', location.section );
	}
	return `?${ params.toString() }`;
}
