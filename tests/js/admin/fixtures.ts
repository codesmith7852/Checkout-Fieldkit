/**
 * Small, realistic data for the admin tests: the GET /context, field set and
 * settings shapes of spec §7, trimmed to a few fields.
 */
import type {
	Context,
	Field,
	FieldSet,
	InitialData,
	Settings,
} from '../../../src/admin/types';

export function field( overrides: Partial< Field > = {} ): Field {
	return {
		name: '',
		type: 'text',
		builtIn: false,
		enabled: true,
		required: false,
		label: '',
		placeholder: '',
		default: '',
		options: [],
		validation: [],
		classes: [],
		headingLevel: 'h3',
		showInEmails: true,
		showOnOrderPages: true,
		...overrides,
	};
}

export function classicFieldSet(): FieldSet {
	return {
		version: 1,
		sections: {
			billing: [
				field( {
					name: 'billing_first_name',
					label: 'First name',
					builtIn: true,
					required: true,
				} ),
				field( {
					name: 'billing_email',
					label: 'Email address',
					type: 'email',
					builtIn: true,
					required: true,
					validation: [ 'email' ],
				} ),
				field( { name: 'billing_vat', label: 'VAT number' } ),
			],
			shipping: [
				field( {
					name: 'shipping_city',
					label: 'Town / City',
					builtIn: true,
					required: true,
				} ),
			],
			additional: [
				field( {
					name: 'order_comments',
					label: 'Order notes',
					type: 'textarea',
					builtIn: true,
				} ),
			],
		},
	};
}

export function blockFieldSet(): FieldSet {
	return {
		version: 1,
		sections: {
			contact: [
				field( {
					name: 'email',
					label: 'Email address',
					type: 'email',
					builtIn: true,
					required: true,
				} ),
			],
			address: [
				field( {
					name: 'country',
					label: 'Country / Region',
					type: 'country',
					builtIn: true,
					required: true,
				} ),
				field( {
					name: 'company',
					label: 'Company',
					builtIn: true,
					enabled: false,
				} ),
			],
			additional: [],
		},
	};
}

export function settings(): Settings {
	return {
		version: 1,
		localeOverrides: {
			label: true,
			placeholder: true,
			order: true,
			required: false,
			classes: false,
		},
	};
}

export function context( overrides: Partial< Context > = {} ): Context {
	return {
		activeCheckoutType: 'block',
		checkoutPageUrl: 'https://shop.example/checkout/',
		blockSupported: true,
		wooCommerceVersion: '11.3.0',
		sections: {
			classic: [
				{ name: 'billing', label: 'Billing' },
				{ name: 'shipping', label: 'Shipping' },
				{ name: 'additional', label: 'Additional' },
			],
			block: [
				{ name: 'contact', label: 'Contact' },
				{ name: 'address', label: 'Address' },
				{ name: 'additional', label: 'Additional' },
			],
		},
		fieldTypes: { classic: [], block: [] },
		validations: { classic: [], block: [] },
		essentialFields: {
			classic: [
				{
					name: 'billing_email',
					consequence: 'Customers get no order emails.',
				},
			],
			block: [ { name: 'country', consequence: 'No shipping rates.' } ],
		},
		builtInEditableSettings: { classic: {}, block: {} },
		reservedNames: [],
		fieldNamePattern: '^[a-z][a-z0-9_]{0,59}$',
		fieldDefaults: { classic: field(), block: field() },
		headingLevels: [ 'h2', 'h3', 'h4', 'h5', 'h6' ],
		defaultSettings: settings(),
		...overrides,
	};
}

export function initialData(
	overrides: Partial< InitialData > = {}
): InitialData {
	return {
		context: context(),
		classic: classicFieldSet(),
		block: blockFieldSet(),
		settings: settings(),
		...overrides,
	};
}
