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
