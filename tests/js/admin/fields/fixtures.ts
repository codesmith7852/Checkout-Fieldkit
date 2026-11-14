/**
 * Context data for the Fields screen tests: real-looking field types,
 * validations and built-in locks on top of the shared fixtures.
 */
import type { Context, FieldTypeDefinition } from '../../../../src/admin/types';
import { context } from '../fixtures';

const VALUE_SETTINGS = [
	'label',
	'placeholder',
	'default',
	'required',
	'validation',
	'classes',
	'showInEmails',
	'showOnOrderPages',
];

function type(
	name: string,
	label: string,
	sections: string[],
	overrides: Partial< FieldTypeDefinition > = {}
): FieldTypeDefinition {
	return {
		name,
		label,
		sections,
		settings: VALUE_SETTINGS,
		collectsValue: true,
		hasOptions: false,
		multiple: false,
		impliedValidation: null,
		builtInOnly: false,
		...overrides,
	};
}

const CLASSIC = [ 'billing', 'shipping', 'additional' ];
const BLOCK = [ 'contact', 'address', 'additional' ];
const BLOCK_INNER = [ 'contact', 'additional' ];
const DISPLAY = { settings: [ 'label', 'classes' ], collectsValue: false };

export function fieldsContext( overrides: Partial< Context > = {} ): Context {
	return context( {
		fieldTypes: {
			classic: [
				type( 'text', 'Text', CLASSIC ),
				type( 'email', 'Email', CLASSIC ),
				type( 'tel', 'Phone', CLASSIC ),
				type( 'textarea', 'Textarea', CLASSIC ),
				type( 'select', 'Select', CLASSIC, { hasOptions: true } ),
				type( 'multiselect', 'Multiselect', CLASSIC, {
					hasOptions: true,
					multiple: true,
				} ),
				type( 'date', 'Date', CLASSIC ),
				type( 'heading', 'Heading', CLASSIC, DISPLAY ),
				type( 'country', 'Country', CLASSIC, { builtInOnly: true } ),
			],
			block: [
				type( 'text', 'Text', BLOCK ),
				type( 'email', 'Email', BLOCK ),
				type( 'textarea', 'Textarea', BLOCK_INNER ),
				type( 'select', 'Select', BLOCK, { hasOptions: true } ),
				type( 'heading', 'Heading', BLOCK_INNER, DISPLAY ),
				type( 'country', 'Country', [ 'address' ], {
					builtInOnly: true,
				} ),
			],
		},
		validations: {
			classic: [
				{ name: 'email', label: 'Email address' },
				{ name: 'phone', label: 'Phone number' },
				{ name: 'postcode', label: 'Postcode' },
				{ name: 'number', label: 'Number' },
			],
			block: [
				{ name: 'email', label: 'Email address' },
				{ name: 'phone', label: 'Phone number' },
				{ name: 'postcode', label: 'Postcode' },
			],
		},
		builtInEditableSettings: {
			classic: {
				billing_first_name: [
					'label',
					'placeholder',
					'default',
					'required',
					'enabled',
					'validation',
					'classes',
				],
			},
			block: {
				email: [],
				country: [ 'label', 'required', 'enabled' ],
				company: [ 'label', 'required', 'enabled' ],
			},
		},
		reservedNames: [ 'first_name', 'country', 'order_comments', 'terms' ],
		...overrides,
	} );
}
