/**
 * Shapes of the data the admin app reads and writes over REST (spec §2, §7).
 */

export type CheckoutType = 'classic' | 'block';

/** A thing the header's Save changes writes: one of the two field sets, or the settings. */
export type Resource = CheckoutType | 'settings';

export interface FieldOption {
	value: string;
	label: string;
}

/**
 * One field (spec §2.2). Keys added through `checkout_fieldkit_field_defaults`
 * come along untouched.
 */
export interface Field {
	name: string;
	type: string;
	builtIn: boolean;
	enabled: boolean;
	required: boolean;
	label: string;
	placeholder: string;
	default: string;
	options: FieldOption[];
	validation: string[];
	classes: string[];
	headingLevel: string;
	showInEmails: boolean;
	showOnOrderPages: boolean;
	[ key: string ]: unknown;
}

/** All the fields of one checkout type, by section name, in display order. */
export interface FieldSet {
	version: number;
	sections: Record< string, Field[] >;
}

export interface Settings {
	version: number;
	localeOverrides: Record< string, boolean >;
	[ key: string ]: unknown;
}

export interface NamedLabel {
	name: string;
	label: string;
}

export interface FieldTypeDefinition {
	name: string;
	label: string;
	sections: string[];
	settings: string[];
	collectsValue: boolean;
	hasOptions: boolean;
	multiple: boolean;
	impliedValidation: string | null;
	builtInOnly: boolean;
}

export interface EssentialField {
	name: string;
	consequence: string;
}

/** The body of GET /context. */
export interface Context {
	activeCheckoutType: CheckoutType | 'unknown';
	checkoutPageUrl: string | null;
	blockSupported: boolean;
	wooCommerceVersion: string;
	sections: Record< CheckoutType, NamedLabel[] >;
	fieldTypes: Record< CheckoutType, FieldTypeDefinition[] >;
	validations: Record< CheckoutType, NamedLabel[] >;
	essentialFields: Record< CheckoutType, EssentialField[] >;
	builtInEditableSettings: Record< CheckoutType, Record< string, string[] > >;
	reservedNames: string[];
	fieldNamePattern: string;
	fieldDefaults: Record< CheckoutType, Field >;
	headingLevels: string[];
	defaultSettings: Settings;
}

/** The three stored resources, as one object. */
export interface StoredData {
	classic: FieldSet;
	block: FieldSet;
	settings: Settings;
}

/** Everything the app needs to start. */
export interface InitialData extends StoredData {
	context: Context;
}

/** One problem from a 422 on PUT /field-sets/{type}. `index` is null for a whole-section problem. */
export interface FieldSetError {
	section: string;
	index: number | null;
	key: string;
	message: string;
}

/** One problem from a 422 on PUT /settings, such as key `localeOverrides.label`. */
export interface SettingsError {
	key: string;
	message: string;
}

/** What `window.checkoutFieldkitAdmin` holds (filter `checkout_fieldkit_admin_settings`). */
export interface AdminSettings {
	restNamespace?: string;
	preloaded?: {
		context?: Context | null;
		fieldSets?: Partial< Record< CheckoutType, FieldSet | null > >;
		settings?: Settings | null;
	};
	[ key: string ]: unknown;
}

/** One problem from a 422 on POST /import/preview or /import. `target` is '' for the document itself. */
export interface ImportError {
	target: Resource | '';
	section: string;
	index: number | null;
	key: string;
	message: string;
}

/** What importing a file would do to one target. Names are field names, or switch keys for settings. */
export interface ImportTargetPreview {
	target: Resource;
	action: 'replace' | 'keep';
	added: string[];
	removed: string[];
	changed: string[];
}

/** The body of POST /import/preview. */
export interface ImportPreview {
	targets: ImportTargetPreview[];
}

/** The body of POST /import: what was imported, and each imported target as now in effect. */
export type ImportResult = { imported: Resource[] } & Partial< StoredData >;

/** The body of POST /reset: the target, and its value as now in effect. */
export type ResetResult = { reset: Resource } & Partial< StoredData >;

/** An export file, ready to download. */
export interface ExportFile {
	fileName: string;
	/** The JSON exactly as the server sent it. */
	contents: string;
}
