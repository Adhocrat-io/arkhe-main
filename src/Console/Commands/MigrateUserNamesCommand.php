<?php

declare(strict_types=1);

namespace Arkhe\Main\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateUserNamesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'arkhe:migrate-user-names
                            {--dry-run : Show what would be migrated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate first_name and last_name columns to name column';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Schema::hasTable('users')) {
            $this->warn(__('Users table does not exist. Skipping migration.'));

            return self::SUCCESS;
        }

        $hasFirstName = Schema::hasColumn('users', 'first_name');
        $hasLastName = Schema::hasColumn('users', 'last_name');
        $hasName = Schema::hasColumn('users', 'name');

        if (! $hasFirstName && ! $hasLastName) {
            $this->info(__('No first_name or last_name columns found. No migration needed.'));

            return self::SUCCESS;
        }

        if (! $hasName) {
            $this->error(__('The name column does not exist in the users table. Please run migrations first.'));

            return self::FAILURE;
        }

        if ($hasFirstName && $hasLastName) {
            return $this->migrateFromFirstNameAndLastName();
        }

        if ($hasFirstName) {
            return $this->migrateFromSingleColumn('first_name');
        }

        return $this->migrateFromSingleColumn('last_name');
    }

    private function migrateFromFirstNameAndLastName(): int
    {
        $users = DB::table('users')
            ->whereNotNull('first_name')
            ->orWhereNotNull('last_name')
            ->get(['id', 'first_name', 'last_name', 'name']);

        if ($users->isEmpty()) {
            $this->info(__('No users to migrate.'));

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($users as $user) {
            $firstName = trim($user->first_name ?? '');
            $lastName = trim($user->last_name ?? '');
            $newName = trim("$firstName $lastName");

            if (empty($newName)) {
                continue;
            }

            if (! empty($user->name) && $user->name !== $newName) {
                $this->line(__("User :id already has name ':name', would become ':new'", [
                    'id' => $user->id,
                    'name' => $user->name,
                    'new' => $newName,
                ]));
            }

            if ($this->option('dry-run')) {
                $this->line(__("Would migrate user :id: ':first' + ':last' -> ':name'", [
                    'id' => $user->id,
                    'first' => $firstName,
                    'last' => $lastName,
                    'name' => $newName,
                ]));
            } else {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['name' => $newName]);
            }

            $count++;
        }

        if ($this->option('dry-run')) {
            $this->info(__(':count users would be migrated.', ['count' => $count]));
        } else {
            $this->info(__(':count users migrated successfully.', ['count' => $count]));
        }

        return self::SUCCESS;
    }

    private function migrateFromSingleColumn(string $column): int
    {
        $users = DB::table('users')
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->get(['id', $column, 'name']);

        if ($users->isEmpty()) {
            $this->info(__('No users to migrate.'));

            return self::SUCCESS;
        }

        $count = 0;

        foreach ($users as $user) {
            $newName = trim($user->$column);

            if (empty($newName)) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line(__("Would migrate user :id: ':column' -> ':name'", [
                    'id' => $user->id,
                    'column' => $newName,
                    'name' => $newName,
                ]));
            } else {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['name' => $newName]);
            }

            $count++;
        }

        if ($this->option('dry-run')) {
            $this->info(__(':count users would be migrated.', ['count' => $count]));
        } else {
            $this->info(__(':count users migrated successfully.', ['count' => $count]));
        }

        return self::SUCCESS;
    }
}
