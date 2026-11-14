/**
 * Helpers for in-app links: real `<a href>`s that the app handles itself on
 * a plain click, and the browser handles otherwise (new tab, new window).
 */
import type { MouseEvent } from 'react';

/**
 * Whether a click is a plain left click the app should handle.
 *
 * @param event Click event.
 */
export function isPlainClick( event: MouseEvent ): boolean {
	return (
		! event.defaultPrevented &&
		event.button === 0 &&
		! event.metaKey &&
		! event.ctrlKey &&
		! event.shiftKey &&
		! event.altKey
	);
}
