/**
 * What our inner blocks show in the editor: a placeholder that names the
 * section's fields, since their look and values belong to the checkout.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';
import { getSectionFields } from '../settings';
import type { Section } from '../types';

const TITLES: Record< Section, () => string > = {
	contact: () => __( 'Contact fields', 'checkout-fieldkit' ),
	additional: () => __( 'Additional fields', 'checkout-fieldkit' ),
};

export default function Preview( { section }: { section: Section } ) {
	const blockProps = useBlockProps( {
		className: 'cfk-checkout-block-preview',
	} );
	const fields = getSectionFields( section );
	const adminUrl = getSetting< string >( 'adminUrl', '' );

	return (
		<div { ...blockProps }>
			<Placeholder
				icon="feedback"
				label={ TITLES[ section ]() }
				instructions={
					fields.length > 0
						? sprintf(
								/* translators: %d: number of fields. */
								_n(
									'Shoppers see %d field here, as set up under WooCommerce → Checkout Fields:',
									'Shoppers see %d fields here, as set up under WooCommerce → Checkout Fields:',
									fields.length,
									'checkout-fieldkit'
								),
								fields.length
							)
						: __(
								'No custom fields in this section yet, so shoppers see nothing here. Add some under WooCommerce → Checkout Fields.',
								'checkout-fieldkit'
							)
				}
			>
				{ fields.length > 0 && (
					<ul className="cfk-checkout-block-preview__fields">
						{ fields.map( ( field ) => (
							<li key={ field.name }>
								{ field.label.trim() !== ''
									? field.label
									: field.name }
								{ field.required ? ' *' : '' }
							</li>
						) ) }
					</ul>
				) }
				{ adminUrl && (
					<a href={ `${ adminUrl }admin.php?page=checkout-fieldkit` }>
						{ __( 'Edit checkout fields', 'checkout-fieldkit' ) }
					</a>
				) }
			</Placeholder>
		</div>
	);
}
