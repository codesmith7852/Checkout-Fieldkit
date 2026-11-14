/**
 * Settings screen (spec §6.4), placeholder. A later issue adds the address
 * field switches, saved with the header's Save changes through
 * useSettingsDraft().
 */
import { __ } from '@wordpress/i18n';
import { Card } from '../components';
import { ScreenHeader } from '../shell/ScreenHeader';

export function SettingsScreen() {
	return (
		<>
			<ScreenHeader
				title={ __( 'Settings', 'checkout-fieldkit' ) }
				description={ __(
					'These apply to the address fields on both checkout types and on My Account → Addresses.',
					'checkout-fieldkit'
				) }
			/>
			<Card
				title={ __(
					'Address fields in different countries',
					'checkout-fieldkit'
				) }
				description={ __(
					'WooCommerce adjusts address fields for each country. Choose where your own settings should win.',
					'checkout-fieldkit'
				) }
			/>
		</>
	);
}
