/**
 * A realistic GET /context for the editor tests: field types, validations,
 * built-in editable settings and reserved names as FieldTypes.php,
 * DefaultFields.php and Rest\Context describe them.
 */
import type {
	CheckoutType,
	Context,
	FieldTypeDefinition,
	InitialData,
} from '../../../../src/admin/types';
import { context, field, initialData } from '../fixtures';

const DISPLAY = [ 'showInEmails', 'showOnOrderPages' ];
const TEXT = [
	'label',
	'placeholder',
	'default',
	'required',
	'validation',
	'classes',
	...DISPLAY,
];
const TEXTAREA = [
	'label',
	'placeholder',
	'default',
	'required',
	'classes',
	...DISPLAY,
];
const DROPDOWN = [
	'label',
	'placeholder',
	'options',
	'default',
	'required',
	'classes',
	...DISPLAY,
];
const CHOICES = [
	'label',
	'options',
	'default',
	'required',
	'classes',
	...DISPLAY,
];
const PLAIN = [ 'label', 'default', 'required', 'classes', ...DISPLAY ];

type Row = [
	name: string,
	label: string,
	settings: string[],
	flags: Partial< FieldTypeDefinition >,
	blockSections: 'all' | string[] | null,
];

const CATALOGUE: Row[] = [
	[ 'text', 'Text', TEXT, {}, 'all' ],
	[ 'number', 'Number', TEXT, { impliedValidation: 'number' }, 'all' ],
	[ 'email', 'Email', TEXT, { impliedValidation: 'email' }, 'all' ],
	[ 'url', 'Web address (URL)', TEXT, { impliedValidation: 'url' }, 'all' ],
	[ 'tel', 'Phone', TEXT, { impliedValidation: 'phone' }, null ],
	[ 'password', 'Password', TEXT, {}, null ],
	[
		'hidden',
		'Hidden',
		[ 'label', 'default', 'classes', ...DISPLAY ],
		{},
		null,
	],
	[ 'textarea', 'Text area', TEXTAREA, {}, [ 'contact', 'additional' ] ],
	[ 'select', 'Dropdown', DROPDOWN, { hasOptions: true }, 'all' ],
	[
		'multiselect',
		'Multi-select dropdown',
		DROPDOWN,
		{ hasOptions: true, multiple: true },
		null,
	],
	[
		'radio',
		'Radio buttons',
		CHOICES,
		{ hasOptions: true },
		[ 'contact', 'additional' ],
	],
	[ 'checkbox', 'Checkbox', PLAIN, {}, 'all' ],
	[
		'checkboxgroup',
		'Checkbox group',
		CHOICES,
		{ hasOptions: true, multiple: true },
		null,
	],
	[ 'date', 'Date', PLAIN, {}, null ],
	[ 'time', 'Time', PLAIN, {}, null ],
	[ 'datetime-local', 'Date and time', PLAIN, {}, null ],
	[ 'month', 'Month', PLAIN, {}, null ],
	[ 'week', 'Week', PLAIN, {}, null ],
	[
		'heading',
		'Heading',
		[ 'label', 'headingLevel', 'classes' ],
		{ collectsValue: false },
		[ 'contact', 'additional' ],
	],
	[
		'paragraph',
		'Paragraph',
		[ 'label', 'classes' ],
		{ collectsValue: false },
		[ 'contact', 'additional' ],
	],
];

const SECTIONS: Record< CheckoutType, string[] > = {
	classic: [ 'billing', 'shipping', 'additional' ],
	block: [ 'contact', 'address', 'additional' ],
};

function definition(
	name: string,
	label: string,
	sections: string[],
	settings: string[],
	flags: Partial< FieldTypeDefinition > = {}
): FieldTypeDefinition {
	return {
		name,
		label,
		sections,
		settings,
		collectsValue: true,
		hasOptions: false,
		multiple: false,
		impliedValidation: null,
		builtInOnly: false,
		...flags,
	};
}

export function fieldTypes( type: CheckoutType ): FieldTypeDefinition[] {
	const types: FieldTypeDefinition[] = [];
	CATALOGUE.forEach( ( [ name, label, settings, flags, blockSections ] ) => {
		if ( type === 'block' && blockSections === null ) {
			return;
		}
		const sections =
			type === 'block' && Array.isArray( blockSections )
				? blockSections
				: SECTIONS[ type ];
		types.push( definition( name, label, sections, settings, flags ) );
	} );
	const builtInOnly: Array< [ string, string ] > = [
		[ 'country', 'Country / Region' ],
		[ 'state', 'State / County' ],
	];
	if ( type === 'block' ) {
		builtInOnly.push( [ 'tel', 'Phone' ] );
	}
	builtInOnly.forEach( ( [ name, label ] ) => {
		types.push(
			definition(
				name,
				label,
				type === 'classic' ? [ 'billing', 'shipping' ] : [ 'address' ],
				TEXT,
				{ builtInOnly: true }
			)
		);
	} );
	return types;
}

const ADDRESS = [
	'first_name',
	'last_name',
	'company',
	'country',
	'address_1',
	'address_2',
	'city',
	'state',
	'postcode',
	'phone',
];

const CLASSIC_EDITABLE = [
	'label',
	'placeholder',
	'default',
	'required',
	'enabled',
	'validation',
	'classes',
];

export function editorContext( overrides: Partial< Context > = {} ): Context {
	return context( {
		fieldTypes: {
			classic: fieldTypes( 'classic' ),
			block: fieldTypes( 'block' ),
		},
		validations: {
			classic: [
				{ name: 'email', label: 'Email address' },
				{ name: 'phone', label: 'Phone number' },
				{ name: 'postcode', label: 'Postcode / ZIP' },
				{ name: 'state', label: 'State / County' },
				{ name: 'number', label: 'Number' },
				{ name: 'url', label: 'Web address (URL)' },
			],
			block: [
				{ name: 'email', label: 'Email address' },
				{ name: 'phone', label: 'Phone number' },
				{ name: 'postcode', label: 'Postcode / ZIP' },
				{ name: 'url', label: 'Web address (URL)' },
			],
		},
		builtInEditableSettings: {
			classic: {
				billing_first_name: CLASSIC_EDITABLE,
				billing_email: CLASSIC_EDITABLE,
				shipping_city: CLASSIC_EDITABLE,
				order_comments: CLASSIC_EDITABLE,
			},
			block: {
				email: [],
				country: [ 'label', 'required', 'enabled' ],
				company: [ 'label', 'required', 'enabled' ],
			},
		},
		reservedNames: [
			...ADDRESS,
			'email',
			'billing_email',
			'shipping_email',
			...ADDRESS.map( ( name ) => `billing_${ name }` ),
			...ADDRESS.map( ( name ) => `shipping_${ name }` ),
			'order_comments',
			'account_username',
			'account_password',
			'createaccount',
			'ship_to_different_address',
			'terms',
			'payment_method',
			'shipping_method',
		],
		fieldDefaults: { classic: field(), block: field() },
		...overrides,
	} );
}

/**
 * The shared field sets plus a few custom fields of interesting types.
 * @param overrides
 */
export function editorData(
	overrides: Partial< InitialData > = {}
): InitialData {
	const data = initialData( { context: editorContext() } );
	data.classic.sections.billing[ 2 ] = field( {
		name: 'billing_vat',
		label: 'VAT number',
		placeholder: 'GB123456789',
		classes: [ 'form-row-wide' ],
	} );
	data.classic.sections.additional.push(
		field( {
			name: 'heard_from',
			label: 'How did you hear about us?',
			type: 'select',
			placeholder: 'Choose an option',
			options: [
				{ value: 'search', label: 'Search engine' },
				{ value: 'social', label: 'Social media' },
				{ value: 'friend', label: 'A friend' },
			],
		} )
	);
	data.block.sections.additional.push(
		field( { name: 'gift_note', label: 'Gift note', type: 'textarea' } )
	);
	return { ...data, ...overrides };
}
