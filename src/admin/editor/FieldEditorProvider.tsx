/**
 * Opens the field editor drawer from anywhere below it (the Fields screen's
 * Add field and Edit buttons) through useFieldEditor().
 */
import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import type { ReactNode } from 'react';
import type { CheckoutType } from '../types';
import { FieldEditorDrawer } from './FieldEditorDrawer';
import './editor.css';

export interface FieldEditorApi {
	/** Opens the drawer to add a custom field at the end of a section. */
	openNew: ( checkoutType: CheckoutType, section: string ) => void;
	/** Opens the drawer for a field, by its row id from useFieldSet(). */
	openExisting: ( checkoutType: CheckoutType, rowId: string ) => void;
}

interface Target {
	checkoutType: CheckoutType;
	section: string;
	rowId: string | null;
	/** New for every opening, so the drawer starts fresh. */
	key: number;
}

const noop = () => {};

const FieldEditorContext = createContext< FieldEditorApi >( {
	openNew: noop,
	openExisting: noop,
} );

/**
 * Where focus goes when the drawer closes and the element that opened it
 * is gone (its field was deleted): the screen's title.
 */
function focusFallback() {
	const active = document.activeElement; // eslint-disable-line @wordpress/no-global-active-element -- the drawer is gone; no node is left to ask.
	if ( active && active !== document.body && active.isConnected ) {
		return;
	}
	document.querySelector< HTMLElement >( '.cfk-content h1' )?.focus();
}

/**
 * Provides useFieldEditor() and renders the drawer while it is open.
 *
 * @param props          Props.
 * @param props.children The screens.
 */
export function FieldEditorProvider( { children }: { children: ReactNode } ) {
	const [ target, setTarget ] = useState< Target | null >( null );
	const openings = useRef( 0 );
	const wasOpen = useRef( false );

	const api = useMemo< FieldEditorApi >(
		() => ( {
			openNew: ( checkoutType, section ) => {
				openings.current += 1;
				setTarget( {
					checkoutType,
					section,
					rowId: null,
					key: openings.current,
				} );
			},
			openExisting: ( checkoutType, rowId ) => {
				openings.current += 1;
				setTarget( {
					checkoutType,
					section: '',
					rowId,
					key: openings.current,
				} );
			},
		} ),
		[]
	);

	const close = useCallback( () => setTarget( null ), [] );

	// The modal gives focus back to the opener; this covers an opener that is gone.
	useEffect( () => {
		if ( target ) {
			wasOpen.current = true;
		} else if ( wasOpen.current ) {
			wasOpen.current = false;
			focusFallback();
		}
	}, [ target ] );

	return (
		<FieldEditorContext.Provider value={ api }>
			{ children }
			{ target && (
				<FieldEditorDrawer
					key={ target.key }
					checkoutType={ target.checkoutType }
					section={ target.section }
					rowId={ target.rowId }
					onClose={ close }
				/>
			) }
		</FieldEditorContext.Provider>
	);
}

/**
 * Opens the field editor. Outside FieldEditorProvider it does nothing.
 */
export function useFieldEditor(): FieldEditorApi {
	return useContext( FieldEditorContext );
}
