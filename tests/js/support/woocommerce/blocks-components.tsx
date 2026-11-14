/**
 * Test double for `@woocommerce/blocks-components` (WooCommerce's
 * `wc.blocksComponents`), following the contract of WooCommerce 11.3's
 * components closely enough to test how our fields use them:
 *
 * - ValidatedTextInput keeps one entry in the validation store under its
 *   `errorId`: validated on mount and on change (hidden), shown on blur. The
 *   message is the input's custom validity when customValidation() set one,
 *   else customValidityMessage( validity ), else the browser's message.
 * - CheckboxControl is a labelled checkbox passing other props to the input.
 */
import { useEffect, useRef } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import type { ReactNode } from 'react';

const STORE = 'wc/store/validation';

interface Store {
	setValidationErrors: (
		errors: Record< string, { message: string; hidden: boolean } >
	) => void;
	clearValidationError: ( id: string ) => void;
}

export function ValidatedTextInput( {
	id,
	errorId,
	type = 'text',
	label,
	value,
	onChange,
	required,
	className,
	customValidation = () => true,
	customValidityMessage,
	...rest
}: {
	id: string;
	errorId?: string;
	type?: string;
	label: string;
	value: string;
	onChange: ( value: string ) => void;
	required?: boolean;
	className?: string;
	customValidation?: ( input: HTMLInputElement ) => boolean;
	customValidityMessage?: ( validity: ValidityState ) => string;
	[ key: string ]: unknown;
} ) {
	const input = useRef< HTMLInputElement >( null );
	const key = errorId || id;
	const { setValidationErrors, clearValidationError } = useDispatch(
		STORE
	) as unknown as Store;
	const error = useSelect(
		( select ) =>
			(
				select( STORE ) as {
					getValidationError: (
						id: string
					) => { message: string; hidden: boolean } | undefined;
				}
			 ).getValidationError( key ),
		[ key ]
	);

	const validate = ( hidden: boolean ) => {
		const element = input.current;
		if ( ! element ) {
			return;
		}
		element.setCustomValidity( '' );
		if ( element.checkValidity() && customValidation( element ) ) {
			clearValidationError( key );
			return;
		}
		const message = element.validity.customError
			? element.validationMessage
			: customValidityMessage?.( element.validity ) ||
				element.validationMessage;
		setValidationErrors( { [ key ]: { message, hidden } } );
	};

	useEffect( () => {
		validate( true );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ value ] );
	useEffect( () => () => clearValidationError( key ), [ key ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const shown = !! error && ! error.hidden;

	return (
		<div
			className={ [
				'wc-block-components-text-input',
				className,
				shown ? 'has-error' : '',
			]
				.filter( Boolean )
				.join( ' ' ) }
		>
			<input
				ref={ input }
				id={ id }
				type={ type }
				value={ value }
				required={ required }
				aria-invalid={ shown }
				aria-errormessage={
					shown ? `validate-error-${ key }` : undefined
				}
				onChange={ ( event ) => onChange( event.target.value ) }
				onBlur={ () => validate( false ) }
				{ ...( rest as Record< string, string > ) }
			/>
			<label htmlFor={ id }>{ label }</label>
			{ shown && (
				<div
					className="wc-block-components-validation-error"
					role="alert"
				>
					<p id={ `validate-error-${ key }` }>{ error.message }</p>
				</div>
			) }
		</div>
	);
}

export function CheckboxControl( {
	id,
	className,
	label,
	checked = false,
	onChange,
	hasError = false,
	children,
	...rest
}: {
	id?: string;
	className?: string;
	label?: ReactNode;
	checked?: boolean;
	onChange: ( checked: boolean ) => void;
	hasError?: boolean;
	children?: ReactNode;
	[ key: string ]: unknown;
} ) {
	return (
		<div
			className={ [
				'wc-block-components-checkbox',
				hasError ? 'has-error' : '',
				className,
			]
				.filter( Boolean )
				.join( ' ' ) }
		>
			<label htmlFor={ id }>
				<input
					id={ id }
					type="checkbox"
					checked={ checked }
					aria-invalid={ hasError }
					onChange={ ( event ) => onChange( event.target.checked ) }
					{ ...( rest as Record< string, string > ) }
				/>
				{ label && <span>{ label }</span> }
				{ children }
			</label>
		</div>
	);
}
