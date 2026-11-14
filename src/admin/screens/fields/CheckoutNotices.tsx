/**
 * Notices above the field list (spec §6.2, §5): the checkout type being
 * viewed is not the one shoppers see, or WooCommerce is too old for Block
 * checkout fields.
 */
import { createInterpolateElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Notice, VisuallyHidden } from '../../components';
import { checkoutTypeLabel } from '../../shell/labels';
import { useContextData } from '../../store';
import type { CheckoutType } from '../../types';
import { otherCheckoutType } from './rules';

export function CheckoutNotices( {
	checkoutType,
}: {
	checkoutType: CheckoutType;
} ) {
	const context = useContextData();

	if ( checkoutType === 'block' && ! context.blockSupported ) {
		return (
			<Notice tone="warning" className="cfk-fields-notice">
				{ sprintf(
					/* translators: %s: WooCommerce version, such as 8.5.0. */
					__(
						'Block checkout fields need WooCommerce 8.9 or newer. This store runs WooCommerce %s, so these fields have no effect until WooCommerce is updated.',
						'checkout-fieldkit'
					),
					context.wooCommerceVersion
				) }
			</Notice>
		);
	}

	if (
		context.activeCheckoutType === 'unknown' ||
		context.activeCheckoutType === checkoutType
	) {
		return null;
	}

	const message = createInterpolateElement(
		sprintf(
			/* translators: 1: the checkout type in use, such as "Block checkout", 2: the one being viewed. */
			__(
				"Your Checkout page uses the <strong>%1$s</strong>, so shoppers don't see these fields right now. They apply if the Checkout page is switched to the %2$s.",
				'checkout-fieldkit'
			),
			checkoutTypeLabel( otherCheckoutType( checkoutType ) ),
			checkoutTypeLabel( checkoutType )
		),
		{ strong: <strong /> }
	);

	return (
		<Notice
			tone="warning"
			className="cfk-fields-notice"
			actions={
				context.checkoutPageUrl ? (
					<a
						href={ context.checkoutPageUrl }
						target="_blank"
						rel="noreferrer"
					>
						{ __( 'Open the Checkout page', 'checkout-fieldkit' ) }{ ' ' }
						<VisuallyHidden>
							{ __(
								'(opens in a new tab)',
								'checkout-fieldkit'
							) }
						</VisuallyHidden>
					</a>
				) : undefined
			}
		>
			<p>{ message }</p>
		</Notice>
	);
}
