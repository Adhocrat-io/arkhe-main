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
    protected $signature = 'arkhe:main:install
                            {--y|yes : Accept all default confirmations without prompting}
                            {--with-test-users : Create test users (ignored in production)}
                            {--force : Force overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Arkhe Main package';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        app()->setLocale(config('app.locale'));

        if ($this->isNonInteractive()) {
            $this->info(__('Running in non-interactive mode...'));
        }

        $this->info(__('Installing Arkhe Main package...'));

        if ($this->shouldProceed(__('Do you want to publish the configuration?'))) {
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
        $this->modifyWelcomeBlade();
        $this->replaceDashboardRoutes();

        if ($this->shouldProceed(__('Do you want to publish the migrations?'))) {
            $this->publishMigrations();
            $this->info(__('Arkhe Main migrations published successfully.'));
        }

        $this->publishSeoAssets();
        $this->info(__('SEO package assets published successfully.'));

        if ($this->shouldProceed(__('Do you want to publish the roles and permissions seeder?'))) {
            $this->publishRolesAndPermissionsSeeder();
            $this->info(__('Arkhe Main roles and permissions seeder published successfully.'));
        }

        if ($this->shouldProceed(__('Do you want to run the migrations?'))) {
            $this->call('migrate');
            $this->info(__('Arkhe Main migrations run successfully.'));
        }

        if ($this->shouldProceed(__('Do you want to publish the lang files?'))) {
            $this->publishLangFiles();
            $this->info(__('Arkhe Main lang files published successfully.'));
        }

        if ($this->shouldPublishModifiedFiles()) {
            $this->publishFiles();
            $this->info(__('Arkhe Main files published successfully.'));
        } else {
            $this->info(__('You will have to manually modify your files to work with Arhkè. See documentation for more information.'));
        }

        if ($this->shouldProceed(__('Do you want to run the roles and permissions seeder?'))) {
            $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class]);
            $this->info(__('Arkhe Main roles and permissions seeder run successfully.'));
        }

        if ($this->shouldCreateTestUsers()) {
            $this->call('db:seed', ['--class' => TestUsersSeeder::class]);
            $this->info(__('Arkhe Main test users created successfully.'));
        }

        $this->info(__('Arkhe Main package installed successfully.'));
    }

    private function shouldProceed(string $question, bool $default = true): bool
    {
        if ($this->option('yes')) {
            return $default;
        }

        return confirm($question, $default);
    }

    private function isNonInteractive(): bool
    {
        return (bool) $this->option('yes');
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
        if ($this->isNonInteractive()) {
            return (bool) $this->option('force');
        }

        return confirm(label: __('Config file already exists. Do you want to overwrite it?'), default: false);
    }

    private function shouldPublishModifiedFiles(): bool
    {
        if ($this->isNonInteractive()) {
            return (bool) $this->option('force');
        }

        return confirm(__('Do you want to publish the modified files?'), false);
    }

    private function shouldCreateTestUsers(): bool
    {
        if ($this->isNonInteractive()) {
            return (bool) $this->option('with-test-users') && ! app()->isProduction();
        }

        return confirm(__("Do you want to create test users (don't do this on production)?"), false);
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

    private function publishSeoAssets(): void
    {
        $this->call(command: 'vendor:publish', arguments: ['--tag' => 'seo-migrations']);
        $this->call(command: 'vendor:publish', arguments: ['--tag' => 'seo-config']);
    }

    private function publishFiles(): void
    {
        $shouldPublish = $this->isNonInteractive()
            ? true
            : confirm(label: __('This will overwrite the existing files. Are you sure?'), default: false);

        if ($shouldPublish) {
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

    private function modifyWelcomeBlade(): void
    {
        $welcomeBladePath = resource_path('views/welcome.blade.php');

        if (File::exists($welcomeBladePath)) {
            $content = File::get($welcomeBladePath);

            $patterns = [
                'href="{{ url(\'/dashboard\') }}"',
                'href="{{ url("/dashboard") }}"',
                'href="{{url(\'/dashboard\')}}"',
                'href="{{url("/dashboard")}}"',
            ];

            $replacement = 'href="{{ route(\'admin.dashboard\') }}"';
            $modified = false;

            foreach ($patterns as $pattern) {
                if (str_contains($content, $pattern)) {
                    $content = str_replace($pattern, $replacement, $content);
                    $modified = true;
                    break;
                }
            }

            if ($modified) {
                File::put($welcomeBladePath, $content);
                $this->info(__('Welcome blade file updated: dashboard route set to /administration/dashboard.'));
            }
        }
    }

    private function replaceDashboardRoutes(): void
    {
        $viewsPath = resource_path('views');

        if (! File::isDirectory($viewsPath)) {
            return;
        }

        $patterns = [
            "route('dashboard')" => "route('admin.dashboard')",
            'route("dashboard")' => 'route("admin.dashboard")',
            "routeIs('dashboard')" => "routeIs('admin.dashboard')",
            'routeIs("dashboard")' => 'routeIs("admin.dashboard")',
        ];

        $files = File::allFiles($viewsPath);
        $modifiedCount = 0;

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = File::get($file->getPathname());
            $originalContent = $content;

            foreach ($patterns as $search => $replace) {
                $content = str_replace($search, $replace, $content);
            }

            if ($content !== $originalContent) {
                File::put($file->getPathname(), $content);
                $modifiedCount++;
                $this->line(__('  Updated: :file', ['file' => $file->getRelativePathname()]));
            }
        }

        if ($modifiedCount > 0) {
            $this->info(__(':count Blade file(s) updated: dashboard routes replaced with admin.dashboard.', ['count' => $modifiedCount]));
        }
    }
}
