/**
 * The locale-override switches (spec §3, §4, §6.4), in screen order. The
 * Tools screen uses the titles too, to say which switches an import changes.
 */
import { __ } from '@wordpress/i18n';

export interface LocaleOverrideSwitch {
	/** Key in `settings.localeOverrides`. */
	key: string;
	title: string;
	description: string;
}

export function localeOverrideSwitches(): LocaleOverrideSwitch[] {
	return [
		{
			key: 'label',
			title: __( 'Keep my labels', 'checkout-fieldkit' ),
			description: __(
				'Show the labels you changed in every country, instead of names like County or PIN Code.',
				'checkout-fieldkit'
			),
		},
		{
			key: 'placeholder',
			title: __( 'Keep my placeholders', 'checkout-fieldkit' ),
			description: __(
				'Show the placeholder text you changed in every country.',
				'checkout-fieldkit'
			),
		},
		{
			key: 'order',
			title: __( 'Keep my field order', 'checkout-fieldkit' ),
			description: __(
				'Keep fields in the order you set, even where WooCommerce reorders them for a country, such as last name first in Japan.',
				'checkout-fieldkit'
			),
		},
		{
			key: 'required',
			title: __( 'Keep my required settings', 'checkout-fieldkit' ),
			description: __(
				'Use the Required setting you changed in every country. When off, WooCommerce decides per country, for example State is optional in the UK.',
				'checkout-fieldkit'
			),
		},
		{
			key: 'classes',
			title: __( 'Keep my CSS classes', 'checkout-fieldkit' ),
			description: __(
				'Use the CSS classes you changed in every country, instead of WooCommerce’s per-country layout classes.',
				'checkout-fieldkit'
			),
		},
	];
}

/**
 * A switch's title, or its key for a switch this screen does not know.
 *
 * @param key Key in `settings.localeOverrides`.
 */
export function switchTitle( key: string ): string {
	return (
		localeOverrideSwitches().find( ( item ) => item.key === key )?.title ||
		key
	);
}
