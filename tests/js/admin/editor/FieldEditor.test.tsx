/**
 * The field editor drawer (spec §6.3), driven through useFieldEditor() as
 * the Fields screen does: what it shows per type, the name suggestion,
 * built-in locks, options, the preview, validation, and what Apply, Cancel
 * and Delete do to the draft. Focus management too.
 */
import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Providers } from '../../../../src/admin/App';
import type { Api } from '../../../../src/admin/api';
import {
	FieldEditorProvider,
	useFieldEditor,
} from '../../../../src/admin/editor';
import { useDraft, useFieldSet } from '../../../../src/admin/store';
import type {
	CheckoutType,
	FieldSet,
	InitialData,
	Settings,
} from '../../../../src/admin/types';
import { editorData } from './fixtures';

function fakeApi( data: InitialData, overrides: Partial< Api > = {} ) {
	return {
		getContext: jest.fn( async () => data.context ),
		getFieldSet: jest.fn( async ( type: CheckoutType ) => data[ type ] ),
		getSettings: jest.fn( async () => data.settings ),
		putFieldSet: jest.fn(
			async ( _type: CheckoutType, fieldSet: FieldSet ) =>
				JSON.parse( JSON.stringify( fieldSet ) )
		),
		putSettings: jest.fn( async ( value: Settings ) => value ),
		...overrides,
	} as jest.Mocked< Api >;
}

/** Stands in for the Fields screen: an Add button per section and an Edit button per field. */
function Screen() {
	const editor = useFieldEditor();
	const { changeCount, save } = useDraft();
	const classic = useFieldSet( 'classic' );
	const block = useFieldSet( 'block' );
	return (
		<div className="cfk-content">
			<h1 tabIndex={ -1 }>Checkout fields</h1>
			<span data-testid="count">{ changeCount }</span>
			<button type="button" onClick={ () => save() }>
				Save
			</button>
			{ [ classic, block ].map( ( fieldSet ) =>
				fieldSet.sections.map( ( section ) => (
					<div key={ `${ fieldSet.checkoutType }-${ section.name }` }>
						<button
							type="button"
							onClick={ () =>
								editor.openNew(
									fieldSet.checkoutType,
									section.name
								)
							}
						>
							{ `Add ${ fieldSet.checkoutType } ${ section.name }` }
						</button>
						{ section.rows.map( ( row ) => (
							<button
								type="button"
								key={ row.id }
								onClick={ () =>
									editor.openExisting(
										fieldSet.checkoutType,
										row.id
									)
								}
							>
								{ `Edit ${ fieldSet.checkoutType } ${ row.field.name }` }
							</button>
						) ) }
					</div>
				) )
			) }
			<pre data-testid="draft">
				{ JSON.stringify( {
					classic: classic.fieldSet,
					block: block.fieldSet,
				} ) }
			</pre>
		</div>
	);
}

function setup(
	data: InitialData = editorData(),
	overrides: Partial< Api > = {}
) {
	const api = fakeApi( data, overrides );
	render(
		<Providers
			settings={ {
				preloaded: {
					context: data.context,
					fieldSets: { classic: data.classic, block: data.block },
					settings: data.settings,
				},
			} }
			api={ api }
		>
			<FieldEditorProvider>
				<Screen />
			</FieldEditorProvider>
		</Providers>
	);
	return { api, user: userEvent.setup() };
}

function draft(): Record< CheckoutType, FieldSet > {
	return JSON.parse( screen.getByTestId( 'draft' ).textContent || '{}' );
}

/** Lets a modal finish closing: its exit animation falls back to a 240 ms timeout in jsdom. */
async function settle() {
	await act( () => new Promise( ( resolve ) => setTimeout( resolve, 300 ) ) );
}

function drawer() {
	return screen.getByRole( 'dialog' );
}

function preview() {
	const element = document.querySelector< HTMLElement >(
		'.cfk-editor-preview__shop'
	);
	if ( ! element ) {
		throw new Error( 'No preview' );
	}
	return element;
}

async function open(
	user: ReturnType< typeof userEvent.setup >,
	button: string
) {
	await user.click( screen.getByRole( 'button', { name: button } ) );
	return drawer();
}

describe( 'opening and closing', () => {
	it( 'opens "Add field" with the checkout type, section and Custom field', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Add classic billing' );

		expect( dialog ).toHaveAccessibleName( 'Add field' );
		expect(
			within( dialog ).getByText(
				'Classic checkout · Billing · Custom field'
			)
		).toBeInTheDocument();
		expect( within( dialog ).getByLabelText( 'Label' ) ).toHaveFocus();
	} );

	it( 'opens "Edit field" for an existing field', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Edit classic billing_email' );

		expect( dialog ).toHaveAccessibleName( 'Edit field' );
		expect(
			within( dialog ).getByText(
				'Classic checkout · Billing · Built-in field'
			)
		).toBeInTheDocument();
		expect( within( dialog ).getByLabelText( 'Label' ) ).toHaveValue(
			'Email address'
		);
	} );

	it( 'closes on Escape, changes nothing and gives focus back to the opener', async () => {
		const { user } = setup();
		await open( user, 'Edit classic billing_vat' );
		await user.clear( screen.getByLabelText( 'Label' ) );
		await user.type( screen.getByLabelText( 'Label' ), 'Tax number' );
		await user.keyboard( '{Escape}' );

		await waitFor( () =>
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
		);
		expect( screen.getByTestId( 'count' ) ).toHaveTextContent( '0' );
		expect(
			screen.getByRole( 'button', { name: 'Edit classic billing_vat' } )
		).toHaveFocus();
	} );

	it( 'closes with the close button and Cancel', async () => {
		const { user } = setup();
		await open( user, 'Add block contact' );
		await user.click( screen.getByRole( 'button', { name: 'Close' } ) );
		await waitFor( () =>
			expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument()
		);
		expect(
			screen.getByRole( 'button', { name: 'Add block contact' } )
		).toHaveFocus();

		await open( user, 'Add block contact' );
		await user.type( screen.getByLabelText( 'Label' ), 'Gift wrap' );
		await user.click( screen.getByRole( 'button', { name: 'Cancel' } ) );
		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		expect( draft().block.sections.contact ).toHaveLength( 1 );
	} );

	it( 'keeps Tab inside the drawer', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Edit classic billing_vat' );
		for ( let step = 0; step < 40; step++ ) {
			await user.tab();
			expect( dialog ).toContainElement(
				dialog.ownerDocument.activeElement as HTMLElement
			);
		}
	} );
} );

describe( 'settings shown per type', () => {
	const shown = ( dialog: HTMLElement ) => ( {
		placeholder: !! within( dialog ).queryByLabelText( 'Placeholder' ),
		options: !! within( dialog ).queryByRole( 'group', {
			name: 'Options',
		} ),
		required: !! within( dialog ).queryByRole( 'switch', {
			name: 'Required',
		} ),
		validation: !! within( dialog ).queryByRole( 'group', {
			name: 'Validation',
		} ),
		emails: !! within( dialog ).queryByRole( 'checkbox', {
			name: /Order emails/,
		} ),
		pages: !! within( dialog ).queryByRole( 'checkbox', {
			name: /Order pages/,
		} ),
		classes: !! within( dialog ).queryByLabelText( 'CSS classes' ),
		headingLevel: !! within( dialog ).queryByLabelText( 'Heading level' ),
	} );

	it.each( [
		[
			'text',
			{
				placeholder: true,
				options: false,
				required: true,
				validation: true,
				emails: true,
				pages: true,
				classes: true,
				headingLevel: false,
			},
		],
		[
			'textarea',
			{
				placeholder: true,
				options: false,
				required: true,
				validation: false,
				emails: true,
				pages: true,
				classes: true,
				headingLevel: false,
			},
		],
		[
			'select',
			{
				placeholder: true,
				options: true,
				required: true,
				validation: false,
				emails: true,
				pages: true,
				classes: true,
				headingLevel: false,
			},
		],
		[
			'radio',
			{
				placeholder: false,
				options: true,
				required: true,
				validation: false,
				emails: true,
				pages: true,
				classes: true,
				headingLevel: false,
			},
		],
		[
			'checkboxgroup',
			{
				placeholder: false,
				options: true,
				required: true,
				validation: false,
				emails: true,
				pages: true,
				classes: true,
				headingLevel: false,
			},
		],
		[
			'hidden',
			{
				placeholder: false,
				options: false,
				required: false,
				validation: false,
				emails: true,
				pages: true,
				classes: true,
				headingLevel: false,
			},
		],
		[
			'date',
			{
				placeholder: false,
				options: false,
				required: true,
				validation: false,
				emails: true,
				pages: true,
				classes: true,
				headingLevel: false,
			},
		],
		[
			'heading',
			{
				placeholder: false,
				options: false,
				required: false,
				validation: false,
				emails: false,
				pages: false,
				classes: true,
				headingLevel: true,
			},
		],
		[
			'paragraph',
			{
				placeholder: false,
				options: false,
				required: false,
				validation: false,
				emails: false,
				pages: false,
				classes: true,
				headingLevel: false,
			},
		],
	] )( 'shows the settings of %s', async ( type, expected ) => {
		const { user } = setup();
		const dialog = await open( user, 'Add classic additional' );
		await user.selectOptions(
			within( dialog ).getByLabelText( 'Field type' ),
			type
		);
		expect( shown( dialog ) ).toEqual( expected );
	} );

	it( 'offers every classic type, and the block types the section allows', async () => {
		const { user } = setup();
		let dialog = await open( user, 'Add classic billing' );
		expect(
			within(
				within( dialog ).getByLabelText( 'Field type' )
			).getAllByRole( 'option' )
		).toHaveLength( 20 );
		await user.click( screen.getByRole( 'button', { name: 'Cancel' } ) );

		dialog = await open( user, 'Add block address' );
		const select = within( dialog ).getByLabelText( 'Field type' );
		const radio = within( select ).getByRole( 'option', {
			name: /Radio buttons/,
		} );
		expect( radio ).toBeDisabled();
		expect(
			within( select ).getByRole( 'option', { name: 'Dropdown' } )
		).toBeEnabled();
		expect( within( select ).getAllByRole( 'option' ) ).toHaveLength( 10 );
		expect(
			within( dialog ).getByText(
				/Not available in the Address section: Text area, Radio buttons, Heading, Paragraph/
			)
		).toBeInTheDocument();
	} );

	it( 'says which validation a type always gets', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Add classic additional' );
		await user.selectOptions(
			within( dialog ).getByLabelText( 'Field type' ),
			'email'
		);
		expect(
			within( dialog ).getByText(
				'Answers are always checked as: Email address.'
			)
		).toBeInTheDocument();
		const group = within( dialog ).getByRole( 'group', {
			name: 'Validation',
		} );
		expect(
			within( group ).queryByRole( 'checkbox', { name: 'Email address' } )
		).not.toBeInTheDocument();
		expect(
			within( group ).getByRole( 'checkbox', { name: 'Phone number' } )
		).toBeInTheDocument();
	} );
} );

describe( 'field name', () => {
	it( 'is suggested from the label with the section prefix, and explained', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Add classic billing' );
		await user.type(
			within( dialog ).getByLabelText( 'Label' ),
			'How did you hear about us?'
		);

		expect( within( dialog ).getByLabelText( 'Field name' ) ).toHaveValue(
			'billing_how_did_you_hear_about_us'
		);
		expect(
			within( dialog ).getByText(
				/Starting the name with billing_ lets WooCommerce also save the answer on the customer/
			)
		).toBeInTheDocument();
	} );

	it( 'has no prefix outside classic Billing and Shipping, and avoids names in use', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Add classic additional' );
		await user.type(
			within( dialog ).getByLabelText( 'Label' ),
			'Heard from'
		);
		expect( within( dialog ).getByLabelText( 'Field name' ) ).toHaveValue(
			'heard_from_2'
		);
		expect(
			within( dialog ).queryByText( /lets WooCommerce also save/ )
		).not.toBeInTheDocument();
	} );

	it( 'stops following the label once edited by hand', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Add classic shipping' );
		const name = within( dialog ).getByLabelText( 'Field name' );
		await user.type( within( dialog ).getByLabelText( 'Label' ), 'Door' );
		await user.clear( name );
		await user.type( name, 'shipping_door_code' );
		await user.type( within( dialog ).getByLabelText( 'Label' ), ' code' );
		expect( name ).toHaveValue( 'shipping_door_code' );
	} );

	it( 'warns when a saved custom field is renamed', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Edit classic billing_vat' );
		const warning =
			/Orders placed before the rename keep their answer under the old name/;
		expect(
			within( dialog ).queryByText( warning )
		).not.toBeInTheDocument();
		await user.type(
			within( dialog ).getByLabelText( 'Field name' ),
			'_number'
		);
		expect( within( dialog ).getByText( warning ) ).toBeInTheDocument();
	} );
} );

describe( 'built-in fields', () => {
	it( 'lock the name and type and offer only what is ours to change', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Edit classic billing_email' );

		expect(
			within( dialog ).getByLabelText( 'Field name' )
		).toHaveAttribute( 'readonly' );
		expect(
			within( dialog ).getByLabelText( 'Field type' )
		).toBeDisabled();
		expect(
			within( dialog ).getByText(
				'WooCommerce’s own fields keep their name and type.'
			)
		).toBeInTheDocument();
		expect(
			within( dialog ).getByLabelText( 'Placeholder' )
		).toBeInTheDocument();
		expect(
			within( dialog ).getByRole( 'group', { name: 'Validation' } )
		).toBeInTheDocument();
		expect(
			within( dialog ).queryByRole( 'checkbox', { name: /Order emails/ } )
		).not.toBeInTheDocument();
		expect(
			within( dialog ).queryByRole( 'button', { name: 'Delete field' } )
		).not.toBeInTheDocument();
	} );

	it( 'on the Block checkout offer only label and required', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Edit block company' );
		expect(
			within( dialog ).getByLabelText( 'Label' )
		).toBeInTheDocument();
		expect(
			within( dialog ).getByRole( 'switch', { name: 'Required' } )
		).toBeInTheDocument();
		expect(
			within( dialog ).queryByLabelText( 'Placeholder' )
		).not.toBeInTheDocument();
		expect(
			within( dialog ).queryByLabelText( 'CSS classes' )
		).not.toBeInTheDocument();
		expect(
			within( dialog ).getByText(
				'WooCommerce decides this field’s other settings.'
			)
		).toBeInTheDocument();
	} );

	it( 'say when nothing but the position can change (Block email)', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Edit block email' );
		expect(
			within( dialog ).queryByLabelText( 'Label' )
		).not.toBeInTheDocument();
		expect(
			within( dialog ).getByText(
				/WooCommerce controls this field. Only its position can change/
			)
		).toBeInTheDocument();
	} );

	it( 'close only the confirmation on Escape, keeping the field required', async () => {
		const { user } = setup();
		await open( user, 'Edit classic billing_email' );
		await user.click( screen.getByRole( 'switch', { name: 'Required' } ) );
		await user.keyboard( '{Escape}' );
		await settle();

		expect( screen.queryByRole( 'alertdialog' ) ).not.toBeInTheDocument();
		expect( screen.getByRole( 'dialog' ) ).toBeInTheDocument();
		const required = screen.getByRole( 'switch', { name: 'Required' } );
		expect( required ).toBeChecked();
		expect( required ).toHaveFocus();
	} );

	it( 'ask before an essential field stops being required', async () => {
		const { user } = setup();
		await open( user, 'Edit classic billing_email' );
		await user.click( screen.getByRole( 'switch', { name: 'Required' } ) );

		const confirm = screen.getByRole( 'alertdialog' );
		expect(
			within( confirm ).getByText( 'Customers get no order emails.' )
		).toBeInTheDocument();
		expect(
			within( confirm ).getByRole( 'button', {
				name: 'Keep it required',
			} )
		).toHaveFocus();
		await user.click(
			within( confirm ).getByRole( 'button', {
				name: 'Keep it required',
			} )
		);
		expect(
			screen.getByRole( 'switch', { name: 'Required' } )
		).toBeChecked();

		await user.click( screen.getByRole( 'switch', { name: 'Required' } ) );
		await user.click(
			screen.getByRole( 'button', { name: 'Make it optional' } )
		);
		expect(
			screen.getByRole( 'switch', { name: 'Required' } )
		).not.toBeChecked();
		await user.click( screen.getByRole( 'button', { name: 'Apply' } ) );
		expect( draft().classic.sections.billing[ 1 ].required ).toBe( false );
	} );

	it( 'keep their locked settings when applied', async () => {
		const { user } = setup();
		await open( user, 'Edit block company' );
		await user.clear( screen.getByLabelText( 'Label' ) );
		await user.type( screen.getByLabelText( 'Label' ), 'Business' );
		await user.click( screen.getByRole( 'button', { name: 'Apply' } ) );
		expect( draft().block.sections.address[ 1 ] ).toEqual( {
			...editorData().block.sections.address[ 1 ],
			label: 'Business',
		} );
	} );
} );

describe( 'options', () => {
	function optionValues( dialog: HTMLElement ) {
		return within( dialog )
			.getAllByRole( 'textbox', { name: /^Saved value/ } )
			.map( ( input ) => ( input as HTMLInputElement ).value );
	}

	it( 'adds an option whose saved value follows its text', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Edit classic heard_from' );
		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Add option' } )
		);
		const shown = within( dialog ).getAllByRole( 'textbox', {
			name: /^Shown text/,
		} );
		expect( shown[ 3 ] ).toHaveFocus();
		await user.type( shown[ 3 ], 'Radio ad' );
		expect( optionValues( dialog ) ).toEqual( [
			'search',
			'social',
			'friend',
			'radio_ad',
		] );
	} );

	it( 'removes an option and drops it from the default', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Edit classic heard_from' );
		await user.selectOptions(
			within( dialog ).getByLabelText( 'Selected by default' ),
			'social'
		);
		await user.click(
			within( dialog ).getByRole( 'button', {
				name: 'Remove option Social media',
			} )
		);
		expect( optionValues( dialog ) ).toEqual( [ 'search', 'friend' ] );
		expect(
			within( dialog ).getByLabelText( 'Selected by default' )
		).toHaveValue( '' );
	} );

	it( 'keeps the default when its option’s value is renamed', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Edit classic heard_from' );
		await user.selectOptions(
			within( dialog ).getByLabelText( 'Selected by default' ),
			'friend'
		);
		const value = within( dialog ).getByRole( 'textbox', {
			name: 'Saved value for A friend',
		} );
		await user.type( value, 's' );
		await user.click( screen.getByRole( 'button', { name: 'Apply' } ) );
		const saved = draft().classic.sections.additional[ 1 ];
		expect( saved.default ).toBe( 'friends' );
		expect( saved.options[ 2 ] ).toEqual( {
			value: 'friends',
			label: 'A friend',
		} );
	} );

	describe( 'reordering with the keyboard', () => {
		// jsdom has no layout: give each option row a place, 40px apart.
		let spy: jest.SpyInstance;
		beforeEach( () => {
			spy = jest
				.spyOn( Element.prototype, 'getBoundingClientRect' )
				.mockImplementation( function ( this: Element ) {
					const row = this.closest( '[data-option-index]' );
					const index = row
						? Number( row.getAttribute( 'data-option-index' ) )
						: 0;
					const top = index * 40;
					return {
						x: 0,
						y: top,
						top,
						left: 0,
						right: 400,
						bottom: top + 40,
						width: 400,
						height: 40,
						toJSON: () => ( {} ),
					} as DOMRect;
				} );
		} );
		afterEach( () => spy.mockRestore() );

		it( 'picks up with Space, moves with the arrows and drops with Space', async () => {
			const { user } = setup();
			const dialog = await open( user, 'Edit classic heard_from' );
			const handle = within( dialog ).getByRole( 'button', {
				name: 'Reorder option Search engine',
			} );
			handle.focus();
			await user.keyboard( '[Space]' );
			await user.keyboard( '[ArrowDown]' );
			await user.keyboard( '[Space]' );
			await waitFor( () =>
				expect( optionValues( dialog ) ).toEqual( [
					'social',
					'search',
					'friend',
				] )
			);
			await waitFor( () =>
				expect(
					within( dialog ).getByRole( 'button', {
						name: 'Reorder option Search engine',
					} )
				).toHaveFocus()
			);
		} );

		it( 'cancels with Escape without closing the drawer', async () => {
			const { user } = setup();
			const dialog = await open( user, 'Edit classic heard_from' );
			within( dialog )
				.getByRole( 'button', { name: 'Reorder option Search engine' } )
				.focus();
			await user.keyboard( '[Space]' );
			await user.keyboard( '[ArrowDown]' );
			await user.keyboard( '{Escape}' );
			await settle();
			expect( optionValues( dialog ) ).toEqual( [
				'search',
				'social',
				'friend',
			] );
			expect( screen.getByRole( 'dialog' ) ).toBeInTheDocument();
		} );
	} );

	it( 'takes several defaults for a checkbox group', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Edit classic heard_from' );
		await user.selectOptions(
			within( dialog ).getByLabelText( 'Field type' ),
			'checkboxgroup'
		);
		const defaults = within( dialog ).getByRole( 'group', {
			name: 'Selected by default',
		} );
		await user.click(
			within( defaults ).getByRole( 'checkbox', {
				name: 'Search engine',
			} )
		);
		await user.click(
			within( defaults ).getByRole( 'checkbox', { name: 'A friend' } )
		);
		await user.click( screen.getByRole( 'button', { name: 'Apply' } ) );
		const saved = draft().classic.sections.additional[ 1 ];
		expect( saved.type ).toBe( 'checkboxgroup' );
		expect( saved.default ).toBe( 'search,friend' );
		expect( saved.placeholder ).toBe( '' );
	} );

	it( 'starts a new choice field with one empty option', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Add classic additional' );
		await user.selectOptions(
			within( dialog ).getByLabelText( 'Field type' ),
			'radio'
		);
		expect( optionValues( dialog ) ).toEqual( [ '' ] );
	} );
} );

describe( 'live preview', () => {
	it( 'updates as you type, with the required marker of the checkout type', async () => {
		const { user } = setup();
		await open( user, 'Add classic billing' );
		await user.type( screen.getByLabelText( 'Label' ), 'Door code' );
		await user.type( screen.getByLabelText( 'Placeholder' ), '1234' );

		expect(
			within( preview() ).getByText( 'Door code' )
		).toBeInTheDocument();
		expect(
			within( preview() ).getByText( '(optional)' )
		).toBeInTheDocument();
		expect(
			within( preview() ).getByPlaceholderText( '1234' )
		).toBeInTheDocument();

		await user.click( screen.getByRole( 'switch', { name: 'Required' } ) );
		expect(
			within( preview() ).queryByText( '(optional)' )
		).not.toBeInTheDocument();
		expect(
			within( preview() ).getByTitle( 'required' )
		).toHaveTextContent( '*' );
	} );

	it( 'shows every classic type', async () => {
		const { user } = setup();
		await open( user, 'Add classic additional' );
		await user.type( screen.getByLabelText( 'Label' ), 'Thing' );
		const typeSelect = screen.getByLabelText( 'Field type' );
		const types = within( typeSelect )
			.getAllByRole( 'option' )
			.map( ( option ) => ( option as HTMLOptionElement ).value );

		// What the shopper sees for each type: the control, and where the label is.
		const seen: Record< string, string > = {};
		for ( const type of types ) {
			await user.selectOptions( typeSelect, type );
			const shop = preview();
			const control = shop.querySelector( 'input, select, textarea' );
			const text = shop.querySelector( 'h2, h3, h4, h5, h6, p' );
			seen[ type ] = [
				control &&
					[
						control.tagName.toLowerCase(),
						control.getAttribute( 'type' ),
					]
						.filter( Boolean )
						.join( ':' ),
				text && `${ text.tagName.toLowerCase() }:${ text.textContent }`,
				within( shop ).queryByText( 'Thing' ) && 'label',
			]
				.filter( Boolean )
				.join( ' ' );
		}

		expect( seen ).toEqual( {
			text: 'input:text label',
			number: 'input:number label',
			email: 'input:email label',
			url: 'input:url label',
			tel: 'input:tel label',
			password: 'input:password label',
			hidden: 'p:Shoppers don’t see hidden fields.',
			textarea: 'textarea label',
			select: 'select label',
			multiselect: 'select label',
			radio: 'input:radio label',
			checkbox: 'input:checkbox label',
			checkboxgroup: 'input:checkbox label',
			date: 'input:date label',
			time: 'input:time label',
			'datetime-local': 'input:datetime-local label',
			month: 'input:month label',
			week: 'input:week label',
			heading: 'h3:Thing label',
			paragraph: 'p:Thing label',
		} );
	} );

	it( 'shows the block look, with options and the default selected', async () => {
		const { user } = setup();
		await open( user, 'Add block contact' );
		await user.type( screen.getByLabelText( 'Label' ), 'Colour' );
		await user.selectOptions(
			screen.getByLabelText( 'Field type' ),
			'radio'
		);
		await user.type(
			screen.getAllByRole( 'textbox', { name: /^Shown text/ } )[ 0 ],
			'Red'
		);
		await user.click(
			screen.getByRole( 'button', { name: 'Add option' } )
		);
		await user.type(
			screen.getAllByRole( 'textbox', { name: /^Shown text/ } )[ 1 ],
			'Blue'
		);
		await user.selectOptions(
			screen.getByLabelText( 'Selected by default' ),
			'blue'
		);

		const shop = preview();
		expect( shop ).toHaveClass( 'is-block' );
		expect(
			within( shop ).getByText( 'Colour (optional)' )
		).toBeInTheDocument();
		const radios = shop.querySelectorAll< HTMLInputElement >(
			'input[type="radio"]'
		);
		expect( radios ).toHaveLength( 2 );
		expect( radios[ 1 ] ).toBeChecked();

		await user.selectOptions(
			screen.getByLabelText( 'Field type' ),
			'heading'
		);
		await user.selectOptions(
			screen.getByLabelText( 'Heading level' ),
			'h5'
		);
		expect( preview().querySelector( 'h5' ) ).toHaveTextContent( 'Colour' );
	} );

	it( 'is hidden from assistive technology and out of the tab order', async () => {
		const { user } = setup();
		await open( user, 'Edit classic billing_vat' );
		expect(
			document.querySelector( '.cfk-editor-preview' )
		).toHaveAttribute( 'aria-hidden', 'true' );
		expect( preview().querySelector( 'input' ) ).toHaveAttribute(
			'tabindex',
			'-1'
		);
	} );
} );

describe( 'validation', () => {
	it( 'shows an inline error and a summary link as soon as the name is wrong', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Add classic billing' );
		await user.type(
			within( dialog ).getByLabelText( 'Label' ),
			'Heard from'
		);
		const name = within( dialog ).getByLabelText( 'Field name' );
		await user.clear( name );
		await user.type( name, 'billing_Heard-From' );

		expect( name ).toHaveAttribute( 'aria-invalid', 'true' );
		const message = within( dialog ).getByText(
			/The field name must start with a lower-case letter/
		);
		expect( message ).toHaveTextContent(
			'For example: billing_heard_from'
		);
		expect( name.getAttribute( 'aria-describedby' ) ).toContain(
			message.closest( '[id]' )?.id
		);

		const summary = within( dialog ).getByRole( 'group', {
			name: /Fix 1 problem before applying/,
		} );
		await user.click(
			within( summary ).getByRole( 'link', { name: 'Field name' } )
		);
		expect( name ).toHaveFocus();
	} );

	it( 'does not complain about an untouched new field until Apply', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Add classic additional' );
		expect(
			within( dialog ).queryByRole( 'group', { name: /Fix/ } )
		).not.toBeInTheDocument();

		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Apply' } )
		);
		const summary = within( dialog ).getByRole( 'group', {
			name: /Fix 1 problem before applying/,
		} );
		expect( summary ).toHaveFocus();
		expect(
			within( dialog ).getByLabelText( 'Field name' )
		).toHaveAttribute( 'aria-invalid', 'true' );
		expect( screen.getByRole( 'dialog' ) ).toBeInTheDocument();
		expect( draft().classic.sections.additional ).toHaveLength( 2 );
	} );

	it( 'refuses a name used elsewhere in the field set or reserved', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Add classic additional' );
		const name = within( dialog ).getByLabelText( 'Field name' );
		await user.type( name, 'billing_vat' );
		expect(
			within( dialog ).getByText(
				'Another field already uses this name. Field names must be unique.'
			)
		).toBeInTheDocument();
		await user.clear( name );
		await user.type( name, 'terms' );
		expect(
			within( dialog ).getByText(
				'WooCommerce uses this name for one of its own fields. Choose another name.'
			)
		).toBeInTheDocument();
	} );

	it( 'checks the default and the options', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Edit classic heard_from' );
		await user.type(
			within( dialog ).getByRole( 'textbox', {
				name: 'Saved value for A friend',
			} ),
			'{Backspace>6/}social'
		);
		expect(
			within( dialog ).getByText(
				'Two options have the same saved value. Each option needs its own.'
			)
		).toBeInTheDocument();
		expect(
			within( dialog ).getByRole( 'textbox', {
				name: 'Saved value for A friend',
			} )
		).toHaveAttribute( 'aria-invalid', 'true' );
		expect(
			within( dialog ).getByRole( 'textbox', {
				name: 'Saved value for Search engine',
			} )
		).not.toHaveAttribute( 'aria-invalid', 'true' );

		await user.click(
			within( dialog ).getByRole( 'button', { name: 'Apply' } )
		);
		expect(
			draft().classic.sections.additional[ 1 ].options[ 2 ].value
		).toBe( 'friend' );
	} );

	it( 'checks a number field’s default', async () => {
		const { user } = setup();
		const dialog = await open( user, 'Add classic additional' );
		await user.selectOptions(
			within( dialog ).getByLabelText( 'Field type' ),
			'number'
		);
		await user.type(
			within( dialog ).getByLabelText( 'Default value' ),
			'ten'
		);
		expect(
			within( dialog ).getByText(
				'The default of a number field must be a number.'
			)
		).toBeInTheDocument();
	} );

	it( 'shows what the server found at the last save', async () => {
		const data = editorData();
		const { user } = setup( data, {
			putFieldSet: jest.fn( async () => {
				throw {
					code: 'checkout_fieldkit_invalid_field_set',
					message: 'Invalid',
					data: {
						status: 422,
						errors: [
							{
								section: 'billing',
								index: 2,
								key: 'label',
								message: 'The server dislikes this label.',
							},
						],
					},
				};
			} ),
		} );
		await open( user, 'Edit classic billing_vat' );
		await user.type( screen.getByLabelText( 'Placeholder' ), 'X' );
		await user.click( screen.getByRole( 'button', { name: 'Apply' } ) );
		await user.click( screen.getByRole( 'button', { name: 'Save' } ) );
		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: 'Save' } )
			).toBeEnabled()
		);

		const dialog = await open( user, 'Edit classic billing_vat' );
		const summary = within( dialog ).getByRole( 'group', {
			name: /The last save found a problem/,
		} );
		expect(
			within( summary ).getByRole( 'link', { name: 'Label' } )
		).toBeInTheDocument();
		const label = within( dialog ).getByLabelText( 'Label' );
		expect( label ).toHaveAttribute( 'aria-invalid', 'true' );
		expect(
			within( dialog ).getAllByText( 'The server dislikes this label.' )
				.length
		).toBeGreaterThan( 0 );

		await user.type( label, '!' );
		expect( label ).not.toHaveAttribute( 'aria-invalid', 'true' );
	} );
} );

describe( 'apply, cancel and delete', () => {
	it( 'adds a new field at the end of its section', async () => {
		const { user } = setup();
		await open( user, 'Add classic billing' );
		await user.type( screen.getByLabelText( 'Label' ), 'Door code' );
		await user.click( screen.getByRole( 'button', { name: 'Apply' } ) );

		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		const billing = draft().classic.sections.billing;
		expect( billing ).toHaveLength( 4 );
		expect( billing[ 3 ] ).toMatchObject( {
			name: 'billing_door_code',
			label: 'Door code',
			type: 'text',
			builtIn: false,
		} );
		expect( screen.getByTestId( 'count' ) ).toHaveTextContent( '1' );
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'“Door code” added to your unsaved changes.'
		);
		expect(
			screen.getByRole( 'button', { name: 'Add classic billing' } )
		).toHaveFocus();
	} );

	it( 'writes the edits of an existing field', async () => {
		const { user } = setup();
		await open( user, 'Edit classic billing_vat' );
		await user.clear( screen.getByLabelText( 'Label' ) );
		await user.type( screen.getByLabelText( 'Label' ), 'Tax ID' );
		await user.clear( screen.getByLabelText( 'CSS classes' ) );
		await user.type(
			screen.getByLabelText( 'CSS classes' ),
			'form-row-first, vat'
		);
		await user.click(
			screen.getByRole( 'checkbox', { name: /Order emails/ } )
		);
		await user.click( screen.getByRole( 'button', { name: 'Apply' } ) );

		expect( draft().classic.sections.billing[ 2 ] ).toMatchObject( {
			name: 'billing_vat',
			label: 'Tax ID',
			classes: [ 'form-row-first', 'vat' ],
			showInEmails: false,
		} );
		expect( screen.getByTestId( 'count' ) ).toHaveTextContent( '1' );
	} );

	it( 'changes nothing when applied unchanged', async () => {
		const { user } = setup();
		await open( user, 'Edit classic heard_from' );
		await user.click( screen.getByRole( 'button', { name: 'Apply' } ) );
		expect( screen.getByTestId( 'count' ) ).toHaveTextContent( '0' );
	} );

	it( 'deletes a custom field and moves focus to the screen title', async () => {
		const { user } = setup();
		await open( user, 'Edit classic billing_vat' );
		await user.click(
			screen.getByRole( 'button', { name: 'Delete field' } )
		);

		expect( screen.queryByRole( 'dialog' ) ).not.toBeInTheDocument();
		expect(
			draft().classic.sections.billing.map( ( item ) => item.name )
		).toEqual( [ 'billing_first_name', 'billing_email' ] );
		expect(
			screen.getByRole( 'heading', { name: 'Checkout fields' } )
		).toHaveFocus();
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'“VAT number” deleted'
		);
	} );

	it( 'offers Delete only for custom fields already in the draft', async () => {
		const { user } = setup();
		await open( user, 'Add classic billing' );
		expect(
			screen.queryByRole( 'button', { name: 'Delete field' } )
		).not.toBeInTheDocument();
		expect(
			screen.getByText(
				'Apply adds this to your unsaved changes. Nothing reaches the checkout until you press Save changes.'
			)
		).toBeInTheDocument();
	} );

	it( 'opens another field when asked while open', async () => {
		const { user } = setup();
		await open( user, 'Edit classic billing_vat' );
		act( () => {
			// The list cannot be clicked behind the modal; this calls the API directly.
			screen
				.getByRole( 'button', {
					name: 'Edit classic heard_from',
					hidden: true,
				} )
				.click();
		} );
		expect( screen.getByLabelText( 'Label' ) ).toHaveValue(
			'How did you hear about us?'
		);
	} );
} );
