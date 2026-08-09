<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * Reads the `from` / `to` query parameters a date-range filter sends. Shared by the
 * dashboard, reports and export so all three read a range the same way.
 *
 * `Request::date()` *throws* on anything Carbon can't read, so `?from=banana` — a
 * hand-edited URL, or a `+` mangled into a space in a pasted link — would take the
 * page down with a 500. A range is a filter, not a command, so an unreadable value
 * falls back to the default range instead. Both ends fall back together: rescuing
 * only one would pair a default with a real value and could invert the range,
 * silently reporting zero for a period that has data.
 *
 * This is a crash guard, not validation: it cannot catch a *plausible but wrong*
 * value, because Carbon happily reads `2026-08-1` as August 1st.
 *
 * KNOWN ISSUE (pre-dates this trait, deliberately not changed here): the picker
 * sends an offset-carrying ISO string and Carbon keeps that offset, but the query
 * builder binds a date using the value's own wall clock — so for a tenant outside
 * UTC every range-filtered figure is out by their offset. Fixing it means
 * converting here *and* making `StockReportService::dailySalesPurchases()` bucket
 * by the same timezone it labels days in, otherwise the chart gains a stray day.
 */
trait ResolvesDateRange
{
    /**
     * The [from, to] the request asks for, in the app timezone.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    protected function dateRange(
        Request $request,
        CarbonInterface $defaultFrom,
        CarbonInterface $defaultTo,
    ): array {
        $from = $this->parseDate($request, 'from');
        $to = $this->parseDate($request, 'to');

        // A value was supplied but couldn't be read: fall back as a pair, so the
        // two ends always come from the same source and stay in order.
        $unreadable = ($request->filled('from') && $from === null)
            || ($request->filled('to') && $to === null);

        if ($unreadable) {
            return [$defaultFrom, $defaultTo];
        }

        return [$from ?? $defaultFrom, $to ?? $defaultTo];
    }

    /** One end of the range, or null when it is absent or unreadable. */
    private function parseDate(Request $request, string $key): ?CarbonInterface
    {
        return rescue(
            fn (): ?CarbonInterface => $request->date($key),
            null,
            report: false,
        );
    }
}
