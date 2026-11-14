/**
 * The fields of one section as a table whose rows reorder by drag or by
 * keyboard (spec §6.2, §6.6): focus a row's handle, Space to pick it up,
 * the arrow keys to move it, Space to drop it, Escape to cancel. Each step
 * is announced through dnd-kit's live region.
 */
import {
	DndContext,
	KeyboardSensor,
	PointerSensor,
	closestCenter,
	useSensor,
	useSensors,
	type Announcements,
	type DragEndEvent,
	type Modifier,
	type UniqueIdentifier,
} from '@dnd-kit/core';
import {
	SortableContext,
	sortableKeyboardCoordinates,
	verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { useEffect, useMemo, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { VisuallyHidden } from '../../components';
import type { FieldRow } from '../../store';
import type { CheckoutType, Context } from '../../types';
import { FieldRowView } from './FieldRowView';
import { displayName, type CopyCheck } from './rules';

export interface FieldTableProps {
	checkoutType: CheckoutType;
	sectionLabel: string;
	context: Context;
	rows: FieldRow[];
	selected: string[];
	isEssential: ( row: FieldRow ) => boolean;
	copyCheck: ( row: FieldRow ) => CopyCheck;
	onSelect: ( ids: string[], selected: boolean ) => void;
	onMove: ( id: string, index: number ) => void;
	onEnabledChange: ( row: FieldRow, enabled: boolean ) => void;
	onRequiredChange: ( row: FieldRow, required: boolean ) => void;
	onEdit: ( row: FieldRow ) => void;
	onCopy: ( row: FieldRow ) => void;
	onDelete: ( row: FieldRow ) => void;
}

/**
 * Rows only move up and down.
 * @param root0
 * @param root0.transform
 */
const verticalOnly: Modifier = ( { transform } ) => ( { ...transform, x: 0 } );

export function FieldTable( {
	checkoutType,
	sectionLabel,
	context,
	rows,
	selected,
	isEssential,
	copyCheck,
	onSelect,
	onMove,
	onEnabledChange,
	onRequiredChange,
	onEdit,
	onCopy,
	onDelete,
}: FieldTableProps ) {
	const ids = useMemo( () => rows.map( ( row ) => row.id ), [ rows ] );
	const sensors = useSensors(
		useSensor( PointerSensor, {
			activationConstraint: { distance: 4 },
		} ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

	const allSelected = rows.length > 0 && selected.length === rows.length;
	const someSelected = selected.length > 0 && ! allSelected;
	const selectAllRef = useRef< HTMLInputElement >( null );
	useEffect( () => {
		if ( selectAllRef.current ) {
			selectAllRef.current.indeterminate = someSelected;
		}
	}, [ someSelected ] );

	// dnd-kit reports the row as over itself as soon as it is picked up; that
	// is not a move, and announcing it would cut off "Picked up".
	const hasMoved = useRef( false );

	const announcements = useMemo( (): Announcements => {
		const total = rows.length;
		const nameOf = ( id: UniqueIdentifier ) => {
			const row = rows.find( ( item ) => item.id === id );
			return row ? displayName( row.field ) : '';
		};
		const positionOf = ( id: UniqueIdentifier ) =>
			ids.indexOf( String( id ) ) + 1;

		return {
			onDragStart: ( { active } ) => {
				hasMoved.current = false;
				return sprintf(
					/* translators: 1: field label, 2: position, 3: number of fields. */
					__(
						'Picked up %1$s. It is at position %2$d of %3$d.',
						'checkout-fieldkit'
					),
					nameOf( active.id ),
					positionOf( active.id ),
					total
				);
			},
			onDragOver: ( { active, over } ) => {
				if (
					! over ||
					( over.id === active.id && ! hasMoved.current )
				) {
					return undefined;
				}
				hasMoved.current = true;
				return sprintf(
					/* translators: 1: field label, 2: position, 3: number of fields. */
					__(
						'%1$s moved to position %2$d of %3$d.',
						'checkout-fieldkit'
					),
					nameOf( active.id ),
					positionOf( over.id ),
					total
				);
			},
			onDragEnd: ( { active, over } ) =>
				sprintf(
					/* translators: 1: field label, 2: position, 3: number of fields. */
					__(
						'%1$s dropped at position %2$d of %3$d.',
						'checkout-fieldkit'
					),
					nameOf( active.id ),
					positionOf( over ? over.id : active.id ),
					total
				),
			onDragCancel: ( { active } ) =>
				sprintf(
					/* translators: 1: field label, 2: position, 3: number of fields. */
					__(
						'Moving cancelled. %1$s is back at position %2$d of %3$d.',
						'checkout-fieldkit'
					),
					nameOf( active.id ),
					positionOf( active.id ),
					total
				),
		};
	}, [ rows, ids ] );

	const onDragEnd = ( { active, over }: DragEndEvent ) => {
		if ( ! over || active.id === over.id ) {
			return;
		}
		const index = ids.indexOf( String( over.id ) );
		if ( index >= 0 ) {
			onMove( String( active.id ), index );
		}
	};

	return (
		<DndContext
			sensors={ sensors }
			collisionDetection={ closestCenter }
			modifiers={ [ verticalOnly ] }
			onDragEnd={ onDragEnd }
			accessibility={ {
				announcements,
				screenReaderInstructions: {
					draggable: __(
						'To reorder, press Space to pick it up. Use the up and down arrow keys to move it, then press Space again to drop it, or Escape to cancel.',
						'checkout-fieldkit'
					),
				},
			} }
		>
			<SortableContext
				items={ ids }
				strategy={ verticalListSortingStrategy }
			>
				<div className="cfk-field-table__scroll">
					<table className="cfk-field-table">
						<thead>
							<tr>
								<th
									scope="col"
									className="cfk-field-table__handle"
								>
									<VisuallyHidden>
										{ __( 'Reorder', 'checkout-fieldkit' ) }
									</VisuallyHidden>
								</th>
								<th
									scope="col"
									className="cfk-field-table__select"
								>
									<input
										ref={ selectAllRef }
										type="checkbox"
										className="cfk-checkbox"
										checked={ allSelected }
										onChange={ ( event ) =>
											onSelect(
												ids,
												event.target.checked
											)
										}
										aria-label={ sprintf(
											/* translators: %s: section, such as "Billing". */
											__(
												'Select all fields in %s',
												'checkout-fieldkit'
											),
											sectionLabel
										) }
									/>
								</th>
								<th
									scope="col"
									className="cfk-field-table__field"
								>
									{ __( 'Field', 'checkout-fieldkit' ) }
								</th>
								<th
									scope="col"
									className="cfk-field-table__type"
								>
									{ __( 'Type', 'checkout-fieldkit' ) }
								</th>
								<th
									scope="col"
									className="cfk-field-table__validation"
								>
									{ __( 'Validation', 'checkout-fieldkit' ) }
								</th>
								<th
									scope="col"
									className="cfk-field-table__switch"
								>
									{ __( 'Required', 'checkout-fieldkit' ) }
								</th>
								<th
									scope="col"
									className="cfk-field-table__switch"
								>
									{ __( 'Enabled', 'checkout-fieldkit' ) }
								</th>
								<th
									scope="col"
									className="cfk-field-table__actions"
								>
									<VisuallyHidden>
										{ __( 'Actions', 'checkout-fieldkit' ) }
									</VisuallyHidden>
								</th>
							</tr>
						</thead>
						<tbody>
							{ rows.map( ( row ) => (
								<FieldRowView
									key={ row.id }
									row={ row }
									checkoutType={ checkoutType }
									context={ context }
									isEssential={ isEssential( row ) }
									isSelected={ selected.includes( row.id ) }
									copy={ copyCheck( row ) }
									onSelect={ ( value ) =>
										onSelect( [ row.id ], value )
									}
									onEnabledChange={ ( value ) =>
										onEnabledChange( row, value )
									}
									onRequiredChange={ ( value ) =>
										onRequiredChange( row, value )
									}
									onEdit={ () => onEdit( row ) }
									onCopy={ () => onCopy( row ) }
									onDelete={ () => onDelete( row ) }
								/>
							) ) }
						</tbody>
					</table>
				</div>
			</SortableContext>
		</DndContext>
	);
}
