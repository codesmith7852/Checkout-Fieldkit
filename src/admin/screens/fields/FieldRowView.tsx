/**
 * One field in the list (spec §6.2): drag handle, select box, label with
 * Custom / Essential tags and the field name, type, validation, Required and
 * Enabled switches, Edit and More. Disabled fields are greyed but stay
 * editable.
 */
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useId } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Switch, Tag, VisuallyHidden, cx } from '../../components';
import type { FieldRow } from '../../store';
import type { CheckoutType, Context } from '../../types';
import { DragHandleIcon, LockIcon } from './icons';
import { RowMenu } from './RowMenu';
import {
	displayName,
	isLocked,
	otherCheckoutType,
	takesRequired,
	type CopyCheck,
} from './rules';

export interface FieldRowViewProps {
	row: FieldRow;
	checkoutType: CheckoutType;
	context: Context;
	isEssential: boolean;
	isSelected: boolean;
	copy: CopyCheck;
	onSelect: ( selected: boolean ) => void;
	onEnabledChange: ( enabled: boolean ) => void;
	onRequiredChange: ( required: boolean ) => void;
	onEdit: () => void;
	onCopy: () => void;
	onDelete: () => void;
}

function labelOf(
	list: Array< { name: string; label: string } >,
	name: string
): string {
	return list.find( ( item ) => item.name === name )?.label || name;
}

/**
 * A switch WooCommerce may keep for itself; when locked it says why.
 *
 * @param props          Props.
 * @param props.label    Accessible name.
 * @param props.checked  On or off.
 * @param props.locked   Why it can't change, or null.
 * @param props.onChange Called with the new value.
 */
function RowSwitch( {
	label,
	checked,
	locked,
	onChange,
}: {
	label: string;
	checked: boolean;
	locked: string | null;
	onChange: ( checked: boolean ) => void;
} ) {
	const reasonId = useId();
	return (
		<span className="cfk-field-row__switch" title={ locked || undefined }>
			<Switch
				size="small"
				label={ label }
				checked={ checked }
				disabled={ !! locked }
				describedBy={ locked ? reasonId : undefined }
				onChange={ onChange }
			/>
			{ locked && (
				<>
					<LockIcon className="cfk-field-row__lock" />
					<VisuallyHidden id={ reasonId }>{ locked }</VisuallyHidden>
				</>
			) }
		</span>
	);
}

export function FieldRowView( {
	row,
	checkoutType,
	context,
	isEssential,
	isSelected,
	copy,
	onSelect,
	onEnabledChange,
	onRequiredChange,
	onEdit,
	onCopy,
	onDelete,
}: FieldRowViewProps ) {
	const { field } = row;
	const name = displayName( field );
	const problemsId = useId();
	const {
		attributes,
		listeners,
		setNodeRef,
		setActivatorNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable( {
		id: row.id,
		attributes: {
			roleDescription: __( 'sortable field', 'checkout-fieldkit' ),
		},
	} );

	const typeLabel = labelOf( context.fieldTypes[ checkoutType ], field.type );
	const validations = field.validation.map( ( item ) =>
		labelOf( context.validations[ checkoutType ], item )
	);
	const hasProblems = row.problems.length > 0;

	return (
		<tr
			ref={ setNodeRef }
			data-row-id={ row.id }
			style={ {
				transform: CSS.Translate.toString( transform ),
				transition,
			} }
			className={ cx(
				'cfk-field-row',
				! field.enabled && 'is-off',
				isSelected && 'is-selected',
				isDragging && 'is-dragging',
				hasProblems && 'has-problems'
			) }
		>
			<td className="cfk-field-row__handle-cell">
				<button
					ref={ setActivatorNodeRef }
					type="button"
					className="cfk-field-row__handle"
					{ ...attributes }
					{ ...listeners }
					aria-label={ sprintf(
						/* translators: %s: field label. */
						__( 'Reorder %s', 'checkout-fieldkit' ),
						name
					) }
				>
					<DragHandleIcon />
				</button>
			</td>
			<td className="cfk-field-row__select-cell">
				<input
					type="checkbox"
					className="cfk-checkbox cfk-field-row__select"
					checked={ isSelected }
					onChange={ ( event ) => onSelect( event.target.checked ) }
					aria-label={ sprintf(
						/* translators: %s: field label. */
						__( 'Select %s', 'checkout-fieldkit' ),
						name
					) }
				/>
			</td>
			<th scope="row" className="cfk-field-row__field">
				<span className="cfk-field-row__label">
					<span className="cfk-field-row__label-text">{ name }</span>
					{ ! field.builtIn && (
						<Tag tone="accent">
							{ __( 'Custom', 'checkout-fieldkit' ) }
						</Tag>
					) }
					{ isEssential && (
						<Tag tone="warning">
							{ __( 'Essential', 'checkout-fieldkit' ) }
						</Tag>
					) }
					{ ! field.enabled && (
						<VisuallyHidden>
							{ __( '(turned off)', 'checkout-fieldkit' ) }
						</VisuallyHidden>
					) }
				</span>
				<code className="cfk-field-row__name">{ field.name }</code>
				{ hasProblems && (
					<ul id={ problemsId } className="cfk-field-row__problems">
						{ row.problems.map( ( problem, index ) => (
							<li key={ index }>{ problem.message }</li>
						) ) }
					</ul>
				) }
			</th>
			<td className="cfk-field-row__type">{ typeLabel }</td>
			<td>
				{ validations.length > 0 ? (
					<span className="cfk-field-row__validation">
						{ validations.join( ', ' ) }
					</span>
				) : (
					<span className="cfk-field-row__none">
						<span aria-hidden="true">—</span>
						<VisuallyHidden>
							{ __( 'None', 'checkout-fieldkit' ) }
						</VisuallyHidden>
					</span>
				) }
			</td>
			<td>
				{ takesRequired( context, checkoutType, field ) ? (
					<RowSwitch
						label={ sprintf(
							/* translators: %s: field label. */
							__( 'Required: %s', 'checkout-fieldkit' ),
							name
						) }
						checked={ field.required }
						locked={
							isLocked( context, checkoutType, field, 'required' )
								? sprintf(
										/* translators: %s: field label. */
										__(
											'WooCommerce decides whether %s is required.',
											'checkout-fieldkit'
										),
										name
									)
								: null
						}
						onChange={ onRequiredChange }
					/>
				) : (
					<span className="cfk-field-row__none">
						<span aria-hidden="true">—</span>
						<VisuallyHidden>
							{ sprintf(
								/* translators: %s: field type, such as "Heading". */
								__(
									'Required does not apply to %s fields.',
									'checkout-fieldkit'
								),
								typeLabel
							) }
						</VisuallyHidden>
					</span>
				) }
			</td>
			<td>
				<RowSwitch
					label={ sprintf(
						/* translators: %s: field label. */
						__( 'Enabled: %s', 'checkout-fieldkit' ),
						name
					) }
					checked={ field.enabled }
					locked={
						isLocked( context, checkoutType, field, 'enabled' )
							? sprintf(
									/* translators: %s: field label. */
									__(
										'WooCommerce always shows %s.',
										'checkout-fieldkit'
									),
									name
								)
							: null
					}
					onChange={ onEnabledChange }
				/>
			</td>
			<td className="cfk-field-row__actions">
				<Button
					size="small"
					label={ sprintf(
						/* translators: %s: field label. */
						__( 'Edit %s', 'checkout-fieldkit' ),
						name
					) }
					aria-describedby={ hasProblems ? problemsId : undefined }
					onClick={ onEdit }
				>
					{ __( 'Edit', 'checkout-fieldkit' ) }
				</Button>
				<RowMenu
					field={ field }
					otherCheckoutType={ otherCheckoutType( checkoutType ) }
					copy={ copy }
					onCopy={ onCopy }
					onDelete={ onDelete }
				/>
			</td>
		</tr>
	);
}
