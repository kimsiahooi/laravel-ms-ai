import {
    endOfMonth,
    endOfWeek,
    format,
    startOfMonth,
    startOfWeek,
    subDays,
    subMonths,
    subWeeks,
} from 'date-fns';
import { CalendarDays, ChevronDown } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

/** A range of local calendar days, inclusive, as YYYY-MM-DD strings. */
export type DateRangeValue = { from: string; to: string };

// Business weeks start on Monday.
const WEEK_OPTS = { weekStartsOn: 1 as const };

type Preset = {
    key: string;
    label: string;
    compute: (now: Date) => DateRangeValue;
};

// A local Date -> "YYYY-MM-DD" (device timezone; no UTC conversion).
const toStr = (date: Date): string => format(date, 'yyyy-MM-dd');

// "YYYY-MM-DD" -> a local Date at midnight (avoids Date's UTC string parsing).
const toDate = (value: string): Date => {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
};

// Every preset is computed from the browser's "now", i.e. the user's own device
// clock and timezone. That is what "Today" / "This week" resolve against.
const PRESETS: Preset[] = [
    {
        key: 'today',
        label: 'Today',
        compute: (n) => ({ from: toStr(n), to: toStr(n) }),
    },
    {
        key: 'this_week',
        label: 'This week',
        compute: (n) => ({
            from: toStr(startOfWeek(n, WEEK_OPTS)),
            to: toStr(n),
        }),
    },
    {
        key: 'last_week',
        label: 'Last week',
        compute: (n) => {
            const w = subWeeks(n, 1);
            return {
                from: toStr(startOfWeek(w, WEEK_OPTS)),
                to: toStr(endOfWeek(w, WEEK_OPTS)),
            };
        },
    },
    {
        key: 'this_month',
        label: 'This month',
        compute: (n) => ({ from: toStr(startOfMonth(n)), to: toStr(n) }),
    },
    {
        key: 'last_month',
        label: 'Last month',
        compute: (n) => {
            const m = subMonths(n, 1);
            return { from: toStr(startOfMonth(m)), to: toStr(endOfMonth(m)) };
        },
    },
    {
        key: 'last_7',
        label: 'Last 7 days',
        compute: (n) => ({ from: toStr(subDays(n, 6)), to: toStr(n) }),
    },
    {
        key: 'last_30',
        label: 'Last 30 days',
        compute: (n) => ({ from: toStr(subDays(n, 29)), to: toStr(n) }),
    },
    {
        key: 'last_90',
        label: 'Last 90 days',
        compute: (n) => ({ from: toStr(subDays(n, 89)), to: toStr(n) }),
    },
];

/** A deterministic, SSR-safe label for a range (no "now" dependency). */
export function formatRangeSpan(value: DateRangeValue): string {
    const from = toDate(value.from);
    const to = toDate(value.to);
    if (value.from === value.to) return format(from, 'MMM d, yyyy');
    return `${format(from, 'MMM d')} – ${format(to, 'MMM d, yyyy')}`;
}

function matchPreset(value: DateRangeValue, now: Date): Preset | undefined {
    return PRESETS.find((p) => {
        const r = p.compute(now);
        return r.from === value.from && r.to === value.to;
    });
}

/**
 * A date-range control: a list of device-timezone presets plus a shadcn Calendar
 * for a custom range. Emits the chosen range as local YYYY-MM-DD dates; the caller
 * decides what to do with it (the dashboard sends it + the device timezone to the
 * server).
 */
export function DateRangePicker({
    value,
    onChange,
    disabled = false,
}: {
    value: DateRangeValue;
    onChange: (value: DateRangeValue) => void;
    disabled?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<DateRange | undefined>({
        from: toDate(value.from),
        to: toDate(value.to),
    });

    // "now" is device-local and only known on the client, so preset matching is
    // deferred to after mount; the first paint shows the SSR-safe span label.
    const [now, setNow] = useState<Date | null>(null);
    useEffect(() => setNow(new Date()), []);

    const activePreset = useMemo(
        () => (now ? matchPreset(value, now) : undefined),
        [now, value],
    );
    const label = activePreset ? activePreset.label : formatRangeSpan(value);

    const applyPreset = (preset: Preset) => {
        const range = preset.compute(new Date());
        onChange(range);
        setDraft({ from: toDate(range.from), to: toDate(range.to) });
        setOpen(false);
    };

    const applyCustom = () => {
        if (!draft?.from || !draft?.to) return;
        onChange({ from: toStr(draft.from), to: toStr(draft.to) });
        setOpen(false);
    };

    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                // Re-sync the calendar to the current value each time it opens.
                if (next) {
                    setDraft({
                        from: toDate(value.from),
                        to: toDate(value.to),
                    });
                }
                setOpen(next);
            }}
        >
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={disabled}
                    className="gap-2"
                >
                    <CalendarDays className="size-4" />
                    <span>{label}</span>
                    <ChevronDown className="size-4 text-muted-foreground" />
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-auto p-0">
                <div className="flex max-sm:flex-col">
                    <div className="flex shrink-0 flex-col gap-1 border-b p-2 sm:border-r sm:border-b-0">
                        {PRESETS.map((preset) => (
                            <Button
                                key={preset.key}
                                variant={
                                    activePreset?.key === preset.key
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                size="sm"
                                className="justify-start"
                                onClick={() => applyPreset(preset)}
                            >
                                {preset.label}
                            </Button>
                        ))}
                    </div>
                    <div className="flex flex-col gap-2 p-2">
                        <Calendar
                            mode="range"
                            numberOfMonths={1}
                            selected={draft}
                            onSelect={setDraft}
                            defaultMonth={draft?.from}
                        />
                        <Button
                            size="sm"
                            onClick={applyCustom}
                            disabled={!draft?.from || !draft?.to}
                        >
                            Apply range
                        </Button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
