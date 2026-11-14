/**
 * Deep equality for JSON-shaped values (objects, arrays, strings, numbers,
 * booleans, null). Key order does not matter; array order does.
 *
 * @param a First value.
 * @param b Second value.
 */
export function deepEqual( a: unknown, b: unknown ): boolean {
	if ( a === b ) {
		return true;
	}
	if (
		typeof a !== 'object' ||
		typeof b !== 'object' ||
		a === null ||
		b === null
	) {
		return false;
	}
	if ( Array.isArray( a ) !== Array.isArray( b ) ) {
		return false;
	}
	if ( Array.isArray( a ) && Array.isArray( b ) ) {
		return (
			a.length === b.length &&
			a.every( ( item, index ) => deepEqual( item, b[ index ] ) )
		);
	}

	const left = a as Record< string, unknown >;
	const right = b as Record< string, unknown >;
	const keys = Object.keys( left );
	if ( keys.length !== Object.keys( right ).length ) {
		return false;
	}

	return keys.every(
		( key ) =>
			Object.prototype.hasOwnProperty.call( right, key ) &&
			deepEqual( left[ key ], right[ key ] )
	);
}
