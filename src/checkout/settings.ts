/**
 * Reads the field definitions WooCommerce passes to the page for us.
 */
import { getSetting } from '@woocommerce/settings';
import type { CheckoutField, ScriptData, Section } from './types';

/** The Store API extension namespace our values travel under. */
export const NAMESPACE = 'checkout-fieldkit';

const EMPTY: ScriptData = {
	namespace: NAMESPACE,
	sections: { contact: [], additional: [] },
};

/**
 * Our script data, or empty sections when the page has none.
 */
export function getScriptData(): ScriptData {
	const data = getSetting< ScriptData >( 'checkout-fieldkit_data', EMPTY );

	return data && data.sections ? data : EMPTY;
}

/**
 * The fields of one section, in the stored order.
 *
 * @param section Section name.
 */
export function getSectionFields( section: Section ): CheckoutField[] {
	const fields = getScriptData().sections[ section ];

	return Array.isArray( fields ) ? fields : [];
}
