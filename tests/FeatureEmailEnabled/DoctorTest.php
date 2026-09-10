<?php

declare(strict_types=1);

use Arkhe\Main\Doctor\Baseline;
use Illuminate\Support\Facades\Artisan;

/**
 * The doctor writes nothing, but these tests plant offending files in the
 * testbench skeleton's app/ to be found — every planted path is removed
 * again, pass or fail.
 */
function plant(string $path, string $contents): string
{
    @mkdir(dirname($path), 0755, recursive: true);
    file_put_contents($path, $contents);

    $GLOBALS['arkhe_planted'][] = $path;

    return $path;
}

beforeEach(function (): void {
    $GLOBALS['arkhe_planted'] = [];
});

afterEach(function (): void {
    foreach ($GLOBALS['arkhe_planted'] ?? [] as $path) {
        @unlink($path);
    }

    @unlink(Baseline::path());
});

function doctorJson(array $options = []): array
{
    Artisan::call('arkhe:doctor', $options + ['--json' => true]);

    return (array) json_decode(Artisan::output(), associative: true);
}

function doctorRow(array $rows, string $id): ?array
{
    foreach ($rows as $row) {
        if (($row['id'] ?? null) === $id) {
            return $row;
        }
    }

    return null;
}

// ─── Socle ───────────────────────────────────────────────────────────────

// Testbench never published the config file, so `email.config` is the one
// legitimate error on this skeleton — and exit 1 proves errors fail.
it('exits 1 on a wiring error and names the fix', function (): void {
    expect(Artisan::call('arkhe:doctor'))->toBe(1);

    $row = doctorRow(doctorJson(), 'email.config');

    expect($row['status'])->toBe('error')
        ->and($row['fix'])->toContain('arkhe:email:install');
});

it('reports the healthy checks as ok', function (): void {
    $rows = doctorJson();

    expect(doctorRow($rows, 'email.migrations')['status'])->toBe('ok')
        ->and(doctorRow($rows, 'email.listeners')['status'])->toBe('ok')
        ->and(doctorRow($rows, 'email.table_collision')['status'])->toBe('ok');
});

it('filters by module', function (): void {
    $ids = array_column(doctorJson(['--module' => 'email']), 'id');

    expect($ids)->toContain('email.config')
        ->not->toContain('ai.sync');
});

// ─── § 13.5 acceptance: the two static-analysis criteria ─────────────────

it('errors when app code references an external verification service', function (): void {
    plant(app_path('Services/EmailChecker.php'), "<?php\n// calls https://api.usebouncer.com/v1.1/email\n");

    $row = doctorRow(doctorJson(), 'email.external_verifier');

    expect($row['status'])->toBe('error')
        ->and($row['message'])->toContain('EmailChecker.php');
});

it('errors on a FormRequest validating email outside ArkheFormRequest, with an actionable fix', function (): void {
    plant(app_path('Http/Requests/LegacyRequest.php'), <<<'PHP'
    <?php

    namespace App\Http\Requests;

    use Illuminate\Foundation\Http\FormRequest;

    class LegacyRequest extends FormRequest
    {
        public function rules(): array
        {
            return ['email' => ['required', 'email']];
        }
    }
    PHP);

    $row = doctorRow(doctorJson(), 'email.form_requests');

    expect($row['status'])->toBe('error')
        ->and($row['fix'])->toContain('ArkheFormRequest');
});

// ─── § 14.7 acceptance: the baseline lifecycle ───────────────────────────

it('tolerates baselined failures, catches new ones, and --strict ignores it all', function (): void {
    plant(app_path('Http/Requests/LegacyRequest.php'), "<?php\nnamespace App\\Http\\Requests;\nuse Illuminate\\Foundation\\Http\\FormRequest;\nclass LegacyRequest extends FormRequest { public function rules(): array { return ['email' => ['required', 'email']]; } }\n");

    // Record today's debt…
    expect(Artisan::call('arkhe:doctor', ['--generate-baseline' => true]))->toBe(0)
        ->and(file_exists(Baseline::path()))->toBeTrue();

    // …the same failures now warn instead of failing…
    expect(Artisan::call('arkhe:doctor'))->toBe(0);

    // …a NEW offender is not covered and fails again…
    plant(app_path('Http/Requests/NewerRequest.php'), "<?php\nnamespace App\\Http\\Requests;\nuse Illuminate\\Foundation\\Http\\FormRequest;\nclass NewerRequest extends FormRequest { public function rules(): array { return ['email' => ['required', 'email']]; } }\n");

    expect(Artisan::call('arkhe:doctor'))->toBe(1);

    // …and --strict measures the whole debt, baseline or not.
    expect(Artisan::call('arkhe:doctor', ['--strict' => true]))->toBe(1);
});
