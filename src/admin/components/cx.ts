/**
 * Joins class names, skipping empty ones.
 *
 * @param names Class names, or falsy values to skip.
 */
export function cx( ...names: Array< string | false | null | undefined > ) {
	return names.filter( Boolean ).join( ' ' );
}
