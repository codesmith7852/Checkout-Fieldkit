#!/usr/bin/env node
/**
 * Runs Plugin Check against the release zip instead of the development folder.
 *
 * `wp plugin check checkout-fieldkit` on a git checkout also scans files that
 * never ship (tests, phpunit.xml.dist, .editorconfig, dist/*.zip) and reports
 * them as errors. This script unpacks dist/checkout-fieldkit.zip into a
 * temporary plugins folder, links the site's other plugins next to it, and
 * runs Plugin Check there with WP_PLUGIN_DIR pointing at that folder. The site
 * itself is not changed.
 *
 * Usage: npm run plugin-check [-- <extra wp plugin check arguments>]
 * Environment: CFK_WP_PATH (WordPress folder, default: the site this plugin is in),
 *              CFK_WP_CLI (wp command, default: wp).
 */
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { spawnSync } = require( 'child_process' );
const AdmZip = require( 'adm-zip' );

const SLUG = 'checkout-fieldkit';
const root = path.resolve( __dirname, '..' );
const pluginsDir = path.dirname( root );
const wpPath = process.env.CFK_WP_PATH || path.resolve( root, '../../..' );
const wpCli = process.env.CFK_WP_CLI || 'wp';
const zipFile = path.join( root, 'dist', `${ SLUG }.zip` );

if ( ! fs.existsSync( zipFile ) ) {
	process.stderr.write( 'No dist zip. Run `npm run zip` first.\n' );
	process.exit( 1 );
}

const tempDir = fs.mkdtempSync( path.join( os.tmpdir(), 'cfk-plugin-check-' ) );
let status = 1;

try {
	new AdmZip( zipFile ).extractAllTo( tempDir, true );

	for ( const entry of fs.readdirSync( pluginsDir, {
		withFileTypes: true,
	} ) ) {
		if ( entry.name !== SLUG && ! entry.name.startsWith( '.' ) ) {
			fs.symlinkSync(
				path.join( pluginsDir, entry.name ),
				path.join( tempDir, entry.name )
			);
		}
	}

	const result = spawnSync(
		wpCli,
		[
			`--path=${ wpPath }`,
			`--exec=define( 'WP_PLUGIN_DIR', ${ JSON.stringify( tempDir ) } );`,
			'plugin',
			'check',
			SLUG,
			...process.argv.slice( 2 ),
		],
		{ encoding: 'utf8' }
	);

	// WP-CLI on new PHP versions prints many deprecation notices of its own.
	const quiet = ( text ) =>
		( text || '' )
			.split( '\n' )
			.filter( ( line ) => ! /^(PHP )?Deprecated:/.test( line ) )
			.join( '\n' );

	process.stdout.write( quiet( result.stdout ) );
	process.stderr.write( quiet( result.stderr ) );

	const errors = /\tERROR\t/.test( result.stdout || '' );
	status = result.status !== 0 || errors ? 1 : 0;
	process.stdout.write(
		errors
			? '\nPlugin Check reported errors.\n'
			: '\nPlugin Check: no errors in the release build.\n'
	);
} finally {
	fs.rmSync( tempDir, { recursive: true, force: true } );
}

process.exit( status );
