import { useEffect, useState } from 'react';

// The sidebar collapses to an off-canvas drawer below this width instead of a
// pinned column, so tablets (e.g. iPad portrait at 768px) get the full content
// width instead of a cramped ~510px column. Matches Tailwind's `lg` breakpoint,
// and is kept in sync with the sidebar breakpoint override in
// resources/css/app.css (both must use 1024).
const MOBILE_BREAKPOINT = 1024;

export function useIsMobile(): boolean {
    const [isMobile, setIsMobile] = useState<boolean | undefined>(undefined);

    useEffect(() => {
        const mql = window.matchMedia(
            `(max-width: ${MOBILE_BREAKPOINT - 1}px)`,
        );
        const onChange = () => {
            setIsMobile(window.innerWidth < MOBILE_BREAKPOINT);
        };
        mql.addEventListener('change', onChange);
        setIsMobile(window.innerWidth < MOBILE_BREAKPOINT);
        return () => mql.removeEventListener('change', onChange);
    }, []);

    return !!isMobile;
}
