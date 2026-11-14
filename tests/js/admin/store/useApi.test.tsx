/**
 * useApi(): screens that call routes outside the draft (Tools) get the same
 * REST client the draft saves through.
 */
import { renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import type { Api } from '../../../../src/admin/api';
import { AnnouncerProvider } from '../../../../src/admin/components/LiveRegion';
import { DraftProvider, useApi } from '../../../../src/admin/store';
import { initialData } from '../fixtures';

describe( 'useApi', () => {
	it( 'returns the client given to DraftProvider', () => {
		const api = { getContext: jest.fn() } as unknown as Api;
		const wrapper = ( { children }: { children: ReactNode } ) => (
			<AnnouncerProvider>
				<DraftProvider initial={ initialData() } api={ api }>
					{ children }
				</DraftProvider>
			</AnnouncerProvider>
		);

		const { result } = renderHook( () => useApi(), { wrapper } );

		expect( result.current ).toBe( api );
	} );
} );
