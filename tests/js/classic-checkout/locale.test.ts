/**
 * Locale overrides applied in the browser after a country change.
 */
import {
	applyLocaleOverrides,
	countryRules,
	effective,
	type Locale,
	type OurField,
	type PriorityStore,
	type ScriptData,
	type Switches,
} from '../../../src/classic-checkout/locale';

const allOff: Switches = {
	label: false,
	placeholder: false,
	order: false,
	required: false,
	classes: false,
};
const allOn: Switches = {
	label: true,
	placeholder: true,
	order: true,
	required: true,
	classes: true,
};

const postcode: OurField = {
	label: 'Postal area',
	placeholder: 'e.g. AB1 2CD',
	required: true,
	priority: 90,
	classes: [ 'form-row-wide', 'address-field' ],
	changed: [ 'label', 'placeholder', 'order', 'required', 'classes' ],
};

const locale: Locale = {
	default: {
		postcode: { label: 'Postcode / ZIP', required: true, priority: 90 },
	},
	GB: { state: { label: 'County', required: false } },
	IN: { postcode: { label: 'PIN Code' }, state: { label: 'State' } },
	JP: {
		postcode: {
			class: [ 'form-row-first', 'address-field' ],
			priority: 65,
		},
		city: { priority: 67 },
	},
	AE: { postcode: { required: false, hidden: true } },
	MX: { postcode: { label: 'Postcode / ZIP', required: true, priority: 90 } },
};

function mapStore(): PriorityStore & { values: Map< HTMLElement, number > } {
	const values = new Map< HTMLElement, number >();
	return {
		values,
		get: ( row ) =>
			values.get( row ) ??
			( Number( row.dataset.priority ) || undefined ),
		set: ( row, priority ) => {
			values.set( row, priority );
		},
	};
}

describe( 'countryRules', () => {
	it( 'returns the rules of a country', () => {
		expect( countryRules( locale, 'IN' ).postcode.label ).toBe(
			'PIN Code'
		);
	} );

	it( 'treats a copy of the defaults (the base country) as no rules', () => {
		expect(
			countryRules( { ...locale, MX: locale.default }, 'MX' )
		).toEqual( {} );
	} );

	it( 'returns nothing for a country without rules', () => {
		expect( countryRules( locale, 'FR' ) ).toEqual( {} );
	} );
} );

describe( 'effective', () => {
	it( 'keeps our label everywhere when the label switch is on', () => {
		expect( effective( postcode, locale.IN.postcode, allOn ).label ).toBe(
			'Postal area'
		);
	} );

	it( 'leaves the country label when the switch is off and the country has one', () => {
		expect(
			effective( postcode, locale.IN.postcode, allOff ).label
		).toBeUndefined();
	} );

	it( 'uses our label when the switch is off but the country has no rule', () => {
		expect( effective( postcode, {}, allOff ).label ).toBe( 'Postal area' );
	} );

	it( 'follows the order switch for Japan', () => {
		expect(
			effective( postcode, locale.JP.postcode, allOn ).priority
		).toBe( 90 );
		expect(
			effective( postcode, locale.JP.postcode, allOff ).priority
		).toBeUndefined();
	} );

	it( 'follows the classes switch for Japan', () => {
		expect(
			effective( postcode, locale.JP.postcode, allOn ).classes
		).toEqual( postcode.classes );
		expect(
			effective( postcode, locale.JP.postcode, allOff ).classes
		).toBeUndefined();
	} );

	it( 'follows the placeholder switch', () => {
		const rule = { placeholder: 'Their placeholder' };
		expect( effective( postcode, rule, allOn ).placeholder ).toBe(
			'e.g. AB1 2CD'
		);
		expect(
			effective( postcode, rule, allOff ).placeholder
		).toBeUndefined();
	} );

	it( 'follows the required switch for Great Britain', () => {
		const state = { ...postcode, required: true };
		expect( effective( state, locale.GB.state, allOn ).required ).toBe(
			true
		);
		expect(
			effective( state, locale.GB.state, allOff ).required
		).toBeUndefined();
	} );

	it( 'leaves every property the store owner did not change, even with its switch on', () => {
		const untouched = { ...postcode, changed: [] };

		expect( effective( untouched, locale.IN.postcode, allOn ) ).toEqual( {
			label: undefined,
			placeholder: undefined,
			priority: undefined,
			classes: undefined,
			required: undefined,
		} );
		expect( effective( untouched, {}, allOn ).label ).toBeUndefined();
	} );

	it( 'never requires a field the country hides', () => {
		expect(
			effective( postcode, locale.AE.postcode, allOn ).required
		).toBe( false );
	} );
} );

describe( 'applyLocaleOverrides', () => {
	function form(): HTMLElement {
		document.body.innerHTML = `
			<div class="woocommerce-billing-fields">
				<div class="woocommerce-billing-fields__field-wrapper">
					<p class="form-row form-row-wide address-field" id="billing_city_field" data-priority="70">
						<label for="billing_city">Town / City&nbsp;<span class="required" aria-hidden="true">*</span></label>
						<span class="woocommerce-input-wrapper"><input id="billing_city" name="billing_city" /></span>
					</p>
					<p class="form-row form-row-wide address-field validate-required" id="billing_postcode_field" data-priority="90">
						<label for="billing_postcode">PIN Code&nbsp;<span class="required" aria-hidden="true">*</span></label>
						<span class="woocommerce-input-wrapper"><input id="billing_postcode" name="billing_postcode" placeholder="" /></span>
					</p>
				</div>
			</div>`;
		return document.querySelector(
			'.woocommerce-billing-fields'
		) as HTMLElement;
	}

	const params = {
		locale: JSON.stringify( locale ).replace( /"/g, '&quot;' ),
		locale_fields: JSON.stringify( {
			city: '#billing_city_field, #shipping_city_field',
			postcode: '#billing_postcode_field, #shipping_postcode_field',
		} ),
		i18n_optional_text: 'optional',
	};

	function data( switches: Switches ): ScriptData {
		return {
			localeOverrides: switches,
			fields: {
				billing_city: {
					label: 'Suburb',
					placeholder: '',
					required: false,
					priority: 100,
					classes: [ 'form-row-wide', 'address-field' ],
					changed: [ 'label', 'order', 'required' ],
				},
				billing_postcode: postcode,
			},
		};
	}

	it( 'puts our label, placeholder and required state back with every switch on', () => {
		const wrapper = form();

		applyLocaleOverrides(
			wrapper,
			'IN',
			data( allOn ),
			params,
			mapStore()
		);

		const label = document.querySelector( '#billing_postcode_field label' );
		expect( label ).toHaveTextContent( 'Postal area *' );
		expect( document.getElementById( 'billing_postcode' ) ).toHaveAttribute(
			'placeholder',
			'e.g. AB1 2CD'
		);
		expect( document.getElementById( 'billing_postcode' ) ).toHaveAttribute(
			'aria-required',
			'true'
		);
	} );

	it( 'leaves India its own label with the label switch off', () => {
		const wrapper = form();

		applyLocaleOverrides(
			wrapper,
			'IN',
			data( allOff ),
			params,
			mapStore()
		);

		expect(
			document.querySelector( '#billing_postcode_field label' )
		).toHaveTextContent( 'PIN Code *' );
		expect(
			document.querySelector( '#billing_city_field label' )
		).toHaveTextContent( 'Suburb (optional)' );
		expect(
			document.getElementById( 'billing_city_field' )
		).not.toHaveClass( 'validate-required' );
	} );

	it( 'reorders rows by our priorities with the order switch on', () => {
		const wrapper = form();
		const store = mapStore();

		applyLocaleOverrides( wrapper, 'GB', data( allOn ), params, store );

		const ids = Array.from( wrapper.querySelectorAll( '.form-row' ) ).map(
			( row ) => row.id
		);
		expect( ids ).toEqual( [
			'billing_postcode_field',
			'billing_city_field',
		] );
		expect(
			store.values.get(
				document.getElementById( 'billing_city_field' ) as HTMLElement
			)
		).toBe( 100 );
	} );

	it( 'keeps Japan its own order with the order switch off', () => {
		const wrapper = form();
		const store = mapStore();
		store.set(
			document.getElementById( 'billing_postcode_field' ) as HTMLElement,
			65
		);

		applyLocaleOverrides( wrapper, 'JP', data( allOff ), params, store );

		// Japan has priority rules for both fields, which WooCommerce's own
		// handler has applied and sorted by; they are left as they are.
		expect(
			store.values.get(
				document.getElementById(
					'billing_postcode_field'
				) as HTMLElement
			)
		).toBe( 65 );
		expect(
			store.values.has(
				document.getElementById( 'billing_city_field' ) as HTMLElement
			)
		).toBe( false );
	} );

	it( 'applies our classes with the classes switch on in Japan', () => {
		const wrapper = form();
		const row = document.getElementById(
			'billing_postcode_field'
		) as HTMLElement;
		row.classList.replace( 'form-row-wide', 'form-row-first' );

		applyLocaleOverrides(
			wrapper,
			'JP',
			data( allOn ),
			params,
			mapStore()
		);

		expect( row ).toHaveClass( 'form-row-wide' );
		expect( row ).not.toHaveClass( 'form-row-first' );
	} );

	it( 'leaves an untouched field as WooCommerce set it', () => {
		const wrapper = form();
		const untouched = data( allOn );
		untouched.fields.billing_postcode = { ...postcode, changed: [] };

		applyLocaleOverrides( wrapper, 'IN', untouched, params, mapStore() );

		expect(
			document.querySelector( '#billing_postcode_field label' )
		).toHaveTextContent( 'PIN Code *' );
		expect( document.getElementById( 'billing_postcode' ) ).toHaveAttribute(
			'placeholder',
			''
		);
	} );
} );
