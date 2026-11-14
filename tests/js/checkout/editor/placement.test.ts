/**
 * Where the editor puts the Additional block in an existing Checkout page:
 * the same place the server uses when rendering a page without it.
 */
import {
	additionalBlockIndex,
	placeAdditionalBlock,
} from '../../../../src/checkout/editor/placement';

const OURS = 'checkout-fieldkit/additional-fields';
const DEFAULT_FORM = [
	'woocommerce/checkout-express-payment-block',
	'woocommerce/checkout-contact-information-block',
	'woocommerce/checkout-shipping-address-block',
	'woocommerce/checkout-billing-address-block',
	'woocommerce/checkout-shipping-methods-block',
	'woocommerce/checkout-payment-block',
	'woocommerce/checkout-additional-information-block',
	'woocommerce/checkout-order-note-block',
	'woocommerce/checkout-terms-block',
	'woocommerce/checkout-actions-block',
];

describe( 'additionalBlockIndex', () => {
	it( 'is right after the Additional information block', () => {
		expect( additionalBlockIndex( DEFAULT_FORM, OURS ) ).toBe( 7 );
	} );

	it.each( [
		[
			'the order notes',
			[ 'payment', 'order-note', 'terms', 'actions' ],
			1,
		],
		[ 'the terms', [ 'payment', 'terms', 'actions' ], 1 ],
		[ 'Place order', [ 'payment', 'actions' ], 1 ],
	] )(
		'without the Additional information block, it is before %s',
		( _place, blocks, index ) => {
			const names = blocks.map(
				( block ) => `woocommerce/checkout-${ block }-block`
			);

			expect( additionalBlockIndex( names, OURS ) ).toBe( index );
		}
	);

	it( 'is at the end when none of those blocks is there', () => {
		expect( additionalBlockIndex( [ 'a', 'b' ], OURS ) ).toBe( 2 );
	} );

	it( 'is nothing when the form has the block already, wherever it is', () => {
		expect(
			additionalBlockIndex( [ OURS, ...DEFAULT_FORM ], OURS )
		).toBeNull();
	} );
} );

describe( 'placeAdditionalBlock', () => {
	it( 'inserts the block into each checkout form that lacks it, once', () => {
		const children: Record< string, string[] > = {
			form: [ ...DEFAULT_FORM ],
		};
		const insert = jest.fn(
			( name: string, index: number, parent: string ) => {
				children[ parent ].splice( index, 0, name );
			}
		);
		const editor = {
			formIds: () => Object.keys( children ),
			childNames: ( id: string ) => children[ id ],
			insert,
		};

		expect( placeAdditionalBlock( editor, OURS ) ).toBe( true );
		expect( placeAdditionalBlock( editor, OURS ) ).toBe( false );

		expect( insert ).toHaveBeenCalledTimes( 1 );
		expect( children.form.indexOf( OURS ) ).toBe(
			children.form.indexOf(
				'woocommerce/checkout-additional-information-block'
			) + 1
		);
	} );

	it( 'does nothing without a checkout form', () => {
		const insert = jest.fn();

		expect(
			placeAdditionalBlock(
				{ formIds: () => [], childNames: () => [], insert },
				OURS
			)
		).toBe( false );
		expect( insert ).not.toHaveBeenCalled();
	} );
} );
