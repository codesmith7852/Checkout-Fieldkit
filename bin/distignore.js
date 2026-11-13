/**
 * Reads .distignore and decides which paths stay out of the release zip.
 *
 * Pattern rules (the subset of rsync's --exclude-from that .distignore uses):
 * - Blank lines and lines starting with # are skipped.
 * - A leading slash anchors the pattern to the plugin root: "/docs".
 * - A pattern without any slash matches a file or folder name at any depth: ".DS_Store".
 * - A pattern with a slash in the middle matches from the root: "/tests/php".
 * - A trailing slash matches folders only: "cache/".
 * - "*" matches within one name, "**" across folders, "?" one character.
 * - When a folder matches, everything inside it is left out too.
 */

/**
 * @typedef {Object} DistignorePattern
 * @property {RegExp}  regex    Matches one path (relative, forward slashes).
 * @property {boolean} anchored Whether the pattern applies from the root only.
 * @property {boolean} dirOnly  Whether the pattern applies to folders only.
 */

/**
 * Turns a glob into a regular expression source.
 *
 * @param {string} glob Glob without leading or trailing slash.
 * @return {string} Regular expression source.
 */
function globToRegex( glob ) {
	let source = '';
	for ( let i = 0; i < glob.length; i++ ) {
		const char = glob[ i ];
		if ( char === '*' && glob[ i + 1 ] === '*' ) {
			source += '.*';
			i++;
		} else if ( char === '*' ) {
			source += '[^/]*';
		} else if ( char === '?' ) {
			source += '[^/]';
		} else {
			source += char.replace( /[.+^${}()|[\]\\]/g, '\\$&' );
		}
	}
	return source;
}

/**
 * Parses the contents of a .distignore file.
 *
 * @param {string} text File contents.
 * @return {DistignorePattern[]} Patterns.
 */
function parseDistignore( text ) {
	return text
		.split( /\r?\n/ )
		.map( ( line ) => line.trim() )
		.filter( ( line ) => line !== '' && ! line.startsWith( '#' ) )
		.map( ( line ) => {
			const dirOnly = line.endsWith( '/' );
			const body = line.replace( /^\/+/, '' ).replace( /\/+$/, '' );
			const anchored = line.startsWith( '/' ) || body.includes( '/' );
			return {
				regex: new RegExp( '^' + globToRegex( body ) + '$' ),
				anchored,
				dirOnly,
			};
		} );
}

/**
 * Whether one path (without looking at its parents) matches a pattern.
 *
 * @param {string}            path    Relative path with forward slashes.
 * @param {boolean}           isDir   Whether the path is a folder.
 * @param {DistignorePattern} pattern Pattern.
 * @return {boolean} Whether it matches.
 */
function matchesSelf( path, isDir, pattern ) {
	if ( pattern.dirOnly && ! isDir ) {
		return false;
	}
	const subject = pattern.anchored ? path : path.split( '/' ).pop();
	return pattern.regex.test( subject );
}

/**
 * Whether a path is left out of the zip, either itself or through one of its folders.
 *
 * @param {string}              path     Path relative to the plugin root, forward slashes.
 * @param {DistignorePattern[]} patterns Parsed patterns.
 * @param {boolean}             [isDir]  Whether the path itself is a folder.
 * @return {boolean} Whether it is ignored.
 */
function isIgnored( path, patterns, isDir = false ) {
	const parts = path.replace( /^\/+/, '' ).split( '/' );
	for ( let depth = 1; depth <= parts.length; depth++ ) {
		const current = parts.slice( 0, depth ).join( '/' );
		const currentIsDir = depth < parts.length || isDir;
		if (
			patterns.some( ( pattern ) =>
				matchesSelf( current, currentIsDir, pattern )
			)
		) {
			return true;
		}
	}
	return false;
}

module.exports = { parseDistignore, isIgnored };
