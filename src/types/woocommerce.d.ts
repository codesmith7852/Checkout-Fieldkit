/**
 * Types for the parts of WooCommerce's block packages we use.
 *
 * The packages are not installed: WooCommerce's dependency extraction turns
 * these imports into its scripts' globals (`wc.blocksCheckout`,
 * `wc.blocksComponents`, `wc.wcSettings`). Only what this plugin calls is
 * declared, as WooCommerce 9.0–11.3 provide it.
 */

declare module '@woocommerce/settings' {
	export function getSetting< T = unknown >(
		name: string,
		fallback?: T,
		filter?: ( value: unknown, fallback: T ) => T
	): T;
}

declare module '@woocommerce/blocks-checkout' {
	import type { ComponentType } from 'react';

	export interface CheckoutBlockMetadata {
		name: string;
		parent: string[];
		attributes?: Record< string, unknown >;
		[ key: string ]: unknown;
	}

	export function registerCheckoutBlock( options: {
		metadata: CheckoutBlockMetadata;

		component: ComponentType< any >;
		force?: boolean;
	} ): void;

	export function isPostcode( args: {
		postcode: string;
		country: string;
	} ): boolean;
}

declare module '@woocommerce/blocks-components' {
	import type { ComponentType, ReactNode } from 'react';

	export interface ValidatedTextInputProps {
		id: string;
		errorId?: string;
		type?: string;
		label: string;
		value: string;
		onChange: ( value: string ) => void;
		required?: boolean;
		className?: string;
		autoComplete?: string;
		autoCapitalize?: string;
		inputMode?: string;
		placeholder?: string;
		customValidation?: ( input: HTMLInputElement ) => boolean;
		customValidityMessage?: ( validity: ValidityState ) => string;
		validateOnMount?: boolean;
	}

	export const ValidatedTextInput: ComponentType< ValidatedTextInputProps >;

	export interface CheckboxControlProps {
		id?: string;
		className?: string;
		label?: ReactNode;
		checked?: boolean;
		onChange: ( checked: boolean ) => void;
		hasError?: boolean;
		disabled?: boolean;
		required?: boolean;
		children?: ReactNode;
		'aria-describedby'?: string;
	}

	export const CheckboxControl: ComponentType< CheckboxControlProps >;
}
