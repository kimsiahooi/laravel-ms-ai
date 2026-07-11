import { useSyncExternalStore } from 'react';

// Below this width the sidebar collapses to an off-canvas drawer instead of a
// pinned column, so tablets (e.g. iPad portrait at 768px) get the full content
// width instead of a cramped ~510px column. Matches Tailwind's `lg` breakpoint.
const MOBILE_BREAKPOINT = 1024;

const mql =
    typeof window === 'undefined'
        ? undefined
        : window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);

function mediaQueryListener(callback: (event: MediaQueryListEvent) => void) {
    if (!mql) {
        return () => {};
    }

    mql.addEventListener('change', callback);

    return () => {
        mql.removeEventListener('change', callback);
    };
}

function isSmallerThanBreakpoint(): boolean {
    return mql?.matches ?? false;
}

function getServerSnapshot(): boolean {
    return false;
}

export function useIsMobile(): boolean {
    return useSyncExternalStore(
        mediaQueryListener,
        isSmallerThanBreakpoint,
        getServerSnapshot,
    );
}
