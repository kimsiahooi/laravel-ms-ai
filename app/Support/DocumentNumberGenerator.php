<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Generates gap-free document numbers like "SO-2026-0001", one sequence per
 * (type, period). `period` is a financial-year label ('' when numbering never
 * resets). Concurrency-safe: insertOrIgnore guarantees a single sequence row, then
 * a row lock serialises the increment. MUST be called inside a DB transaction.
 */
final class DocumentNumberGenerator
{
    public function next(string $type, string $prefix, ?string $period): string
    {
        $period ??= '';

        DB::table('document_sequences')->insertOrIgnore([
            'type' => $type,
            'period' => $period,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = (int) DB::table('document_sequences')
            ->where('type', $type)
            ->where('period', $period)
            ->lockForUpdate()
            ->value('next_number');

        DB::table('document_sequences')
            ->where('type', $type)
            ->where('period', $period)
            ->update(['next_number' => $sequence + 1, 'updated_at' => now()]);

        $padded = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

        return implode('-', $period === '' ? [$prefix, $padded] : [$prefix, $period, $padded]);
    }
}
