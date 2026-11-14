/**
 * Icons for the Tools screen. Decorative, like ../../components/icons.
 */
import type { SVGProps } from 'react';

type IconProps = SVGProps< SVGSVGElement >;

export function DownloadIcon( props: IconProps ) {
	return (
		<svg
			width="16"
			height="16"
			viewBox="0 0 16 16"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.7"
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden
			focusable={ false }
			{ ...props }
		>
			<path d="M8 2v8M4.5 6.5 8 10l3.5-3.5M3 13h10" />
		</svg>
	);
}

export function FileIcon( props: IconProps ) {
	return (
		<svg
			width="20"
			height="20"
			viewBox="0 0 20 20"
			fill="none"
			stroke="currentColor"
			strokeWidth="1.5"
			aria-hidden
			focusable={ false }
			{ ...props }
		>
			<path d="M5 2.5h7l3.5 3.5v11.5H5z" />
			<path d="M12 2.5V6h3.5" />
		</svg>
	);
}
