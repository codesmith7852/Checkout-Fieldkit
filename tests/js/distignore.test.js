/**
 * Tests for the .distignore matcher used by `npm run zip`.
 */
const { parseDistignore, isIgnored } = require( '../../bin/distignore' );

describe( 'parseDistignore', () => {
	it( 'skips comments and blank lines', () => {
		const patterns = parseDistignore(
			'# A comment\n\n/docs\n   \n*.log\n'
		);

		expect( patterns ).toHaveLength( 2 );
	} );

	it( 'accepts Windows line endings', () => {
		expect( parseDistignore( '/docs\r\n/tests\r\n' ) ).toHaveLength( 2 );
	} );
} );

describe( 'isIgnored', () => {
	const patterns = parseDistignore(
		[
			'/README.md',
			'/docs',
			'/node_modules',
			'/phpunit.xml.dist',
			'.DS_Store',
			'*.log',
			'cache/',
		].join( '\n' )
	);

	it( 'matches a leading-slash pattern only at the root', () => {
		expect( isIgnored( 'README.md', patterns ) ).toBe( true );
		expect( isIgnored( 'includes/README.md', patterns ) ).toBe( false );
	} );

	it( 'excludes everything inside an ignored directory', () => {
		expect( isIgnored( 'docs', patterns, true ) ).toBe( true );
		expect( isIgnored( 'docs/adr/0001.md', patterns ) ).toBe( true );
		expect( isIgnored( 'node_modules/react/index.js', patterns ) ).toBe(
			true
		);
	} );

	it( 'does not treat a name that only starts like an ignored path as ignored', () => {
		expect( isIgnored( 'docs-extra/file.txt', patterns ) ).toBe( false );
		expect( isIgnored( 'README.md.bak', patterns ) ).toBe( false );
	} );

	it( 'matches a pattern without a slash at any depth', () => {
		expect( isIgnored( '.DS_Store', patterns ) ).toBe( true );
		expect( isIgnored( 'includes/Admin/.DS_Store', patterns ) ).toBe(
			true
		);
	} );

	it( 'supports * wildcards', () => {
		expect( isIgnored( 'debug.log', patterns ) ).toBe( true );
		expect( isIgnored( 'build/deep/error.log', patterns ) ).toBe( true );
		expect( isIgnored( 'logo.svg', patterns ) ).toBe( false );
	} );

	it( 'applies a trailing-slash pattern to directories only', () => {
		expect( isIgnored( 'cache', patterns, true ) ).toBe( true );
		expect( isIgnored( 'includes/cache/file.php', patterns ) ).toBe( true );
		expect( isIgnored( 'cache', patterns, false ) ).toBe( false );
	} );

	it( 'keeps files nobody listed', () => {
		expect( isIgnored( 'checkout-fieldkit.php', patterns ) ).toBe( false );
		expect( isIgnored( 'includes/Plugin.php', patterns ) ).toBe( false );
		expect( isIgnored( 'build/admin.js', patterns ) ).toBe( false );
	} );
} );

describe( 'the real .distignore', () => {
	const fs = require( 'fs' );
	const path = require( 'path' );
	const real = parseDistignore(
		fs.readFileSync(
			path.join( __dirname, '..', '..', '.distignore' ),
			'utf8'
		)
	);

	it.each( [
		'docs/spec.md',
		'GLOSSARY.md',
		'README.md',
		'tests/php/bootstrap.php',
		'tests/js/distignore.test.js',
		'node_modules/react/index.js',
		'vendor/autoload.php',
		'composer.json',
		'package.json',
		'phpcs.xml.dist',
		'phpunit.xml.dist',
		'tsconfig.json',
		'bin/zip.js',
		'dist/checkout-fieldkit.zip',
		'.editorconfig',
		'.gitignore',
	] )( 'leaves out %s', ( file ) => {
		expect( isIgnored( file, real ) ).toBe( true );
	} );

	it.each( [
		'checkout-fieldkit.php',
		'uninstall.php',
		'readme.txt',
		'LICENSE',
		'includes/Plugin.php',
		'build/admin.js',
		'build/admin.asset.php',
	] )( 'ships %s', ( file ) => {
		expect( isIgnored( file, real ) ).toBe( false );
	} );
} );
