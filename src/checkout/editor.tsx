/**
 * Editor side of our Block checkout inner blocks: registers the two block
 * types (so the Checkout block accepts them and shows their placeholder) and
 * puts the Additional block in place in Checkout pages opened in the editor.
 *
 * The Contact block is locked against removal by default, so WooCommerce's
 * Contact information block adds it by itself. The Additional block is not
 * (WooCommerce would add it after Place order); placeAdditionalBlock() adds it
 * right after the Additional information block instead, locked against removal.
 */
import { registerBlockType, createBlock } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { select, dispatch, subscribe } from '@wordpress/data';
import Preview from './editor/Preview';
import {
	additionalBlockIndex,
	FIELDS_BLOCK,
	placeAdditionalBlock,
	type EditorAccess,
} from './editor/placement';
import { ADDITIONAL_BLOCK, BLOCKS } from './blocks';

for ( const { metadata, section } of BLOCKS ) {
	registerBlockType( metadata as any, {
		edit: () => <Preview section={ section } />,
		save: () => <div { ...useBlockProps.save() } />,
	} );
}

interface BlockEditorSelectors {
	getBlocksByName?: ( name: string ) => string[];
	getClientIdsWithDescendants: () => string[];
	getBlockName: ( clientId: string ) => string | null;
	getBlockOrder: ( clientId: string ) => string[];
}

interface BlockEditorActions {
	insertBlock: (
		block: ReturnType< typeof createBlock >,
		index: number,
		rootClientId: string,
		updateSelection: boolean
	) => void;
}

/**
 * Access to the block editor's data for placeAdditionalBlock().
 */
function editorAccess(): EditorAccess | null {
	const editor = select( 'core/block-editor' ) as unknown as
		BlockEditorSelectors | undefined;
	if ( ! editor ) {
		return null;
	}

	return {
		formIds: () =>
			editor.getBlocksByName
				? editor.getBlocksByName( FIELDS_BLOCK )
				: editor
						.getClientIdsWithDescendants()
						.filter(
							( id ) => editor.getBlockName( id ) === FIELDS_BLOCK
						),
		childNames: ( clientId ) =>
			editor
				.getBlockOrder( clientId )
				.map( ( id ) => editor.getBlockName( id ) ?? '' ),
		insert: ( name, index, parentId ) =>
			(
				dispatch( 'core/block-editor' ) as unknown as BlockEditorActions
			 ).insertBlock(
				createBlock( name, { lock: { remove: true } } ),
				index,
				parentId,
				false
			),
	};
}

let scheduled = false;

/**
 * Whether WooCommerce's payment methods have finished loading in the editor.
 * Changing the checkout form while they load makes WooCommerce's payment
 * block re-render endlessly, so the block is placed afterwards.
 */
function paymentsSettled(): boolean {
	const payment = select( 'wc/store/payment' ) as unknown as
		| {
				paymentMethodsInitialized?: () => boolean;
				expressPaymentMethodsInitialized?: () => boolean;
		  }
		| undefined;

	return (
		! payment ||
		( ( payment.paymentMethodsInitialized?.() ?? true ) &&
			( payment.expressPaymentMethodsInitialized?.() ?? true ) )
	);
}

// Watch for checkout forms without the block. The block is inserted after the
// current update has finished, not while the editor is still notifying its
// listeners, so the Checkout block's own components are not updated mid-render.
subscribe( () => {
	const access = editorAccess();
	if ( scheduled || ! access || ! paymentsSettled() ) {
		return;
	}

	const missing = access
		.formIds()
		.some(
			( id ) =>
				additionalBlockIndex(
					access.childNames( id ),
					ADDITIONAL_BLOCK
				) !== null
		);
	if ( ! missing ) {
		return;
	}

	scheduled = true;
	window.setTimeout( () => {
		const current = editorAccess();
		if ( current ) {
			placeAdditionalBlock( current, ADDITIONAL_BLOCK );
		}
		scheduled = false;
	}, 0 );
}, 'core/block-editor' );
