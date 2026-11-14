/**
 * The app around the screens: header, problem summary, the screen for the
 * current tab, and the leave-page warning.
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { cx } from '../components';
import { FieldEditorProvider } from '../editor';
import { useLocation } from '../location';
import { FieldsScreen } from '../screens/FieldsScreen';
import { SettingsScreen } from '../screens/SettingsScreen';
import { ToolsScreen } from '../screens/ToolsScreen';
import { useDraft } from '../store';
import { Header } from './Header';
import { ProblemSummary } from './ProblemSummary';
import { useLeaveWarning } from './useLeaveWarning';

const SCREENS = {
	fields: FieldsScreen,
	settings: SettingsScreen,
	tools: ToolsScreen,
};

export function Shell() {
	const { location } = useLocation();
	const { isDirty, save } = useDraft();
	const saveRef = useRef< HTMLButtonElement >( null );
	const summaryRef = useRef< HTMLDivElement >( null );
	const contentRef = useRef< HTMLDivElement >( null );
	const [ failedSaves, setFailedSaves ] = useState( 0 );
	const shownTab = useRef( location.tab );

	useLeaveWarning( isDirty );

	// A new tab is a new page for screen reader users: move focus to its title.
	useEffect( () => {
		if ( shownTab.current === location.tab ) {
			return;
		}
		shownTab.current = location.tab;
		contentRef.current?.querySelector< HTMLElement >( 'h1' )?.focus();
	}, [ location.tab ] );

	// After a failed save, take the user to the summary of what went wrong.
	useEffect( () => {
		if ( failedSaves > 0 ) {
			summaryRef.current?.focus();
		}
	}, [ failedSaves ] );

	const onSave = useCallback( async () => {
		const outcome = await save();
		if ( outcome.status === 'invalid' || outcome.status === 'failed' ) {
			setFailedSaves( ( count ) => count + 1 );
		}
	}, [ save ] );

	const Screen = SCREENS[ location.tab ];

	return (
		<div className="cfk-shell">
			<Header onSave={ onSave } saveRef={ saveRef } />
			<div
				ref={ contentRef }
				className={ cx(
					'cfk-content',
					location.tab !== 'fields' && 'cfk-content--narrow'
				) }
			>
				<ProblemSummary ref={ summaryRef } onRetry={ onSave } />
				<FieldEditorProvider>
					<Screen />
				</FieldEditorProvider>
			</div>
		</div>
	);
}
