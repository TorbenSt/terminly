import { SVGAttributes } from 'react';

export default function ApplicationLogo(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 48 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <rect width="48" height="48" rx="12" className="fill-current opacity-15" />
            <rect x="10" y="14" width="28" height="24" rx="4" stroke="currentColor" strokeWidth="2.5" />
            <path d="M10 20h28" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
            <path d="M18 10v6M30 10v6" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
            <path
                d="M17 28l4 4 8-8"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
