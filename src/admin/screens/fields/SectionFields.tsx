/**
 * One section of a field set on the Fields screen (spec §6.2): toolbar or
 * selection bar, the field table (or the empty state), and what the actions
 * on it need: the essential-field confirmation, copying to the other
 * checkout, and deleting with undo.
 */
import {
	createInterpolateElement,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { MouseEvent } from 'react';
import { Button, Notice, useAnnounce } from '../../components';
import { useFieldEditor } from '../../editor';
import { useLocation } from '../../location';
import { checkoutTypeLabel } from '../../shell/labels';
import { isPlainClick } from '../../shell/links';
import {
	useContextData,
	useFieldSet,
	type FieldRow,
	type SectionRows,
} from '../../store';
import type { CheckoutType, Field, FieldSet } from '../../types';
import { EmptySection } from './EmptySection';
import { EssentialDialog, type EssentialChange } from './EssentialDialog';
import { FieldTable } from './FieldTable';
import { LockIcon, PlusIcon, SmallInfoIcon } from './icons';
import {
	checkCopy,
	displayName,
	essentialConsequence,
	isLocked,
	otherCheckoutType,
} from './rules';
import { SelectionBar } from './SelectionBar';

interface DeletedField {
	id: string;
	section: string;
	index: number;
	field: Field;
}

type Feedback =
	| { kind: 'deleted'; items: DeletedField[] }
	| {
			kind: 'copied';
			to: CheckoutType;
			copied: Array< {
				name: string;
				section: string;
				sectionLabel: string;
			} >;
			refused: string[];
	  };

interface PendingConfirm {
	request: EssentialChange;
	apply: () => void;
}

export interface SectionFieldsProps {
	checkoutType: CheckoutType;
	section: SectionRows;
}

function copyTitle( feedback: Feedback & { kind: 'copied' } ): string {
	const to = checkoutTypeLabel( feedback.to );
	if ( feedback.copied.length === 0 ) {
		return sprintf(
			/* translators: %s: checkout type, such as "Block checkout". */
			__( 'Nothing was copied to the %s.', 'checkout-fieldkit' ),
			to
		);
	}
	return sprintf(
		/* translators: 1: number of fields, 2: checkout type, such as "Block checkout". */
		_n(
			'Copied %1$d field to the %2$s.',
			'Copied %1$d fields to the %2$s.',
			feedback.copied.length,
			'checkout-fieldkit'
		),
		feedback.copied.length,
		to
	);
}

function deletedTitle( items: DeletedField[] ): string {
	return items.length === 1
		? sprintf(
				/* translators: %s: field label. */
				__( 'Deleted %s.', 'checkout-fieldkit' ),
				displayName( items[ 0 ].field )
			)
		: sprintf(
				/* translators: %d: number of fields. */
				_n(
					'Deleted %d field.',
					'Deleted %d fields.',
					items.length,
					'checkout-fieldkit'
				),
				items.length
			);
}

export function SectionFields( { checkoutType, section }: SectionFieldsProps ) {
	const context = useContextData();
	const fieldSet = useFieldSet( checkoutType );
	const other = useFieldSet( otherCheckoutType( checkoutType ) );
	const editor = useFieldEditor();
	const announce = useAnnounce();
	const { navigate, hrefFor } = useLocation();

	const [ selectedIds, setSelectedIds ] = useState< string[] >( [] );
	const [ confirm, setConfirm ] = useState< PendingConfirm | null >( null );
	const [ feedback, setFeedback ] = useState< Feedback | null >( null );
	const [ focusRowId, setFocusRowId ] = useState< string | null >( null );
	const undoRef = useRef< HTMLButtonElement >( null );
	const panelRef = useRef< HTMLDivElement >( null );

	const { rows } = section;
	const selectedRows = rows.filter( ( row ) =>
		selectedIds.includes( row.id )
	);
	const selected = selectedRows.map( ( row ) => row.id );
	const toLabel = checkoutTypeLabel( otherCheckoutType( checkoutType ) );

	// After a delete, the row (and the control used) is gone: offer Undo.
	useEffect( () => {
		if ( feedback?.kind === 'deleted' ) {
			undoRef.current?.focus();
		}
	}, [ feedback ] );

	// After an undo, go back to the restored row.
	useEffect( () => {
		if ( ! focusRowId ) {
			return;
		}
		panelRef.current
			?.querySelector< HTMLElement >(
				`[data-row-id="${ focusRowId }"] .cfk-field-row__select`
			)
			?.focus();
		setFocusRowId( null );
	}, [ focusRowId ] );

	const consequenceOf = ( row: FieldRow ) =>
		essentialConsequence( context, checkoutType, row.field );

	const onSelect = ( ids: string[], value: boolean ) => {
		const next = value
			? [ ...new Set( [ ...selected, ...ids ] ) ]
			: selected.filter( ( id ) => ! ids.includes( id ) );
		setSelectedIds( next );
		// The selection bar appears above the table, away from the focused box.
		announce(
			next.length > 0
				? sprintf(
						/* translators: %d: number of selected fields. */
						_n(
							'%d field selected. Actions for it are above the table.',
							'%d fields selected. Actions for them are above the table.',
							next.length,
							'checkout-fieldkit'
						),
						next.length
					)
				: __( 'No fields selected.', 'checkout-fieldkit' )
		);
	};

	const onEnabledChange = ( row: FieldRow, enabled: boolean ) => {
		const consequence = consequenceOf( row );
		if ( ! enabled && row.field.enabled && consequence ) {
			setConfirm( {
				request: {
					change: 'off',
					rows: [ row ],
					essentials: [ { row, consequence } ],
				},
				apply: () => fieldSet.setEnabled( row.id, false ),
			} );
			return;
		}
		fieldSet.setEnabled( row.id, enabled );
	};

	const onRequiredChange = ( row: FieldRow, required: boolean ) => {
		const consequence = consequenceOf( row );
		if ( ! required && row.field.required && consequence ) {
			setConfirm( {
				request: {
					change: 'optional',
					rows: [ row ],
					essentials: [ { row, consequence } ],
				},
				apply: () => fieldSet.setRequired( row.id, false ),
			} );
			return;
		}
		fieldSet.setRequired( row.id, required );
	};

	const turnSelected = ( enabled: boolean ) => {
		const locked = selectedRows.filter( ( row ) =>
			isLocked( context, checkoutType, row.field, 'enabled' )
		);
		const changing = selectedRows.filter(
			( row ) => ! locked.includes( row ) && row.field.enabled !== enabled
		);

		const apply = () => {
			if ( changing.length > 0 ) {
				fieldSet.setEnabled(
					changing.map( ( row ) => row.id ),
					enabled
				);
			}
			const parts: string[] = [];
			if ( changing.length > 0 && enabled ) {
				parts.push(
					sprintf(
						/* translators: %d: number of fields. */
						_n(
							'Turned on %d field.',
							'Turned on %d fields.',
							changing.length,
							'checkout-fieldkit'
						),
						changing.length
					)
				);
			} else if ( changing.length > 0 ) {
				parts.push(
					sprintf(
						/* translators: %d: number of fields. */
						_n(
							'Turned off %d field.',
							'Turned off %d fields.',
							changing.length,
							'checkout-fieldkit'
						),
						changing.length
					)
				);
			}
			locked.forEach( ( row ) =>
				parts.push(
					sprintf(
						/* translators: %s: field label. */
						__(
							'WooCommerce always shows %s.',
							'checkout-fieldkit'
						),
						displayName( row.field )
					)
				)
			);
			announce(
				parts.join( ' ' ) ||
					( enabled
						? __(
								'The selected fields are already on.',
								'checkout-fieldkit'
							)
						: __(
								'The selected fields are already off.',
								'checkout-fieldkit'
							) )
			);
		};

		const essentials = enabled
			? []
			: changing.flatMap( ( row ) => {
					const consequence = consequenceOf( row );
					return consequence ? [ { row, consequence } ] : [];
				} );
		if ( essentials.length > 0 ) {
			setConfirm( {
				request: { change: 'off', rows: changing, essentials },
				apply,
			} );
			return;
		}
		apply();
	};

	const copyRows = ( targets: FieldRow[] ) => {
		const to = otherCheckoutType( checkoutType );
		let target: FieldSet = other.fieldSet;
		const copied: Array< {
			name: string;
			section: string;
			sectionLabel: string;
		} > = [];
		const refused: string[] = [];

		targets.forEach( ( row ) => {
			const check = checkCopy(
				context,
				checkoutType,
				row.section,
				row.field,
				target
			);
			if ( ! check.ok ) {
				refused.push( check.reason );
				return;
			}
			other.addField( check.section, check.field );
			target = {
				...target,
				sections: {
					...target.sections,
					[ check.section ]: [
						...( target.sections[ check.section ] || [] ),
						check.field,
					],
				},
			};
			copied.push( {
				name: displayName( row.field ),
				section: check.section,
				sectionLabel: check.sectionLabel,
			} );
		} );

		const result: Feedback = { kind: 'copied', to, copied, refused };
		setFeedback( result );
		announce( [ copyTitle( result ), ...refused ].join( ' ' ) );
	};

	const removeRows = ( targets: FieldRow[] ) => {
		const removable = targets.filter( ( row ) => ! row.field.builtIn );
		if ( removable.length === 0 ) {
			return;
		}
		const items = removable.map( ( row ) => ( {
			id: row.id,
			section: row.section,
			index: row.index,
			field: row.field,
		} ) );
		const ids = items.map( ( item ) => item.id );
		fieldSet.removeFields( ids );
		setSelectedIds( ( previous ) =>
			previous.filter( ( id ) => ! ids.includes( id ) )
		);
		setFeedback( { kind: 'deleted', items } );
		announce( deletedTitle( items ) );
	};

	const undoDelete = ( items: DeletedField[] ) => {
		[ ...items ]
			.sort( ( a, b ) => a.index - b.index )
			.forEach( ( item ) =>
				fieldSet.addField(
					item.section,
					item.field,
					item.index,
					item.id
				)
			);
		setFeedback( null );
		setFocusRowId( items[ 0 ].id );
		announce(
			items.length === 1
				? sprintf(
						/* translators: %s: field label. */
						__( 'Restored %s.', 'checkout-fieldkit' ),
						displayName( items[ 0 ].field )
					)
				: sprintf(
						/* translators: %d: number of fields. */
						_n(
							'Restored %d field.',
							'Restored %d fields.',
							items.length,
							'checkout-fieldkit'
						),
						items.length
					)
		);
	};

	const builtInSelected = selectedRows.filter( ( row ) => row.field.builtIn );
	let deleteBlockedReason: string | null = null;
	if ( builtInSelected.length === 1 ) {
		deleteBlockedReason = sprintf(
			/* translators: %s: field label. */
			__(
				'%s is built in, so it can be turned off but not deleted.',
				'checkout-fieldkit'
			),
			displayName( builtInSelected[ 0 ].field )
		);
	} else if ( builtInSelected.length > 1 ) {
		deleteBlockedReason = sprintf(
			/* translators: %d: number of built-in fields. */
			__(
				'%d of the selected fields are built in, so they can be turned off but not deleted.',
				'checkout-fieldkit'
			),
			builtInSelected.length
		);
	}

	const addField = () => editor.openNew( checkoutType, section.name );

	const goToCopies = ( feedbackTo: CheckoutType, sectionName: string ) => ( {
		href: hrefFor( {
			tab: 'fields',
			checkout: feedbackTo,
			section: sectionName,
		} ),
		onClick: ( event: MouseEvent< HTMLAnchorElement > ) => {
			if ( isPlainClick( event ) ) {
				event.preventDefault();
				navigate( { checkout: feedbackTo, section: sectionName } );
			}
		},
	} );

	return (
		<div ref={ panelRef } className="cfk-section-fields">
			{ checkoutType === 'block' && section.name === 'address' && (
				<p className="cfk-section-note">
					<SmallInfoIcon />
					{ __(
						'Fields in this section appear in both the billing and the shipping address forms.',
						'checkout-fieldkit'
					) }
				</p>
			) }

			{ rows.length > 0 &&
				( selected.length > 0 ? (
					<SelectionBar
						count={ selected.length }
						otherCheckoutType={ otherCheckoutType( checkoutType ) }
						deleteBlockedReason={ deleteBlockedReason }
						onTurnOn={ () => turnSelected( true ) }
						onTurnOff={ () => turnSelected( false ) }
						onCopy={ () => copyRows( selectedRows ) }
						onDelete={ () => removeRows( selectedRows ) }
						onClear={ () => setSelectedIds( [] ) }
					/>
				) : (
					<div className="cfk-fields-toolbar">
						<Button
							variant="primary"
							size="small"
							icon={ <PlusIcon /> }
							onClick={ addField }
						>
							{ __( 'Add field', 'checkout-fieldkit' ) }
						</Button>
						<span className="cfk-fields-toolbar__hint">
							<LockIcon />
							{ __(
								'Built-in fields can be turned off, not deleted.',
								'checkout-fieldkit'
							) }
						</span>
					</div>
				) ) }

			{ feedback?.kind === 'deleted' && (
				<Notice
					tone="info"
					className="cfk-fields-feedback"
					title={ deletedTitle( feedback.items ) }
					onDismiss={ () => setFeedback( null ) }
					actions={
						<Button
							ref={ undoRef }
							size="small"
							onClick={ () => undoDelete( feedback.items ) }
						>
							{ __( 'Undo', 'checkout-fieldkit' ) }
						</Button>
					}
				/>
			) }

			{ feedback?.kind === 'copied' && (
				<Notice
					tone={ feedback.refused.length > 0 ? 'warning' : 'success' }
					className="cfk-fields-feedback"
					title={ copyTitle( feedback ) }
					onDismiss={ () => setFeedback( null ) }
					actions={
						feedback.copied.length > 0 ? (
							<a
								{ ...goToCopies(
									feedback.to,
									feedback.copied[ 0 ].section
								) }
							>
								{ sprintf(
									/* translators: %s: checkout type, such as "Block checkout". */
									__( 'Go to %s', 'checkout-fieldkit' ),
									toLabel
								) }
							</a>
						) : undefined
					}
				>
					{ feedback.copied.length > 0 && (
						<ul className="cfk-fields-feedback__list">
							{ feedback.copied.map( ( item ) => (
								<li key={ item.name }>
									{ sprintf(
										/* translators: 1: field label, 2: section it was copied into, such as "Address". */
										__(
											'%1$s → %2$s',
											'checkout-fieldkit'
										),
										item.name,
										item.sectionLabel
									) }
								</li>
							) ) }
						</ul>
					) }
					{ feedback.refused.length > 0 && (
						<>
							<p>{ __( 'Not copied:', 'checkout-fieldkit' ) }</p>
							<ul className="cfk-fields-feedback__list">
								{ feedback.refused.map( ( reason ) => (
									<li key={ reason }>{ reason }</li>
								) ) }
							</ul>
						</>
					) }
				</Notice>
			) }

			{ rows.length > 0 ? (
				<>
					<FieldTable
						checkoutType={ checkoutType }
						sectionLabel={ section.label }
						context={ context }
						rows={ rows }
						selected={ selected }
						isEssential={ ( row ) => consequenceOf( row ) !== null }
						copyCheck={ ( row ) =>
							checkCopy(
								context,
								checkoutType,
								row.section,
								row.field,
								other.fieldSet
							)
						}
						onSelect={ onSelect }
						onMove={ ( id, index ) =>
							fieldSet.moveField( id, section.name, index )
						}
						onEnabledChange={ onEnabledChange }
						onRequiredChange={ onRequiredChange }
						onEdit={ ( row ) =>
							editor.openExisting( checkoutType, row.id )
						}
						onCopy={ ( row ) => copyRows( [ row ] ) }
						onDelete={ ( row ) => removeRows( [ row ] ) }
					/>
					<p className="cfk-fields-footer">
						{ createInterpolateElement(
							__(
								'Drag a row by its handle, or focus the handle and press <kbd>Space</kbd>, then <kbd>↑</kbd> <kbd>↓</kbd> to move it and <kbd>Space</kbd> to drop it.',
								'checkout-fieldkit'
							),
							{ kbd: <kbd /> }
						) }
					</p>
				</>
			) : (
				<EmptySection
					checkoutType={ checkoutType }
					section={ section.name }
					sectionLabel={ section.label }
					onAdd={ addField }
				/>
			) }

			{ confirm && (
				<EssentialDialog
					request={ confirm.request }
					onCancel={ () => setConfirm( null ) }
					onConfirm={ () => {
						confirm.apply();
						setConfirm( null );
					} }
				/>
			) }
		</div>
	);
}
