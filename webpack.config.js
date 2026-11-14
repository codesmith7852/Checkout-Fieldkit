/**
 * Webpack configuration: the `@wordpress/scripts` defaults with our own entry points.
 * Add an entry here for each new script bundle (admin app, checkout blocks).
 *
 * WooCommerce's dependency extraction replaces WordPress's: it also turns
 * `@woocommerce/*` imports into WooCommerce's script globals and lists their
 * handles in each bundle's `.asset.php`.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: './src/admin/index.tsx',
		'classic-checkout': './src/classic-checkout/index.ts',
		'checkout-blocks': './src/checkout/frontend.tsx',
		'checkout-blocks-editor': './src/checkout/editor.tsx',
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new WooCommerceDependencyExtractionWebpackPlugin(),
	],
};
