/**
 * Tabs (WAI-ARIA tabs pattern): one tab stop on the selected tab; Left/Right
 * move between tabs and wrap, Home/End go to the ends. With automatic
 * activation (the default) moving also selects; with manual activation
 * Enter or Space selects. The children are the selected tab's panel.
 */
import { useId, useRef, useState } from '@wordpress/element';
import type { KeyboardEvent, ReactNode } from 'react';
import { cx } from './cx';

export interface TabItem {
	id: string;
	label: ReactNode;
	/** A number shown after the label, such as the fields in a section. */
	count?: number;
	disabled?: boolean;
}

export interface TabsProps {
	/** Accessible name of the tab list. */
	label: string;
	tabs: TabItem[];
	selected: string;
	onSelect: ( id: string ) => void;
	activation?: 'automatic' | 'manual';
	children?: ReactNode;
	className?: string;
}

export function Tabs( {
	label,
	tabs,
	selected,
	onSelect,
	activation = 'automatic',
	children,
	className,
}: TabsProps ) {
	const prefix = useId();
	const refs = useRef< Array< HTMLButtonElement | null > >( [] );
	const [ focused, setFocused ] = useState< string | null >( null );
	const enabled = tabs.filter( ( tab ) => ! tab.disabled );
	// The tab stop: the focused tab while moving with arrows (manual mode), else the selected one.
	const stop = focused ?? selected;

	const tabId = ( id: string ) => `${ prefix }-tab-${ id }`;
	const panelId = `${ prefix }-panel`;

	const moveTo = ( id: string ) => {
		const index = tabs.findIndex( ( tab ) => tab.id === id );
		refs.current[ index ]?.focus();
		setFocused( id );
		if ( activation === 'automatic' ) {
			onSelect( id );
		}
	};

	const onKeyDown = ( event: KeyboardEvent, id: string ) => {
		const position = enabled.findIndex( ( tab ) => tab.id === id );
		let next: number | null = null;
		if ( event.key === 'ArrowRight' ) {
			next = ( position + 1 ) % enabled.length;
		} else if ( event.key === 'ArrowLeft' ) {
			next = ( position - 1 + enabled.length ) % enabled.length;
		} else if ( event.key === 'Home' ) {
			next = 0;
		} else if ( event.key === 'End' ) {
			next = enabled.length - 1;
		}
		if ( next !== null && enabled[ next ] ) {
			event.preventDefault();
			moveTo( enabled[ next ].id );
		}
	};

	return (
		<div className={ cx( 'cfk-tabs', className ) }>
			<div
				className="cfk-tabs__list"
				role="tablist"
				aria-label={ label }
				onBlur={ ( event ) => {
					if (
						! event.currentTarget.contains(
							event.relatedTarget as Node | null
						)
					) {
						setFocused( null );
					}
				} }
			>
				{ tabs.map( ( tab, index ) => {
					const isSelected = tab.id === selected;
					return (
						<button
							key={ tab.id }
							ref={ ( element ) => {
								refs.current[ index ] = element;
							} }
							type="button"
							role="tab"
							id={ tabId( tab.id ) }
							aria-selected={ isSelected }
							aria-controls={ isSelected ? panelId : undefined }
							tabIndex={ tab.id === stop ? 0 : -1 }
							disabled={ tab.disabled }
							className={ cx(
								'cfk-tabs__tab',
								isSelected && 'is-selected'
							) }
							onClick={ () => {
								setFocused( tab.id );
								onSelect( tab.id );
							} }
							onKeyDown={ ( event ) =>
								onKeyDown( event, tab.id )
							}
						>
							{ tab.label }
							{ tab.count !== undefined && (
								<span className="cfk-tabs__count">
									{ tab.count }
								</span>
							) }
						</button>
					);
				} ) }
			</div>
			<div
				className="cfk-tabs__panel"
				role="tabpanel"
				id={ panelId }
				aria-labelledby={ tabId( selected ) }
				tabIndex={ 0 }
			>
				{ children }
			</div>
		</div>
	);
}
