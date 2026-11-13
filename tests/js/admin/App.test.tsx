/**
 * Placeholder admin app: proves the TypeScript + Testing Library setup works.
 */
import { render, screen } from '@testing-library/react';
import App from '../../../src/admin/App';

describe( 'App', () => {
	it( 'shows the page title as a heading', () => {
		render( <App /> );

		expect(
			screen.getByRole( 'heading', { name: 'Checkout Fields' } )
		).toBeInTheDocument();
	} );
} );
