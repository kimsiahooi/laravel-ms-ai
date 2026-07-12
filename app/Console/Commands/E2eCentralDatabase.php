<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Create or drop the isolated E2E *central* database. Run in the normal (dev) env —
 * it issues a server-level CREATE/DROP over the existing central connection, so the
 * E2E suite gets its own `..._e2e` central DB that never touches dev data.
 */
class E2eCentralDatabase extends Command
{
    protected $signature = 'e2e:central {action : up|down}';

    protected $description = 'Create or drop the isolated E2E central database (a dedicated *_e2e DB).';

    public function handle(): int
    {
        $name = config('database.connections.central.database').'_e2e';

        // Safety rail: this command only ever touches a database whose name ends in
        // `_e2e`, so it can never drop the real central DB.
        if (! str_ends_with($name, '_e2e')) {
            $this->error("Refusing: \"{$name}\" is not an _e2e database.");

            return self::FAILURE;
        }

        $pdo = DB::connection('central')->getPdo();

        if ($this->argument('action') === 'up') {
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->info("Created {$name}.");
        } else {
            $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
            $this->info("Dropped {$name}.");
        }

        return self::SUCCESS;
    }
}
