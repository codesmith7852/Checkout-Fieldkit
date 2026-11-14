/**
 * Screen reader announcements (spec §6.6): a polite and an assertive live
 * region, and `useAnnounce()` to speak through them from anywhere in the app.
 */
import {
	createContext,
	useCallback,
	useContext,
	useMemo,
	useState,
} from '@wordpress/element';
import type { ReactNode } from 'react';

export type Politeness = 'polite' | 'assertive';

export type Announce = ( message: string, politeness?: Politeness ) => void;

const AnnounceContext = createContext< Announce >( () => {} );

interface Message {
	text: string;
	/** Changes with every announcement, so the same text is spoken again. */
	key: number;
}

export interface LiveRegionProps {
	message: string;
	politeness?: Politeness;
	/** Re-render key: a new key makes screen readers repeat the same text. */
	messageKey?: number;
}

/**
 * A visually hidden live region. Polite regions have role `status`, assertive
 * ones role `alert`.
 *
 * @param props            Props.
 * @param props.message    Text to announce.
 * @param props.politeness `polite` (default) or `assertive`.
 * @param props.messageKey Changes when the same text should be spoken again.
 */
export function LiveRegion( {
	message,
	politeness = 'polite',
	messageKey = 0,
}: LiveRegionProps ) {
	return (
		<div
			className="cfk-visually-hidden"
			role={ politeness === 'assertive' ? 'alert' : 'status' }
			aria-live={ politeness }
			aria-atomic="true"
		>
			{ message ? <span key={ messageKey }>{ message }</span> : null }
		</div>
	);
}

/**
 * Renders the app's two live regions after its children.
 *
 * @param props          Props.
 * @param props.children The app.
 */
export function AnnouncerProvider( { children }: { children: ReactNode } ) {
	const [ polite, setPolite ] = useState< Message >( { text: '', key: 0 } );
	const [ assertive, setAssertive ] = useState< Message >( {
		text: '',
		key: 0,
	} );

	const announce = useCallback< Announce >(
		( text, politeness = 'polite' ) => {
			const set = politeness === 'assertive' ? setAssertive : setPolite;
			const clear = politeness === 'assertive' ? setPolite : setAssertive;
			set( ( previous ) => ( { text, key: previous.key + 1 } ) );
			// Only the latest announcement stays in the tree.
			clear( ( previous ) => ( { ...previous, text: '' } ) );
		},
		[]
	);

	const value = useMemo( () => announce, [ announce ] );

	return (
		<AnnounceContext.Provider value={ value }>
			{ children }
			<LiveRegion
				politeness="polite"
				message={ polite.text }
				messageKey={ polite.key }
			/>
			<LiveRegion
				politeness="assertive"
				message={ assertive.text }
				messageKey={ assertive.key }
			/>
		</AnnounceContext.Provider>
	);
}

/** The app's announcer; a no-op outside AnnouncerProvider. */
export function useAnnounce(): Announce {
	return useContext( AnnounceContext );
}
