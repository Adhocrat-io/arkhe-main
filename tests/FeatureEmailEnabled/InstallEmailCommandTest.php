<?php

declare(strict_types=1);

use Arkhe\Main\Doctor\Baseline;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * The install command writes into the (vendored) testbench skeleton —
 * everything it may touch is snapshotted before and restored after, so
 * the skeleton stays pristine for every other suite.
 */
function installArtifacts(): array
{
    return array_merge(
        [
            config_path('arkhe-email.php'),
            database_path('seeders/data/email_domains.json'),
            base_path('CLAUDE.md'),
            base_path('AGENTS.md'),
            Baseline::path(),
        ],
        glob(database_path('migrations/*_create_arkhe_email_*_table.php')) ?: [],
    );
}

function cleanInstallArtifacts(): void
{
    foreach (installArtifacts() as $path) {
        @unlink($path);
    }

    File::deleteDirectory(base_path('.claude'));
    File::deleteDirectory(database_path('seeders/data'));
}

beforeEach(fn () => cleanInstallArtifacts());
afterEach(fn () => cleanInstallArtifacts());

it('activates the module end to end on a clean application', function (): void {
    expect(Artisan::call('arkhe:email:install'))->toBe(0);

    // Config published AND activated — the one place `enabled` flips.
    expect(file_exists(config_path('arkhe-email.php')))->toBeTrue()
        ->and((string) file_get_contents(config_path('arkhe-email.php')))->toContain("'enabled' => true,")
        ->and(config('arkhe-email.enabled'))->toBeTrue();

    // Both migrations, the seed, the assistant files.
    expect(glob(database_path('migrations/*_create_arkhe_email_*_table.php')))->toHaveCount(2)
        ->and(file_exists(database_path('seeders/data/email_domains.json')))->toBeTrue()
        ->and(file_exists(base_path('CLAUDE.md')))->toBeTrue()
        ->and(file_exists(base_path('.claude/skills/arkhe-email/SKILL.md')))->toBeTrue();

    // Clean application: everything conforms, so NO baseline (§ 14.4 — a
    // fresh app must stay fully binding).
    expect(file_exists(Baseline::path()))->toBeFalse();
});

it('is idempotent', function (): void {
    Artisan::call('arkhe:email:install');
    expect(Artisan::call('arkhe:email:install'))->toBe(0)
        ->and(glob(database_path('migrations/*_create_arkhe_email_*_table.php')))->toHaveCount(2);
});

// § 14.7 acceptance: a same-name table with a foreign schema stops the
// install with an explicit message and publishes no migration.
it('refuses to install over a colliding table', function (): void {
    config()->set('arkhe-email.tables.domains', 'users'); // exists, wrong schema

    expect(Artisan::call('arkhe:email:install'))->toBe(1)
        ->and(Artisan::output())->toContain('autre schéma')
        ->and(glob(database_path('migrations/*_create_arkhe_email_*_table.php')))->toBe([]);
});
