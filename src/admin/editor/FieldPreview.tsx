/**
 * Live preview of a field as a shopper sees it (spec §6.3), in the look of
 * the Classic checkout (label above, `*` or "(optional)") or the Block
 * checkout (label inside the box, "(optional)" after it). It is a picture:
 * hidden from assistive technology, inert and out of the tab order.
 */
import { useEffect, useId, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { ReactNode } from 'react';
import { cx } from '../components';
import type { CheckoutType, Field, FieldTypeDefinition } from '../types';

export interface FieldPreviewProps {
	checkoutType: CheckoutType;
	field: Field;
	definition: FieldTypeDefinition | null;
}

const noop = () => {};

/** Props that keep a preview control out of reach. */
const still = { tabIndex: -1, onChange: noop } as const;

const TEXT_TYPES = [
	'text',
	'number',
	'email',
	'url',
	'tel',
	'password',
	'date',
	'time',
	'datetime-local',
	'month',
	'week',
];

function selectPrompt( field: Field ): string {
	if ( field.placeholder ) {
		return field.placeholder;
	}
	if ( field.type === 'country' ) {
		return __( 'Select a country / region…', 'checkout-fieldkit' );
	}
	return __( 'Choose an option', 'checkout-fieldkit' );
}

function defaults( field: Field ): string[] {
	return field.default === '' ? [] : field.default.split( ',' );
}

function optionText( label: string, value: string ): string {
	return label || value;
}

/**
 * The field's control, shared by both looks.
 *
 * @param props            Props.
 * @param props.field      The field.
 * @param props.definition Its type.
 * @param props.id         Id for the control.
 */
function Control( {
	field,
	definition,
	id,
}: {
	field: Field;
	definition: FieldTypeDefinition | null;
	id: string;
} ) {
	const chosen = defaults( field );
	const options = field.options.filter(
		( option ) => option.value !== '' || option.label !== ''
	);

	switch ( field.type ) {
		case 'textarea':
			return (
				<textarea
					id={ id }
					className="cfk-preview__input"
					placeholder={ field.placeholder }
					value={ field.default }
					rows={ 2 }
					readOnly
					{ ...still }
				/>
			);
		case 'select':
		case 'country':
		case 'state':
			return (
				<select
					id={ id }
					className="cfk-preview__input"
					value={ chosen[ 0 ] || '' }
					{ ...still }
				>
					<option value="">{ selectPrompt( field ) }</option>
					{ options
						.filter( ( option ) => option.value !== '' )
						.map( ( option, index ) => (
							<option key={ index } value={ option.value }>
								{ optionText( option.label, option.value ) }
							</option>
						) ) }
				</select>
			);
		case 'multiselect':
			return (
				<select
					id={ id }
					className="cfk-preview__input"
					multiple
					value={ chosen }
					{ ...still }
				>
					{ options.map( ( option, index ) => (
						<option key={ index } value={ option.value }>
							{ optionText( option.label, option.value ) }
						</option>
					) ) }
				</select>
			);
		default:
			return (
				<input
					id={ id }
					className="cfk-preview__input"
					type={
						definition && TEXT_TYPES.includes( field.type )
							? field.type
							: 'text'
					}
					placeholder={ field.placeholder }
					value={ field.default }
					readOnly
					{ ...still }
				/>
			);
	}
}

/**
 * Radio buttons or a checkbox group.
 *
 * @param props        Props.
 * @param props.field  The field.
 * @param props.legend The group's caption.
 */
function Choices( { field, legend }: { field: Field; legend: ReactNode } ) {
	const chosen = defaults( field );
	const name = useId();
	const type = field.type === 'radio' ? 'radio' : 'checkbox';
	return (
		<fieldset className="cfk-preview__choices">
			<legend>{ legend }</legend>
			{ field.options.map( ( option, index ) => (
				<label
					key={ index }
					className="cfk-preview__choice"
					htmlFor={ `${ name }-${ index }` }
				>
					<input
						id={ `${ name }-${ index }` }
						type={ type }
						name={ name }
						value={ option.value }
						checked={
							option.value !== '' &&
							chosen.includes( option.value )
						}
						{ ...still }
					/>
					<span>{ optionText( option.label, option.value ) }</span>
				</label>
			) ) }
		</fieldset>
	);
}

function ClassicLabelText( { field }: { field: Field } ) {
	return (
		<>
			<span className="cfk-preview__label-text">{ field.label }</span>{ ' ' }
			{ field.required ? (
				<abbr className="cfk-preview__required" title="required">
					*
				</abbr>
			) : (
				<span className="cfk-preview__optional">
					{ __( '(optional)', 'checkout-fieldkit' ) }
				</span>
			) }
		</>
	);
}

function blockLabelText( field: Field ): string {
	return field.required
		? field.label
		: sprintf(
				/* translators: %s: field label. */
				__( '%s (optional)', 'checkout-fieldkit' ),
				field.label
			);
}

function Display( { field }: { field: Field } ) {
	if ( field.type === 'heading' ) {
		const Heading = (
			[ 'h2', 'h3', 'h4', 'h5', 'h6' ].includes( field.headingLevel )
				? field.headingLevel
				: 'h3'
		) as 'h2' | 'h3' | 'h4' | 'h5' | 'h6';
		return (
			<Heading className="cfk-preview__heading">{ field.label }</Heading>
		);
	}
	return <p className="cfk-preview__paragraph">{ field.label }</p>;
}

function ClassicField( {
	field,
	definition,
	id,
}: {
	field: Field;
	definition: FieldTypeDefinition | null;
	id: string;
} ) {
	if ( field.type === 'checkbox' ) {
		return (
			<label className="cfk-preview__checkbox" htmlFor={ id }>
				<input
					id={ id }
					type="checkbox"
					checked={ field.default === '1' }
					{ ...still }
				/>
				<span>
					<ClassicLabelText field={ field } />
				</span>
			</label>
		);
	}
	if ( field.type === 'radio' || field.type === 'checkboxgroup' ) {
		return (
			<Choices
				field={ field }
				legend={ <ClassicLabelText field={ field } /> }
			/>
		);
	}
	return (
		<>
			<label className="cfk-preview__label" htmlFor={ id }>
				<ClassicLabelText field={ field } />
			</label>
			<Control field={ field } definition={ definition } id={ id } />
		</>
	);
}

function BlockField( {
	field,
	definition,
	id,
}: {
	field: Field;
	definition: FieldTypeDefinition | null;
	id: string;
} ) {
	const label = blockLabelText( field );
	if ( field.type === 'checkbox' ) {
		return (
			<label className="cfk-preview__checkbox" htmlFor={ id }>
				<input
					id={ id }
					type="checkbox"
					checked={ field.default === '1' }
					{ ...still }
				/>
				<span>{ label }</span>
			</label>
		);
	}
	if ( field.type === 'radio' || field.type === 'checkboxgroup' ) {
		return <Choices field={ field } legend={ label } />;
	}
	const hasValue =
		field.default !== '' ||
		field.placeholder !== '' ||
		[ 'select', 'country', 'state' ].includes( field.type );
	return (
		<div className={ cx( 'cfk-preview__box', hasValue && 'has-value' ) }>
			<Control field={ field } definition={ definition } id={ id } />
			<label className="cfk-preview__label" htmlFor={ id }>
				{ label }
			</label>
		</div>
	);
}

export function FieldPreview( {
	checkoutType,
	field,
	definition,
}: FieldPreviewProps ) {
	const id = useId();
	const ref = useRef< HTMLDivElement >( null );

	// `inert` keeps the preview's controls from being clicked or focused.
	useEffect( () => {
		ref.current?.setAttribute( 'inert', '' );
	}, [] );

	let content: ReactNode;
	if ( field.type === 'hidden' ) {
		content = (
			<p className="cfk-preview__note">
				{ field.default
					? sprintf(
							/* translators: %s: the hidden field's value. */
							__(
								'Shoppers don’t see hidden fields. This one sends “%s” with the order.',
								'checkout-fieldkit'
							),
							field.default
						)
					: __(
							'Shoppers don’t see hidden fields.',
							'checkout-fieldkit'
						) }
			</p>
		);
	} else if ( definition && ! definition.collectsValue ) {
		content = <Display field={ field } />;
	} else if ( checkoutType === 'block' ) {
		content = (
			<BlockField field={ field } definition={ definition } id={ id } />
		);
	} else {
		content = (
			<ClassicField field={ field } definition={ definition } id={ id } />
		);
	}

	return (
		<div className="cfk-editor-preview" aria-hidden="true">
			<div className="cfk-editor-preview__header">
				<span className="cfk-editor-preview__title">
					{ __( 'Live preview', 'checkout-fieldkit' ) }
				</span>
				<span>
					{ __(
						'Your theme’s styles apply on the real checkout',
						'checkout-fieldkit'
					) }
				</span>
			</div>
			<div
				ref={ ref }
				className={ cx(
					'cfk-editor-preview__shop',
					checkoutType === 'block' ? 'is-block' : 'is-classic'
				) }
			>
				{ content }
			</div>
		</div>
	);
}
