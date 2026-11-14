/**
 * Settings screen (spec §6.4, frame 4): the locale overrides for address
 * fields. Edits go into the draft and are saved with the header's Save
 * changes.
 */
import { useId } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	Switch,
	Tag,
	VisuallyHidden,
	useAnnounce,
} from '../components';
import { ScreenHeader } from '../shell/ScreenHeader';
import { useSettingsDraft } from '../store';
import type { Problem } from '../store';
import { localeOverrideSwitches } from './settings/switches';
import './settings/settings.css';

interface OverrideRowProps {
	title: string;
	description: string;
	checked: boolean;
	changed: boolean;
	problems: Problem[];
	onChange: ( checked: boolean ) => void;
}

function OverrideRow( {
	title,
	description,
	checked,
	changed,
	problems,
	onChange,
}: OverrideRowProps ) {
	const id = useId();
	const titleId = `${ id }-title`;
	const descriptionId = `${ id }-description`;
	const changedId = `${ id }-changed`;
	const problemId = `${ id }-problem`;
	const describedBy = [
		descriptionId,
		changed && changedId,
		problems.length > 0 && problemId,
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<div className="cfk-setting">
			<div className="cfk-setting__text">
				<p className="cfk-setting__title">
					<span id={ titleId }>{ title }</span>
					{ changed && (
						<>
							<Tag
								tone="warning"
								className="cfk-setting__changed"
								aria-hidden="true"
							>
								{ __( 'Changed', 'checkout-fieldkit' ) }
							</Tag>
							<VisuallyHidden id={ changedId }>
								{ __(
									'Changed, not saved yet.',
									'checkout-fieldkit'
								) }
							</VisuallyHidden>
						</>
					) }
				</p>
				<p id={ descriptionId } className="cfk-setting__description">
					{ description }
				</p>
				{ problems.length > 0 && (
					<p id={ problemId } className="cfk-setting__problem">
						{ problems
							.map( ( problem ) => problem.message )
							.join( ' ' ) }
					</p>
				) }
			</div>
			<Switch
				checked={ checked }
				onChange={ onChange }
				labelledBy={ titleId }
				describedBy={ describedBy }
				className="cfk-setting__switch"
			/>
		</div>
	);
}

export function SettingsScreen() {
	const { settings, savedSettings, defaultSettings, problems, update } =
		useSettingsDraft();
	const announce = useAnnounce();
	const current = settings.localeOverrides || {};
	const saved = savedSettings.localeOverrides || {};
	const defaults = defaultSettings.localeOverrides || {};
	const switches = localeOverrideSwitches();
	const isDefault = switches.every(
		( item ) => !! current[ item.key ] === !! defaults[ item.key ]
	);

	const restoreDefaults = () => {
		if ( isDefault ) {
			return;
		}
		update( { localeOverrides: { ...defaults } } );
		announce(
			__(
				'Defaults restored. Save changes to keep them.',
				'checkout-fieldkit'
			)
		);
	};

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
				className="cfk-settings-card"
				title={ __(
					'Address fields in different countries',
					'checkout-fieldkit'
				) }
				description={ __(
					'WooCommerce adjusts address fields for each country. In the UK it renames State to County and makes it optional; in India the postcode becomes PIN Code; in Japan the last name comes before the first name. Choose where your own settings should win.',
					'checkout-fieldkit'
				) }
				footer={
					<>
						<span className="cfk-settings-card__note">
							{ __(
								'A switched-on setting applies only where you changed that property from WooCommerce’s default. Anything you left as it was still follows each country’s rules. Fields a country hides, such as State where there are no states, stay hidden.',
								'checkout-fieldkit'
							) }
						</span>
						<Button
							aria-disabled={ isDefault || undefined }
							onClick={ restoreDefaults }
						>
							{ __( 'Restore defaults', 'checkout-fieldkit' ) }
						</Button>
					</>
				}
			>
				{ switches.map( ( item ) => (
					<OverrideRow
						key={ item.key }
						title={ item.title }
						description={ item.description }
						checked={ !! current[ item.key ] }
						changed={
							!! current[ item.key ] !== !! saved[ item.key ]
						}
						problems={ problems.filter(
							( problem ) =>
								problem.key === `localeOverrides.${ item.key }`
						) }
						onChange={ ( checked ) =>
							update( {
								localeOverrides: { [ item.key ]: checked },
							} )
						}
					/>
				) ) }
			</Card>
		</>
	);
}
