/**
 * Actions for the selected rows (spec §6.2; frame 2b): count, Turn on, Turn
 * off, Copy to the other checkout, Delete (unavailable with a reason while a
 * built-in field is selected) and Clear selection.
 */
import { useId } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button } from '../../components';
import { checkoutTypeLabel } from '../../shell/labels';
import type { CheckoutType } from '../../types';

export interface SelectionBarProps {
	count: number;
	otherCheckoutType: CheckoutType;
	/** Why Delete is unavailable, or null when it is available. */
	deleteBlockedReason: string | null;
	onTurnOn: () => void;
	onTurnOff: () => void;
	onCopy: () => void;
	onDelete: () => void;
	onClear: () => void;
}

export function SelectionBar( {
	count,
	otherCheckoutType,
	deleteBlockedReason,
	onTurnOn,
	onTurnOff,
	onCopy,
	onDelete,
	onClear,
}: SelectionBarProps ) {
	const reasonId = useId();

	return (
		<div
			className="cfk-selection-bar"
			role="group"
			aria-label={ __( 'Selected fields', 'checkout-fieldkit' ) }
		>
			<strong className="cfk-selection-bar__count">
				{ sprintf(
					/* translators: %d: number of selected fields. */
					_n(
						'%d selected',
						'%d selected',
						count,
						'checkout-fieldkit'
					),
					count
				) }
			</strong>
			<Button size="small" onClick={ onTurnOn }>
				{ __( 'Turn on', 'checkout-fieldkit' ) }
			</Button>
			<Button size="small" onClick={ onTurnOff }>
				{ __( 'Turn off', 'checkout-fieldkit' ) }
			</Button>
			<Button size="small" onClick={ onCopy }>
				{ sprintf(
					/* translators: %s: the other checkout type, such as "Block checkout". */
					__( 'Copy to %s', 'checkout-fieldkit' ),
					checkoutTypeLabel( otherCheckoutType )
				) }
			</Button>
			<Button
				size="small"
				aria-disabled={ deleteBlockedReason ? true : undefined }
				aria-describedby={ deleteBlockedReason ? reasonId : undefined }
				onClick={ () => {
					if ( ! deleteBlockedReason ) {
						onDelete();
					}
				} }
			>
				{ __( 'Delete', 'checkout-fieldkit' ) }
			</Button>
			{ deleteBlockedReason && (
				<span id={ reasonId } className="cfk-selection-bar__why">
					{ deleteBlockedReason }
				</span>
			) }
			<span className="cfk-selection-bar__spacer" />
			<Button size="small" onClick={ onClear }>
				{ __( 'Clear selection', 'checkout-fieldkit' ) }
			</Button>
		</div>
	);
}
