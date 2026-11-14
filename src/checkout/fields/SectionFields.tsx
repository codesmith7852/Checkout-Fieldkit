/**
 * Every field of one section, as our Contact and Additional inner blocks
 * render them on the Block checkout. The values travel with the order as
 * checkout extension data (`extensions.checkout-fieldkit.<name>`), which the
 * server checks and saves (Block\OrderValues in PHP).
 */
import { useEffect, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { isPostcode } from '@woocommerce/blocks-checkout';
import { getSectionFields, NAMESPACE } from '../settings';
import type { CheckoutField, FieldValue, Section } from '../types';
import type { ValidationContext } from '../validation';
import CheckboxField from './CheckboxField';
import DisplayField from './DisplayField';
import RadioField from './RadioField';
import SelectField from './SelectField';
import TextareaField from './TextareaField';
import TextField from './TextField';
import { classNames, initialValue } from './shared';

/** What WooCommerce passes our inner blocks for the checkout's extension data. */
export interface CheckoutExtensionData {
	extensionData?: Record< string, Record< string, unknown > >;
	setExtensionData: (
		namespace: string,
		key: string,
		value: unknown
	) => void;
}

interface Props {
	section: Section;
	checkoutExtensionData?: CheckoutExtensionData;
}

const DISPLAY_TYPES = [ 'heading', 'paragraph' ];

/**
 * The value fields start with: one already in the checkout (the block was
 * rendered before), else the field's default.
 *
 * @param fields Fields.
 * @param stored Our extension data so far.
 */
function startingValues(
	fields: CheckoutField[],
	stored: Record< string, unknown > | undefined
): Record< string, FieldValue > {
	const values: Record< string, FieldValue > = {};
	for ( const field of fields ) {
		if ( DISPLAY_TYPES.includes( field.type ) ) {
			continue;
		}
		const previous = stored?.[ field.name ];
		values[ field.name ] =
			typeof previous === 'string' || typeof previous === 'boolean'
				? previous
				: initialValue( field );
	}

	return values;
}

/**
 * The shopper's billing country, for postcode checks.
 */
function useValidationContext(): ValidationContext {
	const country = useSelect( ( select ) => {
		const cart = select( 'wc/store/cart' ) as
			| {
					getCustomerData?: () => {
						billingAddress?: { country?: string };
					};
			  }
			| undefined;

		return cart?.getCustomerData?.()?.billingAddress?.country ?? '';
	}, [] );

	return { country, isPostcode };
}

export default function SectionFields( {
	section,
	checkoutExtensionData,
}: Props ) {
	const fields = getSectionFields( section );
	const [ values, setValues ] = useState( () =>
		startingValues(
			fields,
			checkoutExtensionData?.extensionData?.[ NAMESPACE ]
		)
	);
	const context = useValidationContext();
	const setExtensionData = checkoutExtensionData?.setExtensionData;

	// Send the starting values, so defaults reach the order untouched.
	useEffect( () => {
		if ( ! setExtensionData ) {
			return;
		}
		for ( const [ name, value ] of Object.entries( values ) ) {
			setExtensionData( NAMESPACE, name, value );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps -- Once, on mount.
	}, [] );

	if ( fields.length === 0 ) {
		return null;
	}

	const change = ( name: string ) => ( value: FieldValue ) => {
		setValues( ( current ) => ( { ...current, [ name ]: value } ) );
		setExtensionData?.( NAMESPACE, name, value );
	};

	return (
		<div
			className={ `cfk-checkout-fields cfk-checkout-fields--${ section }` }
		>
			{ fields.map( ( field ) => (
				<div
					key={ field.name }
					className={ classNames(
						'cfk-checkout-field',
						`cfk-checkout-field--${ field.type }`,
						...field.classes
					) }
				>
					{ renderField(
						field,
						values[ field.name ],
						change( field.name ),
						context
					) }
				</div>
			) ) }
		</div>
	);
}

/**
 * The component of one field.
 *
 * @param field    Field.
 * @param value    Its value.
 * @param onChange Called with a new value.
 * @param context  Validation context.
 */
function renderField(
	field: CheckoutField,
	value: FieldValue | undefined,
	onChange: ( value: FieldValue ) => void,
	context: ValidationContext
) {
	const text = typeof value === 'string' ? value : '';

	switch ( field.type ) {
		case 'heading':
		case 'paragraph':
			return <DisplayField field={ field } />;
		case 'checkbox':
			return (
				<CheckboxField
					field={ field }
					value={ value === true }
					onChange={ onChange }
					context={ context }
				/>
			);
		case 'textarea':
			return (
				<TextareaField
					field={ field }
					value={ text }
					onChange={ onChange }
					context={ context }
				/>
			);
		case 'select':
			return (
				<SelectField
					field={ field }
					value={ text }
					onChange={ onChange }
					context={ context }
				/>
			);
		case 'radio':
			return (
				<RadioField
					field={ field }
					value={ text }
					onChange={ onChange }
					context={ context }
				/>
			);
		default:
			return (
				<TextField
					field={ field }
					value={ text }
					onChange={ onChange }
					context={ context }
				/>
			);
	}
}
