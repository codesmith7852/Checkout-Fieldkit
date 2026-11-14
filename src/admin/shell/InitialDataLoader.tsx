/**
 * Starts the app with the data the page preloaded, fetching over REST
 * whatever is missing (for example when a preload request failed).
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import type { Api } from '../api';
import { Button, Notice } from '../components';
import { errorMessage } from '../store/errors';
import type { AdminSettings, InitialData } from '../types';

type Preloaded = AdminSettings[ 'preloaded' ];

/**
 * The preloaded data, when all of it is there.
 *
 * @param preloaded What the page passed in.
 */
function complete( preloaded: Preloaded ): InitialData | null {
	const context = preloaded?.context;
	const classic = preloaded?.fieldSets?.classic;
	const block = preloaded?.fieldSets?.block;
	const settings = preloaded?.settings;
	if ( context && classic && block && settings ) {
		return { context, classic, block, settings };
	}
	return null;
}

type LoadState =
	| { status: 'ready'; data: InitialData }
	| { status: 'loading' }
	| { status: 'error'; message: string };

export interface InitialDataLoaderProps {
	preloaded: Preloaded;
	api: Api;
	children: ( data: InitialData ) => ReactNode;
}

export function InitialDataLoader( {
	preloaded,
	api,
	children,
}: InitialDataLoaderProps ) {
	const [ state, setState ] = useState< LoadState >( () => {
		const data = complete( preloaded );
		return data ? { status: 'ready', data } : { status: 'loading' };
	} );

	const load = useCallback( async () => {
		setState( { status: 'loading' } );
		try {
			const [ context, classic, block, settings ] = await Promise.all( [
				preloaded?.context || api.getContext(),
				preloaded?.fieldSets?.classic || api.getFieldSet( 'classic' ),
				preloaded?.fieldSets?.block || api.getFieldSet( 'block' ),
				preloaded?.settings || api.getSettings(),
			] );
			setState( {
				status: 'ready',
				data: { context, classic, block, settings },
			} );
		} catch ( error ) {
			setState( { status: 'error', message: errorMessage( error ) } );
		}
	}, [ preloaded, api ] );

	const needsLoading = state.status === 'loading';
	useEffect( () => {
		if ( needsLoading ) {
			load();
		}
		// Only on mount; Try again calls load() itself.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	if ( state.status === 'ready' ) {
		return <>{ children( state.data ) }</>;
	}

	if ( state.status === 'error' ) {
		return (
			<div className="cfk-content">
				<Notice
					tone="error"
					role="alert"
					title={ __(
						'Checkout Fields could not load your settings.',
						'checkout-fieldkit'
					) }
					actions={
						<Button size="small" onClick={ load }>
							{ __( 'Try again', 'checkout-fieldkit' ) }
						</Button>
					}
				>
					<p>{ state.message }</p>
				</Notice>
			</div>
		);
	}

	return (
		<div className="cfk-content">
			<p className="cfk-loading" role="status">
				{ __( 'Loading checkout fields…', 'checkout-fieldkit' ) }
			</p>
		</div>
	);
}
