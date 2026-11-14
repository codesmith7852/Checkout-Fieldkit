/**
 * Where the Additional block goes in the checkout form, the same rule as on
 * the server (CheckoutBlocks::add_additional_block() in PHP): right after
 * WooCommerce's Additional information block, else before the order notes,
 * the terms or the Place order block, else at the end.
 */
export const FIELDS_BLOCK = 'woocommerce/checkout-fields-block';

const PLACES: Array< [ 'after' | 'before', string ] > = [
	[ 'after', 'woocommerce/checkout-additional-information-block' ],
	[ 'before', 'woocommerce/checkout-order-note-block' ],
	[ 'before', 'woocommerce/checkout-terms-block' ],
	[ 'before', 'woocommerce/checkout-actions-block' ],
];

/**
 * The index to insert the Additional block at.
 *
 * @param names      Names of the checkout form's inner blocks, in order.
 * @param additional Name of the Additional block.
 * @return The index, or null when the form already has the block.
 */
export function additionalBlockIndex(
	names: string[],
	additional: string
): number | null {
	if ( names.includes( additional ) ) {
		return null;
	}

	for ( const [ where, name ] of PLACES ) {
		const index = names.indexOf( name );
		if ( index !== -1 ) {
			return where === 'after' ? index + 1 : index;
		}
	}

	return names.length;
}

/** The parts of the block editor's data store that placing the block needs. */
export interface EditorAccess {
	/** Client ids of every checkout form block in the editor. */
	formIds: () => string[];
	/** Names of a block's inner blocks, in order. */
	childNames: ( clientId: string ) => string[];
	/** Inserts a new block of a type into a block at an index. */
	insert: ( name: string, index: number, parentId: string ) => void;
}

/**
 * Adds the Additional block to every checkout form in the editor that lacks
 * it, at its place. Returns whether anything was added.
 *
 * @param editor     Block editor access.
 * @param additional Name of the Additional block.
 */
export function placeAdditionalBlock(
	editor: EditorAccess,
	additional: string
): boolean {
	let added = false;
	for ( const formId of editor.formIds() ) {
		const index = additionalBlockIndex(
			editor.childNames( formId ),
			additional
		);
		if ( index !== null ) {
			editor.insert( additional, index, formId );
			added = true;
		}
	}

	return added;
}
