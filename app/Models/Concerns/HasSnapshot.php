<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * A `{name, sku, unit}` snapshot for order/return line-item tables — the immutable
 * record of what a product / raw material was called when the line was created (so a
 * later rename or soft-delete can't rewrite history). `snapshotOf()` is null-safe for
 * the callers whose model may be missing.
 */
trait HasSnapshot
{
    /**
     * @return array{name: string, sku: string, unit: string}
     */
    public function snapshot(): array
    {
        return [
            'name' => $this->name,
            'sku' => $this->sku ?? '',
            'unit' => $this->unit ?? '',
        ];
    }

    /**
     * @return array{name: string, sku: string, unit: string}
     */
    public static function snapshotOf(?self $model): array
    {
        return $model?->snapshot() ?? ['name' => '', 'sku' => '', 'unit' => ''];
    }
}
