/**
 * Headings and paragraphs: text shown to the shopper, collecting nothing.
 */
import type { CheckoutField } from '../types';

const LEVELS = [ 'h2', 'h3', 'h4', 'h5', 'h6' ] as const;

export default function DisplayField( { field }: { field: CheckoutField } ) {
	if ( field.type === 'heading' ) {
		const Tag =
			LEVELS.find( ( level ) => level === field.headingLevel ) ?? 'h3';

		return <Tag className="cfk-checkout-heading">{ field.label }</Tag>;
	}

	return <p className="cfk-checkout-paragraph">{ field.label }</p>;
}
