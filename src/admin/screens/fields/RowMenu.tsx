/**
 * A row's More menu (spec §6.2): Copy to the other checkout, and Delete for
 * custom fields. Actions that can't be done stay in the menu, unavailable,
 * with the reason under them.
 */
import { DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { checkoutTypeLabel } from '../../shell/labels';
import type { CheckoutType, Field } from '../../types';
import { MoreIcon } from './icons';
import { displayName, type CopyCheck } from './rules';

/**
 * Keeps an unavailable item focusable, so keyboard and screen reader users
 * reach it and hear why. MenuItem passes it on to its Button; its types
 * don't list it yet.
 */
const STAY_FOCUSABLE = { accessibleWhenDisabled: true } as Record<
	string,
	unknown
>;

export interface RowMenuProps {
	field: Field;
	otherCheckoutType: CheckoutType;
	copy: CopyCheck;
	onCopy: () => void;
	onDelete: () => void;
}

export function RowMenu( {
	field,
	otherCheckoutType,
	copy,
	onCopy,
	onDelete,
}: RowMenuProps ) {
	return (
		<DropdownMenu
			icon={ <MoreIcon /> }
			label={ sprintf(
				/* translators: %s: field label. */
				__( 'More actions for %s', 'checkout-fieldkit' ),
				displayName( field )
			) }
			className="cfk-row-menu"
			toggleProps={ {
				className:
					'cfk-button cfk-button--secondary cfk-button--small cfk-button--icon-only',
				showTooltip: false,
			} }
			popoverProps={ {
				placement: 'bottom-end',
				className: 'cfk-row-menu__popover',
			} }
		>
			{ ( { onClose } ) => (
				<MenuGroup>
					<MenuItem
						disabled={ ! copy.ok }
						{ ...STAY_FOCUSABLE }
						info={ copy.ok ? undefined : copy.reason }
						onClick={ () => {
							if ( copy.ok ) {
								onClose();
								onCopy();
							}
						} }
					>
						{ sprintf(
							/* translators: %s: the other checkout type, such as "Block checkout". */
							__( 'Copy to %s', 'checkout-fieldkit' ),
							checkoutTypeLabel( otherCheckoutType )
						) }
					</MenuItem>
					<MenuItem
						disabled={ field.builtIn }
						{ ...STAY_FOCUSABLE }
						isDestructive={ ! field.builtIn }
						info={
							field.builtIn
								? __(
										'Built-in fields can be turned off, not deleted.',
										'checkout-fieldkit'
									)
								: undefined
						}
						onClick={ () => {
							if ( ! field.builtIn ) {
								onClose();
								onDelete();
							}
						} }
					>
						{ __( 'Delete', 'checkout-fieldkit' ) }
					</MenuItem>
				</MenuGroup>
			) }
		</DropdownMenu>
	);
}
