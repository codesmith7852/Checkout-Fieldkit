/**
 * The options of a choice field (spec §6.3): rows of saved value and shown
 * text, reordered by drag or keyboard (dnd-kit: focus the handle, Space to
 * pick up, arrow keys to move, Space to drop, Escape to cancel), removed,
 * and added at the end.
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
	type UniqueIdentifier,
} from '@dnd-kit/core';
import {
	SortableContext,
	arrayMove,
	sortableKeyboardCoordinates,
	useSortable,
	verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { __, sprintf } from '@wordpress/i18n';
import { Button, CloseIcon, cx } from '../components';

/** One option while it is being edited; `key` stays the same while it is edited or moved. */
export interface EditorOption {
	key: string;
	value: string;
	label: string;
	/** Whether the saved value was typed, rather than following the shown text. */
	valueEdited: boolean;
}

export interface OptionsEditorProps {
	options: EditorOption[];
	onChange: ( options: EditorOption[] ) => void;
	/** Whether each option has a problem (empty or repeated saved value). */
	issues: boolean[];
	/** Id of the group's error message, when there is one. */
	errorId?: string;
	/** Id of the saved value input of an option. */
	valueId: ( key: string ) => string;
	/** Id of the shown text input of an option. */
	labelId: ( key: string ) => string;
	addId: string;
	onAdd: () => void;
	onRemove: ( key: string ) => void;
	/** Saved value edited by hand. */
	onValueChange: ( key: string, value: string ) => void;
	/** Shown text edited. */
	onLabelChange: ( key: string, label: string ) => void;
	/** Tells the drawer a keyboard or pointer drag is going on, so Escape cancels the drag only. */
	onDraggingChange: ( dragging: boolean ) => void;
}

/**
 * What an option is called in labels and announcements.
 *
 * @param option   The option.
 * @param position Its position, from 1.
 */
export function optionName( option: EditorOption, position: number ): string {
	return (
		option.label ||
		option.value ||
		sprintf(
			/* translators: %d: position of the option in the list. */
			__( 'option %d', 'checkout-fieldkit' ),
			position
		)
	);
}

function DragHandleIcon() {
	return (
		<svg
			width="10"
			height="14"
			viewBox="0 0 10 14"
			fill="currentColor"
			aria-hidden="true"
			focusable="false"
		>
			<circle cx="2.5" cy="2.5" r="1.3" />
			<circle cx="7.5" cy="2.5" r="1.3" />
			<circle cx="2.5" cy="7" r="1.3" />
			<circle cx="7.5" cy="7" r="1.3" />
			<circle cx="2.5" cy="11.5" r="1.3" />
			<circle cx="7.5" cy="11.5" r="1.3" />
		</svg>
	);
}

interface RowProps {
	option: EditorOption;
	index: number;
	invalid: boolean;
	props: OptionsEditorProps;
}

function OptionRow( { option, index, invalid, props }: RowProps ) {
	const {
		attributes,
		listeners,
		setNodeRef,
		setActivatorNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable( { id: option.key } );
	const name = optionName( option, index + 1 );
	const describedBy = invalid ? props.errorId : undefined;

	return (
		<div
			ref={ setNodeRef }
			className={ cx( 'cfk-options__row', isDragging && 'is-dragging' ) }
			style={ {
				transform: CSS.Translate.toString( transform ),
				transition,
			} }
			data-option-index={ index }
		>
			<button
				type="button"
				ref={ setActivatorNodeRef }
				className="cfk-options__handle"
				{ ...attributes }
				{ ...listeners }
				aria-label={ sprintf(
					/* translators: %s: option name. */
					__( 'Reorder option %s', 'checkout-fieldkit' ),
					name
				) }
			>
				<DragHandleIcon />
			</button>
			<input
				id={ props.valueId( option.key ) }
				type="text"
				className="cfk-input cfk-input--mono cfk-input--small"
				value={ option.value }
				aria-label={ sprintf(
					/* translators: %s: option name. */
					__( 'Saved value for %s', 'checkout-fieldkit' ),
					name
				) }
				aria-invalid={ invalid || undefined }
				aria-describedby={ describedBy }
				onChange={ ( event ) =>
					props.onValueChange( option.key, event.target.value )
				}
			/>
			<input
				id={ props.labelId( option.key ) }
				type="text"
				className="cfk-input cfk-input--small"
				value={ option.label }
				aria-label={ sprintf(
					/* translators: %s: option name. */
					__( 'Shown text for %s', 'checkout-fieldkit' ),
					option.value ||
						sprintf(
							/* translators: %d: position of the option in the list. */
							__( 'option %d', 'checkout-fieldkit' ),
							index + 1
						)
				) }
				onChange={ ( event ) =>
					props.onLabelChange( option.key, event.target.value )
				}
			/>
			<Button
				variant="ghost"
				size="small"
				className="cfk-options__remove"
				icon={ <CloseIcon /> }
				label={ sprintf(
					/* translators: %s: option name. */
					__( 'Remove option %s', 'checkout-fieldkit' ),
					name
				) }
				onClick={ () => props.onRemove( option.key ) }
			/>
		</div>
	);
}

export function OptionsEditor( props: OptionsEditorProps ) {
	const { options, onChange, issues, onDraggingChange } = props;
	const sensors = useSensors(
		useSensor( PointerSensor, { activationConstraint: { distance: 4 } } ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

	const position = ( id: UniqueIdentifier ) =>
		options.findIndex( ( option ) => option.key === id ) + 1;
	const nameOf = ( id: UniqueIdentifier ) => {
		const at = position( id );
		return at > 0 ? optionName( options[ at - 1 ], at ) : '';
	};

	const announcements: Announcements = {
		onDragStart: ( { active } ) =>
			sprintf(
				/* translators: 1: option name, 2: position, 3: number of options. */
				__(
					'Picked up option %1$s, at position %2$d of %3$d.',
					'checkout-fieldkit'
				),
				nameOf( active.id ),
				position( active.id ),
				options.length
			),
		onDragOver: ( { active, over } ) =>
			over
				? sprintf(
						/* translators: 1: option name, 2: position, 3: number of options. */
						__(
							'Option %1$s moved to position %2$d of %3$d.',
							'checkout-fieldkit'
						),
						nameOf( active.id ),
						position( over.id ),
						options.length
					)
				: undefined,
		onDragEnd: ( { active, over } ) =>
			over
				? sprintf(
						/* translators: 1: option name, 2: position, 3: number of options. */
						__(
							'Option %1$s dropped at position %2$d of %3$d.',
							'checkout-fieldkit'
						),
						nameOf( active.id ),
						position( over.id ),
						options.length
					)
				: undefined,
		onDragCancel: ( { active } ) =>
			sprintf(
				/* translators: 1: option name, 2: position. */
				__(
					'Reordering cancelled. Option %1$s is back at position %2$d.',
					'checkout-fieldkit'
				),
				nameOf( active.id ),
				position( active.id )
			),
	};

	const onDragEnd = ( { active, over }: DragEndEvent ) => {
		onDraggingChange( false );
		if ( ! over || active.id === over.id ) {
			return;
		}
		const from = position( active.id ) - 1;
		const to = position( over.id ) - 1;
		if ( from >= 0 && to >= 0 ) {
			onChange( arrayMove( options, from, to ) );
		}
	};

	return (
		<div className="cfk-options">
			<div
				className="cfk-options__row cfk-options__row--head"
				aria-hidden="true"
			>
				<span />
				<span>{ __( 'Saved value', 'checkout-fieldkit' ) }</span>
				<span>{ __( 'Shown to shoppers', 'checkout-fieldkit' ) }</span>
				<span />
			</div>
			<DndContext
				sensors={ sensors }
				collisionDetection={ closestCenter }
				onDragStart={ () => onDraggingChange( true ) }
				onDragEnd={ onDragEnd }
				onDragCancel={ () => onDraggingChange( false ) }
				accessibility={ {
					announcements,
					screenReaderInstructions: {
						draggable: __(
							'To reorder an option, press Space or Enter to pick it up, use the arrow keys to move it, then press Space or Enter to drop it, or Escape to cancel.',
							'checkout-fieldkit'
						),
					},
				} }
			>
				<SortableContext
					items={ options.map( ( option ) => option.key ) }
					strategy={ verticalListSortingStrategy }
				>
					{ options.map( ( option, index ) => (
						<OptionRow
							key={ option.key }
							option={ option }
							index={ index }
							invalid={ !! issues[ index ] }
							props={ props }
						/>
					) ) }
				</SortableContext>
			</DndContext>
			<button
				type="button"
				id={ props.addId }
				className="cfk-options__add"
				onClick={ props.onAdd }
			>
				<svg
					width="14"
					height="14"
					viewBox="0 0 14 14"
					stroke="currentColor"
					strokeWidth="1.8"
					strokeLinecap="round"
					aria-hidden="true"
					focusable="false"
				>
					<path d="M7 2v10M2 7h10" />
				</svg>
				{ __( 'Add option', 'checkout-fieldkit' ) }
			</button>
		</div>
	);
}
