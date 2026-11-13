/**
 * Jest configuration.
 *
 * `@wordpress/scripts` 36+ runs Vitest by default and no longer ships Jest
 * defaults, so this follows its "keep an existing Jest suite" guide: the
 * published WordPress Jest preset (jsdom, CSS mocks, console assertions) with
 * Babel for TypeScript/JSX, plus Testing Library's DOM matchers.
 * `npm test` runs it through `wp-scripts test-unit-jest`.
 */
module.exports = {
	preset: '@wordpress/jest-preset-default',
	transform: {
		'\\.[jt]sx?$': [
			'babel-jest',
			{ presets: [ '@wordpress/babel-preset-default' ] },
		],
	},
	roots: [ '<rootDir>/src', '<rootDir>/tests/js' ],
	setupFilesAfterEnv: [ '<rootDir>/tests/js/setup.ts' ],
};
