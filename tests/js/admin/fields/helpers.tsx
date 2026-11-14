/**
 * Renders the Fields screen inside the real providers, with a probe that
 * shows the draft, and gives rows a layout so dnd-kit's keyboard sensor can
 * measure them in jsdom.
 */
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Providers } from '../../../../src/admin/App';
import type { Api } from '../../../../src/admin/api';
import { FieldsScreen } from '../../../../src/admin/screens/FieldsScreen';
import { useDraft, useFieldSet } from '../../../../src/admin/store';
import type {
	CheckoutType,
	FieldSet,
	InitialData,
} from '../../../../src/admin/types';
import { initialData } from '../fixtures';
import { fieldsContext } from './fixtures';

function fakeApi( overrides: Partial< Api > = {} ): Api {
	return {
		getContext: jest.fn(),
		getFieldSet: jest.fn(),
		getSettings: jest.fn(),
		putFieldSet: jest.fn(),
		putSettings: jest.fn(),
		...overrides,
	} as unknown as Api;
}

/** Exposes the draft to the tests. */
const probe: {
	changeCount: number;
	fieldSets: Partial< Record< CheckoutType, FieldSet > >;
	save: () => Promise< unknown >;
} = { changeCount: 0, fieldSets: {}, save: async () => null };

function Probe() {
	const { changeCount, save } = useDraft();
	const classic = useFieldSet( 'classic' );
	const block = useFieldSet( 'block' );
	probe.changeCount = changeCount;
	probe.save = save;
	probe.fieldSets = { classic: classic.fieldSet, block: block.fieldSet };
	return null;
}

export function draft() {
	return probe;
}

/**
 * Names of the fields in a section of the draft.
 * @param type
 * @param section
 */
export function names( type: CheckoutType, section: string ): string[] {
	return ( probe.fieldSets[ type ]?.sections[ section ] || [] ).map(
		( field ) => field.name
	);
}

export function renderFields(
	search = '?page=checkout-fieldkit',
	data: Partial< InitialData > = {},
	api: Partial< Api > = {}
) {
	window.history.replaceState( null, '', '/wp-admin/admin.php' + search );
	const user = userEvent.setup();
	const all = initialData( { context: fieldsContext(), ...data } );
	const view = render(
		<Providers
			settings={ {
				preloaded: {
					context: all.context,
					fieldSets: { classic: all.classic, block: all.block },
					settings: all.settings,
				},
			} }
			api={ fakeApi( api ) }
		>
			<FieldsScreen />
			<Probe />
		</Providers>
	);
	return { user, ...view };
}

/**
 * The table row of a field, found by its field name.
 * @param name
 */
export function rowOf( name: string ): HTMLElement {
	const code = screen.getByText( name, { selector: 'code' } );
	return code.closest( 'tr' ) as HTMLElement;
}

export function inRow( name: string ) {
	return within( rowOf( name ) );
}

/**
 * jsdom has no layout: give each table body row a 48px-high box in DOM
 * order, so dnd-kit can work out which row is above or below.
 */
export function mockRowLayout() {
	const original = Element.prototype.getBoundingClientRect;
	jest.spyOn( Element.prototype, 'getBoundingClientRect' ).mockImplementation(
		function ( this: Element ) {
			const row = this.closest( 'tbody tr' );
			if ( ! row || ! row.parentElement ) {
				return original.call( this );
			}
			const index = Array.from( row.parentElement.children ).indexOf(
				row
			);
			const top = index * 48;
			return {
				x: 0,
				y: top,
				top,
				left: 0,
				right: 900,
				bottom: top + 48,
				width: 900,
				height: 48,
				toJSON: () => ( {} ),
			} as DOMRect;
		}
	);
}
