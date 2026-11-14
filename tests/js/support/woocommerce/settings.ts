/**
 * Test double for `@woocommerce/settings` (WooCommerce's `wc.wcSettings`):
 * tests put settings in `setTestSettings()`.
 */
let settings: Record< string, unknown > = {};

export function setTestSettings( next: Record< string, unknown > ): void {
	settings = next;
}

export function getSetting< T = unknown >( name: string, fallback?: T ): T {
	return ( name in settings ? settings[ name ] : fallback ) as T;
}
