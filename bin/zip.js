#!/usr/bin/env node
/**
 * Builds dist/checkout-fieldkit.zip from the plugin folder, leaving out
 * everything listed in .distignore. Run `npm run zip`, which builds first.
 */
const fs = require( 'fs' );
const path = require( 'path' );
const AdmZip = require( 'adm-zip' );
const { parseDistignore, isIgnored } = require( './distignore' );

const SLUG = 'checkout-fieldkit';
const root = path.resolve( __dirname, '..' );
const output = path.join( root, 'dist', `${ SLUG }.zip` );
const patterns = parseDistignore(
	fs.readFileSync( path.join( root, '.distignore' ), 'utf8' )
);

if ( ! fs.existsSync( path.join( root, 'build' ) ) ) {
	process.stderr.write( 'No build folder. Run `npm run build` first.\n' );
	process.exit( 1 );
}

/**
 * Lists the files that go into the zip.
 *
 * @param {string} dir Relative folder ('' for the root).
 * @return {string[]} Relative file paths, sorted.
 */
function collect( dir ) {
	const entries = fs
		.readdirSync( path.join( root, dir ), { withFileTypes: true } )
		.sort( ( a, b ) => a.name.localeCompare( b.name ) );
	const files = [];
	for ( const entry of entries ) {
		const relative = dir ? `${ dir }/${ entry.name }` : entry.name;
		if ( isIgnored( relative, patterns, entry.isDirectory() ) ) {
			continue;
		}
		if ( entry.isDirectory() ) {
			files.push( ...collect( relative ) );
		} else if ( entry.isFile() ) {
			files.push( relative );
		}
	}
	return files;
}

const files = collect( '' );
const zip = new AdmZip();
for ( const file of files ) {
	zip.addFile(
		`${ SLUG }/${ file }`,
		fs.readFileSync( path.join( root, file ) )
	);
}

fs.mkdirSync( path.dirname( output ), { recursive: true } );
zip.writeZip( output );

process.stdout.write(
	`${ path.relative( root, output ) }: ${ files.length } files\n` +
		files.map( ( file ) => `  ${ SLUG }/${ file }` ).join( '\n' ) +
		'\n'
);
