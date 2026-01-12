<?php

declare(strict_types=1);

namespace Arkhe\Main\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateToUsernameCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'arkhe:migrate-to-username
                            {--dry-run : Show what would be migrated without making changes}
                            {--quiet : Suppress all output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate users from first_name/last_name to username field';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $quiet = $this->option('quiet');

        // Check if migration is needed
        if (! Schema::hasColumn('users', 'first_name') || ! Schema::hasColumn('users', 'last_name')) {
            if (! $quiet) {
                $this->info(__('Migration not needed: first_name/last_name columns do not exist.'));
            }

            return Command::SUCCESS;
        }

        // Check if username column already exists
        if (Schema::hasColumn('users', 'username')) {
            if (! $quiet) {
                $this->info(__('Migration already completed: username column already exists.'));
            }

            return Command::SUCCESS;
        }

        // Count users to migrate
        $userCount = DB::table('users')->count();

        if ($userCount === 0) {
            if (! $quiet) {
                $this->info(__('No users to migrate.'));
            }

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->info(__('Dry run mode - no changes will be made.'));
            $this->info(__(':count users would be migrated.', ['count' => $userCount]));

            // Show sample of what would be migrated
            $samples = DB::table('users')
                ->select('id', 'first_name', 'last_name')
                ->limit(5)
                ->get();

            $this->table(
                ['ID', 'First Name', 'Last Name', 'Would become Username'],
                $samples->map(fn ($user) => [
                    $user->id,
                    $user->first_name,
                    $user->last_name,
                    trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                ])
            );

            return Command::SUCCESS;
        }

        if (! $quiet) {
            $this->info(__('Migrating :count users...', ['count' => $userCount]));
        }

        // Run the migration
        $this->call('migrate', [
            '--path' => 'database/migrations/0001_01_01_000101_rename_name_fields_to_username.php',
            '--force' => true,
        ]);

        if (! $quiet) {
            $this->info(__('Migration completed successfully.'));
        }

        return Command::SUCCESS;
    }
}
