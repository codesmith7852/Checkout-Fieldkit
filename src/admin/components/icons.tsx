/**
 * Inline icons. All are decorative (aria-hidden); the text next to them or the
 * control's label carries the meaning.
 */
import type { SVGProps } from 'react';

type IconProps = SVGProps< SVGSVGElement >;

const base = {
	'aria-hidden': true,
	focusable: false,
	fill: 'none',
	stroke: 'currentColor',
	strokeLinecap: 'round',
	strokeLinejoin: 'round',
} as const;

/**
 * The FieldKit mark: lines of a form with a tick.
 * @param props
 */
export function LogoMark( props: IconProps ) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			strokeWidth="1.8"
			{ ...base }
			{ ...props }
		>
			<path d="M3 4h10M3 8h6M3 12h4" />
			<path d="m10 11.5 1.6 1.6L14 10.5" />
		</svg>
	);
}

export function CheckIcon( props: IconProps ) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			strokeWidth="1.8"
			{ ...base }
			{ ...props }
		>
			<path d="m3.5 8.5 3 3 6-7" />
		</svg>
	);
}

export function InfoIcon( props: IconProps ) {
	return (
		<svg
			width="20"
			height="20"
			viewBox="0 0 20 20"
			strokeWidth="1.6"
			{ ...base }
			{ ...props }
		>
			<circle cx="10" cy="10" r="8" />
			<path d="M10 9v5M10 6v.01" />
		</svg>
	);
}

export function WarningIcon( props: IconProps ) {
	return (
		<svg
			width="20"
			height="20"
			viewBox="0 0 20 20"
			strokeWidth="1.6"
			{ ...base }
			{ ...props }
		>
			<circle cx="10" cy="10" r="8" />
			<path d="M10 6v5M10 14v.01" />
		</svg>
	);
}

export function ErrorIcon( props: IconProps ) {
	return (
		<svg
			width="20"
			height="20"
			viewBox="0 0 20 20"
			strokeWidth="1.6"
			{ ...base }
			{ ...props }
		>
			<path d="M10 2.5 18 17H2L10 2.5Z" />
			<path d="M10 8v4M10 14.5v.01" />
		</svg>
	);
}

export function CloseIcon( props: IconProps ) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 18 18"
			strokeWidth="1.8"
			{ ...base }
			{ ...props }
		>
			<path d="M4 4l10 10M14 4 4 14" />
		</svg>
	);
}
