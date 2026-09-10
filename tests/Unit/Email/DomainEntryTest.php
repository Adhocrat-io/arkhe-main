<?php

declare(strict_types=1);

use Arkhe\Main\Email\DTOs\DomainEntry;
use Arkhe\Main\Email\DTOs\DomainListDocument;
use Arkhe\Main\Email\Enums\DomainStatus;

// The § 7.1 invariant, enforced at the earliest possible point: nothing in
// the module can ever hold a typo entry that leads nowhere.
it('rejects a typo entry without its correction', function (): void {
    new DomainEntry('hotmaij.com', DomainStatus::Typo);
})->throws(InvalidArgumentException::class);

it('normalizes domains to lowercase', function (): void {
    $entry = new DomainEntry('HotMail.COM', DomainStatus::Allowed);

    expect($entry->domain)->toBe('hotmail.com');
});

it('fingerprints only the shareable identity', function (): void {
    $a = new DomainEntry('x.fr', DomainStatus::Allowed, bounces: 5, deliveries: 2, note: 'local note');
    $b = new DomainEntry('x.fr', DomainStatus::Allowed);

    // Counters and notes are local knowledge — they must not make an entry
    // "different from the seed" in a delta export.
    expect($a->fingerprint())->toBe($b->fingerprint());
});

// ─── The exchange document (FRD § 7.1) ───────────────────────────────────

it('rejects a document with an unknown schema', function (): void {
    DomainListDocument::fromJson('{"schema": 99, "kind": "full", "entries": []}');
})->throws(InvalidArgumentException::class);

it('rejects the whole file when one entry is invalid', function (): void {
    DomainListDocument::fromJson(json_encode([
        'schema' => 1,
        'kind' => 'full',
        'entries' => [
            ['domain' => 'ok.fr', 'status' => 'allowed'],
            ['domain' => 'hotmaij.com', 'status' => 'typo'], // no corrected_domain
        ],
    ]));
})->throws(InvalidArgumentException::class);

it('parses a valid document', function (): void {
    $document = DomainListDocument::fromJson(json_encode([
        'schema' => 1,
        'kind' => 'delta',
        'exported_at' => '2026-09-09T10:00:00Z',
        'origin' => null,
        'entries' => [
            ['domain' => 'hotmaij.com', 'status' => 'typo', 'corrected_domain' => 'hotmail.com'],
            ['domain' => 'yopmail.com', 'status' => 'blocked'],
        ],
    ]));

    expect($document->kind)->toBe('delta')
        ->and($document->entries)->toHaveCount(2)
        ->and($document->entries[0]->correctedDomain)->toBe('hotmail.com');
});

it('never writes counters or sources into a file', function (): void {
    $document = new DomainListDocument('full', [
        new DomainEntry('x.fr', DomainStatus::Allowed, bounces: 9, deliveries: 3, note: 'secret'),
    ]);

    $json = $document->toJson();

    expect($json)->not->toContain('bounces')
        ->not->toContain('deliveries')
        ->not->toContain('source')
        ->not->toContain('secret'); // notes only travel with --with-notes
});
