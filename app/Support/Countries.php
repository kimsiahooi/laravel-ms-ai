<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The countries this app knows how to trade in.
 *
 * One list, because the same two codes drive three different things: the tenant's
 * own tax treatment, a customer's address, and which e-invoice standard a document
 * is built for. A free-text country code would quietly produce an e-invoice no
 * tax authority accepts.
 */
final class Countries
{
    /** ISO 3166-1 alpha-2 → the name a user recognises. */
    private const NAMES = [
        'MY' => 'Malaysia',
        'SG' => 'Singapore',
    ];

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::NAMES);
    }

    /**
     * The picker options, in the `{value, label}` shape the UI's combobox takes.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $code, string $name): array => ['value' => $code, 'label' => $name],
            array_keys(self::NAMES),
            array_values(self::NAMES),
        );
    }
}
