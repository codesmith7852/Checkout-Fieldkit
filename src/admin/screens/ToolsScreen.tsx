/**
 * Tools screen (spec §6.5, frame 5): Export, Import and Reset. These act on
 * the server at once; after an import or reset the store takes the server's
 * new data through useDraft().replaceSaved().
 */
import { __ } from '@wordpress/i18n';
import { ScreenHeader } from '../shell/ScreenHeader';
import { ExportCard } from './tools/ExportCard';
import { ImportCard } from './tools/ImportCard';
import { ResetCard } from './tools/ResetCard';
import './tools/tools.css';

export function ToolsScreen() {
	return (
		<div className="cfk-tools">
			<ScreenHeader
				title={ __( 'Tools', 'checkout-fieldkit' ) }
				description={ __(
					'Move your setup between sites, or start over.',
					'checkout-fieldkit'
				) }
			/>
			<ExportCard />
			<ImportCard />
			<ResetCard />
		</div>
	);
}
