/**
 * A field's error under it, in WooCommerce's error style.
 * @param root0
 * @param root0.id
 * @param root0.message
 */
export default function FieldError( {
	id,
	message,
}: {
	id: string;
	message: string | undefined;
} ) {
	if ( ! message ) {
		return null;
	}

	return (
		<div className="wc-block-components-validation-error" role="alert">
			<p id={ id }>{ message }</p>
		</div>
	);
}

/**
 * The id of a field's error message.
 *
 * @param inputId The field's input id.
 */
export function errorIdFor( inputId: string ): string {
	return `${ inputId }-error`;
}
