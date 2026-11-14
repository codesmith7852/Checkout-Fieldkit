/**
 * The field editor drawer (spec §6.3). The Fields screen opens it with
 *
 *     const { openNew, openExisting } = useFieldEditor();
 *     openNew( 'classic', 'billing' );        // Add field
 *     openExisting( 'block', row.id );        // Edit, with the row id from useFieldSet()
 *
 * FieldEditorProvider is mounted by the shell around the screens.
 */
export { FieldEditorProvider, useFieldEditor } from './FieldEditorProvider';
export type { FieldEditorApi } from './FieldEditorProvider';
