/**
 * Icons of the Fields screen. All are decorative (aria-hidden): the control's
 * label carries the meaning.
 */
import type { SVGProps } from 'react';

type IconProps = SVGProps< SVGSVGElement >;

const hidden = { 'aria-hidden': true, focusable: false } as const;

export function DragHandleIcon( props: IconProps ) {
	return (
		<svg
			width="12"
			height="16"
			viewBox="0 0 12 16"
			fill="currentColor"
			{ ...hidden }
			{ ...props }
		>
			<circle cx="3" cy="3" r="1.4" />
			<circle cx="9" cy="3" r="1.4" />
			<circle cx="3" cy="8" r="1.4" />
			<circle cx="9" cy="8" r="1.4" />
			<circle cx="3" cy="13" r="1.4" />
			<circle cx="9" cy="13" r="1.4" />
		</svg>
	);
}

export function PlusIcon( props: IconProps ) {
	return (
		<svg
			width="14"
			height="14"
			viewBox="0 0 14 14"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.8"
			strokeLinecap="round"
			{ ...hidden }
			{ ...props }
		>
			<path d="M7 2v10M2 7h10" />
		</svg>
	);
}

export function LockIcon( props: IconProps ) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.5"
			{ ...hidden }
			{ ...props }
		>
			<rect x="3" y="7" width="10" height="7" rx="1.5" />
			<path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2" />
		</svg>
	);
}

export function MoreIcon( props: IconProps ) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="currentColor"
			{ ...hidden }
			{ ...props }
		>
			<circle cx="3" cy="8" r="1.4" />
			<circle cx="8" cy="8" r="1.4" />
			<circle cx="13" cy="8" r="1.4" />
		</svg>
	);
}

export function SmallInfoIcon( props: IconProps ) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.5"
			strokeLinecap="round"
			{ ...hidden }
			{ ...props }
		>
			<circle cx="8" cy="8" r="6.5" />
			<path d="M8 7v4M8 4.8v.01" />
		</svg>
	);
}

export function WarningBadgeIcon( props: IconProps ) {
	return (
		<svg
			width="22"
			height="22"
			viewBox="0 0 22 22"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.7"
			strokeLinejoin="round"
			strokeLinecap="round"
			{ ...hidden }
			{ ...props }
		>
			<path d="M11 3 2.5 18h17L11 3Z" />
			<path d="M11 9v4M11 15.5v.01" />
		</svg>
	);
}

/**
 * The empty section illustration: a form with a plus.
 * @param props
 */
export function EmptyFormIllustration( props: IconProps ) {
	return (
		<svg
			width="72"
			height="72"
			viewBox="0 0 72 72"
			fill="none"
			strokeWidth="1.6"
			className="cfk-empty-section__art"
			{ ...hidden }
			{ ...props }
		>
			<g stroke="var(--cfk-icon-muted)" strokeLinecap="round">
				<rect x="14" y="10" width="44" height="52" rx="6" />
				<path d="M23 24h26M23 34h18M23 44h12" />
			</g>
			<circle
				cx="52"
				cy="52"
				r="10"
				fill="var(--cfk-accent-soft)"
				stroke="var(--cfk-accent)"
			/>
			<path
				d="M52 47v10M47 52h10"
				stroke="var(--cfk-accent)"
				strokeLinecap="round"
			/>
		</svg>
	);
}
