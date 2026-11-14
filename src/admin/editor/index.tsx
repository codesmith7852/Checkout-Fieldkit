/**
 * STUB of the field editor drawer's API (spec §6.3), so the Fields screen
 * (#9) compiles and can be tested before the editor (#10) lands. #10 owns
 * this folder and replaces this file with the real drawer; the exports and
 * their signatures are the agreed contract and must not change here.
 */
import type { ReactNode } from 'react';
import type { CheckoutType } from '../types';

export interface FieldEditorApi {
	/** Opens the drawer with a new custom field for `section`. */
	openNew: ( checkoutType: CheckoutType, section: string ) => void;
	/** Opens the drawer on an existing field; `rowId` is the row id from useFieldSet(). */
	openExisting: ( checkoutType: CheckoutType, rowId: string ) => void;
}

const noop = () => {};

const STUB: FieldEditorApi = { openNew: noop, openExisting: noop };

/**
 * Holds the editor drawer for the app below it. The stub only renders its
 * children.
 *
 * @param props          Props.
 * @param props.children The app.
 */
export function FieldEditorProvider( { children }: { children: ReactNode } ) {
	return <>{ children }</>;
}

/** Opens the field editor drawer. The stub does nothing. */
export function useFieldEditor(): FieldEditorApi {
	return STUB;
}
