/**
 * A stand-in for WooCommerce's validation data store (`wc/store/validation`),
 * with the selectors and actions WooCommerce's checkout and our fields use.
 * WooCommerce's Place order button is blocked while hasValidationErrors() is
 * true, and showAllValidationErrors() is what it calls when it is pressed.
 */
import { createReduxStore, register, dispatch, select } from '@wordpress/data';

export const VALIDATION_STORE = 'wc/store/validation';

interface ValidationError {
	message: string;
	hidden: boolean;
}

type State = Record< string, ValidationError >;

type Action =
	| { type: 'SET'; errors: State }
	| { type: 'CLEAR'; id: string }
	| { type: 'SHOW'; id: string }
	| { type: 'HIDE'; id: string }
	| { type: 'SHOW_ALL' }
	| { type: 'RESET' };

function reducer( state: State = {}, action: Action ): State {
	switch ( action.type ) {
		case 'SET':
			return { ...state, ...action.errors };
		case 'CLEAR': {
			// Like WooCommerce's store: a new state even when nothing was there.
			const next = { ...state };
			delete next[ action.id ];
			return next;
		}
		case 'SHOW':
		case 'HIDE':
			return state[ action.id ]
				? {
						...state,
						[ action.id ]: {
							...state[ action.id ],
							hidden: action.type === 'HIDE',
						},
					}
				: state;
		case 'SHOW_ALL':
			return Object.fromEntries(
				Object.entries( state ).map( ( [ id, error ] ) => [
					id,
					{ ...error, hidden: false },
				] )
			);
		case 'RESET':
			return {};
	}

	return state;
}

const store = createReduxStore( VALIDATION_STORE, {
	reducer,
	actions: {
		setValidationErrors: ( errors: State ) => ( { type: 'SET', errors } ),
		clearValidationError: ( id: string ) => ( { type: 'CLEAR', id } ),
		showValidationError: ( id: string ) => ( { type: 'SHOW', id } ),
		hideValidationError: ( id: string ) => ( { type: 'HIDE', id } ),
		showAllValidationErrors: () => ( { type: 'SHOW_ALL' } ),
		reset: () => ( { type: 'RESET' } ),
	},
	selectors: {
		getValidationError: ( state: State, id: string ) => state[ id ],
		getValidationErrorId: ( state: State, id: string ) =>
			state[ id ] && ! state[ id ].hidden ? `validate-error-${ id }` : '',
		hasValidationErrors: ( state: State ) =>
			Object.keys( state ).length > 0,
		getAll: ( state: State ) => state,
	},
} );

let registered = false;

/**
 * Registers the store once and empties it.
 */
export function resetValidationStore(): void {
	if ( ! registered ) {
		register( store );
		registered = true;
	}
	( dispatch( VALIDATION_STORE ) as { reset: () => void } ).reset();
}

/**
 * The errors in the store, keyed by id.
 */
export function validationErrors(): State {
	return (
		select( VALIDATION_STORE ) as unknown as { getAll: () => State }
	 ).getAll();
}

/**
 * Does what WooCommerce does when Place order is pressed.
 */
export function pressPlaceOrder(): boolean {
	(
		dispatch( VALIDATION_STORE ) as { showAllValidationErrors: () => void }
	 ).showAllValidationErrors();

	return ! (
		select( VALIDATION_STORE ) as unknown as {
			hasValidationErrors: () => boolean;
		}
	 ).hasValidationErrors();
}
