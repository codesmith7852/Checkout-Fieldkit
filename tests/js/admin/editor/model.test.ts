/**
 * The editor's pure rules: name suggestion, which settings apply, which
 * types a section allows, preparing a field for the draft, and the client
 * mirror of spec §2.5 (FieldSetValidator.php).
 */
import {
	applicableSettings,
	namePrefix,
	prepareField,
	suggestName,
	typeChoices,
	validateField,
	type ValidationInput,
} from '../../../../src/admin/editor/model';
import type { Field } from '../../../../src/admin/types';
import { field } from '../fixtures';
import { editorContext } from './fixtures';

const context = editorContext();

describe( 'suggestName', () => {
	it( 'turns a label into a field name', () => {
		expect( suggestName( 'How did you hear about us?' ) ).toBe(
			'how_did_you_hear_about_us'
		);
	} );

	it( 'drops accents and collapses separators', () => {
		expect( suggestName( '  Café — Größe / Taille  ' ) ).toBe(
			'cafe_grosse_taille'
		);
	} );

	it( 'adds the section prefix', () => {
		expect( suggestName( 'Heard from', { prefix: 'billing_' } ) ).toBe(
			'billing_heard_from'
		);
	} );

	it( 'does not repeat a prefix the label already gives', () => {
		expect( suggestName( 'Billing VAT', { prefix: 'billing_' } ) ).toBe(
			'billing_vat'
		);
	} );

	it( 'starts with a letter', () => {
		expect( suggestName( '2nd address' ) ).toBe( 'field_2nd_address' );
	} );

	it( 'is empty for a label with nothing usable', () => {
		expect( suggestName( '???' ) ).toBe( '' );
		expect( suggestName( '???', { prefix: 'billing_' } ) ).toBe( '' );
	} );

	it( 'keeps to 60 characters', () => {
		const name = suggestName( 'a'.repeat( 80 ), { prefix: 'shipping_' } );
		expect( name ).toHaveLength( 60 );
		expect( name.startsWith( 'shipping_' ) ).toBe( true );
	} );

	it( 'adds a number when the name is taken or reserved', () => {
		const taken = new Set( [ 'gift', 'gift_2' ] );
		expect( suggestName( 'Gift', { taken } ) ).toBe( 'gift_3' );
		expect(
			suggestName( 'Email', {
				prefix: 'billing_',
				taken: new Set( [ 'billing_email' ] ),
			} )
		).toBe( 'billing_email_2' );
	} );
} );

describe( 'namePrefix', () => {
	it( 'is billing_ or shipping_ on the classic Billing and Shipping sections only', () => {
		expect( namePrefix( 'classic', 'billing' ) ).toBe( 'billing_' );
		expect( namePrefix( 'classic', 'shipping' ) ).toBe( 'shipping_' );
		expect( namePrefix( 'classic', 'additional' ) ).toBe( '' );
		expect( namePrefix( 'block', 'address' ) ).toBe( '' );
	} );
} );

describe( 'applicableSettings', () => {
	it.each( [
		[
			'text',
			[
				'label',
				'placeholder',
				'default',
				'required',
				'validation',
				'classes',
				'showInEmails',
				'showOnOrderPages',
			],
		],
		[
			'hidden',
			[
				'label',
				'default',
				'classes',
				'showInEmails',
				'showOnOrderPages',
			],
		],
		[
			'radio',
			[
				'label',
				'options',
				'default',
				'required',
				'classes',
				'showInEmails',
				'showOnOrderPages',
			],
		],
		[ 'heading', [ 'label', 'headingLevel', 'classes' ] ],
		[ 'paragraph', [ 'label', 'classes' ] ],
	] )( 'gives the settings of a custom %s field', ( type, expected ) => {
		expect(
			applicableSettings( context, 'classic', field( { type } ) )
		).toEqual( expected );
	} );

	it( 'gives a built-in field only what is ours to change and applies to its type', () => {
		expect(
			applicableSettings(
				context,
				'classic',
				field( {
					name: 'order_comments',
					type: 'textarea',
					builtIn: true,
				} )
			)
		).toEqual( [
			'label',
			'placeholder',
			'default',
			'required',
			'classes',
		] );
		expect(
			applicableSettings(
				context,
				'block',
				field( { name: 'country', type: 'country', builtIn: true } )
			)
		).toEqual( [ 'label', 'required' ] );
		expect(
			applicableSettings(
				context,
				'block',
				field( { name: 'email', type: 'email', builtIn: true } )
			)
		).toEqual( [] );
	} );
} );

describe( 'typeChoices', () => {
	it( 'offers every custom type, never the built-in-only ones', () => {
		const names = typeChoices( context, 'classic', 'billing' ).map(
			( choice ) => choice.name
		);
		expect( names ).toHaveLength( 20 );
		expect( names ).not.toContain( 'country' );
	} );

	it( 'marks types the section does not allow, with the reason', () => {
		const choices = typeChoices( context, 'block', 'address' );
		expect( choices ).toHaveLength( 10 );
		const radio = choices.find( ( choice ) => choice.name === 'radio' );
		expect( radio?.allowed ).toBe( false );
		expect( radio?.reason ).toBe(
			'Radio buttons fields cannot be used in the Address section.'
		);
		expect(
			choices.find( ( choice ) => choice.name === 'select' )?.allowed
		).toBe( true );
	} );
} );

describe( 'prepareField', () => {
	it( 'clears settings that do not apply to a custom field’s type', () => {
		const prepared = prepareField(
			context,
			'classic',
			field( {
				type: 'heading',
				label: ' Delivery ',
				placeholder: 'x',
				default: 'y',
				required: true,
				validation: [ 'email' ],
				options: [ { value: 'a', label: 'A' } ],
				headingLevel: 'h4',
				showInEmails: false,
			} )
		);
		expect( prepared ).toEqual(
			field( { type: 'heading', label: 'Delivery', headingLevel: 'h4' } )
		);
	} );

	it( 'keeps a built-in field’s locked settings as they were', () => {
		const original = field( {
			name: 'country',
			type: 'country',
			builtIn: true,
			placeholder: 'Choose',
			classes: [ 'wide' ],
		} );
		const prepared = prepareField(
			context,
			'block',
			{ ...original, label: 'Land', placeholder: 'changed', classes: [] },
			original
		);
		expect( prepared ).toEqual( { ...original, label: 'Land' } );
	} );

	it( 'stores a checkbox default as "1" or ""', () => {
		expect(
			prepareField(
				context,
				'classic',
				field( { type: 'checkbox', default: 'yes' } )
			).default
		).toBe( '1' );
	} );

	it( 'trims options and the default of a multiple choice field', () => {
		const prepared = prepareField(
			context,
			'classic',
			field( {
				type: 'checkboxgroup',
				options: [
					{ value: ' a ', label: ' A ' },
					{ value: 'b', label: 'B' },
				],
				default: 'a, b,,a',
			} )
		);
		expect( prepared.options ).toEqual( [
			{ value: 'a', label: 'A' },
			{ value: 'b', label: 'B' },
		] );
		expect( prepared.default ).toBe( 'a,b' );
	} );
} );

describe( 'validateField', () => {
	function input(
		overrides: Partial< ValidationInput > = {}
	): ValidationInput {
		return {
			context,
			checkoutType: 'classic',
			section: 'additional',
			field: field( { name: 'gift', label: 'Gift' } ),
			otherNames: [ 'billing_email', 'billing_vat' ],
			...overrides,
		};
	}
	const errors = ( overrides: Partial< ValidationInput > = {} ) =>
		validateField( input( overrides ) );
	const withField = ( changes: Partial< Field > ) =>
		errors( { field: field( { name: 'gift', ...changes } ) } );

	it( 'accepts a good field', () => {
		expect( errors() ).toEqual( [] );
	} );

	it.each( [ '', 'Gift', '1gift', 'gift-note', 'a'.repeat( 61 ) ] )(
		'refuses the name %p',
		( name ) => {
			expect( withField( { name } ) ).toEqual( [
				{
					key: 'name',
					message:
						'The field name must start with a lower-case letter and use only lower-case letters, digits and underscores, up to 60 characters.',
				},
			] );
		}
	);

	it( 'refuses a name another field uses', () => {
		expect( withField( { name: 'billing_vat' } ) ).toEqual( [
			{
				key: 'name',
				message:
					'Another field already uses this name. Field names must be unique.',
			},
		] );
	} );

	it( 'refuses a reserved name for a custom field', () => {
		expect( withField( { name: 'shipping_method' } ) ).toEqual( [
			{
				key: 'name',
				message:
					'WooCommerce uses this name for one of its own fields. Choose another name.',
			},
		] );
	} );

	it( 'lets a built-in field keep its reserved name', () => {
		expect(
			errors( {
				section: 'billing',
				field: field( {
					name: 'billing_email',
					type: 'email',
					builtIn: true,
				} ),
				otherNames: [ 'billing_vat' ],
			} )
		).toEqual( [] );
	} );

	it( 'refuses a type the section does not allow', () => {
		expect(
			errors( {
				checkoutType: 'block',
				section: 'address',
				field: field( {
					name: 'gift',
					type: 'radio',
					options: [ { value: 'a', label: 'A' } ],
				} ),
			} )
		).toEqual( [
			{
				key: 'type',
				message:
					'Radio buttons fields cannot be used in the Address section.',
			},
		] );
	} );

	it( 'refuses a type the checkout does not have', () => {
		expect(
			errors( {
				checkoutType: 'block',
				section: 'contact',
				field: field( { name: 'gift', type: 'week' } ),
			} )
		).toEqual( [
			{
				key: 'type',
				message: '"week" is not a field type this checkout supports.',
			},
		] );
	} );

	it( 'refuses a default that is not among the options', () => {
		expect(
			withField( {
				type: 'select',
				options: [ { value: 'a', label: 'A' } ],
				default: 'b',
			} )
		).toEqual( [
			{
				key: 'default',
				message: 'The default must be one of the option values.',
			},
		] );
		expect(
			withField( {
				type: 'checkboxgroup',
				options: [
					{ value: 'a', label: 'A' },
					{ value: 'b', label: 'B' },
				],
				default: 'a,c',
			} )
		).toHaveLength( 1 );
		expect(
			withField( {
				type: 'checkboxgroup',
				options: [
					{ value: 'a', label: 'A' },
					{ value: 'b', label: 'B' },
				],
				default: 'a,b',
			} )
		).toEqual( [] );
	} );

	it( 'refuses a number field default that is not a number', () => {
		expect( withField( { type: 'number', default: '12a' } ) ).toEqual( [
			{
				key: 'default',
				message: 'The default of a number field must be a number.',
			},
		] );
		[ '12', '-1.5', '.5', '1e3', '+3' ].forEach( ( value ) =>
			expect( withField( { type: 'number', default: value } ) ).toEqual(
				[]
			)
		);
	} );

	it( 'needs every option to have a saved value', () => {
		expect(
			withField( {
				type: 'radio',
				options: [
					{ value: 'a', label: 'A' },
					{ value: '', label: 'B' },
				],
			} )
		).toEqual( [
			{ key: 'options', message: 'Every option needs a saved value.' },
		] );
		expect(
			withField( {
				type: 'select',
				options: [
					{ value: 'a', label: 'A' },
					{ value: '', label: 'B' },
				],
			} )
		).toEqual( [
			{
				key: 'options',
				message:
					'Every option needs a saved value. Only the first option of a dropdown may be empty, as a prompt.',
			},
		] );
	} );

	it( 'lets a dropdown’s first option be an empty prompt', () => {
		expect(
			withField( {
				type: 'select',
				options: [
					{ value: '', label: 'Choose…' },
					{ value: 'a', label: 'A' },
				],
			} )
		).toEqual( [] );
	} );

	it( 'needs option values to be unique', () => {
		expect(
			withField( {
				type: 'radio',
				options: [
					{ value: 'a', label: 'A' },
					{ value: 'a', label: 'B' },
				],
			} )
		).toEqual( [
			{
				key: 'options',
				message:
					'Two options have the same saved value. Each option needs its own.',
			},
		] );
	} );

	it( 'needs at least one option', () => {
		expect( withField( { type: 'radio', options: [] } ) ).toEqual( [
			{ key: 'options', message: 'Add at least one option.' },
		] );
		expect(
			withField( {
				type: 'select',
				options: [ { value: '', label: 'Choose' } ],
			} )
		).toEqual( [
			{ key: 'options', message: 'Add at least one option.' },
		] );
	} );

	it( 'reports several problems at once, one per key', () => {
		expect(
			withField( {
				name: 'Bad',
				type: 'select',
				options: [
					{ value: 'a', label: 'A' },
					{ value: 'a', label: 'A' },
				],
				default: 'z',
			} ).map( ( error ) => error.key )
		).toEqual( [ 'name', 'default', 'options' ] );
	} );
} );
