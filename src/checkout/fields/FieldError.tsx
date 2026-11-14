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
			<p id={ id }>
				<svg
					className="cfk-checkout-error-icon"
					viewBox="0 0 24 24"
					width="24"
					height="24"
					aria-hidden="true"
					focusable="false"
				>
					<circle cx="12" cy="12" r="9" fill="currentColor" />
					<path d="M11 7h2v6h-2zm0 8h2v2h-2z" fill="#fff" />
				</svg>
				<span>{ message }</span>
			</p>
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
