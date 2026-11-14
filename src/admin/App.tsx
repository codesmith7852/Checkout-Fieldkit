/**
 * The admin app (WooCommerce → Checkout Fields): loads the data, then
 * provides the draft, the location and announcements to the shell.
 */
import { useMemo } from '@wordpress/element';
import type { ReactNode } from 'react';
import { createApi, REST_NAMESPACE, type Api } from './api';
import { AnnouncerProvider } from './components';
import { LocationProvider } from './location';
import { InitialDataLoader } from './shell/InitialDataLoader';
import { Shell } from './shell/Shell';
import { DraftProvider } from './store';
import type { AdminSettings } from './types';

export interface AppProps {
	/** `window.checkoutFieldkitAdmin`, from the checkout_fieldkit_admin_settings filter. */
	settings: AdminSettings;
	/** REST client; api-fetch by default. */
	api?: Api;
}

/**
 * Everything the screens need, around `children`.
 *
 * @param props          Props.
 * @param props.settings Data the page passed in.
 * @param props.api      REST client.
 * @param props.children What to render once the data is loaded.
 */
export function Providers( {
	settings,
	api,
	children,
}: AppProps & { children: ReactNode } ) {
	const client = useMemo(
		() =>
			api ||
			createApi( undefined, settings.restNamespace || REST_NAMESPACE ),
		[ api, settings.restNamespace ]
	);

	return (
		<div className="cfk-admin">
			<AnnouncerProvider>
				<InitialDataLoader
					preloaded={ settings.preloaded }
					api={ client }
				>
					{ ( data ) => (
						<DraftProvider initial={ data } api={ client }>
							<LocationProvider context={ data.context }>
								{ children }
							</LocationProvider>
						</DraftProvider>
					) }
				</InitialDataLoader>
			</AnnouncerProvider>
		</div>
	);
}

export default function App( props: AppProps ) {
	return (
		<Providers { ...props }>
			<Shell />
		</Providers>
	);
}
