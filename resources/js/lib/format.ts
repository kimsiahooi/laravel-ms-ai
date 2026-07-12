// A FIXED locale for number formatting. These strings are rendered during SSR (Node)
// and again on the client (browser); an unpinned `undefined` locale resolves
// differently across the two runtimes, producing "12,500" vs "12.500" and a React
// hydration mismatch (#418). Pinning to en-US (comma-group, dot-decimal — the grouping
// MY/SG users expect) makes the output identical on both sides. Dates keep the viewer's
// locale intentionally (they render client-side, past hydration, or via timeAgo).
const NUMBER_LOCALE = 'en-US';

const RELATIVE_TIME = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

/** Best-effort relative timestamp; render-time snapshot (does not tick live). */
export function timeAgo(iso: string): string {
    const then = new Date(iso).getTime();

    if (Number.isNaN(then)) {
        return '';
    }

    const seconds = Math.round((then - Date.now()) / 1000);
    const abs = Math.abs(seconds);

    if (abs < 60) {
        return 'just now';
    }

    const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
        ['minute', 60],
        ['hour', 3600],
        ['day', 86400],
        ['month', 2592000],
        ['year', 31536000],
    ];

    let chosen: [Intl.RelativeTimeFormatUnit, number] = ['minute', 60];

    for (const unit of units) {
        if (abs >= unit[1]) {
            chosen = unit;
        }
    }

    return RELATIVE_TIME.format(Math.round(seconds / chosen[1]), chosen[0]);
}

/** Format a stock quantity for display: digit grouping + up to 4 decimals. */
export function formatQuantity(value: number | string): string {
    return Number(value).toLocaleString(NUMBER_LOCALE, {
        maximumFractionDigits: 4,
    });
}

/** Compact number for tight axis ticks / chips — e.g. 1200 → "1.2K". */
export function formatCompact(value: number): string {
    return new Intl.NumberFormat(NUMBER_LOCALE, {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}

export function absoluteDate(iso: string): string {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

/** Medium calendar date, no time — e.g. "Jul 10, 2026". */
export function formatDate(iso: string): string {
    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        date,
    );
}

/** Currency-formatted amount, falling back to "CODE 0.00" for unknown codes. */
export function formatMoney(amount: number, currency: string): string {
    try {
        return new Intl.NumberFormat(NUMBER_LOCALE, {
            style: 'currency',
            currency,
        }).format(amount);
    } catch {
        return `${currency} ${amount.toFixed(2)}`;
    }
}
