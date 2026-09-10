<?php

declare(strict_types=1);

use Arkhe\Main\Email\Contracts\EmailDomainRepository;
use Arkhe\Main\Email\DTOs\DomainEntry;
use Arkhe\Main\Email\DTOs\DomainListDocument;
use Arkhe\Main\Email\Enums\DomainSource;
use Arkhe\Main\Email\Enums\DomainStatus;
use Arkhe\Main\Email\Models\EmailDomain;
use Arkhe\Main\Email\Services\DomainListExportService;
use Arkhe\Main\Email\Services\DomainListImportService;

function importDoc(array $entries): DomainListDocument
{
    return DomainListDocument::fromJson(json_encode([
        'schema' => 1,
        'kind' => 'full',
        'entries' => $entries,
    ]));
}

function repo(): EmailDomainRepository
{
    return app(EmailDomainRepository::class);
}

// ─── Merge rules, one per row of the § 7.3 table ─────────────────────────

it('creates absent entries as imported', function (): void {
    $report = app(DomainListImportService::class)->import(importDoc([
        ['domain' => 'new.fr', 'status' => 'allowed'],
    ]));

    expect($report->created)->toBe(1)
        ->and(repo()->find('new.fr')->source)->toBe(DomainSource::Imported);
});

it('updates seed and imported rows', function (): void {
    repo()->upsert(new DomainEntry('a.fr', DomainStatus::Allowed, source: DomainSource::Seed));
    repo()->upsert(new DomainEntry('b.fr', DomainStatus::Allowed, source: DomainSource::Imported));

    $report = app(DomainListImportService::class)->import(importDoc([
        ['domain' => 'a.fr', 'status' => 'blocked'],
        ['domain' => 'b.fr', 'status' => 'blocked'],
    ]));

    expect($report->updated)->toBe(2)
        ->and(repo()->find('a.fr')->status)->toBe(DomainStatus::Blocked)
        ->and(repo()->find('a.fr')->source)->toBe(DomainSource::Imported);
});

// Acceptance criterion § 11: a file contradicting a manual entry does not
// touch it and lists it in the conflict report.
it('never touches a manual row and reports the conflict', function (): void {
    repo()->upsert(new DomainEntry('kept.fr', DomainStatus::Allowed, source: DomainSource::Manual));

    $report = app(DomainListImportService::class)->import(importDoc([
        ['domain' => 'kept.fr', 'status' => 'blocked'],
    ]));

    expect($report->conflicts)->toHaveCount(1)
        ->and($report->conflicts[0]['domain'])->toBe('kept.fr')
        ->and($report->conflicts[0]['fileStatus'])->toBe('blocked')
        ->and(repo()->find('kept.fr')->status)->toBe(DomainStatus::Allowed);
});

// Local field data outranks the central list: a learned verdict that
// disagrees with the file survives, as a listed conflict.
it('keeps a disagreeing learned row and reports it', function (): void {
    repo()->upsert(new DomainEntry('field.fr', DomainStatus::Blocked, source: DomainSource::Learned));

    $report = app(DomainListImportService::class)->import(importDoc([
        ['domain' => 'field.fr', 'status' => 'allowed'],
    ]));

    expect($report->conflicts)->toHaveCount(1)
        ->and(repo()->find('field.fr')->status)->toBe(DomainStatus::Blocked);
});

it('counts an agreeing learned row as unchanged', function (): void {
    repo()->upsert(new DomainEntry('agree.fr', DomainStatus::Blocked, source: DomainSource::Learned));

    $report = app(DomainListImportService::class)->import(importDoc([
        ['domain' => 'agree.fr', 'status' => 'blocked'],
    ]));

    expect($report->unchanged)->toBe(1)
        ->and($report->conflicts)->toBe([])
        ->and(repo()->find('agree.fr')->source)->toBe(DomainSource::Learned);
});

// Acceptance criterion § 11: replaying a file produces a report with no
// creation and no update.
it('is idempotent', function (): void {
    $doc = importDoc([
        ['domain' => 'one.fr', 'status' => 'allowed'],
        ['domain' => 'two.fr', 'status' => 'typo', 'corrected_domain' => 'one.fr'],
    ]);

    app(DomainListImportService::class)->import($doc);
    $second = app(DomainListImportService::class)->import($doc);

    expect($second->created)->toBe(0)
        ->and($second->updated)->toBe(0)
        ->and($second->unchanged)->toBe(2);
});

// Acceptance criterion § 11: --dry-run writes nothing.
it('writes nothing in dry-run', function (): void {
    $report = app(DomainListImportService::class)->import(importDoc([
        ['domain' => 'ghost.fr', 'status' => 'allowed'],
    ]), dryRun: true);

    expect($report->created)->toBe(1)
        ->and(EmailDomain::query()->count())->toBe(0);
});

// ─── Export (FRD § 7.2) ──────────────────────────────────────────────────

// Acceptance criterion § 11: the delta of a freshly seeded application is
// empty — the app has nothing to teach yet.
it('exports an empty delta right after seeding', function (): void {
    $this->artisan('arkhe:email:seed-domains')->assertSuccessful();

    $document = app(DomainListExportService::class)->export('delta');

    expect($document->entries)->toBe([]);
});

it('exports what the application changed or learned in the delta', function (): void {
    $this->artisan('arkhe:email:seed-domains')->assertSuccessful();

    repo()->upsert(new DomainEntry('mairie-exemple.fr', DomainStatus::Allowed, source: DomainSource::Learned));
    repo()->upsert(new DomainEntry('yopmail.com', DomainStatus::Allowed, source: DomainSource::Manual));

    $domains = array_map(
        fn (DomainEntry $e): string => $e->domain,
        app(DomainListExportService::class)->export('delta')->entries,
    );

    expect($domains)->toContain('mairie-exemple.fr')
        ->toContain('yopmail.com')
        ->not->toContain('hotmaij.com');
});

// Acceptance criterion § 11, on a realistic dataset: the exported file
// contains no email address — and no counter, no source either.
it('exports no address and no local data whatsoever', function (): void {
    $this->artisan('arkhe:email:seed-domains')->assertSuccessful();
    repo()->incrementBounces('gmail.com');

    $json = app(DomainListExportService::class)->export('full')->toJson();

    expect(preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+/i', $json))->toBe(0)
        ->and($json)->not->toContain('bounces')
        ->not->toContain('"source"');
});

it('stays anonymous unless an origin is configured', function (): void {
    repo()->upsert(new DomainEntry('x.fr', DomainStatus::Allowed));

    expect(app(DomainListExportService::class)->export('full')->origin)->toBeNull();

    config()->set('arkhe-email.export.origin', 'app-du-client');

    expect(app(DomainListExportService::class)->export('full')->origin)->toBe('app-du-client');
});

// ─── Commands ────────────────────────────────────────────────────────────

it('round-trips through the export and import commands', function (): void {
    repo()->upsert(new DomainEntry('cmd.fr', DomainStatus::Typo, 'gmail.com', DomainSource::Manual));

    $path = storage_path('framework/testing/export.json');
    @mkdir(dirname($path), recursive: true);

    $this->artisan('arkhe:email:export-domains', ['--output' => $path])->assertSuccessful();

    EmailDomain::query()->delete();

    $this->artisan('arkhe:email:import-domains', ['file' => $path])->assertSuccessful();

    expect(repo()->find('cmd.fr')->correctedDomain)->toBe('gmail.com')
        ->and(repo()->find('cmd.fr')->source)->toBe(DomainSource::Imported);
});

// Acceptance criterion § 14.7: --from-php converts an associative array to
// typo/manual entries, a plain list to allowed/manual.
it('converts a legacy php list into manual entries', function (): void {
    $path = storage_path('framework/testing/legacy.php');
    @mkdir(dirname($path), recursive: true);
    file_put_contents($path, <<<'PHP'
    <?php

    return [
        'hotmaij.com' => 'hotmail.com',
        'mairie-exemple.fr',
    ];
    PHP);

    $this->artisan('arkhe:email:import-domains', ['--from-php' => $path])->assertSuccessful();

    $typo = repo()->find('hotmaij.com');
    $allowed = repo()->find('mairie-exemple.fr');

    expect($typo->status)->toBe(DomainStatus::Typo)
        ->and($typo->correctedDomain)->toBe('hotmail.com')
        ->and($typo->source)->toBe(DomainSource::Manual)
        ->and($allowed->status)->toBe(DomainStatus::Allowed)
        ->and($allowed->source)->toBe(DomainSource::Manual);
});

it('leaves the table alone when converting with dry-run', function (): void {
    $path = storage_path('framework/testing/legacy-dry.php');
    @mkdir(dirname($path), recursive: true);
    file_put_contents($path, "<?php\n\nreturn ['hotmaij.com' => 'hotmail.com'];\n");

    $this->artisan('arkhe:email:import-domains', ['--from-php' => $path, '--dry-run' => true])->assertSuccessful();

    expect(EmailDomain::query()->count())->toBe(0);
});
