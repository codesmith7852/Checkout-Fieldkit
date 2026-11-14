/**
 * Fields screen (spec §6.2), placeholder: the title, the Classic/Block
 * switch with In use / Not in use, and section tabs with a plain list of the
 * fields. Issue #9 builds the field rows and their actions.
 */
import { __ } from '@wordpress/i18n';
import { Card, Pill, Tabs, cx } from '../components';
import { useLocation } from '../location';
import { checkoutTypeLabel } from '../shell/labels';
import { isPlainClick } from '../shell/links';
import { ScreenHeader } from '../shell/ScreenHeader';
import { useContextData, useFieldSet } from '../store';
import type { CheckoutType } from '../types';

const CHECKOUT_TYPES: CheckoutType[] = [ 'classic', 'block' ];

function CheckoutTypeSwitch() {
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

export function FieldsScreen() {
	const { location, navigate } = useLocation();
	const { sections } = useFieldSet( location.checkout );
	const current = sections.find(
		( section ) => section.name === location.section
	);

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
			<Card
				label={ checkoutTypeLabel( location.checkout ) }
				className="cfk-fields-card"
			>
				<Tabs
					label={ __( 'Sections', 'checkout-fieldkit' ) }
					tabs={ sections.map( ( section ) => ( {
						id: section.name,
						label: section.label,
						count: section.rows.length,
					} ) ) }
					selected={ location.section }
					onSelect={ ( section ) =>
						navigate( { section }, { replace: true } )
					}
				>
					<ul className="cfk-placeholder-list">
						{ ( current?.rows || [] ).map( ( row ) => (
							<li key={ row.id }>
								<span className="cfk-placeholder-list__label">
									{ row.field.label || row.field.name }
								</span>
								<code className="cfk-placeholder-list__name">
									{ row.field.name }
								</code>
							</li>
						) ) }
					</ul>
				</Tabs>
			</Card>
		</>
	);
}
