/**
 * The REST routes the draft reads and writes (spec §7), over
 * `@wordpress/api-fetch`, which adds WordPress's REST nonce and root URL.
 */
import apiFetch from '@wordpress/api-fetch';
import type {
	CheckoutType,
	Context,
	ExportFile,
	FieldSet,
	FieldSetError,
	ImportError,
	ImportPreview,
	ImportResult,
	ResetResult,
	Resource,
	Settings,
	SettingsError,
} from './types';

export const REST_NAMESPACE = 'checkout-fieldkit/v1';

export interface Api {
	getContext: () => Promise< Context >;
	getFieldSet: ( type: CheckoutType ) => Promise< FieldSet >;
	getSettings: () => Promise< Settings >;
	/** Resolves with the field set as saved, or rejects with a 422 ApiError. */
	putFieldSet: (
		type: CheckoutType,
		fieldSet: FieldSet
	) => Promise< FieldSet >;
	/** Resolves with the settings as saved, or rejects with a 422 ApiError. */
	putSettings: ( settings: Settings ) => Promise< Settings >;
	/** GET /export for the given targets, with the server's suggested file name. */
	exportSetup: ( include: Resource[] ) => Promise< ExportFile >;
	/** What importing the document would change; rejects with a 422 ApiError when invalid. */
	previewImport: ( document: unknown ) => Promise< ImportPreview >;
	/** Replaces every target in the document; rejects with a 422 ApiError when invalid. */
	importSetup: ( document: unknown ) => Promise< ImportResult >;
	/** Returns a field set or the settings to the defaults. */
	reset: ( target: Resource ) => Promise< ResetResult >;
}

/** What api-fetch rejects with: a WordPress REST error body, or its own `fetch_error`/`invalid_json`. */
export interface ApiError {
	code?: string;
	message?: string;
	data?: {
		status?: number;
		errors?: Array< FieldSetError | SettingsError | ImportError >;
	};
}

type Fetcher = < T >( options: {
	path: string;
	method?: string;
	data?: unknown;
	/** False to get the raw response, for its headers. */
	parse?: boolean;
} ) => Promise< T >;

/** The parts of a fetch Response that an export needs. */
interface RawResponse {
	headers: { get: ( name: string ) => string | null };
	text: () => Promise< string >;
}

/**
 * The file name in a Content-Disposition header, without any folder. Falls
 * back to `checkout-fieldkit-<host>-<YYYY-MM-DD>.json` (spec §6.5).
 *
 * @param header The header's value, or null.
 * @param now    Today, for the fallback.
 */
export function fileNameFrom(
	header: string | null,
	now: Date = new Date()
): string {
	const match = header?.match( /filename\s*=\s*(?:"([^"]*)"|([^;\s]+))/i );
	const name = ( match?.[ 1 ] ?? match?.[ 2 ] ?? '' )
		.split( /[\\/]/ )
		.pop()
		?.trim();
	if ( name ) {
		return name;
	}
	const pad = ( value: number ) => String( value ).padStart( 2, '0' );
	const date = `${ now.getFullYear() }-${ pad( now.getMonth() + 1 ) }-${ pad(
		now.getDate()
	) }`;
	return `checkout-fieldkit-${ window.location.hostname }-${ date }.json`;
}

/**
 * The REST client.
 *
 * @param fetcher   api-fetch, or a stand-in in tests.
 * @param namespace Route namespace.
 */
export function createApi(
	fetcher: Fetcher = apiFetch as Fetcher,
	namespace: string = REST_NAMESPACE
): Api {
	const path = ( route: string ) => `/${ namespace }/${ route }`;

	return {
		getContext: () => fetcher< Context >( { path: path( 'context' ) } ),
		getFieldSet: ( type ) =>
			fetcher< FieldSet >( { path: path( `field-sets/${ type }` ) } ),
		getSettings: () => fetcher< Settings >( { path: path( 'settings' ) } ),
		putFieldSet: ( type, fieldSet ) =>
			fetcher< FieldSet >( {
				path: path( `field-sets/${ type }` ),
				method: 'PUT',
				data: fieldSet,
			} ),
		putSettings: ( settings ) =>
			fetcher< Settings >( {
				path: path( 'settings' ),
				method: 'PUT',
				data: settings,
			} ),
		exportSetup: async ( include ) => {
			let response: RawResponse;
			try {
				response = await fetcher< RawResponse >( {
					path: `${ path( 'export' ) }?include=${ encodeURIComponent(
						include.join( ',' )
					) }`,
					parse: false,
				} );
			} catch ( reason ) {
				// Unparsed, api-fetch rejects with the Response itself: read its error body.
				const failed = reason as Partial< Response > | null;
				if ( failed && typeof failed.json === 'function' ) {
					let body: unknown;
					try {
						body = await failed.json();
					} catch {
						throw reason;
					}
					throw body;
				}
				throw reason;
			}
			return {
				fileName: fileNameFrom(
					response.headers.get( 'Content-Disposition' )
				),
				contents: await response.text(),
			};
		},
		previewImport: ( document ) =>
			fetcher< ImportPreview >( {
				path: path( 'import/preview' ),
				method: 'POST',
				data: document,
			} ),
		importSetup: ( document ) =>
			fetcher< ImportResult >( {
				path: path( 'import' ),
				method: 'POST',
				data: document,
			} ),
		reset: ( target ) =>
			fetcher< ResetResult >( {
				path: path( 'reset' ),
				method: 'POST',
				data: { target },
			} ),
	};
}
