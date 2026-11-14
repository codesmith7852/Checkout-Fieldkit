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
import { FIELDS_BLOCK, placeAdditionalBlock } from './editor/placement';
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

let placing = false;

subscribe( () => {
	const editor = select( 'core/block-editor' ) as unknown as
		BlockEditorSelectors | undefined;
	if ( placing || ! editor ) {
		return;
	}

	placing = true;
	try {
		placeAdditionalBlock(
			{
				formIds: () =>
					editor.getBlocksByName
						? editor.getBlocksByName( FIELDS_BLOCK )
						: editor
								.getClientIdsWithDescendants()
								.filter(
									( id ) =>
										editor.getBlockName( id ) ===
										FIELDS_BLOCK
								),
				childNames: ( clientId ) =>
					editor
						.getBlockOrder( clientId )
						.map( ( id ) => editor.getBlockName( id ) ?? '' ),
				insert: ( name, index, parentId ) =>
					(
						dispatch(
							'core/block-editor'
						) as unknown as BlockEditorActions
					 ).insertBlock(
						createBlock( name, { lock: { remove: true } } ),
						index,
						parentId,
						false
					),
			},
			ADDITIONAL_BLOCK
		);
	} finally {
		placing = false;
	}
}, 'core/block-editor' );
