import { router } from '@inertiajs/react';
import { useCallback } from 'react';
import type { DateRangeValue } from '@/components/date-range-picker';

/**
 * A DateRangePicker `onChange` that pushes the range to the current page as
 * `?from=&to=`. `only` is required, so every caller scopes its partial reload to the
 * props the range actually affects — the server (whose props are closures) then
 * recomputes just those, never the whole page.
 */
export function useDateRangeFilter(
    baseUrl: string,
    only: string[],
): (range: DateRangeValue) => void {
    return useCallback(
        (range: DateRangeValue) => {
            router.get(
                baseUrl,
                { from: range.from, to: range.to },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only,
                },
            );
        },
        [baseUrl, only],
    );
}
