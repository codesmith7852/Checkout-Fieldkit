/**
 * The Fields screen (spec §6.2): checkout switch and notices, section tabs,
 * field rows and their switches, essential confirmation, reorder, the
 * selection bar, copying, deleting with undo, the empty state and the calls
 * into the field editor.
 */
import { act, screen, waitFor, within } from '@testing-library/react';
import type { FieldEditorApi } from '../../../../src/admin/editor';
import { blockFieldSet, classicFieldSet, field } from '../fixtures';
import { fieldsContext } from './fixtures';
import {
	draft,
	inRow,
	mockRowLayout,
	names,
	renderFields,
	rowOf,
} from './helpers';

jest.mock( '../../../../src/admin/editor', () => {
	const mockEditor = { openNew: jest.fn(), openExisting: jest.fn() };
	return {
		FieldEditorProvider: ( { children }: { children: unknown } ) =>
			children,
		useFieldEditor: () => mockEditor,
		mockEditor,
	};
} );

const editor = jest.requireMock< {
	mockEditor: jest.Mocked< FieldEditorApi >;
} >( '../../../../src/admin/editor' ).mockEditor;

const CLASSIC_BILLING = '?page=checkout-fieldkit&checkout=classic';

beforeEach( () => {
	editor.openNew.mockClear();
	editor.openExisting.mockClear();
} );

afterEach( () => {
	jest.restoreAllMocks();
} );

describe( 'checkout switch, notices and sections', () => {
	it( 'opens on the active checkout type with In use and Not in use pills', () => {
		renderFields();
		const group = screen.getByRole( 'group', { name: 'Checkout type' } );
		const block = within( group ).getByRole( 'link', {
			name: /Block checkout/,
		} );
		const classic = within( group ).getByRole( 'link', {
			name: /Classic checkout/,
		} );

		expect( block ).toHaveAttribute( 'aria-current', 'page' );
		expect( block ).toHaveTextContent( 'In use' );
		expect( classic ).toHaveTextContent( 'Not in use' );
		expect(
			screen.queryByText( /shoppers don't see these fields/ )
		).not.toBeInTheDocument();
	} );

	it( 'says the fields are not seen when viewing the checkout not in use', () => {
		renderFields( CLASSIC_BILLING );
		expect(
			screen.getByText( /shoppers don't see these fields right now/ )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: /^Open the Checkout page/ } )
		).toHaveAttribute( 'href', 'https://shop.example/checkout/' );
	} );

	it( 'says why block fields do nothing on an old WooCommerce', () => {
		renderFields( '?page=checkout-fieldkit&checkout=block', {
			context: fieldsContext( {
				activeCheckoutType: 'classic',
				blockSupported: false,
				wooCommerceVersion: '8.5.0',
			} ),
		} );
		expect(
			screen.getByText( /need WooCommerce 8.9 or newer/ )
		).toBeInTheDocument();
	} );

	it( 'shows section tabs with field counts and the Block Address note', async () => {
		const { user } = renderFields();
		const tabs = screen.getAllByRole( 'tab' );
		expect( tabs.map( ( tab ) => tab.textContent ) ).toEqual( [
			'Contact1',
			'Address2',
			'Additional0',
		] );
		expect(
			screen.queryByText( /appear in both the billing and the shipping/ )
		).not.toBeInTheDocument();

		await user.click( screen.getByRole( 'tab', { name: /Address/ } ) );
		expect(
			screen.getByText(
				'Fields in this section appear in both the billing and the shipping address forms.'
			)
		).toBeInTheDocument();
	} );
} );

describe( 'field rows', () => {
	it( 'shows label, tags, name, type and validation', () => {
		renderFields( CLASSIC_BILLING );

		const email = inRow( 'billing_email' );
		expect(
			email.getByText( 'Email address', {
				selector: '.cfk-field-row__label-text',
			} )
		).toBeInTheDocument();
		expect( email.getByText( 'Essential' ) ).toBeInTheDocument();
		expect( email.queryByText( 'Custom' ) ).not.toBeInTheDocument();
		expect( email.getByText( 'Email' ) ).toBeInTheDocument();
		expect(
			email.getByText( 'Email address', {
				selector: '.cfk-field-row__validation',
			} )
		).toBeInTheDocument();

		const vat = inRow( 'billing_vat' );
		expect( vat.getByText( 'Custom' ) ).toBeInTheDocument();
		expect( vat.queryByText( 'Essential' ) ).not.toBeInTheDocument();
		expect( vat.getByText( 'Text' ) ).toBeInTheDocument();
		expect( vat.getByText( 'None' ) ).toBeInTheDocument();
	} );

	it( 'greys disabled rows but keeps them editable', async () => {
		const { user } = renderFields(
			'?page=checkout-fieldkit&section=address'
		);
		const company = rowOf( 'company' );
		expect( company ).toHaveClass( 'is-off' );

		await user.click(
			within( company ).getByRole( 'button', { name: 'Edit Company' } )
		);
		expect( editor.openExisting ).toHaveBeenCalledWith(
			'block',
			expect.stringMatching( /\S/ )
		);
	} );

	it( 'opens the editor on the row’s own id', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		await user.click(
			inRow( 'billing_vat' ).getByRole( 'button', {
				name: 'Edit VAT number',
			} )
		);
		await user.click(
			inRow( 'billing_first_name' ).getByRole( 'button', {
				name: 'Edit First name',
			} )
		);
		const [ [ , vatId ], [ , firstId ] ] = editor.openExisting.mock.calls;
		expect( editor.openExisting ).toHaveBeenNthCalledWith(
			1,
			'classic',
			vatId
		);
		expect( vatId ).not.toBe( firstId );
	} );

	it( 'updates the draft from the Required and Enabled switches', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		const required = inRow( 'billing_vat' ).getByRole( 'switch', {
			name: 'Required: VAT number',
		} );
		const enabled = inRow( 'billing_vat' ).getByRole( 'switch', {
			name: 'Enabled: VAT number',
		} );

		await user.click( required );
		expect( required ).toHaveAttribute( 'aria-checked', 'true' );
		await user.click( enabled );
		expect( enabled ).toHaveAttribute( 'aria-checked', 'false' );
		expect( rowOf( 'billing_vat' ) ).toHaveClass( 'is-off' );

		const vat = draft().fieldSets.classic?.sections.billing[ 2 ];
		expect( vat ).toMatchObject( { required: true, enabled: false } );
		expect( draft().changeCount ).toBe( 1 );
	} );

	it( 'locks the switches WooCommerce keeps for itself, saying why', () => {
		renderFields();
		const email = inRow( 'email' );
		const required = email.getByRole( 'switch', {
			name: 'Required: Email address',
		} );
		const enabled = email.getByRole( 'switch', {
			name: 'Enabled: Email address',
		} );
		expect( required ).toBeDisabled();
		expect( enabled ).toBeDisabled();
		expect( required ).toHaveAccessibleDescription(
			'WooCommerce decides whether Email address is required.'
		);
		expect( enabled ).toHaveAccessibleDescription(
			'WooCommerce always shows Email address.'
		);
	} );

	it( 'has no Required switch for display fields', () => {
		const block = blockFieldSet();
		block.sections.contact.push(
			field( {
				name: 'intro',
				label: 'Before you order',
				type: 'heading',
			} )
		);
		renderFields( '', { block } );
		expect(
			inRow( 'intro' ).queryByRole( 'switch', { name: /Required/ } )
		).not.toBeInTheDocument();
		expect(
			inRow( 'intro' ).getByText(
				'Required does not apply to Heading fields.'
			)
		).toBeInTheDocument();
	} );

	it( 'shows problems from the last save next to the field', async () => {
		const { user } = renderFields(
			CLASSIC_BILLING,
			{},
			{
				putFieldSet: jest.fn( async () => {
					throw {
						code: 'checkout_fieldkit_invalid_field_set',
						message: 'Not saved.',
						data: {
							status: 422,
							errors: [
								{
									section: 'billing',
									index: 2,
									key: 'name',
									message: 'That name is taken.',
								},
							],
						},
					};
				} ),
			}
		);
		await user.click(
			inRow( 'billing_vat' ).getByRole( 'switch', {
				name: 'Required: VAT number',
			} )
		);
		await act( async () => {
			await draft().save();
		} );

		expect( rowOf( 'billing_vat' ) ).toHaveClass( 'has-problems' );
		expect(
			inRow( 'billing_vat' ).getByRole( 'button', {
				name: 'Edit VAT number',
			} )
		).toHaveAccessibleDescription( 'That name is taken.' );
	} );
} );

describe( 'essential fields', () => {
	it( 'asks before turning one off, with Keep it on focused', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		await user.click(
			inRow( 'billing_email' ).getByRole( 'switch', {
				name: 'Enabled: Email address',
			} )
		);

		const dialog = screen.getByRole( 'alertdialog', {
			name: 'Turn off Email address?',
		} );
		expect(
			within( dialog ).getByText( 'Customers get no order emails.' )
		).toBeInTheDocument();
		expect(
			within( dialog ).getByRole( 'button', { name: 'Keep it on' } )
		).toHaveFocus();

		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Keep it on' } )
		);
		expect( screen.queryByRole( 'alertdialog' ) ).not.toBeInTheDocument();
		expect( draft().changeCount ).toBe( 0 );

		await user.click(
			inRow( 'billing_email' ).getByRole( 'switch', {
				name: 'Enabled: Email address',
			} )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Turn off anyway' } )
		);
		expect( draft().fieldSets.classic?.sections.billing[ 1 ].enabled ).toBe(
			false
		);
	} );

	it( 'asks before making one optional', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		await user.click(
			inRow( 'billing_email' ).getByRole( 'switch', {
				name: 'Required: Email address',
			} )
		);
		const dialog = screen.getByRole( 'alertdialog', {
			name: 'Make Email address optional?',
		} );
		expect(
			within( dialog ).getByRole( 'button', { name: 'Keep it required' } )
		).toHaveFocus();

		await user.keyboard( '{Escape}' );
		await waitFor( () =>
			expect(
				screen.queryByRole( 'alertdialog' )
			).not.toBeInTheDocument()
		);
		expect(
			draft().fieldSets.classic?.sections.billing[ 1 ].required
		).toBe( true );

		await user.click(
			inRow( 'billing_email' ).getByRole( 'switch', {
				name: 'Required: Email address',
			} )
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Make it optional' } )
		);
		expect(
			draft().fieldSets.classic?.sections.billing[ 1 ].required
		).toBe( false );
	} );

	it( 'does not ask when turning one back on', async () => {
		const classic = classicFieldSet();
		classic.sections.billing[ 1 ].enabled = false;
		const { user } = renderFields( CLASSIC_BILLING, { classic } );
		await user.click(
			inRow( 'billing_email' ).getByRole( 'switch', {
				name: 'Enabled: Email address',
			} )
		);
		expect( screen.queryByRole( 'alertdialog' ) ).not.toBeInTheDocument();
		expect( draft().fieldSets.classic?.sections.billing[ 1 ].enabled ).toBe(
			true
		);
	} );
} );

describe( 'reordering', () => {
	beforeEach( () => mockRowLayout() );

	function handle( name: string ) {
		return within( rowOf( name ) ).getByRole( 'button', {
			name: /^Reorder /,
		} );
	}

	it( 'moves a field with Space, the arrow keys and Space, announcing each step', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		act( () => handle( 'billing_vat' ).focus() );

		await user.keyboard( '[Space]' );
		await waitFor( () =>
			expect(
				screen.getByText(
					'Picked up VAT number. It is at position 3 of 3.'
				)
			).toBeInTheDocument()
		);

		await user.keyboard( '[ArrowUp]' );
		await waitFor( () =>
			expect(
				screen.getByText( 'VAT number moved to position 2 of 3.' )
			).toBeInTheDocument()
		);

		await user.keyboard( '[Space]' );
		await waitFor( () =>
			expect( names( 'classic', 'billing' ) ).toEqual( [
				'billing_first_name',
				'billing_vat',
				'billing_email',
			] )
		);
		expect(
			screen.getByText( 'VAT number dropped at position 2 of 3.' )
		).toBeInTheDocument();
		expect( draft().changeCount ).toBe( 1 );
	} );

	it( 'puts the field back on Escape', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		act( () => handle( 'billing_first_name' ).focus() );

		await user.keyboard( '[Space]' );
		await user.keyboard( '[ArrowDown]' );
		await user.keyboard( '[Escape]' );

		await waitFor( () =>
			expect(
				screen.getByText(
					'Moving cancelled. First name is back at position 1 of 3.'
				)
			).toBeInTheDocument()
		);
		expect( names( 'classic', 'billing' ) ).toEqual( [
			'billing_first_name',
			'billing_email',
			'billing_vat',
		] );
	} );

	it( 'tells keyboard users how to move a field', () => {
		renderFields( CLASSIC_BILLING );
		expect( handle( 'billing_vat' ) ).toHaveAccessibleDescription(
			/press Space to pick it up/
		);
	} );
} );

describe( 'selection bar', () => {
	function select( name: string ) {
		return inRow( name ).getByRole( 'checkbox' );
	}

	it( 'appears with a count and clears', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		expect(
			screen.queryByRole( 'group', { name: 'Selected fields' } )
		).not.toBeInTheDocument();

		await user.click( select( 'billing_vat' ) );
		await user.click( select( 'billing_first_name' ) );
		const bar = screen.getByRole( 'group', { name: 'Selected fields' } );
		expect( within( bar ).getByText( '2 selected' ) ).toBeInTheDocument();
		expect(
			screen.getByText(
				'2 fields selected. Actions for them are above the table.',
				{ selector: '[role="status"] *' }
			)
		).toBeInTheDocument();

		await user.click(
			within( bar ).getByRole( 'button', { name: 'Clear selection' } )
		);
		expect(
			screen.queryByRole( 'group', { name: 'Selected fields' } )
		).not.toBeInTheDocument();
		expect( select( 'billing_vat' ) ).not.toBeChecked();
		expect(
			screen.getByRole( 'button', { name: 'Add field' } )
		).toHaveFocus();
	} );

	it( 'selects every field in the section from the header checkbox', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		const all = screen.getByRole( 'checkbox', {
			name: 'Select all fields in Billing',
		} );
		await user.click( select( 'billing_vat' ) );
		expect( all ).toHaveProperty( 'indeterminate', true );

		await user.click( all );
		expect( screen.getByText( '3 selected' ) ).toBeInTheDocument();
		await user.click( all );
		expect( screen.queryByText( /selected$/ ) ).not.toBeInTheDocument();
	} );

	it( 'turns the selected fields off and on, asking about essential ones', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		await user.click( select( 'billing_vat' ) );
		await user.click( select( 'billing_email' ) );
		const bar = screen.getByRole( 'group', { name: 'Selected fields' } );

		await user.click(
			within( bar ).getByRole( 'button', { name: 'Turn off' } )
		);
		const dialog = screen.getByRole( 'alertdialog', {
			name: 'Turn off an essential field?',
		} );
		expect(
			within( dialog ).getByText( /Email address/ )
		).toBeInTheDocument();
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Turn off anyway' } )
		);
		const billing = draft().fieldSets.classic?.sections.billing || [];
		expect( billing.map( ( item ) => item.enabled ) ).toEqual( [
			true,
			false,
			false,
		] );

		await user.click(
			within( bar ).getByRole( 'button', { name: 'Turn on' } )
		);
		expect(
			draft().fieldSets.classic?.sections.billing.map(
				( item ) => item.enabled
			)
		).toEqual( [ true, true, true ] );
	} );

	it( 'leaves fields WooCommerce keeps on alone', async () => {
		const { user } = renderFields();
		await user.click( select( 'email' ) );
		await user.click( screen.getByRole( 'button', { name: 'Turn off' } ) );
		expect( draft().fieldSets.block?.sections.contact[ 0 ].enabled ).toBe(
			true
		);
		expect(
			screen.getByText( 'WooCommerce always shows Email address.', {
				selector: '[role="status"] *',
			} )
		).toBeInTheDocument();
	} );

	it( 'cannot delete built-in fields, and says why', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		await user.click( select( 'billing_vat' ) );
		await user.click( select( 'billing_first_name' ) );
		const remove = screen.getByRole( 'button', { name: 'Delete' } );

		expect( remove ).toHaveAttribute( 'aria-disabled', 'true' );
		expect( remove ).toHaveAccessibleDescription(
			'First name is built in, so it can be turned off but not deleted.'
		);
		await user.click( remove );
		expect( names( 'classic', 'billing' ) ).toHaveLength( 3 );
	} );

	it( 'deletes custom fields, with undo', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		await user.click( select( 'billing_vat' ) );
		await user.click( screen.getByRole( 'button', { name: 'Delete' } ) );

		expect( names( 'classic', 'billing' ) ).not.toContain( 'billing_vat' );
		expect(
			screen.getByText( 'Deleted VAT number.', {
				selector: '.cfk-notice__title',
			} )
		).toBeInTheDocument();
		const undo = screen.getByRole( 'button', { name: 'Undo' } );
		expect( undo ).toHaveFocus();

		await user.click( undo );
		expect( names( 'classic', 'billing' ) ).toEqual( [
			'billing_first_name',
			'billing_email',
			'billing_vat',
		] );
		expect( draft().changeCount ).toBe( 0 );
	} );

	it( 'copies what it can to the other checkout and says why the rest was not', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		await user.click( select( 'billing_vat' ) );
		await user.click( select( 'billing_first_name' ) );
		await user.click(
			screen.getByRole( 'button', { name: 'Copy to Block checkout' } )
		);

		expect( names( 'block', 'address' ) ).toEqual( [
			'country',
			'company',
			'billing_vat',
		] );
		const notice = screen
			.getByText( 'Copied 1 field to the Block checkout.', {
				selector: '.cfk-notice__title',
			} )
			.closest( '.cfk-notice' ) as HTMLElement;
		expect(
			within( notice ).getByText( /VAT number → Address/ )
		).toBeInTheDocument();
		expect(
			within( notice ).getByText( /First name is built in/ )
		).toBeInTheDocument();

		await user.click(
			within( notice ).getByRole( 'link', {
				name: 'Go to Block checkout',
			} )
		);
		expect( rowOf( 'billing_vat' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'tab', { name: /Address/ } )
		).toHaveAttribute( 'aria-selected', 'true' );
	} );
} );

describe( 'More menu', () => {
	async function openMenu(
		user: ReturnType< typeof renderFields >[ 'user' ],
		label: string
	) {
		await user.click(
			screen.getByRole( 'button', {
				name: `More actions for ${ label }`,
			} )
		);
		return screen.getByRole( 'menu' );
	}

	it( 'copies a custom field to the other checkout', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		const menu = await openMenu( user, 'VAT number' );
		await user.click(
			within( menu ).getByRole( 'menuitem', {
				name: /Copy to Block checkout/,
			} )
		);
		expect( names( 'block', 'address' ) ).toContain( 'billing_vat' );
		expect( names( 'classic', 'billing' ) ).toContain( 'billing_vat' );
	} );

	it( 'refuses a copy the other checkout cannot take, saying why', async () => {
		const classic = classicFieldSet();
		classic.sections.billing[ 2 ].type = 'textarea';
		const { user } = renderFields( CLASSIC_BILLING, { classic } );
		const menu = await openMenu( user, 'VAT number' );
		const copy = within( menu ).getByRole( 'menuitem', {
			name: /Copy to Block checkout/,
		} );
		expect( copy ).toHaveAttribute( 'aria-disabled', 'true' );
		expect( copy ).toHaveTextContent(
			"Textarea fields can't go in the Block checkout's Address section."
		);
		await user.click( copy );
		expect( names( 'block', 'address' ) ).not.toContain( 'billing_vat' );
	} );

	it( 'deletes custom fields only', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		let menu = await openMenu( user, 'First name' );
		expect(
			within( menu ).getByRole( 'menuitem', { name: /Delete/ } )
		).toHaveAttribute( 'aria-disabled', 'true' );
		// jsdom has no layout, so the popover can't find a tabbable item to
		// focus; close it with its toggle rather than Escape.
		await user.click(
			screen.getByRole( 'button', {
				name: 'More actions for First name',
			} )
		);
		await waitFor( () =>
			expect( screen.queryByRole( 'menu' ) ).not.toBeInTheDocument()
		);

		menu = await openMenu( user, 'VAT number' );
		await user.click(
			within( menu ).getByRole( 'menuitem', { name: /Delete/ } )
		);
		expect( names( 'classic', 'billing' ) ).not.toContain( 'billing_vat' );
		expect( screen.getByRole( 'button', { name: 'Undo' } ) ).toHaveFocus();
	} );
} );

describe( 'adding fields', () => {
	it( 'shows an empty state for a section with no fields', async () => {
		const { user } = renderFields(
			'?page=checkout-fieldkit&section=additional'
		);
		expect(
			screen.getByRole( 'heading', {
				name: 'No fields in Additional yet',
			} )
		).toBeInTheDocument();
		expect( screen.getByText( 'Gift message' ) ).toBeInTheDocument();
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();

		const buttons = screen.getAllByRole( 'button', { name: 'Add field' } );
		await user.click( buttons[ buttons.length - 1 ] );
		expect( editor.openNew ).toHaveBeenCalledWith( 'block', 'additional' );
	} );

	it( 'opens the editor with a new field from the toolbar', async () => {
		const { user } = renderFields( CLASSIC_BILLING );
		await user.click( screen.getByRole( 'button', { name: 'Add field' } ) );
		expect( editor.openNew ).toHaveBeenCalledWith( 'classic', 'billing' );
	} );
} );
