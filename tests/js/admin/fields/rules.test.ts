/**
 * The Fields screen's rules (spec §2.3, §6.2): where a copied field lands and
 * when a copy is refused, essential fields, and built-in locks.
 */
import {
	checkCopy,
	copyTargetSection,
	essentialConsequence,
	isLocked,
	otherCheckoutType,
	takesRequired,
} from '../../../../src/admin/screens/fields/rules';
import { blockFieldSet, classicFieldSet, field } from '../fixtures';
import { fieldsContext } from './fixtures';

const context = fieldsContext();

describe( 'otherCheckoutType', () => {
	it( 'swaps classic and block', () => {
		expect( otherCheckoutType( 'classic' ) ).toBe( 'block' );
		expect( otherCheckoutType( 'block' ) ).toBe( 'classic' );
	} );
} );

describe( 'copyTargetSection', () => {
	it.each( [
		[ 'classic', 'billing', 'address' ],
		[ 'classic', 'shipping', 'address' ],
		[ 'classic', 'additional', 'additional' ],
		[ 'block', 'contact', 'billing' ],
		[ 'block', 'additional', 'additional' ],
		[ 'block', 'address', 'billing' ],
	] as const )( '%s %s goes to %s', ( from, section, expected ) => {
		expect( copyTargetSection( context, from, section ) ).toBe( expected );
	} );

	it( 'prefers a same-named section on the other checkout', () => {
		const withShipping = fieldsContext( {
			sections: {
				...context.sections,
				block: [
					...context.sections.block,
					{ name: 'shipping', label: 'Shipping' },
				],
			},
		} );
		expect( copyTargetSection( withShipping, 'classic', 'shipping' ) ).toBe(
			'shipping'
		);
	} );
} );

describe( 'checkCopy', () => {
	const gift = field( {
		name: 'gift_note',
		label: 'Gift note',
		type: 'textarea',
	} );

	it( 'copies a custom field into the mapped section', () => {
		const result = checkCopy(
			context,
			'classic',
			'additional',
			gift,
			blockFieldSet()
		);
		expect( result ).toEqual( {
			ok: true,
			section: 'additional',
			sectionLabel: 'Additional',
			field: gift,
		} );
	} );

	it( 'refuses built-in fields', () => {
		const result = checkCopy(
			context,
			'block',
			'address',
			blockFieldSet().sections.address[ 1 ],
			classicFieldSet()
		);
		expect( result ).toEqual( {
			ok: false,
			reason: expect.stringContaining( 'Company is built in' ),
		} );
	} );

	it( 'refuses a type the section on the other checkout does not allow', () => {
		const result = checkCopy(
			context,
			'classic',
			'billing',
			field( {
				name: 'delivery_notes',
				label: 'Notes',
				type: 'textarea',
			} ),
			blockFieldSet()
		);
		expect( result ).toEqual( {
			ok: false,
			reason: "Textarea fields can't go in the Block checkout's Address section.",
		} );
	} );

	it( 'refuses a type the other checkout does not have', () => {
		const result = checkCopy(
			context,
			'classic',
			'additional',
			field( { name: 'delivery_date', label: 'Delivery', type: 'date' } ),
			blockFieldSet()
		);
		expect( result ).toEqual( {
			ok: false,
			reason: 'The Block checkout has no Date fields.',
		} );
	} );

	it( 'refuses a name that is taken anywhere in the other field set', () => {
		const target = blockFieldSet();
		target.sections.contact.push( field( { name: 'gift_note' } ) );
		const result = checkCopy(
			context,
			'classic',
			'additional',
			gift,
			target
		);
		expect( result ).toEqual( {
			ok: false,
			reason: 'The Block checkout already has a field named gift_note.',
		} );
	} );

	it( 'refuses a name WooCommerce reserves', () => {
		const result = checkCopy(
			context,
			'classic',
			'additional',
			field( { name: 'terms', label: 'Terms' } ),
			blockFieldSet()
		);
		expect( result ).toEqual( {
			ok: false,
			reason: 'WooCommerce reserves the name terms.',
		} );
	} );

	it( 'drops validations the other checkout does not offer', () => {
		const result = checkCopy(
			context,
			'classic',
			'additional',
			field( {
				name: 'guests',
				label: 'Guests',
				validation: [ 'number', 'phone' ],
			} ),
			blockFieldSet()
		);
		expect( result.ok && result.field.validation ).toEqual( [ 'phone' ] );
	} );
} );

describe( 'essentialConsequence', () => {
	it( 'gives the consequence of an essential built-in field', () => {
		expect(
			essentialConsequence(
				context,
				'classic',
				classicFieldSet().sections.billing[ 1 ]
			)
		).toBe( 'Customers get no order emails.' );
	} );

	it( 'is null for other fields', () => {
		expect(
			essentialConsequence(
				context,
				'classic',
				classicFieldSet().sections.billing[ 0 ]
			)
		).toBeNull();
		expect(
			essentialConsequence(
				context,
				'block',
				field( { name: 'country', builtIn: false } )
			)
		).toBeNull();
	} );
} );

describe( 'isLocked', () => {
	const [ email ] = blockFieldSet().sections.contact;
	const [ country ] = blockFieldSet().sections.address;

	it( 'locks settings missing from a built-in field’s editable list', () => {
		expect( isLocked( context, 'block', email, 'enabled' ) ).toBe( true );
		expect( isLocked( context, 'block', email, 'required' ) ).toBe( true );
		expect( isLocked( context, 'block', country, 'required' ) ).toBe(
			false
		);
	} );

	it( 'never locks custom fields or built-ins the context does not list', () => {
		expect(
			isLocked(
				context,
				'block',
				field( { name: 'email', builtIn: false } ),
				'enabled'
			)
		).toBe( false );
		expect(
			isLocked(
				context,
				'classic',
				classicFieldSet().sections.shipping[ 0 ],
				'enabled'
			)
		).toBe( false );
	} );
} );

describe( 'takesRequired', () => {
	it( 'is false for display fields and true for value fields', () => {
		expect(
			takesRequired( context, 'block', field( { type: 'heading' } ) )
		).toBe( false );
		expect(
			takesRequired( context, 'block', field( { type: 'text' } ) )
		).toBe( true );
	} );

	it( 'is true for types the context does not describe', () => {
		expect(
			takesRequired( context, 'block', field( { type: 'mystery' } ) )
		).toBe( true );
	} );
} );
