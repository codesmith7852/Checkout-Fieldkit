/**
 * Frontend of our Block checkout inner blocks: registers the Contact and
 * Additional blocks with WooCommerce's checkout, which renders them where
 * the page has them.
 *
 * Both are registered as forced (`force: true`): WooCommerce renders a forced
 * block at the end of its parent area when the page lacks it. The Contact
 * block relies on that (it then appears inside the Contact information block,
 * after its fields). The Additional block is normally already placed by the
 * server after the Additional information block (CheckoutBlocks in PHP);
 * forcing it only matters if that failed, so its fields are never missing.
 */
import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';
import SectionFields, {
	type CheckoutExtensionData,
} from './fields/SectionFields';
import { BLOCKS } from './blocks';
import type { Section } from './types';
import './index.css';

/**
 * The frontend component of one section's block.
 *
 * @param section Section.
 */
function sectionBlock( section: Section ) {
	return function CheckoutFieldKitBlock( {
		checkoutExtensionData,
	}: {
		checkoutExtensionData?: CheckoutExtensionData;
	} ) {
		return (
			<SectionFields
				section={ section }
				checkoutExtensionData={ checkoutExtensionData }
			/>
		);
	};
}

for ( const { metadata, section } of BLOCKS ) {
	registerCheckoutBlock( {
		metadata: {
			...metadata,
			attributes: {
				...metadata.attributes,
				lock: { type: 'object', default: { remove: true } },
			},
		},
		component: sectionBlock( section ),
		force: true,
	} );
}
