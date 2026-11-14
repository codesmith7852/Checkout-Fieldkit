/**
 * Classic checkout / Block checkout switch, with In use / Not in use pills
 * from the context's active checkout type (spec §6.2). Real links, so each
 * side can open in a new tab.
 */
import { __ } from '@wordpress/i18n';
import { Pill, cx } from '../../components';
import { useLocation } from '../../location';
import { checkoutTypeLabel } from '../../shell/labels';
import { isPlainClick } from '../../shell/links';
import { useContextData } from '../../store';
import type { CheckoutType } from '../../types';

const CHECKOUT_TYPES: CheckoutType[] = [ 'classic', 'block' ];

export function CheckoutTypeSwitch() {
	const context = useContextData();
	const { location, navigate, hrefFor } = useLocation();

	return (
		<div
			className="cfk-segmented"
			role="group"
			aria-label={ __( 'Checkout type', 'checkout-fieldkit' ) }
		>
			{ CHECKOUT_TYPES.map( ( type ) => {
				const current = location.checkout === type;
				const inUse = context.activeCheckoutType === type;
				return (
					<a
						key={ type }
						href={ hrefFor( { tab: 'fields', checkout: type } ) }
						className={ cx(
							'cfk-segmented__item',
							current && 'is-current'
						) }
						aria-current={ current ? 'page' : undefined }
						onClick={ ( event ) => {
							if ( isPlainClick( event ) ) {
								event.preventDefault();
								navigate( { checkout: type } );
							}
						} }
					>
						{ checkoutTypeLabel( type ) }
						{ context.activeCheckoutType !== 'unknown' && (
							<Pill tone={ inUse ? 'success' : 'neutral' }>
								{ inUse
									? __( 'In use', 'checkout-fieldkit' )
									: __( 'Not in use', 'checkout-fieldkit' ) }
							</Pill>
						) }
					</a>
				);
			} ) }
		</div>
	);
}
