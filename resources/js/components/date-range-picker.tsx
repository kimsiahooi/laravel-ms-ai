import {
    endOfDay,
    endOfMonth,
    endOfWeek,
    endOfYear,
    format,
    parseISO,
    startOfDay,
    startOfMonth,
    startOfWeek,
    startOfYear,
    subDays,
    subMonths,
    subWeeks,
    subYears,
} from 'date-fns';
import { CalendarDays, ChevronDown } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/**
 * An inclusive datetime range. `from`/`to` are ISO-8601 strings that carry their
 * own UTC offset (e.g. "2026-07-10T09:30:00+08:00"), so the value alone is enough
 * to place it on a timeline — no separate timezone field is needed.
 */
export type DateRangeValue = { from: string; to: string };

// Business weeks start on Monday.
const WEEK_OPTS = { weekStartsOn: 1 as const };

// A local Date -> "2026-07-10T09:30:00+08:00" (offset is the browser's own).
const toIso = (date: Date): string => format(date, "yyyy-MM-dd'T'HH:mm:ssxxx");
const timeOf = (date: Date): string => format(date, 'HH:mm');

const pad = (n: number): string => String(n).padStart(2, '0');

const HOURS_12 = Array.from({ length: 12 }, (_, i) => String(i + 1));
const MINUTES = Array.from({ length: 60 }, (_, i) => pad(i));
const MERIDIEMS = ['AM', 'PM'];

// "HH:mm" (24h) -> the three dropdown values (12-hour clock).
function to12(value: string): {
    hour: string;
    minute: string;
    meridiem: string;
} {
    const [h, m] = value.split(':').map(Number);
    const hour24 = h || 0;
    return {
        hour: String(hour24 % 12 === 0 ? 12 : hour24 % 12),
        minute: pad(m || 0),
        meridiem: hour24 < 12 ? 'AM' : 'PM',
    };
}

// The three dropdown values -> "HH:mm" (24h).
function to24(hour: string, minute: string, meridiem: string): string {
    let h = Number(hour) % 12;
    if (meridiem === 'PM') h += 12;
    return `${pad(h)}:${minute}`;
}

// "HH:mm" (24h) -> "9:15 AM" for display.
function time12(value: string): string {
    const { hour, minute, meridiem } = to12(value);
    return `${hour}:${minute} ${meridiem}`;
}

function TimeCol({
    id,
    label,
    options,
    value,
    onChange,
}: {
    id: string;
    label: string;
    options: string[];
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger id={id} aria-label={label} className="flex-1">
                <SelectValue />
            </SelectTrigger>
            <SelectContent className="max-h-56">
                {options.map((option) => (
                    <SelectItem key={option} value={option}>
                        {option}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

// A time field as three dropdowns: hour, minute, and AM/PM.
function TimeSelect({
    idPrefix,
    label,
    value,
    onChange,
}: {
    idPrefix: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    const { hour, minute, meridiem } = to12(value);
    const set = (h: string, m: string, mer: string) =>
        onChange(to24(h, m, mer));

    return (
        <div className="space-y-1">
            <Label className="text-muted-foreground text-xs">{label}</Label>
            <div className="flex items-center gap-1">
                <TimeCol
                    id={`${idPrefix}-hour`}
                    label={`${label} hour`}
                    options={HOURS_12}
                    value={hour}
                    onChange={(h) => set(h, minute, meridiem)}
                />
                <span className="text-muted-foreground">:</span>
                <TimeCol
                    id={`${idPrefix}-minute`}
                    label={`${label} minute`}
                    options={MINUTES}
                    value={minute}
                    onChange={(m) => set(hour, m, meridiem)}
                />
                <TimeCol
                    id={`${idPrefix}-meridiem`}
                    label={`${label} AM or PM`}
                    options={MERIDIEMS}
                    value={meridiem}
                    onChange={(mer) => set(hour, minute, mer)}
                />
            </div>
        </div>
    );
}

// Combine a calendar day with an "HH:mm" time into a local Date.
function combine(day: Date, time: string, endOfMinute = false): Date {
    const [h, m] = time.split(':').map(Number);
    return new Date(
        day.getFullYear(),
        day.getMonth(),
        day.getDate(),
        h || 0,
        m || 0,
        endOfMinute ? 59 : 0,
    );
}

type Preset = {
    key: string;
    label: string;
    // Computed from the browser's "now" — the user's own device clock + zone.
    compute: (now: Date) => { from: Date; to: Date };
};

const PRESETS: Preset[] = [
    {
        key: 'today',
        label: 'Today',
        compute: (n) => ({ from: startOfDay(n), to: endOfDay(n) }),
    },
    {
        key: 'this_week',
        label: 'This week',
        compute: (n) => ({ from: startOfWeek(n, WEEK_OPTS), to: endOfDay(n) }),
    },
    {
        key: 'last_week',
        label: 'Last week',
        compute: (n) => {
            const w = subWeeks(n, 1);
            return {
                from: startOfWeek(w, WEEK_OPTS),
                to: endOfWeek(w, WEEK_OPTS),
            };
        },
    },
    {
        key: 'this_month',
        label: 'This month',
        compute: (n) => ({ from: startOfMonth(n), to: endOfDay(n) }),
    },
    {
        key: 'last_month',
        label: 'Last month',
        compute: (n) => {
            const m = subMonths(n, 1);
            return { from: startOfMonth(m), to: endOfMonth(m) };
        },
    },
    {
        key: 'this_year',
        label: 'This year',
        compute: (n) => ({ from: startOfYear(n), to: endOfDay(n) }),
    },
    {
        key: 'last_year',
        label: 'Last year',
        compute: (n) => {
            const y = subYears(n, 1);
            return { from: startOfYear(y), to: endOfYear(y) };
        },
    },
    {
        key: 'last_7',
        label: 'Last 7 days',
        compute: (n) => ({ from: startOfDay(subDays(n, 6)), to: endOfDay(n) }),
    },
    {
        key: 'last_30',
        label: 'Last 30 days',
        compute: (n) => ({ from: startOfDay(subDays(n, 29)), to: endOfDay(n) }),
    },
    {
        key: 'last_90',
        label: 'Last 90 days',
        compute: (n) => ({ from: startOfDay(subDays(n, 89)), to: endOfDay(n) }),
    },
];

/** A short, date-only label for a range (no time), e.g. "Jul 1 – Jul 10, 2026". */
export function formatRangeDates(value: DateRangeValue): string {
    const from = parseISO(value.from);
    const to = parseISO(value.to);
    if (format(from, 'yyyy-MM-dd') === format(to, 'yyyy-MM-dd')) {
        return format(from, 'MMM d, yyyy');
    }
    return `${format(from, 'MMM d')} – ${format(to, 'MMM d, yyyy')}`;
}

/** A label with date + time, e.g. "Jul 1, 12:00 AM – Jul 10, 11:59 PM". */
export function formatRangeDateTime(value: DateRangeValue): string {
    const from = parseISO(value.from);
    const to = parseISO(value.to);
    return `${format(from, 'MMM d, h:mm a')} – ${format(to, 'MMM d, h:mm a')}`;
}

/** The preset a value corresponds to (matched to the minute in local time), if any. */
function matchPreset(value: DateRangeValue, now: Date): Preset | null {
    const key = (d: Date) => format(d, 'yyyy-MM-dd HH:mm');
    const vFrom = key(parseISO(value.from));
    const vTo = key(parseISO(value.to));
    return (
        PRESETS.find((p) => {
            const { from, to } = p.compute(now);
            return key(from) === vFrom && key(to) === vTo;
        }) ?? null
    );
}

/** The default range: the current month (12:00 AM on the 1st → 11:59 PM today), local. */
export function thisMonthRange(): DateRangeValue {
    const now = new Date();
    return { from: toIso(startOfMonth(now)), to: toIso(endOfDay(now)) };
}

/**
 * A datetime-range control: device-timezone presets, a shadcn Calendar for the
 * dates, and From/To time inputs. Emits `{ from, to }` as offset-carrying ISO
 * datetimes so the caller can send them as-is with no separate timezone.
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
    const [range, setRange] = useState<DateRange | undefined>({
        from: parseISO(value.from),
        to: parseISO(value.to),
    });
    const [fromTime, setFromTime] = useState(timeOf(parseISO(value.from)));
    const [toTime, setToTime] = useState(timeOf(parseISO(value.to)));
    const [presetKey, setPresetKey] = useState<string | null>(null);
    // false = a complete range is shown; the next click starts a fresh one.
    // true = a start is picked and the next click sets the end.
    const [pickingEnd, setPickingEnd] = useState(false);
    // A 2-month calendar is ~480px wide — too wide for a phone. Show one month
    // below `sm` (the popover then fits a 375px screen), two on larger screens.
    // The popover only renders on open (client-side), so reading matchMedia here
    // is hydration-safe.
    const [monthsToShow, setMonthsToShow] = useState<1 | 2>(() =>
        typeof window !== 'undefined' &&
        window.matchMedia('(min-width: 640px)').matches
            ? 2
            : 1,
    );

    useEffect(() => {
        const mq = window.matchMedia('(min-width: 640px)');
        const update = () => setMonthsToShow(mq.matches ? 2 : 1);
        update();
        mq.addEventListener('change', update);
        return () => mq.removeEventListener('change', update);
    }, []);

    const syncToValue = () => {
        setRange({ from: parseISO(value.from), to: parseISO(value.to) });
        setFromTime(timeOf(parseISO(value.from)));
        setToTime(timeOf(parseISO(value.to)));
        setPickingEnd(false);
        setPresetKey(matchPreset(value, new Date())?.key ?? null);
    };

    const pickPreset = (preset: Preset) => {
        const { from, to } = preset.compute(new Date());
        setRange({ from, to });
        setFromTime(timeOf(from));
        setToTime(timeOf(to));
        setPresetKey(preset.key);
        setPickingEnd(false);
    };

    // Reproduce shadcn's default range flow even though a range is pre-selected:
    // 1st click starts a fresh range, 2nd sets the end, a 3rd starts over.
    const pickDay = (day: Date) => {
        setPresetKey(null);
        if (pickingEnd) {
            setRange((prev) => {
                const start = prev?.from ?? day;
                return day < start
                    ? { from: day, to: start }
                    : { from: start, to: day };
            });
            setPickingEnd(false);
        } else {
            setRange({ from: day, to: undefined });
            setPickingEnd(true);
        }
    };

    const apply = () => {
        if (!range?.from || !range?.to) return;
        onChange({
            from: toIso(combine(range.from, fromTime)),
            to: toIso(combine(range.to, toTime, true)),
        });
        setOpen(false);
    };

    // The trigger always shows the applied range as date + time.
    const label = formatRangeDateTime(value);

    // A live summary of what's being drafted, shown next to Apply.
    const draftSummary =
        range?.from && range?.to
            ? `${format(range.from, 'MMM d')}, ${time12(fromTime)} → ${format(range.to, 'MMM d')}, ${time12(toTime)}`
            : range?.from
              ? `${format(range.from, 'MMM d')} — pick an end date`
              : 'Pick a start date';

    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                if (next) syncToValue();
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
            <PopoverContent
                align="end"
                className="w-auto max-w-[calc(100vw-1rem)] p-0"
            >
                <div className="flex max-sm:flex-col">
                    <div className="flex shrink-0 flex-col gap-0.5 border-b p-2 sm:w-40 sm:border-r sm:border-b-0">
                        <p className="px-2 pt-1 pb-1.5 font-medium text-muted-foreground text-xs">
                            Quick ranges
                        </p>
                        {PRESETS.map((preset) => (
                            <Button
                                key={preset.key}
                                variant={
                                    presetKey === preset.key
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                size="sm"
                                className="justify-start font-normal"
                                onClick={() => pickPreset(preset)}
                            >
                                {preset.label}
                            </Button>
                        ))}
                    </div>
                    <div className="flex flex-col">
                        {/* center the calendar: on mobile the single month is
                            narrower than the popover (presets/footer set the
                            width), so it would otherwise sit left-aligned. */}
                        <div className="flex justify-center p-2">
                            <Calendar
                                mode="range"
                                numberOfMonths={monthsToShow}
                                selected={range}
                                onSelect={(_selected, day) => pickDay(day)}
                                defaultMonth={range?.from}
                            />
                        </div>
                        <div className="grid grid-cols-1 gap-3 border-t p-3 sm:grid-cols-2">
                            <TimeSelect
                                idPrefix="range-from-time"
                                label="From time"
                                value={fromTime}
                                onChange={(v) => {
                                    setFromTime(v);
                                    setPresetKey(null);
                                }}
                            />
                            <TimeSelect
                                idPrefix="range-to-time"
                                label="To time"
                                value={toTime}
                                onChange={(v) => {
                                    setToTime(v);
                                    setPresetKey(null);
                                }}
                            />
                        </div>
                        <div className="flex items-center justify-between gap-3 border-t p-3">
                            <span className="text-muted-foreground text-xs tabular-nums">
                                {draftSummary}
                            </span>
                            <Button
                                size="sm"
                                onClick={apply}
                                disabled={!range?.from || !range?.to}
                            >
                                Apply range
                            </Button>
                        </div>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
