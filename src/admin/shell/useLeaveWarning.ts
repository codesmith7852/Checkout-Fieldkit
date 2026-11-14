/**
 * Asks the browser to confirm leaving the page while there are unsaved
 * changes (spec §6.1). Browsers show their own wording.
 */
import { useEffect } from '@wordpress/element';

/**
 * @param active Whether leaving should be confirmed.
 */
export function useLeaveWarning( active: boolean ): void {
	useEffect( () => {
		if ( ! active ) {
			return undefined;
		}
		const onBeforeUnload = ( event: BeforeUnloadEvent ) => {
			event.preventDefault();
			// Older browsers need returnValue set.
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', onBeforeUnload );
		return () =>
			window.removeEventListener( 'beforeunload', onBeforeUnload );
	}, [ active ] );
}
