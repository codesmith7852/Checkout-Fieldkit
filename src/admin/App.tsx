/**
 * Placeholder for the admin app (WooCommerce → Checkout Fields).
 * The real screens arrive in later tasks.
 */
import { __ } from '@wordpress/i18n';

export default function App() {
	return (
		<div className="cfk-admin">
			<h1 className="cfk-admin__title">
				{ __( 'Checkout Fields', 'checkout-fieldkit' ) }
			</h1>
		</div>
	);
}
