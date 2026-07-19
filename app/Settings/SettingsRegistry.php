<?php

declare(strict_types=1);

namespace App\Settings;

/**
 * Resolves a category slug to its settings provider, so the SettingsController stays
 * generic. Add a category by registering it here.
 */
class SettingsRegistry
{
    /** @var array<string, class-string<SettingsCategory>> */
    private const CATEGORIES = [
        BusinessSettings::CATEGORY => BusinessSettings::class,
    ];

    public function resolve(string $category): SettingsCategory
    {
        abort_unless(isset(self::CATEGORIES[$category]), 404);

        return app(self::CATEGORIES[$category]);
    }

    public function has(string $category): bool
    {
        return isset(self::CATEGORIES[$category]);
    }

    /**
     * Every registered category provider (for seeding / bulk operations).
     *
     * @return list<SettingsCategory>
     */
    public function all(): array
    {
        return array_map(fn (string $slug): SettingsCategory => $this->resolve($slug), $this->slugs());
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys(self::CATEGORIES);
    }
}
