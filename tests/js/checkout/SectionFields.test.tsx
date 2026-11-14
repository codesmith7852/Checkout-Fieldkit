/**
 * The fields of one section as our inner blocks render them on the Block
 * checkout: every type, labels, required and optional, errors in WooCommerce's
 * validation store (which blocks Place order), and values sent with the order
 * as checkout extension data.
 */
import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import SectionFields from '../../../src/checkout/fields/SectionFields';
import type { CheckoutField } from '../../../src/checkout/types';
import { setTestSettings } from '../support/woocommerce/settings';
import {
	pressPlaceOrder,
	resetValidationStore,
	validationErrors,
} from '../support/validation-store';

const field = ( extra: Partial< CheckoutField > ): CheckoutField => ( {
	name: 'answer',
	type: 'text',
	label: 'Answer',
	classes: [],
	required: false,
	default: '',
	...extra,
} );

const SIZES = [
	{ value: '', label: 'Pick a size' },
	{ value: 's', label: 'Small' },
	{ value: 'm', label: 'Medium' },
];

function useFields(
	contact: CheckoutField[],
	additional: CheckoutField[] = []
): void {
	setTestSettings( {
		'checkout-fieldkit_data': {
			namespace: 'checkout-fieldkit',
			sections: { contact, additional },
		},
	} );
}

function renderSection(
	section: 'contact' | 'additional' = 'contact',
	extensionData: Record< string, Record< string, unknown > > = {}
) {
	const setExtensionData = jest.fn();
	const view = render(
		<SectionFields
			section={ section }
			checkoutExtensionData={ { extensionData, setExtensionData } }
		/>
	);

	return { ...view, setExtensionData };
}

beforeEach( () => {
	resetValidationStore();
} );

describe( 'SectionFields', () => {
	it( 'renders nothing for a section without fields', () => {
		useFields( [] );

		const { container } = renderSection();

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders every field of its section in order, and no other', () => {
		useFields(
			[
				field( { name: 'first', label: 'First' } ),
				field( { name: 'second', label: 'Second' } ),
			],
			[ field( { name: 'other', label: 'Other' } ) ]
		);

		renderSection();

		const boxes = screen.getAllByRole( 'textbox' );
		expect( boxes.map( ( box ) => box.id ) ).toEqual( [
			'checkout-fieldkit-first',
			'checkout-fieldkit-second',
		] );
		expect( screen.queryByLabelText( /Other/ ) ).not.toBeInTheDocument();
	} );

	it.each( [
		[ 'text', 'text' ],
		[ 'email', 'email' ],
		[ 'url', 'url' ],
		[ 'number', 'text' ],
	] )( 'renders a %s field as a labelled input', ( type, inputType ) => {
		useFields( [
			field( {
				type: type as CheckoutField[ 'type' ],
				required: true,
				label: 'Your answer',
			} ),
		] );

		renderSection();

		const input = screen.getByLabelText( 'Your answer' );
		expect( input ).toHaveAttribute( 'type', inputType );
		expect( input ).toBeRequired();
	} );

	it( 'gives a number field a decimal keyboard', () => {
		useFields( [ field( { type: 'number', label: 'Guests' } ) ] );

		renderSection();

		expect( screen.getByLabelText( 'Guests (optional)' ) ).toHaveAttribute(
			'inputmode',
			'decimal'
		);
	} );

	it( 'marks optional fields in the label', () => {
		useFields( [ field( { label: 'Nickname' } ) ] );

		renderSection();

		expect(
			screen.getByLabelText( 'Nickname (optional)' )
		).not.toBeRequired();
	} );

	it( 'renders a textarea with its label and placeholder', () => {
		useFields( [
			field( {
				type: 'textarea',
				label: 'Gift note',
				placeholder: 'Write something nice',
				required: true,
			} ),
		] );

		renderSection();

		const textarea = screen.getByRole( 'textbox', { name: 'Gift note' } );
		expect( textarea.tagName ).toBe( 'TEXTAREA' );
		expect( textarea ).toHaveAttribute(
			'placeholder',
			'Write something nice'
		);
		expect( textarea ).toBeRequired();
		expect( textarea ).toHaveClass( 'wc-block-components-textarea' );
	} );

	it( 'renders a select with its options and the empty prompt', () => {
		useFields( [
			field( { type: 'select', label: 'Size', options: SIZES } ),
		] );

		renderSection();

		const select = screen.getByRole( 'combobox', {
			name: 'Size (optional)',
		} );
		expect(
			within( select )
				.getAllByRole( 'option' )
				.map( ( option ) => option.textContent )
		).toEqual( [ 'Pick a size', 'Small', 'Medium' ] );
		expect( select ).toHaveValue( '' );
	} );

	it( 'gives a select without an empty option a placeholder prompt', () => {
		useFields( [
			field( {
				type: 'select',
				label: 'Size',
				placeholder: 'Choose',
				options: SIZES.slice( 1 ),
			} ),
		] );

		renderSection();

		expect(
			within( screen.getByRole( 'combobox' ) )
				.getAllByRole( 'option' )
				.map( ( option ) => option.textContent )
		).toEqual( [ 'Choose', 'Small', 'Medium' ] );
	} );

	it( 'renders radio buttons in a group named by the label', () => {
		useFields( [
			field( {
				type: 'radio',
				label: 'Wrapping',
				required: true,
				options: [
					{ value: 'plain', label: 'Plain' },
					{ value: 'gold', label: 'Gold' },
				],
				default: 'gold',
			} ),
		] );

		renderSection();

		const group = screen.getByRole( 'radiogroup', { name: 'Wrapping' } );
		expect( within( group ).getByLabelText( 'Plain' ) ).not.toBeChecked();
		expect( within( group ).getByLabelText( 'Gold' ) ).toBeChecked();
	} );

	it( 'renders a checkbox ticked by its default', () => {
		useFields( [
			field( { type: 'checkbox', label: 'Send me news', default: '1' } ),
		] );

		renderSection();

		expect(
			screen.getByRole( 'checkbox', { name: 'Send me news (optional)' } )
		).toBeChecked();
	} );

	it( 'renders headings at their level and paragraphs as text', () => {
		useFields( [
			field( {
				type: 'heading',
				label: 'About you',
				headingLevel: 'h4',
			} ),
			field( {
				name: 'intro',
				type: 'paragraph',
				label: 'We ask so we can help.',
			} ),
		] );

		renderSection();

		expect(
			screen.getByRole( 'heading', { level: 4, name: 'About you' } )
		).toBeInTheDocument();
		expect( screen.getByText( 'We ask so we can help.' ).tagName ).toBe(
			'P'
		);
	} );

	it( 'puts the field classes on the field wrapper', () => {
		useFields( [ field( { classes: [ 'wide', 'highlight' ] } ) ] );

		const { container } = renderSection();

		expect(
			container.querySelector( '.cfk-checkout-field--text' )
		).toHaveClass( 'wide', 'highlight' );
	} );

	it( 'sends the defaults with the order from the start', () => {
		useFields(
			[],
			[
				field( { name: 'note', default: 'Hello' } ),
				field( { name: 'news', type: 'checkbox', default: '1' } ),
				field( { name: 'title', type: 'heading', label: 'Title' } ),
			]
		);

		const { setExtensionData } = renderSection( 'additional' );

		expect( setExtensionData ).toHaveBeenCalledWith(
			'checkout-fieldkit',
			'note',
			'Hello'
		);
		expect( setExtensionData ).toHaveBeenCalledWith(
			'checkout-fieldkit',
			'news',
			true
		);
		expect( setExtensionData ).not.toHaveBeenCalledWith(
			'checkout-fieldkit',
			'title',
			expect.anything()
		);
	} );

	it( 'keeps values already in the checkout when it renders again', () => {
		useFields( [ field( { name: 'nickname', default: 'Default' } ) ] );

		renderSection( 'contact', {
			'checkout-fieldkit': { nickname: 'Ada' },
		} );

		expect( screen.getByRole( 'textbox' ) ).toHaveValue( 'Ada' );
	} );

	it( 'sends every change of every type with the order', async () => {
		const user = userEvent.setup();
		useFields( [
			field( { name: 'nickname', label: 'Nickname' } ),
			field( { name: 'note', type: 'textarea', label: 'Note' } ),
			field( {
				name: 'size',
				type: 'select',
				label: 'Size',
				options: SIZES,
			} ),
			field( {
				name: 'wrap',
				type: 'radio',
				label: 'Wrap',
				options: [
					{ value: 'plain', label: 'Plain' },
					{ value: 'gold', label: 'Gold' },
				],
			} ),
			field( { name: 'news', type: 'checkbox', label: 'News' } ),
		] );
		const { setExtensionData } = renderSection();

		await user.type(
			screen.getByLabelText( 'Nickname (optional)' ),
			'Ada'
		);
		await user.type( screen.getByLabelText( 'Note (optional)' ), 'Hi' );
		await user.selectOptions( screen.getByRole( 'combobox' ), 'm' );
		await user.click( screen.getByLabelText( 'Gold' ) );
		await user.click( screen.getByLabelText( 'News (optional)' ) );

		expect( setExtensionData ).toHaveBeenCalledWith(
			'checkout-fieldkit',
			'nickname',
			'Ada'
		);
		expect( setExtensionData ).toHaveBeenCalledWith(
			'checkout-fieldkit',
			'note',
			'Hi'
		);
		expect( setExtensionData ).toHaveBeenCalledWith(
			'checkout-fieldkit',
			'size',
			'm'
		);
		expect( setExtensionData ).toHaveBeenCalledWith(
			'checkout-fieldkit',
			'wrap',
			'gold'
		);
		expect( setExtensionData ).toHaveBeenLastCalledWith(
			'checkout-fieldkit',
			'news',
			true
		);
		expect( screen.getByLabelText( 'Nickname (optional)' ) ).toHaveValue(
			'Ada'
		);
	} );

	describe( 'validation', () => {
		const requiredOfEachKind = () => [
			field( { name: 'nickname', label: 'Nickname', required: true } ),
			field( {
				name: 'note',
				type: 'textarea',
				label: 'Note',
				required: true,
			} ),
			field( {
				name: 'size',
				type: 'select',
				label: 'Size',
				required: true,
				options: SIZES,
			} ),
			field( {
				name: 'wrap',
				type: 'radio',
				label: 'Wrap',
				required: true,
				options: [
					{ value: 'plain', label: 'Plain' },
					{ value: 'gold', label: 'Gold' },
				],
			} ),
			field( {
				name: 'terms',
				type: 'checkbox',
				label: 'I agree',
				required: true,
			} ),
		];

		it( 'puts hidden errors for empty required fields in WooCommerce’s validation store, which blocks Place order', () => {
			useFields( requiredOfEachKind() );

			renderSection();

			expect( Object.keys( validationErrors() ).sort() ).toEqual( [
				'checkout-fieldkit-nickname',
				'checkout-fieldkit-note',
				'checkout-fieldkit-size',
				'checkout-fieldkit-terms',
				'checkout-fieldkit-wrap',
			] );
			expect(
				Object.values( validationErrors() ).every(
					( error ) => error.hidden
				)
			).toBe( true );
			expect( screen.queryByRole( 'alert' ) ).not.toBeInTheDocument();

			let canPlace = true;
			act( () => {
				canPlace = pressPlaceOrder();
			} );
			expect( canPlace ).toBe( false );
		} );

		it( 'shows every error, linked to its input, once Place order is pressed', () => {
			useFields( requiredOfEachKind() );
			renderSection();

			act( () => {
				pressPlaceOrder();
			} );

			for ( const [ label, message ] of [
				[ 'Note', 'Note is required.' ],
				[ 'Size', 'Size is required.' ],
				[ 'I agree', 'I agree is required.' ],
			] ) {
				const input = screen.getByLabelText( label );
				expect( input ).toHaveAttribute( 'aria-invalid', 'true' );
				expect( input ).toHaveAccessibleDescription( message );
			}
			expect(
				screen.getByRole( 'radiogroup', { name: 'Wrap' } )
			).toHaveAccessibleDescription( 'Wrap is required.' );
			expect( screen.getByLabelText( 'Nickname' ) ).toHaveAttribute(
				'aria-invalid',
				'true'
			);
			expect(
				screen.getByText( 'Nickname is required.' )
			).toBeInTheDocument();
		} );

		it( 'clears the errors when the fields are filled in, unblocking Place order', async () => {
			const user = userEvent.setup();
			useFields( requiredOfEachKind() );
			renderSection();

			await user.type( screen.getByLabelText( 'Nickname' ), 'Ada' );
			await user.type( screen.getByLabelText( 'Note' ), 'Hi' );
			await user.selectOptions( screen.getByLabelText( 'Size' ), 's' );
			await user.click( screen.getByLabelText( 'Plain' ) );
			await user.click( screen.getByLabelText( 'I agree' ) );

			expect( validationErrors() ).toEqual( {} );
			let canPlace = false;
			act( () => {
				canPlace = pressPlaceOrder();
			} );
			expect( canPlace ).toBe( true );
		} );

		it.each( [
			[
				'email',
				'not-an-email',
				'Contact is not a valid email address.',
			],
			[
				'url',
				'example.org',
				'Contact is not a valid web address. Start it with http:// or https://.',
			],
			[ 'number', 'twelve', 'Contact must be a number.' ],
		] )(
			'shows an invalid %s value’s message when the shopper leaves the field',
			async ( type, value, message ) => {
				const user = userEvent.setup();
				useFields( [
					field( {
						type: type as CheckoutField[ 'type' ],
						label: 'Contact',
					} ),
				] );
				renderSection();

				await user.type(
					screen.getByLabelText( 'Contact (optional)' ),
					value
				);
				await user.tab();

				expect( screen.getByText( message ) ).toBeInTheDocument();
				expect(
					screen.getByLabelText( 'Contact (optional)' )
				).toHaveAttribute( 'aria-invalid', 'true' );
			}
		);

		it( 'checks a phone validation on a text field', async () => {
			const user = userEvent.setup();
			useFields( [
				field( { label: 'Gate phone', validation: [ 'phone' ] } ),
			] );
			renderSection();

			await user.type(
				screen.getByLabelText( 'Gate phone (optional)' ),
				'call me'
			);
			await user.tab();

			expect(
				screen.getByText( 'Gate phone is not a valid phone number.' )
			).toBeInTheDocument();
		} );

		it( 'shows a textarea’s error once the shopper leaves it', async () => {
			const user = userEvent.setup();
			useFields( [
				field( { type: 'textarea', label: 'Note', required: true } ),
			] );
			renderSection();

			await user.click( screen.getByLabelText( 'Note' ) );
			expect( screen.queryByRole( 'alert' ) ).not.toBeInTheDocument();
			await user.tab();

			expect( screen.getByRole( 'alert' ) ).toHaveTextContent(
				'Note is required.'
			);
		} );

		it( 'removes its errors when it is no longer on the page', () => {
			useFields( requiredOfEachKind() );
			const { unmount } = renderSection();

			unmount();

			expect( validationErrors() ).toEqual( {} );
		} );
	} );
} );
