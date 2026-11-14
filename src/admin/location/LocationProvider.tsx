/**
 * The app's location, kept in sync with the URL query and the browser's
 * history.
 */
import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import type { ReactNode } from 'react';
import type { Context } from '../types';
import {
	locationSearch,
	parseLocation,
	validSection,
	type AppLocation,
} from './url';

export interface NavigateOptions {
	/** Replace the current history entry instead of adding one. */
	replace?: boolean;
}

export interface LocationApi {
	location: AppLocation;
	/**
	 * Goes to another location. A new checkout type without a section opens
	 * its first section.
	 */
	navigate: ( to: Partial< AppLocation >, options?: NavigateOptions ) => void;
	/** A link to another location, for real `<a href>`s that also open in a new tab. */
	hrefFor: ( to: Partial< AppLocation > ) => string;
}

const LocationContext = createContext< LocationApi | null >( null );

/**
 * Where `to` leads from `from`.
 *
 * @param context GET /context.
 * @param from    Current location.
 * @param to      Changes.
 */
function resolve(
	context: Context,
	from: AppLocation,
	to: Partial< AppLocation >
): AppLocation {
	const checkout = to.checkout || from.checkout;
	let section = to.section;
	if ( section === undefined ) {
		section = checkout === from.checkout ? from.section : '';
	}
	return {
		tab: to.tab || from.tab,
		checkout,
		section: validSection( context, checkout, section ),
	};
}

export interface LocationProviderProps {
	context: Context;
	children: ReactNode;
}

/**
 * Provides useLocation() to the app.
 *
 * @param props          Props.
 * @param props.context  GET /context, for the sections and the default checkout type.
 * @param props.children The app.
 */
export function LocationProvider( {
	context,
	children,
}: LocationProviderProps ) {
	const [ location, setLocation ] = useState< AppLocation >( () =>
		parseLocation( window.location.search, context )
	);
	const latest = useRef( location );
	latest.current = location;

	useEffect( () => {
		const onPopState = () => {
			const next = parseLocation( window.location.search, context );
			// Settings and Tools URLs carry no checkout or section: keep the last ones.
			setLocation(
				next.tab === 'fields'
					? next
					: {
							...next,
							checkout: latest.current.checkout,
							section: latest.current.section,
						}
			);
		};
		window.addEventListener( 'popstate', onPopState );
		return () => window.removeEventListener( 'popstate', onPopState );
	}, [ context ] );

	const hrefFor = useCallback(
		( to: Partial< AppLocation > ) =>
			window.location.pathname +
			locationSearch(
				window.location.search,
				resolve( context, latest.current, to )
			),
		[ context ]
	);

	const navigate = useCallback(
		( to: Partial< AppLocation >, options: NavigateOptions = {} ) => {
			const next = resolve( context, latest.current, to );
			const url =
				window.location.pathname +
				locationSearch( window.location.search, next );
			if ( options.replace ) {
				window.history.replaceState( null, '', url );
			} else {
				window.history.pushState( null, '', url );
			}
			latest.current = next;
			setLocation( next );
		},
		[ context ]
	);

	const value = useMemo(
		() => ( { location, navigate, hrefFor } ),
		[ location, navigate, hrefFor ]
	);

	return (
		<LocationContext.Provider value={ value }>
			{ children }
		</LocationContext.Provider>
	);
}

/** The app's location and how to change it. */
export function useLocation(): LocationApi {
	const value = useContext( LocationContext );
	if ( ! value ) {
		throw new Error(
			'useLocation() must be used inside <LocationProvider>.'
		);
	}
	return value;
}
