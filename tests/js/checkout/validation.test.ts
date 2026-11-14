/**
 * Client-side checks mirror the server's (Orders\ValueValidator), so the
 * shopper sees the same message before the order is sent.
 */
import { problem, isEmptyValue } from '../../../src/checkout/validation';
import type { CheckoutField } from '../../../src/checkout/types';

const field = ( extra: Partial< CheckoutField > = {} ): CheckoutField => ( {
	name: 'answer',
	type: 'text',
	label: 'Answer',
	classes: [],
	required: false,
	default: '',
	...extra,
} );

const choices = {
	options: [
		{ value: '', label: 'Choose' },
		{ value: 'a', label: 'A' },
		{ value: 'b', label: 'B' },
	],
};

describe( 'isEmptyValue', () => {
	it.each( [
		[ '', true ],
		[ '   ', true ],
		[ false, true ],
		[ 'x', false ],
		[ '0', false ],
		[ true, false ],
	] )( '%p is empty: %p', ( value, expected ) => {
		expect( isEmptyValue( value ) ).toBe( expected );
	} );
} );

describe( 'problem', () => {
	it.each( [
		[ 'text', 'Anything' ],
		[ 'number', '-4.5' ],
		[ 'number', '0' ],
		[ 'number', '1e3' ],
		[ 'email', 'ada@example.org' ],
		[ 'url', 'https://example.org/a?b=1' ],
		[ 'textarea', 'Two\nlines' ],
		[ 'text', '' ],
		[ 'email', '' ],
	] )( 'accepts a %s value %p', ( type, value ) => {
		expect(
			problem( field( { type: type as CheckoutField[ 'type' ] } ), value )
		).toBe( '' );
	} );

	it.each( [
		[ 'text', '' ],
		[ 'textarea', '  ' ],
		[ 'select', '' ],
		[ 'radio', '' ],
	] )( 'requires a value for a required %s', ( type, value ) => {
		expect(
			problem(
				field( {
					type: type as CheckoutField[ 'type' ],
					required: true,
					...choices,
				} ),
				value
			)
		).toBe( 'Answer is required.' );
	} );

	it( 'requires a required checkbox to be ticked', () => {
		const box = field( { type: 'checkbox', required: true } );

		expect( problem( box, false ) ).toBe( 'Answer is required.' );
		expect( problem( box, true ) ).toBe( '' );
	} );

	it.each( [
		[ 'number', '12 apples', 'Answer must be a number.' ],
		[ 'email', 'not-an-email', 'Answer is not a valid email address.' ],
		[
			'url',
			'example.org',
			'Answer is not a valid web address. Start it with http:// or https://.',
		],
		[
			'url',
			'ftp://example.org',
			'Answer is not a valid web address. Start it with http:// or https://.',
		],
	] )( 'checks a %s value %p', ( type, value, message ) => {
		expect(
			problem( field( { type: type as CheckoutField[ 'type' ] } ), value )
		).toBe( message );
	} );

	it.each( [
		[ 'phone', 'call me', 'Answer is not a valid phone number.' ],
		[ 'email', 'nope', 'Answer is not a valid email address.' ],
		[
			'url',
			'nope',
			'Answer is not a valid web address. Start it with http:// or https://.',
		],
		[ 'postcode', '12#45', 'Answer is not a valid postcode / ZIP.' ],
	] )( 'applies the %s validation', ( validation, value, message ) => {
		expect(
			problem( field( { validation: [ validation ] } ), value )
		).toBe( message );
	} );

	it( 'accepts phone numbers with the characters WooCommerce allows', () => {
		expect(
			problem(
				field( { validation: [ 'phone' ] } ),
				'+44 (0)1234 567-890'
			)
		).toBe( '' );
	} );

	it( 'checks postcodes for the billing country with WooCommerce', () => {
		const isPostcode = jest.fn(
			( { postcode, country }: { postcode: string; country: string } ) =>
				country === 'US' && /^\d{5}$/.test( postcode )
		);
		const zip = field( { validation: [ 'postcode' ] } );

		expect( problem( zip, '90210', { country: 'US', isPostcode } ) ).toBe(
			''
		);
		expect( problem( zip, '90210', { country: 'GB', isPostcode } ) ).toBe(
			'Answer is not a valid postcode / ZIP.'
		);
		expect( isPostcode ).toHaveBeenCalledWith( {
			postcode: '90210',
			country: 'GB',
		} );
	} );

	it( 'only accepts choices among the options', () => {
		expect( problem( field( { type: 'select', ...choices } ), 'z' ) ).toBe(
			'Answer: choose one of the options.'
		);
		expect( problem( field( { type: 'radio', ...choices } ), 'b' ) ).toBe(
			''
		);
	} );

	it( 'never checks display fields', () => {
		expect(
			problem( field( { type: 'heading', required: true } ), '' )
		).toBe( '' );
	} );

	it( 'names the field by its name when it has no label', () => {
		expect( problem( field( { label: '', required: true } ), '' ) ).toBe(
			'answer is required.'
		);
	} );
} );
