<?php

declare(strict_types=1);

namespace Arkhe\Main\Console\Commands;

use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'arkhe-main:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Arkhe Main package';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info(__('Installing Arkhe Main package...'));

        if (confirm(__('Do you want to publish the configuration?'), true)) {
            if (! $this->configExists('arkhe.php')) {
                $this->publishConfiguration();
                $this->info(__('Arkhe Main configuration published successfully.'));
            } else {
                if ($this->shouldOverwriteConfig()) {
                    $this->info(__('Overwriting existing configuration...'));
                    $this->publishConfiguration(force: true);
                } else {
                    $this->info(__('Existing configuration was not overwritten.'));
                }
            }
        }

        $this->publishAndModifyFortifyConfig();

        if (confirm(__('Do you want to publish the migrations?'), true)) {
            $this->publishMigrations();
            $this->info(__('Arkhe Main migrations published successfully.'));
        }

        if (confirm(__('Do you want to publish the roles and permissions seeder?'), true)) {
            $this->publishRolesAndPermissionsSeeder();
            $this->info(__('Arkhe Main roles and permissions seeder published successfully.'));
        }

        if (confirm(__('Do you want to run the migrations?'), true)) {
            $this->call('migrate');
            $this->info(__('Arkhe Main migrations run successfully.'));
        }

        if (confirm(__('Do you want to publish the lang files?'), true)) {
            $this->publishLangFiles();
            $this->info(__('Arkhe Main lang files published successfully.'));
        }

        if (confirm(__('Do you want to publish the modified files?'), false)) {
            $this->publishFiles();
            $this->info(__('Arkhe Main files published successfully.'));
        } else {
            $this->info(__('You will have to manually modify your files to work with Arhkè. See documentation for more information.'));
        }

        if (confirm(__('Do you want to run the roles and permissions seeder?'), true)) {
            $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class]);
            $this->info(__('Arkhe Main roles and permissions seeder run successfully.'));
        }

        if (confirm(__("Do you want to create test users (don't do this on production)?"), true)) {
            $this->call('db:seed', ['--class' => TestUsersSeeder::class]);
            $this->info(__('Arkhe Main test users created successfully.'));
        }

        $this->info(__('Arkhe Main package installed successfully.'));
    }

    private function configExists(string $fileName): bool
    {
        return File::exists(config_path($fileName));
    }

    private function publishConfiguration(bool $force = false): void
    {
        $params = [
            '--tag' => 'arkhe-main-config',
        ];

        if ($force) {
            $params['--force'] = true;
        }

        $this->call(command: 'vendor:publish', arguments: $params);
    }

    private function shouldOverwriteConfig(): bool
    {
        return confirm(label: __('Config file already exists. Do you want to overwrite it?'), default: false);
    }

    private function publishMigrations(): void
    {
        $this->call(command: 'vendor:publish', arguments: ['--tag' => 'arkhe-main-migrations', '--force' => true]);
    }

    private function publishRolesAndPermissionsSeeder(): void
    {
        $this->call(command: 'vendor:publish', arguments: ['--tag' => 'arkhe-main-roles-seeder', '--force' => true]);
    }

    private function publishLangFiles(): void
    {
        $this->call(command: 'vendor:publish', arguments: ['--tag' => 'arkhe-main-lang', '--force' => true]);
    }

    private function publishFiles(): void
    {
        if (confirm(label: __('This will overwrite the existing files. Are you sure?'), default: false)) {
            $this->call(command: 'vendor:publish', arguments: ['--tag' => 'arkhe-main-files', '--force' => true]);
        }
    }

    private function publishAndModifyFortifyConfig(): void
    {
        $fortifyConfigPath = config_path('fortify.php');

        if (! File::exists($fortifyConfigPath)) {
            $this->info(__('Publishing Fortify configuration...'));
            $this->call('vendor:publish', [
                '--provider' => 'Laravel\Fortify\FortifyServiceProvider',
                '--tag' => 'config',
            ]);
        }

        if (File::exists($fortifyConfigPath)) {
            $content = File::get($fortifyConfigPath);

            $patterns = [
                "'home' => '/dashboard'",
                '"home" => "/dashboard"',
                "'home' => \"/dashboard\"",
                '"home" => \'/dashboard\'',
            ];

            $replacement = "'home' => '/administration/dashboard'";
            $modified = false;

            foreach ($patterns as $pattern) {
                if (str_contains($content, $pattern)) {
                    $content = str_replace($pattern, $replacement, $content);
                    $modified = true;
                    break;
                }
            }

            if ($modified) {
                File::put($fortifyConfigPath, $content);
                $this->info(__('Fortify configuration updated: home route set to /administration/dashboard.'));
            }
        }
    }
}
