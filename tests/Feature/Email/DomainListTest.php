<?php

declare(strict_types=1);

use Arkhe\Main\Email\Contracts\EmailDomainRepository;
use Arkhe\Main\Email\DTOs\DomainEntry;
use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\Enums\DomainSource;
use Arkhe\Main\Email\Enums\DomainStatus;
use Arkhe\Main\Email\Enums\EmailVerificationStatus;
use Arkhe\Main\Email\Models\EmailDomain;
use Arkhe\Main\Email\Rules\DeliverableEmail;
use Arkhe\Main\Email\Services\EmailVerificationService;
use Arkhe\Main\Email\Verifiers\DomainListVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

function domains(): EmailDomainRepository
{
    return app(EmailDomainRepository::class);
}

// ─── Repository ──────────────────────────────────────────────────────────

it('round-trips an entry through upsert and find', function (): void {
    domains()->upsert(new DomainEntry('hotmaij.com', DomainStatus::Typo, 'hotmail.com', DomainSource::Seed));

    $entry = domains()->find('HOTMAIJ.com');

    expect($entry)->not->toBeNull()
        ->and($entry->status)->toBe(DomainStatus::Typo)
        ->and($entry->correctedDomain)->toBe('hotmail.com')
        ->and($entry->source)->toBe(DomainSource::Seed);
});

it('preserves local counters when an upsert rewrites a row', function (): void {
    domains()->upsert(new DomainEntry('x.fr', DomainStatus::Allowed, source: DomainSource::Seed));
    domains()->incrementBounces('x.fr');
    domains()->incrementDeliveries('x.fr');

    // An import updating the status must not erase what the field observed.
    domains()->upsert(new DomainEntry('x.fr', DomainStatus::Blocked, source: DomainSource::Imported));

    $entry = domains()->find('x.fr');

    expect($entry->status)->toBe(DomainStatus::Blocked)
        ->and($entry->bounces)->toBe(1)
        ->and($entry->deliveries)->toBe(1);
});

it('serves lookups from cache until a write invalidates the entry', function (): void {
    domains()->upsert(new DomainEntry('cached.fr', DomainStatus::Allowed));
    domains()->find('cached.fr');

    // Bypass the repository: the cache cannot know, so the stale answer is
    // expected — that is what "cached" means.
    DB::table((new EmailDomain)->getTable())->where('domain', 'cached.fr')->delete();

    expect(domains()->find('cached.fr'))->not->toBeNull();

    // A write THROUGH the repository forgets the entry: next lookup sees
    // the real state.
    domains()->upsert(new DomainEntry('cached.fr', DomainStatus::Blocked));

    expect(domains()->find('cached.fr')->status)->toBe(DomainStatus::Blocked);
});

it('computes the delta against a seed by shareable identity', function (): void {
    $seed = [
        new DomainEntry('same.fr', DomainStatus::Allowed),
        new DomainEntry('retyped.fr', DomainStatus::Allowed),
    ];

    domains()->upsert(new DomainEntry('same.fr', DomainStatus::Allowed, source: DomainSource::Seed));
    domains()->upsert(new DomainEntry('retyped.fr', DomainStatus::Blocked, source: DomainSource::Learned));
    domains()->upsert(new DomainEntry('novel.fr', DomainStatus::Allowed, source: DomainSource::Learned));

    $delta = collect(domains()->delta($seed))->map(fn (DomainEntry $e): string => $e->domain)->all();

    expect($delta)->toBe(['novel.fr', 'retyped.fr']);
});

// ─── DomainListVerifier (FRD § 5.5) ──────────────────────────────────────

it('maps each domain status to its verdict', function (): void {
    domains()->upsert(new DomainEntry('gmail.com', DomainStatus::Allowed));
    domains()->upsert(new DomainEntry('yopmail.com', DomainStatus::Blocked));
    domains()->upsert(new DomainEntry('hotmaij.com', DomainStatus::Typo, 'hotmail.com'));

    $verifier = app(DomainListVerifier::class);

    expect($verifier->verify(new EmailCandidate('a@gmail.com'))->status)->toBe(EmailVerificationStatus::Deliverable)
        ->and($verifier->verify(new EmailCandidate('a@yopmail.com'))->status)->toBe(EmailVerificationStatus::Undeliverable)
        ->and($verifier->verify(new EmailCandidate('a@nowhere.example'))->status)->toBe(EmailVerificationStatus::Unverifiable);

    $typo = $verifier->verify(new EmailCandidate('x@hotmaij.com'));

    expect($typo->status)->toBe(EmailVerificationStatus::Risky)
        ->and($typo->suggestion)->toBe('x@hotmail.com');
});

// ─── Acceptance criteria (FRD § 11), on the real pipeline ────────────────

it('suggests the corrected domain for a known typo', function (): void {
    $this->artisan('arkhe:email:seed-domains')->assertSuccessful();

    $result = app(EmailVerificationService::class)->verify(new EmailCandidate('x@hotmaij.com'));

    expect($result->status)->toBe(EmailVerificationStatus::Risky)
        ->and($result->suggestion)->toBe('x@hotmail.com');
});

it('refuses a blocked domain through the validation rule', function (): void {
    $this->artisan('arkhe:email:seed-domains')->assertSuccessful();

    $validator = Validator::make(['email' => 'x@yopmail.com'], ['email' => [new DeliverableEmail]]);

    expect($validator->fails())->toBeTrue();
});

// Provider rules outrank the allowed list: gmail.com is Deliverable by
// domain, but a 2-character local part is a certainty of non-delivery and
// Undeliverable interrupts the pipeline.
it('refuses ab@gmail.com even though gmail.com is allowed', function (): void {
    $this->artisan('arkhe:email:seed-domains')->assertSuccessful();

    $validator = Validator::make(['email' => 'ab@gmail.com'], ['email' => [new DeliverableEmail]]);

    expect($validator->fails())->toBeTrue();
});

// ─── Seed command (FRD § 6.3) ────────────────────────────────────────────

it('seeds the shipped list and stays idempotent', function (): void {
    $this->artisan('arkhe:email:seed-domains')->assertSuccessful();

    $count = EmailDomain::query()->count();

    expect($count)->toBeGreaterThan(80)
        ->and(EmailDomain::query()->where('domain', 'hotmaij.com')->value('status'))->toBe(DomainStatus::Typo);

    $this->artisan('arkhe:email:seed-domains')->assertSuccessful();

    expect(EmailDomain::query()->count())->toBe($count);
});

it('never overwrites a row whose source is not seed', function (): void {
    // The admin decided yopmail.com is fine for this app — the factory list
    // must not fight them on every deploy.
    domains()->upsert(new DomainEntry('yopmail.com', DomainStatus::Allowed, source: DomainSource::Manual));

    $this->artisan('arkhe:email:seed-domains')->assertSuccessful();

    $entry = domains()->find('yopmail.com');

    expect($entry->status)->toBe(DomainStatus::Allowed)
        ->and($entry->source)->toBe(DomainSource::Manual);
});
