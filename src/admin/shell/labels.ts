/**
 * Display names shared by the shell and the screens.
 */
import { __ } from '@wordpress/i18n';
import type { CheckoutType } from '../types';

export function checkoutTypeLabel( type: CheckoutType ): string {
	return type === 'classic'
		? __( 'Classic checkout', 'checkout-fieldkit' )
		: __( 'Block checkout', 'checkout-fieldkit' );
}
