/**
 * Keeps one field's problem in WooCommerce's validation store, the way
 * WooCommerce's own fields do: the error is there (hidden) as long as the
 * value is invalid, so Place order stays blocked; it is shown once the
 * shopper leaves or changes the field, or presses Place order (WooCommerce
 * then shows every error).
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/** WooCommerce's validation data store. */
export const VALIDATION_STORE = 'wc/store/validation';

interface ValidationError {
	message: string;
	hidden: boolean;
}

interface ValidationActions {
	setValidationErrors: ( errors: Record< string, ValidationError > ) => void;
	clearValidationError: ( id: string ) => void;
}

interface ValidationSelectors {
	getValidationError: ( id: string ) => ValidationError | undefined;
}

/**
 * @param errorId Id of the field's entry in the store.
 * @param message What is wrong with the value; '' when it is fine.
 * @return The error to show (undefined when none is shown) and a function
 *         that marks the field as visited, so its error shows.
 */
export default function useFieldValidation(
	errorId: string,
	message: string
): { shownError: string | undefined; reveal: () => void } {
	const [ visited, setVisited ] = useState( false );
	const { setValidationErrors, clearValidationError } = useDispatch(
		VALIDATION_STORE
	) as unknown as ValidationActions;
	const error = useSelect(
		( select ) =>
			(
				select( VALIDATION_STORE ) as ValidationSelectors | undefined
			 )?.getValidationError( errorId ),
		[ errorId ]
	);
	const hiddenNow = error ? error.hidden : true;

	useEffect( () => {
		if ( message === '' ) {
			clearValidationError( errorId );
			return;
		}
		// Once shown (by the shopper or by Place order), it stays shown.
		setValidationErrors( {
			[ errorId ]: { message, hidden: ! visited && hiddenNow },
		} );
		// eslint-disable-next-line react-hooks/exhaustive-deps -- hiddenNow is read, not watched.
	}, [
		errorId,
		message,
		visited,
		setValidationErrors,
		clearValidationError,
	] );

	useEffect(
		() => () => clearValidationError( errorId ),
		[ errorId, clearValidationError ]
	);

	const reveal = useCallback( () => setVisited( true ), [] );

	return {
		shownError: error && ! error.hidden ? error.message : undefined,
		reveal,
	};
}
