/**
 * Renders the whole app on one tab with a fake REST client, for the Settings
 * and Tools screen tests.
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import App from '../../../../src/admin/App';
import type { Api } from '../../../../src/admin/api';
import type {
	AdminSettings,
	FieldSet,
	InitialData,
	Settings,
} from '../../../../src/admin/types';
import { initialData } from '../fixtures';

const copy = < T, >( value: T ): T => JSON.parse( JSON.stringify( value ) );

export function fakeApi(
	data: InitialData = initialData(),
	overrides: Partial< Api > = {}
): jest.Mocked< Api > {
	return {
		getContext: jest.fn( async () => data.context ),
		getFieldSet: jest.fn( async ( type ) => data[ type ] ),
		getSettings: jest.fn( async () => data.settings ),
		putFieldSet: jest.fn( async ( _type, fieldSet: FieldSet ) =>
			copy( fieldSet )
		),
		putSettings: jest.fn( async ( value: Settings ) => copy( value ) ),
		exportSetup: jest.fn( async () => ( {
			fileName: 'checkout-fieldkit-shop.example-2026-11-14.json',
			contents: '{"plugin":"checkout-fieldkit","format":1}',
		} ) ),
		previewImport: jest.fn(),
		importSetup: jest.fn(),
		reset: jest.fn(),
		...overrides,
	} as jest.Mocked< Api >;
}

export function renderApp(
	tab: 'settings' | 'tools',
	{
		data = initialData(),
		api = fakeApi( data ),
	}: { data?: InitialData; api?: jest.Mocked< Api > } = {}
) {
	window.history.replaceState(
		null,
		'',
		`/wp-admin/admin.php?page=checkout-fieldkit&tab=${ tab }`
	);
	const settings: AdminSettings = {
		restNamespace: 'checkout-fieldkit/v1',
		preloaded: {
			context: data.context,
			fieldSets: { classic: data.classic, block: data.block },
			settings: data.settings,
		},
	};
	const user = userEvent.setup();
	const utils = render( <App settings={ settings } api={ api } /> );
	return { ...utils, api, user };
}

export const header = () => screen.getByRole( 'banner' );

export const politeAnnouncements = () =>
	document.querySelector( '.cfk-admin [aria-live="polite"]' )?.textContent ||
	'';
