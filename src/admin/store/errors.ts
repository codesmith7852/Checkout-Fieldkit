/**
 * Turning a failed save into problems pinned to fields, or a message.
 */
import { __ } from '@wordpress/i18n';
import type { ApiError } from '../api';
import type { FieldSetError, Resource, SettingsError } from '../types';
import type { Problem, SectionIds } from './reducer';

function isValidationError( error: unknown ): error is ApiError & {
	data: { errors: Array< FieldSetError | SettingsError > };
} {
	const data = ( error as ApiError | null )?.data;
	return !! data && data.status === 422 && Array.isArray( data.errors );
}

/**
 * The problems in a 422 response, each pinned to the field it is about.
 * Null when the error is not a validation error.
 *
 * @param resource What was being saved.
 * @param error    What api-fetch rejected with.
 * @param sentIds  The field ids of the field set that was sent, index for index.
 */
export function problemsFromError(
	resource: Resource,
	error: unknown,
	sentIds?: SectionIds
): Problem[] | null {
	if ( ! isValidationError( error ) ) {
		return null;
	}

	return error.data.errors.map( ( item ) => {
		if ( resource === 'settings' || ! ( 'section' in item ) ) {
			return {
				resource,
				section: null,
				fieldId: null,
				key: String( item.key ),
				message: String( item.message ),
			};
		}
		const ids = sentIds?.[ item.section ] || [];
		const fieldId =
			typeof item.index === 'number' && ids[ item.index ] !== undefined
				? ids[ item.index ]
				: null;
		return {
			resource,
			section: item.section,
			fieldId,
			key: String( item.key ),
			message: String( item.message ),
		};
	} );
}

/**
 * A sentence for a failed request that is not a validation error.
 *
 * @param error What api-fetch rejected with.
 */
export function errorMessage( error: unknown ): string {
	const { code, message } = ( error || {} ) as ApiError;

	if ( code === 'fetch_error' ) {
		return __(
			'The server could not be reached. Check your connection and try again.',
			'checkout-fieldkit'
		);
	}
	if ( code === 'invalid_json' ) {
		return __(
			'The server sent an answer the page could not read. Try again, or reload the page.',
			'checkout-fieldkit'
		);
	}
	if ( typeof message === 'string' && message.trim() !== '' ) {
		return message;
	}
	return __( 'Something went wrong. Try again.', 'checkout-fieldkit' );
}
