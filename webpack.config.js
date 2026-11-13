/**
 * Webpack configuration: the `@wordpress/scripts` defaults with our own entry points.
 * Add an entry here for each new script bundle (admin app, checkout blocks).
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: './src/admin/index.tsx',
	},
};
