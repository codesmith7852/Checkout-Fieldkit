/**
 * What stopped the last save (spec §6.1): every problem with a link to where
 * it is, or the reason a request failed with Try again. The screens show the
 * same problems next to the affected fields (useFieldSet rows' `problems`).
 */
import { forwardRef } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, Notice } from '../components';
import { useLocation, type AppLocation } from '../location';
import {
	useContextData,
	useDraft,
	useFieldSet,
	type FieldSetApi,
	type Problem,
} from '../store';
import { checkoutTypeLabel } from './labels';
import { isPlainClick } from './links';

interface Target {
	label: string;
	location: Partial< AppLocation >;
}

export interface ProblemSummaryProps {
	onRetry: () => void;
}

export const ProblemSummary = forwardRef< HTMLDivElement, ProblemSummaryProps >(
	function ProblemSummaryRender( { onRetry }, ref ) {
		const { problems, saveError, isSaving } = useDraft();
		const context = useContextData();
		const fieldSets: Record< 'classic' | 'block', FieldSetApi > = {
			classic: useFieldSet( 'classic' ),
			block: useFieldSet( 'block' ),
		};
		const { navigate, hrefFor } = useLocation();

		if ( problems.length === 0 && ! saveError ) {
			return null;
		}

		const target = ( problem: Problem ): Target => {
			if ( problem.resource === 'settings' ) {
				return {
					label: __( 'Settings', 'checkout-fieldkit' ),
					location: { tab: 'settings' },
				};
			}
			const type = problem.resource;
			const section = problem.section || '';
			const parts = [ checkoutTypeLabel( type ) ];
			const sectionLabel = context.sections[ type ].find(
				( item ) => item.name === section
			)?.label;
			parts.push( sectionLabel || section );
			const row = problem.fieldId
				? fieldSets[ type ].getRow( problem.fieldId )
				: null;
			if ( row ) {
				parts.push(
					row.field.label ||
						row.field.name ||
						__( 'New field', 'checkout-fieldkit' )
				);
			}
			return {
				label: parts.join( ' · ' ),
				location: {
					tab: 'fields',
					checkout: type,
					section: row ? row.section : section,
				},
			};
		};

		const title = saveError
			? __( 'Your changes were not saved', 'checkout-fieldkit' )
			: sprintf(
					/* translators: %d: number of problems. */
					_n(
						'Fix %d problem, then save again',
						'Fix %d problems, then save again',
						problems.length,
						'checkout-fieldkit'
					),
					problems.length
				);

		return (
			<Notice
				ref={ ref }
				tone="error"
				role="group"
				tabIndex={ -1 }
				className="cfk-problem-summary"
				title={ title }
				actions={
					saveError ? (
						<Button
							size="small"
							disabled={ isSaving }
							onClick={ onRetry }
						>
							{ __( 'Try again', 'checkout-fieldkit' ) }
						</Button>
					) : undefined
				}
			>
				{ saveError && <p>{ saveError }</p> }
				{ problems.length > 0 && (
					<ul className="cfk-problem-summary__list">
						{ problems.map( ( problem, index ) => {
							const { label, location } = target( problem );
							return (
								<li key={ index }>
									<a
										href={ hrefFor( location ) }
										onClick={ ( event ) => {
											if ( isPlainClick( event ) ) {
												event.preventDefault();
												navigate( location );
											}
										} }
									>
										{ label }
									</a>
									{ ': ' }
									{ problem.message }
								</li>
							);
						} ) }
					</ul>
				) }
			</Notice>
		);
	}
);
