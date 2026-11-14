/**
 * The REST routes the draft reads and writes (spec §7), over
 * `@wordpress/api-fetch`, which adds WordPress's REST nonce and root URL.
 */
import apiFetch from '@wordpress/api-fetch';
import type {
	CheckoutType,
	Context,
	FieldSet,
	FieldSetError,
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
}

/** What api-fetch rejects with: a WordPress REST error body, or its own `fetch_error`/`invalid_json`. */
export interface ApiError {
	code?: string;
	message?: string;
	data?: {
		status?: number;
		errors?: Array< FieldSetError | SettingsError >;
	};
}

type Fetcher = < T >( options: {
	path: string;
	method?: string;
	data?: unknown;
} ) => Promise< T >;

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
	};
}
