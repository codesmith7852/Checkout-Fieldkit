/**
 * Fields screen (spec §6.2; frames 1, 2, 2b, 3b): the Classic/Block switch,
 * notices about the checkout type being viewed, and the field list of each
 * section in tabs with field counts.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Card, Tabs } from '../components';
import { useLocation } from '../location';
import { checkoutTypeLabel } from '../shell/labels';
import { ScreenHeader } from '../shell/ScreenHeader';
import { useFieldSet } from '../store';
import { CheckoutNotices } from './fields/CheckoutNotices';
import { CheckoutTypeSwitch } from './fields/CheckoutTypeSwitch';
import { SectionFields } from './fields/SectionFields';

export function FieldsScreen() {
	const { location, navigate } = useLocation();
	const { sections } = useFieldSet( location.checkout );
	const current =
		sections.find( ( section ) => section.name === location.section ) ||
		sections[ 0 ];

	return (
		<>
			<ScreenHeader
				title={ __( 'Checkout fields', 'checkout-fieldkit' ) }
				description={ __(
					'Choose what shoppers fill in at checkout, and in what order.',
					'checkout-fieldkit'
				) }
				actions={ <CheckoutTypeSwitch /> }
			/>
			<CheckoutNotices checkoutType={ location.checkout } />
			<Card
				label={ sprintf(
					/* translators: %s: checkout type, such as "Block checkout". */
					__( '%s fields', 'checkout-fieldkit' ),
					checkoutTypeLabel( location.checkout )
				) }
				className="cfk-fields-card"
			>
				<Tabs
					label={ __( 'Sections', 'checkout-fieldkit' ) }
					tabs={ sections.map( ( section ) => ( {
						id: section.name,
						label: section.label,
						count: section.rows.length,
					} ) ) }
					selected={ current?.name || '' }
					onSelect={ ( section ) =>
						navigate( { section }, { replace: true } )
					}
				>
					{ current && (
						<SectionFields
							key={ `${ location.checkout }:${ current.name }` }
							checkoutType={ location.checkout }
							section={ current }
						/>
					) }
				</Tabs>
			</Card>
		</>
	);
}
