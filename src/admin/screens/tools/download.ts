/**
 * Saving an export file in the browser: a Blob behind a temporary link with
 * a `download` name.
 */
import type { ExportFile } from '../../types';

/**
 * Starts the browser's download of an export file.
 *
 * @param file The file name and the JSON text.
 */
export function downloadFile( file: ExportFile ): void {
	const blob = new Blob( [ file.contents ], { type: 'application/json' } );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = file.fileName;
	link.rel = 'noopener';
	link.hidden = true;
	document.body.appendChild( link );
	link.click();
	link.remove();
	// Some browsers read the URL after click() returns; let them start first.
	window.setTimeout( () => URL.revokeObjectURL( url ), 0 );
}

/**
 * Reads a chosen file as text.
 *
 * @param file The file from an `<input type="file">`.
 */
export function readText( file: Blob ): Promise< string > {
	return new Promise( ( resolve, reject ) => {
		const reader = new FileReader();
		reader.onload = () => resolve( String( reader.result ?? '' ) );
		reader.onerror = () => reject( reader.error );
		reader.readAsText( file );
	} );
}
