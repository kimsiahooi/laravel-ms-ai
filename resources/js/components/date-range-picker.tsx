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
    // false = a complete range is shown; the next click starts a fresh one.
    // true = a start is picked and the next click sets the end.
    const [pickingEnd, setPickingEnd] = useState(false);

    const syncToValue = () => {
        setRange({ from: parseISO(value.from), to: parseISO(value.to) });
        setFromTime(timeOf(parseISO(value.from)));
        setToTime(timeOf(parseISO(value.to)));
        setPickingEnd(false);
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
                            onSelect={(_selected, day) => pickDay(day)}
                            defaultMonth={range?.from}
                        />
                        <div className="flex flex-col gap-3">
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
