/**
 * Entry point of the admin app. Mounts into #cfk-admin-root when the page has it.
 */
import { createRoot } from '@wordpress/element';
import App from './App';
import './index.css';

const container = document.getElementById( 'cfk-admin-root' );

if ( container ) {
	createRoot( container ).render( <App /> );
}
