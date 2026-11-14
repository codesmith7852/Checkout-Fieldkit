/**
 * Tools screen (spec §6.5), placeholder. A later issue adds Export, Import
 * and Reset; after an import or reset it hands the server's new data to
 * useDraft().replaceSaved().
 */
import { __ } from '@wordpress/i18n';
import { Card } from '../components';
import { ScreenHeader } from '../shell/ScreenHeader';

export function ToolsScreen() {
	return (
		<>
			<ScreenHeader
				title={ __( 'Tools', 'checkout-fieldkit' ) }
				description={ __(
					'Move your setup between sites, or start over.',
					'checkout-fieldkit'
				) }
			/>
			<Card
				title={ __( 'Export', 'checkout-fieldkit' ) }
				description={ __(
					'Download your fields and settings as a file you can import on another site.',
					'checkout-fieldkit'
				) }
			/>
			<Card
				title={ __( 'Import', 'checkout-fieldkit' ) }
				description={ __(
					"Replace this site's setup with one from an exported file.",
					'checkout-fieldkit'
				) }
			/>
			<Card
				tone="danger"
				title={ __( 'Reset', 'checkout-fieldkit' ) }
				description={ __(
					"Go back to WooCommerce's own fields. Orders keep the answers already saved on them.",
					'checkout-fieldkit'
				) }
			/>
		</>
	);
}
