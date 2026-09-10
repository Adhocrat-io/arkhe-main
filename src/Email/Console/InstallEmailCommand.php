<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Console;

use Arkhe\Main\Doctor\Baseline;
use Arkhe\Main\Doctor\Checks\Email\FormRequestsCheck;
use Arkhe\Main\Doctor\Checks\Email\LivewireFormsCheck;
use Arkhe\Main\Doctor\DoctorStatus;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;

/**
 * THE activation point of the module (FRD § 14.1, FR-AI-04): everything
 * before this command is inert classes. It publishes the config with
 * `enabled = true` and `mode = observe`, the migrations (refusing to
 * touch a colliding table), the seed, wires the seeder, syncs the
 * assistant files, generates the doctor baseline when the app carries
 * legacy forms, and ends on a doctor report.
 *
 * Idempotent: published files are left alone unless `--force`.
 */
class InstallEmailCommand extends Command
{
    private const TABLE_SIGNATURE = [
        'create_arkhe_email_domains_table' => ['domains', ['status', 'corrected_domain', 'source']],
        'create_arkhe_email_bounces_table' => ['bounces', ['type', 'bounced_at', 'cleared_at']],
    ];

    protected $signature = 'arkhe:email:install {--force : Overwrite already-published files}';

    protected $description = 'Activate the email deliverability module (config, migrations, seed, assistant files, doctor)';

    public function handle(Filesystem $files): int
    {
        $packageRoot = dirname(__DIR__, 3);

        // 1. Config, published activated. The runtime config follows so the
        // steps below (and the closing doctor) see the module as enabled.
        $this->publishConfig($files, $packageRoot);
        config(['arkhe-email.enabled' => true]);

        // 2. Migrations — refused outright on a table collision: migrating
        // over a stranger's table is the one unrecoverable mistake here.
        if (! $this->publishMigrations($files, $packageRoot)) {
            return self::FAILURE;
        }

        // 3. Seed + DatabaseSeeder wiring.
        $this->publishSeed($files, $packageRoot);
        $this->wireDatabaseSeeder($files);

        // 4./5. Admin screen and webhook routes are mounted by the package
        // itself now that the module is enabled — nothing to publish.
        $this->components->info('Écran d’administration et routes webhook montés par le package (rien à publier).');

        // 6. Assistant files.
        $this->call('arkhe:sync-ai');

        // 7. Baseline, only when the app carries non-conforming forms and
        // none exists yet — a fresh application stays fully binding.
        $this->maybeGenerateBaseline();

        // 8. The doctor has the last word.
        $this->call('arkhe:doctor');

        $this->components->info('Module activé en mode observe. Reste à faire : `php artisan migrate` puis `php artisan arkhe:email:seed-domains`.');

        return self::SUCCESS;
    }

    private function publishConfig(Filesystem $files, string $packageRoot): void
    {
        $target = config_path('arkhe-email.php');

        if (! $files->exists($target) || $this->option('force')) {
            $files->copy($packageRoot.'/config/arkhe-email.php', $target);
        }

        // Whether freshly copied or already published: activation IS this
        // command's job, so the flag flips here and only here.
        $contents = (string) $files->get($target);

        if (str_contains($contents, "'enabled' => false,")) {
            $files->put($target, str_replace("'enabled' => false,", "'enabled' => true,", $contents));
        }

        $this->components->info('config/arkhe-email.php publié, enabled = true, mode = observe.');
    }

    private function publishMigrations(Filesystem $files, string $packageRoot): bool
    {
        foreach (self::TABLE_SIGNATURE as [$key, $columns]) {
            $table = (string) config("arkhe-email.tables.{$key}");

            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $this->components->error(
                        "La table `{$table}` existe déjà avec un autre schéma (colonne `{$column}` absente). "
                        .'Changez le nom dans `arkhe-email.tables` puis relancez — aucune migration n’a été publiée.',
                    );

                    return false;
                }
            }
        }

        $published = 0;

        foreach (array_keys(self::TABLE_SIGNATURE) as $index => $name) {
            $already = glob(database_path('migrations/*_'.$name.'.php'));

            if ($already !== false && $already !== [] && ! $this->option('force')) {
                continue;
            }

            $files->copy(
                $packageRoot.'/database/migrations/'.$name.'.php.stub',
                database_path('migrations/'.now()->format('Y_m_d_His').$index.'_'.$name.'.php'),
            );

            $published++;
        }

        $this->components->info($published > 0 ? "{$published} migration(s) publiée(s)." : 'Migrations déjà publiées.');

        return true;
    }

    private function publishSeed(Filesystem $files, string $packageRoot): void
    {
        $target = database_path('seeders/data/email_domains.json');

        if ($files->exists($target) && ! $this->option('force')) {
            $this->components->info('Seed déjà publié.');

            return;
        }

        $files->ensureDirectoryExists(dirname($target));
        $files->copy($packageRoot.'/database/seeders/data/email_domains.json', $target);

        $this->components->info('Seed publié dans database/seeders/data/email_domains.json.');
    }

    private function wireDatabaseSeeder(Filesystem $files): void
    {
        $path = database_path('seeders/DatabaseSeeder.php');

        if (! $files->exists($path)) {
            $this->components->warn('DatabaseSeeder.php introuvable — ajoutez vous-même `Artisan::call(\'arkhe:email:seed-domains\');` à votre seeder.');

            return;
        }

        $contents = (string) $files->get($path);

        if (str_contains($contents, 'arkhe:email:seed-domains')) {
            return;
        }

        // Inserted right after run()'s opening brace: the least fragile
        // anchor a seeder reliably has.
        $patched = preg_replace(
            '/(public function run\(\)[^{]*\{)/',
            "$1\n        \\Illuminate\\Support\\Facades\\Artisan::call('arkhe:email:seed-domains');\n",
            $contents,
            limit: 1,
            count: $count,
        );

        if ($count === 1 && is_string($patched)) {
            $files->put($path, $patched);
            $this->components->info('DatabaseSeeder branché sur arkhe:email:seed-domains.');
        } else {
            $this->components->warn('DatabaseSeeder non modifiable automatiquement — ajoutez `Artisan::call(\'arkhe:email:seed-domains\');` dans run().');
        }
    }

    private function maybeGenerateBaseline(): void
    {
        if (file_exists(Baseline::path())) {
            return;
        }

        $failing = [];

        foreach ([app(FormRequestsCheck::class), app(LivewireFormsCheck::class)] as $check) {
            $result = $check->run();

            if ($result->status === DoctorStatus::Error) {
                $failing[$check->id()] = $result->targets;
            }
        }

        if ($failing !== []) {
            Baseline::write($failing);
            $this->components->warn(
                'Application existante détectée : baseline générée ('.Baseline::path().'). '
                .'Les formulaires listés sont tolérés en avertissement ; résorbez-les au fil des tâches (§ UPGRADE.md).',
            );
        }
    }
}
