<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

function aiFiles(): array
{
    return [
        base_path('CLAUDE.md'),
        base_path('AGENTS.md'),
        base_path('.claude/rules/arkhe-email.md'),
        base_path('.claude/skills/arkhe-email/SKILL.md'),
        base_path('.claude/skills/arkhe-email/examples.md'),
    ];
}

beforeEach(function (): void {
    foreach (aiFiles() as $path) {
        @unlink($path);
    }
});

afterEach(function (): void {
    foreach (aiFiles() as $path) {
        @unlink($path);
    }

    File::deleteDirectory(base_path('.claude'));
});

// ─── § 13.5 acceptance criteria ──────────────────────────────────────────

it('publishes every assistant file', function (): void {
    expect(Artisan::call('arkhe:sync-ai'))->toBe(0);

    foreach (aiFiles() as $path) {
        expect(file_exists($path))->toBeTrue($path.' missing');
    }

    $claude = (string) file_get_contents(base_path('CLAUDE.md'));

    expect($claude)->toContain('<!-- arkhe:start -->')
        ->toContain('arkhe:doctor')
        ->toContain('ArkheFormRequest');
});

it('preserves everything outside the markers on resync', function (): void {
    file_put_contents(base_path('CLAUDE.md'),
        "# Mon projet\n\nConsignes maison.\n\n<!-- arkhe:start -->\nvieux bloc\n<!-- arkhe:end -->\n\nPost-scriptum.\n");

    Artisan::call('arkhe:sync-ai');

    $claude = (string) file_get_contents(base_path('CLAUDE.md'));

    expect($claude)->toContain('# Mon projet')
        ->toContain('Consignes maison.')
        ->toContain('Post-scriptum.')
        ->toContain('arkhe:doctor')
        ->not->toContain('vieux bloc');
});

// § 14.7: no markers yet — the block lands at the end, the team's content
// stays integrally.
it('appends the block to a marker-less CLAUDE.md without touching the rest', function (): void {
    file_put_contents(base_path('CLAUDE.md'), "# Notes internes\n\nNe pas déployer le vendredi.\n");

    Artisan::call('arkhe:sync-ai');

    $claude = (string) file_get_contents(base_path('CLAUDE.md'));

    expect($claude)->toStartWith('# Notes internes')
        ->toContain('Ne pas déployer le vendredi.')
        ->toContain('<!-- arkhe:start -->');
});

it('answers 1 from --check when drifted and 0 once synced', function (): void {
    expect(Artisan::call('arkhe:sync-ai', ['--check' => true]))->toBe(1);

    Artisan::call('arkhe:sync-ai');

    expect(Artisan::call('arkhe:sync-ai', ['--check' => true]))->toBe(0);

    // A stub-owned file edited locally counts as drift again — it belongs
    // to Arkhè and will be rewritten.
    file_put_contents(base_path('.claude/skills/arkhe-email/SKILL.md'), 'modifié à la main');

    expect(Artisan::call('arkhe:sync-ai', ['--check' => true]))->toBe(1);
});
