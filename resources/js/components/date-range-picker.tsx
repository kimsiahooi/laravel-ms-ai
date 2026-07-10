import {
    endOfMonth,
    endOfWeek,
    format,
    parseISO,
    startOfDay,
    startOfMonth,
    startOfWeek,
    subDays,
    subMonths,
    subWeeks,
} from 'date-fns';
import { CalendarDays, ChevronDown } from 'lucide-react';
import { useState } from 'react';
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

// 30-minute slots across the day for the time dropdowns.
const TIME_SLOTS = Array.from(
    { length: 48 },
    (_, i) => `${pad(Math.floor(i / 2))}:${i % 2 ? '30' : '00'}`,
);

// The slots plus the current value, so an off-grid time (e.g. a preset's "now")
// still appears as a selectable option instead of showing blank.
function timeOptions(current: string): string[] {
    return TIME_SLOTS.includes(current)
        ? TIME_SLOTS
        : [...TIME_SLOTS, current].sort();
}

function TimeSelect({
    id,
    label,
    value,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="space-y-1">
            <Label htmlFor={id} className="text-muted-foreground text-xs">
                {label}
            </Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent className="max-h-56">
                    {timeOptions(value).map((slot) => (
                        <SelectItem key={slot} value={slot}>
                            {slot}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
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
        compute: (n) => ({ from: startOfDay(n), to: n }),
    },
    {
        key: 'this_week',
        label: 'This week',
        compute: (n) => ({ from: startOfWeek(n, WEEK_OPTS), to: n }),
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
        compute: (n) => ({ from: startOfMonth(n), to: n }),
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
        key: 'last_7',
        label: 'Last 7 days',
        compute: (n) => ({ from: startOfDay(subDays(n, 6)), to: n }),
    },
    {
        key: 'last_30',
        label: 'Last 30 days',
        compute: (n) => ({ from: startOfDay(subDays(n, 29)), to: n }),
    },
    {
        key: 'last_90',
        label: 'Last 90 days',
        compute: (n) => ({ from: startOfDay(subDays(n, 89)), to: n }),
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

    const syncToValue = () => {
        setRange({ from: parseISO(value.from), to: parseISO(value.to) });
        setFromTime(timeOf(parseISO(value.from)));
        setToTime(timeOf(parseISO(value.to)));
    };

    const pickPreset = (preset: Preset) => {
        const { from, to } = preset.compute(new Date());
        setRange({ from, to });
        setFromTime(timeOf(from));
        setToTime(timeOf(to));
        setPresetKey(preset.key);
    };

    const apply = () => {
        if (!range?.from || !range?.to) return;
        onChange({
            from: toIso(combine(range.from, fromTime)),
            to: toIso(combine(range.to, toTime, true)),
        });
        setOpen(false);
    };

    const label = presetKey
        ? (PRESETS.find((p) => p.key === presetKey)?.label ??
          formatRangeDates(value))
        : formatRangeDates(value);

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
            <PopoverContent align="end" className="w-auto p-0">
                <div className="flex max-sm:flex-col">
                    <div className="flex shrink-0 flex-col gap-1 border-b p-2 sm:border-r sm:border-b-0">
                        {PRESETS.map((preset) => (
                            <Button
                                key={preset.key}
                                variant={
                                    presetKey === preset.key
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                size="sm"
                                className="justify-start"
                                onClick={() => pickPreset(preset)}
                            >
                                {preset.label}
                            </Button>
                        ))}
                    </div>
                    <div className="flex flex-col gap-3 p-2">
                        <Calendar
                            mode="range"
                            numberOfMonths={2}
                            selected={range}
                            onSelect={(next) => {
                                setRange(next);
                                setPresetKey(null);
                            }}
                            defaultMonth={range?.from}
                        />
                        <div className="grid grid-cols-2 gap-2">
                            <TimeSelect
                                id="range-from-time"
                                label="From time"
                                value={fromTime}
                                onChange={(v) => {
                                    setFromTime(v);
                                    setPresetKey(null);
                                }}
                            />
                            <TimeSelect
                                id="range-to-time"
                                label="To time"
                                value={toTime}
                                onChange={(v) => {
                                    setToTime(v);
                                    setPresetKey(null);
                                }}
                            />
                        </div>
                        <Button
                            size="sm"
                            onClick={apply}
                            disabled={!range?.from || !range?.to}
                        >
                            Apply range
                        </Button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
