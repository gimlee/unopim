<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;

class CheckSchemaReadiness extends Command
{
    protected $signature = 'unopim:schema:check';

    protected $description = 'Fail when the database schema has pending tracked migrations';

    public function handle(Migrator $migrator): int
    {
        if (! $migrator->repositoryExists()) {
            $this->error('Schema is not ready: the migrations repository does not exist. Run php artisan migrate.');

            return self::FAILURE;
        }

        $paths = array_values(array_unique([
            database_path('migrations'),
            ...$migrator->paths(),
        ]));
        $files = $migrator->getMigrationFiles($paths);
        $pending = array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));

        if ($pending !== []) {
            $this->error('Schema is not ready: pending migrations were found:');

            foreach ($pending as $migration) {
                $this->line("  - {$migration}");
            }

            $this->line('Run php artisan migrate, then retry startup.');

            return self::FAILURE;
        }

        $this->info('Schema is ready: no pending migrations.');

        return self::SUCCESS;
    }
}
