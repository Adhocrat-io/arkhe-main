<?php

declare(strict_types=1);

namespace Arkhe\Main\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateUserNamesCommand extends Command
{
    protected $signature = 'arkhe:main:migrate-user-names
                            {--dry-run : Show what would be migrated without making changes}';

    protected $description = 'Migrate first_name and last_name columns to name column';

    public function handle(): int
    {
        if (! Schema::hasTable('users')) {
            $this->warn(__('Users table does not exist. Skipping migration.'));

            return self::SUCCESS;
        }

        $hasFirstName = Schema::hasColumn('users', 'first_name');
        $hasLastName = Schema::hasColumn('users', 'last_name');

        if (! $hasFirstName && ! $hasLastName) {
            $this->info(__('No first_name or last_name columns found. No migration needed.'));

            return self::SUCCESS;
        }

        $hasName = Schema::hasColumn('users', 'name');

        if (! $hasName) {
            $this->createNameColumn();
        }

        if ($hasFirstName && $hasLastName) {
            $result = $this->migrateUsers(
                fn () => $this->getUsersWithBothColumns($hasName),
                fn ($user) => $this->extractNameFromBothColumns($user),
                "Would migrate user :id: ':first' + ':last' -> ':name'"
            );
        } else {
            $column = $hasFirstName ? 'first_name' : 'last_name';

            $result = $this->migrateUsers(
                fn () => $this->getUsersWithSingleColumn($column, $hasName),
                fn ($user) => trim($user->$column),
                "Would migrate user :id: $column ':value' -> ':name'"
            );
        }

        if ($result === self::SUCCESS) {
            $this->dropOldColumns($hasFirstName, $hasLastName);
        }

        return $result;
    }

    private function createNameColumn(): void
    {
        if ($this->option('dry-run')) {
            $this->line(__("Would create 'name' column after 'id' in users table."));

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
        });

        $this->info(__("Column 'name' created successfully."));
    }

    private function getUsersWithBothColumns(bool $hasName): Collection
    {
        $columns = ['id', 'first_name', 'last_name'];

        if ($hasName) {
            $columns[] = 'name';
        }

        return DB::table('users')
            ->where(function ($query) {
                $query->whereNotNull('first_name')
                    ->where('first_name', '!=', '');
            })
            ->orWhere(function ($query) {
                $query->whereNotNull('last_name')
                    ->where('last_name', '!=', '');
            })
            ->get($columns);
    }

    private function getUsersWithSingleColumn(string $column, bool $hasName): Collection
    {
        $columns = ['id', $column];

        if ($hasName) {
            $columns[] = 'name';
        }

        return DB::table('users')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->get($columns);
    }

    private function extractNameFromBothColumns(object $user): string
    {
        $firstName = trim($user->first_name ?? '');
        $lastName = trim($user->last_name ?? '');

        return trim("$firstName $lastName");
    }

    private function migrateUsers(callable $queryBuilder, callable $nameExtractor, string $logFormat): int
    {
        $users = $queryBuilder();

        if ($users->isEmpty()) {
            $this->info(__('No users to migrate.'));

            return self::SUCCESS;
        }

        $count = $this->processUsers($users, $nameExtractor, $logFormat);

        $this->outputMigrationResult($count);

        return self::SUCCESS;
    }

    private function processUsers(Collection $users, callable $nameExtractor, string $logFormat): int
    {
        $count = 0;
        $isDryRun = $this->option('dry-run');

        DB::transaction(function () use ($users, $nameExtractor, $logFormat, &$count, $isDryRun) {
            foreach ($users as $user) {
                $newName = $nameExtractor($user);

                if (empty($newName)) {
                    continue;
                }

                $this->warnIfNameConflict($user, $newName);

                if ($isDryRun) {
                    $this->logDryRunMigration($user, $newName, $logFormat);
                } else {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['name' => $newName]);
                }

                $count++;
            }
        });

        return $count;
    }

    private function warnIfNameConflict(object $user, string $newName): void
    {
        $existingName = $user->name ?? null;

        if (! empty($existingName) && $existingName !== $newName) {
            $this->line(__("User :id already has name ':name', would become ':new'", [
                'id' => $user->id,
                'name' => $existingName,
                'new' => $newName,
            ]));
        }
    }

    private function logDryRunMigration(object $user, string $newName, string $logFormat): void
    {
        $params = [
            'id' => $user->id,
            'name' => $newName,
            'value' => $newName,
        ];

        if (isset($user->first_name)) {
            $params['first'] = trim($user->first_name ?? '');
        }

        if (isset($user->last_name)) {
            $params['last'] = trim($user->last_name ?? '');
        }

        $this->line(__($logFormat, $params));
    }

    private function outputMigrationResult(int $count): void
    {
        if ($this->option('dry-run')) {
            $this->info(__(':count users would be migrated.', ['count' => $count]));
        } else {
            $this->info(__(':count users migrated successfully.', ['count' => $count]));
        }
    }

    private function dropOldColumns(bool $hasFirstName, bool $hasLastName): void
    {
        $columnsToDrop = array_filter([
            $hasFirstName ? 'first_name' : null,
            $hasLastName ? 'last_name' : null,
        ]);

        if (empty($columnsToDrop)) {
            return;
        }

        $columnsLabel = implode(', ', $columnsToDrop);

        if ($this->option('dry-run')) {
            $this->line(__('Would drop columns: :columns', ['columns' => $columnsLabel]));

            return;
        }

        Schema::table('users', function (Blueprint $table) use ($columnsToDrop) {
            $table->dropColumn($columnsToDrop);
        });

        $this->info(__('Columns dropped: :columns', ['columns' => $columnsLabel]));
    }
}
