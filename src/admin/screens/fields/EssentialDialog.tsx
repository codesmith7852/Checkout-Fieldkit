/**
 * Confirmation before turning off an essential field, or making it optional
 * (spec §2.3, §6.2, ADR 0003; frame 3b). The safe choice is focused first.
 */
import { useId, useRef } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, Dialog } from '../../components';
import type { FieldRow } from '../../store';
import { WarningBadgeIcon } from './icons';
import { displayName } from './rules';

export interface EssentialChange {
	/** `off`: turn the fields off; `optional`: switch Required off. */
	change: 'off' | 'optional';
	/** Every field the change applies to. */
	rows: FieldRow[];
	/** The essential ones among them, each with what stops working. */
	essentials: Array< { row: FieldRow; consequence: string } >;
}

export interface EssentialDialogProps {
	request: EssentialChange;
	onConfirm: () => void;
	onCancel: () => void;
}

function title( request: EssentialChange ): string {
	const { change, rows, essentials } = request;
	if ( rows.length === 1 ) {
		const name = displayName( rows[ 0 ].field );
		return change === 'off'
			? /* translators: %s: field label. */
				sprintf( __( 'Turn off %s?', 'checkout-fieldkit' ), name )
			: /* translators: %s: field label. */
				sprintf( __( 'Make %s optional?', 'checkout-fieldkit' ), name );
	}
	return essentials.length === 1
		? __( 'Turn off an essential field?', 'checkout-fieldkit' )
		: sprintf(
				/* translators: %d: number of essential fields. */
				__( 'Turn off %d essential fields?', 'checkout-fieldkit' ),
				essentials.length
			);
}

export function EssentialDialog( {
	request,
	onConfirm,
	onCancel,
}: EssentialDialogProps ) {
	const keepRef = useRef< HTMLButtonElement >( null );
	const bodyId = useId();
	const { change, rows, essentials } = request;
	const single = rows.length === 1;
	const others = rows.length - essentials.length;

	return (
		<Dialog
			title={ title( request ) }
			role="alertdialog"
			onClose={ onCancel }
			initialFocusRef={ keepRef }
			describedBy={ bodyId }
			size="medium"
			className="cfk-essential-dialog"
			actions={
				<>
					<Button variant="danger" onClick={ onConfirm }>
						{ change === 'off'
							? __( 'Turn off anyway', 'checkout-fieldkit' )
							: __( 'Make it optional', 'checkout-fieldkit' ) }
					</Button>
					<Button
						ref={ keepRef }
						variant="primary"
						onClick={ onCancel }
					>
						{ change === 'off'
							? _n(
									'Keep it on',
									'Keep them on',
									essentials.length,
									'checkout-fieldkit'
								)
							: __( 'Keep it required', 'checkout-fieldkit' ) }
					</Button>
				</>
			}
		>
			<div className="cfk-essential-dialog__content">
				<span className="cfk-essential-dialog__badge">
					<WarningBadgeIcon />
				</span>
				<div id={ bodyId } className="cfk-essential-dialog__text">
					{ single && change === 'optional' && (
						<p>
							{ sprintf(
								/* translators: %s: field label. */
								__(
									'Shoppers will be able to leave %s empty.',
									'checkout-fieldkit'
								),
								displayName( rows[ 0 ].field )
							) }
						</p>
					) }
					{ single ? (
						<p>{ essentials[ 0 ].consequence }</p>
					) : (
						<ul className="cfk-essential-dialog__list">
							{ essentials.map( ( { row, consequence } ) => (
								<li key={ row.id }>
									<strong>
										{ displayName( row.field ) }
									</strong>{ ' ' }
									{ consequence }
								</li>
							) ) }
						</ul>
					) }
					{ others > 0 && (
						<p>
							{ sprintf(
								/* translators: %d: number of other selected fields. */
								_n(
									'The %d other selected field is turned off too.',
									'The %d other selected fields are turned off too.',
									others,
									'checkout-fieldkit'
								),
								others
							) }
						</p>
					) }
					<p>
						{ change === 'off'
							? __(
									'You can turn it back on at any time. Nothing changes on the checkout until you save.',
									'checkout-fieldkit'
								)
							: __(
									'You can make it required again at any time. Nothing changes on the checkout until you save.',
									'checkout-fieldkit'
								) }
					</p>
				</div>
			</div>
		</Dialog>
	);
}
