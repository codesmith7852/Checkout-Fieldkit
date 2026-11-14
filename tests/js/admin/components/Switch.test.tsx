/**
 * Switch: a real button with role="switch" that exposes its state (spec §6.6).
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from '@wordpress/element';
import { Switch } from '../../../../src/admin/components';

function Controlled( { initial = false }: { initial?: boolean } ) {
	const [ checked, setChecked ] = useState( initial );
	return (
		<Switch
			label="Required: First name"
			checked={ checked }
			onChange={ setChecked }
		/>
	);
}

describe( 'Switch', () => {
	it( 'is a switch named by its label, with its state in aria-checked', () => {
		render( <Controlled initial /> );

		const control = screen.getByRole( 'switch', {
			name: 'Required: First name',
		} );
		expect( control ).toHaveAttribute( 'aria-checked', 'true' );
		expect( control.tagName ).toBe( 'BUTTON' );
		expect( control ).toHaveAttribute( 'type', 'button' );
	} );

	it( 'toggles on click, Space and Enter', async () => {
		const user = userEvent.setup();
		render( <Controlled /> );
		const control = screen.getByRole( 'switch' );

		await user.click( control );
		expect( control ).toHaveAttribute( 'aria-checked', 'true' );

		await user.keyboard( ' ' );
		expect( control ).toHaveAttribute( 'aria-checked', 'false' );

		await user.keyboard( '{Enter}' );
		expect( control ).toHaveAttribute( 'aria-checked', 'true' );
	} );

	it( 'does nothing when disabled', async () => {
		const user = userEvent.setup();
		const onChange = jest.fn();
		render(
			<Switch
				label="Enabled"
				checked={ false }
				onChange={ onChange }
				disabled
			/>
		);

		await user.click( screen.getByRole( 'switch' ) );
		expect( onChange ).not.toHaveBeenCalled();
		expect( screen.getByRole( 'switch' ) ).toBeDisabled();
	} );

	it( 'can be labelled and described by other elements', () => {
		render(
			<>
				<strong id="title">Keep my labels</strong>
				<span id="help">Show your labels in every country.</span>
				<Switch
					labelledBy="title"
					describedBy="help"
					checked
					onChange={ () => {} }
				/>
			</>
		);

		const control = screen.getByRole( 'switch', {
			name: 'Keep my labels',
		} );
		expect( control ).toHaveAccessibleDescription(
			'Show your labels in every country.'
		);
	} );
} );
