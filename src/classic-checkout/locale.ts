/**
 * Locale overrides on the classic checkout and My Account address form.
 *
 * When the shopper changes country, WooCommerce's address-i18n script applies
 * that country's address field rules (label, placeholder, priority, classes,
 * required, hidden), falling back to WooCommerce's defaults. This module runs
 * straight after it and puts the plugin's settings back where the store's
 * locale override switches say so, the same way the server does
 * (includes/Classic/LocaleOverrides.php). Only properties the store owner
 * changed are ours: in every country when their switch is on; when it is off,
 * only where the country has no rule. Everything else stays as WooCommerce's
 * script left it. A rule that hides a field always applies.
 */

export interface Switches {
	label: boolean;
	placeholder: boolean;
	order: boolean;
	required: boolean;
	classes: boolean;
}

/** Our settings for one built-in address field. */
export interface OurField {
	label: string;
	placeholder: string;
	required: boolean;
	priority: number;
	classes: string[];
	/** Switch names of the properties the store owner changed. */
	changed: Array< keyof Switches >;
}

/** WooCommerce's rule for one field in one country. */
export interface Rule {
	label?: string;
	placeholder?: string;
	required?: boolean;
	priority?: number;
	class?: string[];
	hidden?: boolean;
}

export type Locale = Record< string, Record< string, Rule > >;

/** What window.checkoutFieldkitClassic holds. */
export interface ScriptData {
	localeOverrides: Switches;
	fields: Record< string, OurField >;
}

/** The parts of wc_address_i18n_params this module reads. */
export interface AddressI18nParams {
	locale: string;
	locale_fields: string;
	i18n_optional_text?: string;
}

/** What to set on a field; undefined leaves WooCommerce's value. */
export interface Effective {
	label?: string;
	placeholder?: string;
	priority?: number;
	classes?: string[];
	required?: boolean;
}

/** Reads and writes a row's priority where WooCommerce's script keeps it. */
export interface PriorityStore {
	get: ( row: HTMLElement ) => number | undefined;
	set: ( row: HTMLElement, priority: number ) => void;
}

const PREFIXES = [ 'billing_', 'shipping_' ];

const FIELD_WRAPPERS =
	'.woocommerce-billing-fields__field-wrapper, .woocommerce-shipping-fields__field-wrapper, .woocommerce-address-fields__field-wrapper';

/**
 * A country's own rules. WooCommerce copies its defaults to the store's base
 * country when that country has no rules; such a copy is not a rule.
 *
 * @param locale  WooCommerce's country locale.
 * @param country Country code.
 */
export function countryRules(
	locale: Locale,
	country: string
): Record< string, Rule > {
	const rules = locale[ country ];
	if ( ! rules || typeof rules !== 'object' ) {
		return {};
	}
	if (
		locale.default &&
		JSON.stringify( rules ) === JSON.stringify( locale.default )
	) {
		return {};
	}
	return rules;
}

/**
 * What one field should show in a country; undefined leaves what
 * WooCommerce's script set.
 *
 * @param ours     Our settings for the field.
 * @param rule     The country's rule for the field, or an empty object.
 * @param switches Locale override switches.
 */
export function effective(
	ours: OurField,
	rule: Rule,
	switches: Switches
): Effective {
	const takesOurs = ( key: keyof Rule, name: keyof Switches ) =>
		ours.changed.includes( name ) &&
		( switches[ name ] ||
			! Object.prototype.hasOwnProperty.call( rule, key ) );

	return {
		label: takesOurs( 'label', 'label' ) ? ours.label : undefined,
		placeholder: takesOurs( 'placeholder', 'placeholder' )
			? ours.placeholder
			: undefined,
		priority: takesOurs( 'priority', 'order' ) ? ours.priority : undefined,
		classes: takesOurs( 'class', 'classes' ) ? ours.classes : undefined,
		required: takesOurs( 'required', 'required' )
			? ours.required && rule.hidden !== true
			: undefined,
	};
}

/**
 * Shows a field as required or optional with WooCommerce's markers.
 *
 * @param row          The field's .form-row.
 * @param required     Whether it is required.
 * @param optionalText WooCommerce's "optional" text.
 */
export function setRequired(
	row: HTMLElement,
	required: boolean,
	optionalText: string
): void {
	const label = row.querySelector( 'label' );
	const controls = row.querySelectorAll(
		'input:not([type="hidden"]), select, textarea'
	);

	if ( required ) {
		label
			?.querySelectorAll( '.optional' )
			.forEach( ( node ) => node.remove() );
		row.classList.add( 'validate-required' );
		if (
			label &&
			! label.querySelector( '.required[aria-hidden="true"]' )
		) {
			label.insertAdjacentHTML(
				'beforeend',
				'&nbsp;<span class="required" aria-hidden="true">*</span>'
			);
		}
		controls.forEach( ( control ) =>
			control.setAttribute( 'aria-required', 'true' )
		);
		return;
	}

	label?.querySelectorAll( '.required' ).forEach( ( node ) => node.remove() );
	row.classList.remove(
		'validate-required',
		'woocommerce-invalid',
		'woocommerce-invalid-required-field'
	);
	if ( label && ! label.querySelector( '.optional' ) ) {
		const optional = document.createElement( 'span' );
		optional.className = 'optional';
		optional.textContent = `(${ optionalText })`;
		label.insertAdjacentHTML( 'beforeend', '&nbsp;' );
		label.appendChild( optional );
	}
	controls.forEach( ( control ) =>
		control.removeAttribute( 'aria-required' )
	);
}

/**
 * Applies one field's effective settings to its row.
 *
 * @param row          The field's .form-row.
 * @param field        Effective settings.
 * @param optionalText WooCommerce's "optional" text.
 * @param priorities   Where row priorities are kept.
 */
export function applyToRow(
	row: HTMLElement,
	field: Effective,
	optionalText: string,
	priorities: PriorityStore
): void {
	const label = row.querySelector( 'label' );
	if ( field.label !== undefined && label ) {
		label.textContent = field.label;
	}

	if ( field.placeholder !== undefined ) {
		const placeholder = field.placeholder;
		row.querySelectorAll( 'input, select, textarea' ).forEach(
			( control ) => {
				control.setAttribute( 'placeholder', placeholder );
				control.setAttribute( 'data-placeholder', placeholder );
			}
		);
		row.querySelectorAll( '.select2-selection__placeholder' ).forEach(
			( node ) => {
				node.textContent = placeholder;
			}
		);
	}

	if ( field.classes !== undefined ) {
		row.classList.remove(
			'form-row-first',
			'form-row-last',
			'form-row-wide'
		);
		field.classes
			.filter( Boolean )
			.forEach( ( name ) => row.classList.add( name ) );
	}

	if ( field.priority !== undefined ) {
		priorities.set( row, field.priority );
	}

	// A new label text drops the marker, so it is put back as it stands.
	if ( field.required !== undefined || field.label !== undefined ) {
		setRequired(
			row,
			field.required ?? row.classList.contains( 'validate-required' ),
			optionalText
		);
	}
}

/**
 * Puts the rows of each address form inside a wrapper in priority order,
 * as WooCommerce's script does.
 *
 * @param wrapper    The address form WooCommerce changed.
 * @param priorities Where row priorities are kept.
 */
export function sortRows(
	wrapper: HTMLElement,
	priorities: PriorityStore
): void {
	const fieldsets: HTMLElement[] = wrapper.matches( FIELD_WRAPPERS )
		? [ wrapper ]
		: Array.from(
				wrapper.querySelectorAll< HTMLElement >( FIELD_WRAPPERS )
			);

	fieldsets.forEach( ( fieldset ) => {
		const rows = Array.from(
			fieldset.querySelectorAll< HTMLElement >( '.form-row' )
		);
		if ( ! rows.length || ! rows[ 0 ].parentElement ) {
			return;
		}
		const parent = rows[ 0 ].parentElement;
		let last = 0;
		const keyed = rows.map( ( row, position ) => {
			const priority = priorities.get( row ) ?? last + 1;
			last = priority;
			return { row, priority, position };
		} );
		keyed
			.sort(
				( a, b ) => a.priority - b.priority || a.position - b.position
			)
			.forEach( ( { row } ) => parent.appendChild( row ) );
	} );
}

/**
 * Applies the locale overrides to an address form after a country change.
 *
 * @param wrapper    The address form WooCommerce changed.
 * @param country    The country chosen.
 * @param data       Our settings and switches.
 * @param params     WooCommerce's address-i18n settings.
 * @param priorities Where row priorities are kept.
 */
export function applyLocaleOverrides(
	wrapper: HTMLElement,
	country: string,
	data: ScriptData,
	params: AddressI18nParams,
	priorities: PriorityStore
): void {
	const locale: Locale = JSON.parse(
		params.locale.replace( /&quot;/g, '"' )
	);
	const keys = Object.keys( JSON.parse( params.locale_fields ) );
	const rules = countryRules( locale, country );
	const optionalText = params.i18n_optional_text ?? 'optional';
	let reorder = false;

	PREFIXES.forEach( ( prefix ) => {
		keys.forEach( ( key ) => {
			const name = prefix + key;
			const ours = data.fields[ name ];
			const row = wrapper.querySelector< HTMLElement >(
				`#${ name }_field`
			);
			if ( ! ours || ! row ) {
				return;
			}
			const field = effective(
				ours,
				rules[ key ] ?? {},
				data.localeOverrides
			);
			applyToRow( row, field, optionalText, priorities );
			reorder = reorder || field.priority !== undefined;
		} );
	} );

	if ( reorder ) {
		sortRows( wrapper, priorities );
	}
}
