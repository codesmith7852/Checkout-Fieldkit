/**
 * The smaller base components: Button, Tag and Pill, Card, Notice,
 * VisuallyHidden, Dialog and the live region.
 */
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {
	AnnouncerProvider,
	Button,
	Card,
	Dialog,
	Notice,
	Pill,
	Tag,
	useAnnounce,
	VisuallyHidden,
} from '../../../../src/admin/components';
import type { Announce } from '../../../../src/admin/components';

describe( 'Button', () => {
	it( 'is a type="button" with variant and size classes', () => {
		render(
			<Button variant="primary" size="small">
				Add field
			</Button>
		);
		const button = screen.getByRole( 'button', { name: 'Add field' } );
		expect( button ).toHaveAttribute( 'type', 'button' );
		expect( button ).toHaveClass(
			'cfk-button',
			'cfk-button--primary',
			'cfk-button--small'
		);
	} );

	it( 'defaults to a secondary button and passes other props on', async () => {
		const user = userEvent.setup();
		const onClick = jest.fn();
		render(
			<Button onClick={ onClick } aria-describedby="x">
				Cancel
			</Button>
		);
		const button = screen.getByRole( 'button', { name: 'Cancel' } );
		expect( button ).toHaveClass( 'cfk-button--secondary' );
		expect( button ).toHaveAttribute( 'aria-describedby', 'x' );
		await user.click( button );
		expect( onClick ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'names an icon-only button by its label', () => {
		render(
			<Button
				variant="ghost"
				label="More actions for First name"
				icon={ <svg /> }
			/>
		);
		expect(
			screen.getByRole( 'button', {
				name: 'More actions for First name',
			} )
		).toHaveClass( 'cfk-button--icon-only' );
	} );

	it( 'is marked busy', () => {
		render(
			<Button variant="danger" isBusy>
				Reset
			</Button>
		);
		expect( screen.getByRole( 'button' ) ).toHaveAttribute(
			'aria-busy',
			'true'
		);
		expect( screen.getByRole( 'button' ) ).toHaveClass(
			'cfk-button--danger'
		);
	} );
} );

describe( 'Tag and Pill', () => {
	it( 'render their text with a tone', () => {
		render(
			<>
				<Tag tone="accent">Custom</Tag>
				<Pill tone="success">In use</Pill>
			</>
		);
		expect( screen.getByText( 'Custom' ) ).toHaveClass(
			'cfk-tag',
			'cfk-tag--accent'
		);
		expect( screen.getByText( 'In use' ) ).toHaveClass(
			'cfk-tag',
			'cfk-tag--pill',
			'cfk-tag--success'
		);
	} );
} );

describe( 'Card', () => {
	it( 'is a region named by its title, with description and footer', () => {
		render(
			<Card
				title="Export"
				description="Download your fields."
				footer={ <Button>Download file</Button> }
			>
				<p>Body</p>
			</Card>
		);
		const card = screen.getByRole( 'region', { name: 'Export' } );
		expect( card ).toHaveTextContent( 'Download your fields.' );
		expect( card ).toHaveTextContent( 'Body' );
		expect(
			screen.getByRole( 'heading', { level: 2, name: 'Export' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Download file' } )
		).toBeInTheDocument();
	} );
} );

describe( 'Notice', () => {
	it( 'shows a title, its content and actions', () => {
		render(
			<Notice
				tone="error"
				title="Your changes were not saved"
				actions={ <Button>Try again</Button> }
			>
				The server could not be reached.
			</Notice>
		);
		const notice = screen
			.getByText( 'Your changes were not saved' )
			.closest( '.cfk-notice' );
		expect( notice ).toHaveClass( 'cfk-notice--error' );
		expect( notice ).toHaveTextContent(
			'The server could not be reached.'
		);
		expect(
			screen.getByRole( 'button', { name: 'Try again' } )
		).toBeInTheDocument();
	} );

	it( 'can be dismissed', async () => {
		const user = userEvent.setup();
		const onDismiss = jest.fn();
		render(
			<Notice tone="info" onDismiss={ onDismiss }>
				Saved.
			</Notice>
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Dismiss this notice' } )
		);
		expect( onDismiss ).toHaveBeenCalled();
	} );
} );

describe( 'VisuallyHidden', () => {
	it( 'keeps text for screen readers', () => {
		render(
			<button type="button">
				<VisuallyHidden>Reorder</VisuallyHidden>
			</button>
		);
		expect(
			screen.getByRole( 'button', { name: 'Reorder' } )
		).toBeVisible();
		expect( screen.getByText( 'Reorder' ) ).toHaveClass(
			'cfk-visually-hidden'
		);
	} );
} );

describe( 'Dialog', () => {
	it( 'is a modal dialog named by its title that closes on Escape', async () => {
		const user = userEvent.setup();
		const onClose = jest.fn();
		render(
			<Dialog
				title="Discard unsaved changes?"
				onClose={ onClose }
				actions={ <Button>Keep editing</Button> }
			>
				<p>3 changes will be lost.</p>
			</Dialog>
		);

		const dialog = screen.getByRole( 'dialog', {
			name: 'Discard unsaved changes?',
		} );
		expect( dialog ).toHaveTextContent( '3 changes will be lost.' );
		expect(
			screen.getByRole( 'button', { name: 'Keep editing' } )
		).toBeInTheDocument();

		// jsdom has no layout, so Modal cannot find a tabbable element to focus.
		act( () => dialog.focus() );
		await user.keyboard( '{Escape}' );
		// Modal closes after its exit animation.
		await waitFor( () => expect( onClose ).toHaveBeenCalled() );
	} );

	it( 'can be an alertdialog', () => {
		render(
			<Dialog
				title="Turn off Email address?"
				onClose={ () => {} }
				role="alertdialog"
			>
				<p>Customers get no order emails.</p>
			</Dialog>
		);
		expect(
			screen.getByRole( 'alertdialog', {
				name: 'Turn off Email address?',
			} )
		).toBeInTheDocument();
	} );
} );

describe( 'AnnouncerProvider', () => {
	it( 'speaks politely through a status region and urgently through an alert', () => {
		let announce: Announce = () => {};
		function Speaker() {
			announce = useAnnounce();
			return null;
		}
		render(
			<AnnouncerProvider>
				<Speaker />
			</AnnouncerProvider>
		);

		act( () => announce( 'Changes saved.' ) );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Changes saved.'
		);

		act( () => announce( 'Not saved.', 'assertive' ) );
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent( 'Not saved.' );
		expect( screen.getByRole( 'status' ) ).toBeEmptyDOMElement();
	} );
} );
