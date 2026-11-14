/**
 * Entry point of the admin app. Mounts into #cfk-admin-root with the data
 * the page passed in `window.checkoutFieldkitAdmin`.
 */
import { createRoot } from '@wordpress/element';
import App from './App';
import type { AdminSettings } from './types';
import './styles/tokens.css';
import './styles/components.css';
import './styles/shell.css';
import './styles/fields.css';

declare global {
	interface Window {
		checkoutFieldkitAdmin?: AdminSettings;
	}
}

const container = document.getElementById( 'cfk-admin-root' );

if ( container ) {
	createRoot( container ).render(
		<App settings={ window.checkoutFieldkitAdmin || {} } />
	);
}
