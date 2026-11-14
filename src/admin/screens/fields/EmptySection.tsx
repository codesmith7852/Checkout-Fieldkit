/**
 * A section with no fields (spec §6.2; frame "empty section"): an
 * illustration, where its fields appear, a few examples, and Add field.
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '../../components';
import type { CheckoutType } from '../../types';
import { EmptyFormIllustration, PlusIcon } from './icons';

interface SectionHelp {
	where: string;
	examples: string[];
}

function helpFor( checkoutType: CheckoutType, section: string ): SectionHelp {
	const key = `${ checkoutType }:${ section }`;
	switch ( key ) {
		case 'classic:billing':
			return {
				where: __(
					'Fields here appear in the billing details form, which every shopper fills in.',
					'checkout-fieldkit'
				),
				examples: [
					__( 'VAT number', 'checkout-fieldkit' ),
					__( 'Purchase order number', 'checkout-fieldkit' ),
					__( 'Company registration number', 'checkout-fieldkit' ),
				],
			};
		case 'classic:shipping':
			return {
				where: __(
					'Fields here appear in the shipping address form, which shoppers see when they ship to a different address.',
					'checkout-fieldkit'
				),
				examples: [
					__( 'Building access code', 'checkout-fieldkit' ),
					__( 'Safe place for parcels', 'checkout-fieldkit' ),
					__( 'Delivery instructions', 'checkout-fieldkit' ),
				],
			};
		case 'block:contact':
			return {
				where: __(
					'Fields here appear with the email address at the start of the checkout. Use them for how to reach the shopper.',
					'checkout-fieldkit'
				),
				examples: [
					__( 'Mobile for delivery updates', 'checkout-fieldkit' ),
					__( 'How did you hear about us?', 'checkout-fieldkit' ),
					__( 'Newsletter sign-up', 'checkout-fieldkit' ),
				],
			};
		case 'block:address':
			return {
				where: __(
					'Fields here appear in both the billing and the shipping address forms.',
					'checkout-fieldkit'
				),
				examples: [
					__( 'Building access code', 'checkout-fieldkit' ),
					__( 'House name', 'checkout-fieldkit' ),
					__( 'Floor or unit', 'checkout-fieldkit' ),
				],
			};
		case 'classic:additional':
		case 'block:additional':
			return {
				where:
					checkoutType === 'block'
						? __(
								"Fields here appear in the order information area of the checkout, after the address. Use them for anything that isn't part of an address.",
								'checkout-fieldkit'
							)
						: __(
								"Fields here appear after the addresses, with the order notes. Use them for anything that isn't part of an address.",
								'checkout-fieldkit'
							),
				examples: [
					__( 'Gift message', 'checkout-fieldkit' ),
					__( 'Preferred delivery date', 'checkout-fieldkit' ),
					__( 'Purchase order number', 'checkout-fieldkit' ),
				],
			};
		default:
			return {
				where: __(
					'Fields you add here appear in this part of the checkout.',
					'checkout-fieldkit'
				),
				examples: [],
			};
	}
}

export interface EmptySectionProps {
	checkoutType: CheckoutType;
	section: string;
	sectionLabel: string;
	onAdd: () => void;
}

export function EmptySection( {
	checkoutType,
	section,
	sectionLabel,
	onAdd,
}: EmptySectionProps ) {
	const help = helpFor( checkoutType, section );
	return (
		<div className="cfk-empty-section">
			<EmptyFormIllustration />
			<h2 className="cfk-empty-section__title">
				{ sprintf(
					/* translators: %s: section, such as "Additional". */
					__( 'No fields in %s yet', 'checkout-fieldkit' ),
					sectionLabel
				) }
			</h2>
			<p className="cfk-empty-section__text">{ help.where }</p>
			{ help.examples.length > 0 && (
				<ul
					className="cfk-empty-section__examples"
					aria-label={ __( 'Examples', 'checkout-fieldkit' ) }
				>
					{ help.examples.map( ( example ) => (
						<li key={ example }>{ example }</li>
					) ) }
				</ul>
			) }
			<Button variant="primary" icon={ <PlusIcon /> } onClick={ onAdd }>
				{ __( 'Add field', 'checkout-fieldkit' ) }
			</Button>
		</div>
	);
}
