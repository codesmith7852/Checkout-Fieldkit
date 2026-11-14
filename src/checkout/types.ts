/**
 * The field definitions our inner blocks receive from the server
 * (InnerBlockFields::script_data() in PHP).
 */

/** The sections our inner blocks render. */
export type Section = 'contact' | 'additional';

/** The field types of the Block checkout's Contact and Additional sections. */
export type FieldType =
	| 'text'
	| 'number'
	| 'email'
	| 'url'
	| 'textarea'
	| 'select'
	| 'radio'
	| 'checkbox'
	| 'heading'
	| 'paragraph';

/** One option of a select or radio field. */
export interface FieldOption {
	value: string;
	label: string;
}

/** A field as the browser gets it. */
export interface CheckoutField {
	name: string;
	type: FieldType;
	label: string;
	classes: string[];
	/** Headings only: h2–h6. */
	headingLevel?: string;
	/** Fields that collect a value. */
	required?: boolean;
	/** Initial value; for a checkbox "1" (ticked) or "". */
	default?: string;
	placeholder?: string;
	options?: FieldOption[];
	/** Chosen validations: email, phone, postcode, url. */
	validation?: string[];
}

/** What `getSetting( 'checkout-fieldkit_data' )` holds. */
export interface ScriptData {
	namespace: string;
	sections: Record< Section, CheckoutField[] >;
}

/** A field's value as it is sent with the order. */
export type FieldValue = string | boolean;
