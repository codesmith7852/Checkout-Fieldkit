/**
 * ESLint configuration for `npm run lint:js` (wp-scripts lint-js).
 *
 * WordPress recommended rules (including TypeScript) everywhere, Jest rules for
 * test files, and Node globals for the build scripts in bin/.
 */
const wpPlugin = require( '@wordpress/eslint-plugin' );
const jestPlugin = require( 'eslint-plugin-jest' );
const globals = require( 'globals' );

module.exports = [
	{
		ignores: [
			'**/build/**',
			'**/dist/**',
			'**/node_modules/**',
			'**/vendor/**',
		],
	},
	...wpPlugin.configs.recommended,
	{
		languageOptions: {
			parserOptions: {
				requireConfigFile: false,
				babelOptions: {
					presets: [
						require.resolve( '@wordpress/babel-preset-default' ),
					],
				},
			},
		},
	},
	{
		...jestPlugin.configs[ 'flat/recommended' ],
		files: [ 'tests/js/**/*.{js,jsx,ts,tsx}' ],
	},
	{
		files: [ 'bin/**/*.js', '*.config.{js,cjs}' ],
		languageOptions: {
			globals: globals.node,
		},
	},
];
