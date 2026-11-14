/**
 * The two inner blocks' metadata (block.json), shared by the frontend and
 * the editor.
 */
import contactMetadata from './blocks/contact-fields/block.json';
import additionalMetadata from './blocks/additional-fields/block.json';
import type { Section } from './types';

export const CONTACT_BLOCK = contactMetadata.name;
export const ADDITIONAL_BLOCK = additionalMetadata.name;

export const BLOCKS: Array< {
	metadata: typeof contactMetadata | typeof additionalMetadata;
	section: Section;
} > = [
	{ metadata: contactMetadata, section: 'contact' },
	{ metadata: additionalMetadata, section: 'additional' },
];
