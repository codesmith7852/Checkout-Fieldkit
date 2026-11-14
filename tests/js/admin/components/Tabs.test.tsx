/**
 * Tabs: WAI-ARIA tabs with a roving tab stop and arrow-key navigation.
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from '@wordpress/element';
import { Tabs } from '../../../../src/admin/components';

const sections = [
	{ id: 'billing', label: 'Billing', count: 13 },
	{ id: 'shipping', label: 'Shipping', count: 9 },
	{ id: 'additional', label: 'Additional', count: 0 },
];

function Controlled( { activation }: { activation?: 'automatic' | 'manual' } ) {
	const [ selected, setSelected ] = useState( 'billing' );
	return (
		<Tabs
			label="Sections"
			tabs={ sections }
			selected={ selected }
			onSelect={ setSelected }
			activation={ activation }
		>
			<p>Fields of { selected }</p>
		</Tabs>
	);
}

describe( 'Tabs', () => {
	it( 'renders a labelled tablist, counts and the selected panel', () => {
		render( <Controlled /> );

		expect(
			screen.getByRole( 'tablist', { name: 'Sections' } )
		).toBeInTheDocument();
		const tabs = screen.getAllByRole( 'tab' );
		expect( tabs.map( ( tab ) => tab.textContent ) ).toEqual( [
			'Billing13',
			'Shipping9',
			'Additional0',
		] );
		expect( tabs[ 0 ] ).toHaveAttribute( 'aria-selected', 'true' );
		expect( tabs[ 1 ] ).toHaveAttribute( 'aria-selected', 'false' );

		const panel = screen.getByRole( 'tabpanel', { name: /Billing/ } );
		expect( panel ).toHaveTextContent( 'Fields of billing' );
		expect( tabs[ 0 ] ).toHaveAttribute( 'aria-controls', panel.id );
	} );

	it( 'has one tab stop: the selected tab', async () => {
		const user = userEvent.setup();
		render(
			<>
				<button type="button">Before</button>
				<Controlled />
			</>
		);

		await user.tab();
		await user.tab();
		expect( screen.getByRole( 'tab', { name: /Billing/ } ) ).toHaveFocus();
		await user.tab();
		expect( screen.getByRole( 'tabpanel' ) ).toHaveFocus();
	} );

	it( 'moves and selects with the arrow keys, Home and End, wrapping around', async () => {
		const user = userEvent.setup();
		render( <Controlled /> );
		await user.click( screen.getByRole( 'tab', { name: /Billing/ } ) );

		await user.keyboard( '{ArrowRight}' );
		const shipping = screen.getByRole( 'tab', { name: /Shipping/ } );
		expect( shipping ).toHaveFocus();
		expect( shipping ).toHaveAttribute( 'aria-selected', 'true' );
		expect( shipping ).toHaveAttribute( 'tabindex', '0' );
		expect(
			screen.getByRole( 'tab', { name: /Billing/ } )
		).toHaveAttribute( 'tabindex', '-1' );

		await user.keyboard( '{End}' );
		expect(
			screen.getByRole( 'tab', { name: /Additional/ } )
		).toHaveFocus();

		await user.keyboard( '{ArrowRight}' );
		expect( screen.getByRole( 'tab', { name: /Billing/ } ) ).toHaveFocus();

		await user.keyboard( '{ArrowLeft}' );
		expect(
			screen.getByRole( 'tab', { name: /Additional/ } )
		).toHaveFocus();

		await user.keyboard( '{Home}' );
		expect( screen.getByRole( 'tab', { name: /Billing/ } ) ).toHaveFocus();
		expect( screen.getByRole( 'tabpanel' ) ).toHaveTextContent(
			'Fields of billing'
		);
	} );

	it( 'with manual activation, arrows move focus and Enter selects', async () => {
		const user = userEvent.setup();
		render( <Controlled activation="manual" /> );
		await user.click( screen.getByRole( 'tab', { name: /Billing/ } ) );

		await user.keyboard( '{ArrowRight}' );
		const shipping = screen.getByRole( 'tab', { name: /Shipping/ } );
		expect( shipping ).toHaveFocus();
		expect( shipping ).toHaveAttribute( 'aria-selected', 'false' );

		await user.keyboard( '{Enter}' );
		expect( shipping ).toHaveAttribute( 'aria-selected', 'true' );
		expect( screen.getByRole( 'tabpanel' ) ).toHaveTextContent(
			'Fields of shipping'
		);
	} );
} );
