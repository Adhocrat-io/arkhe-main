<?php

declare(strict_types=1);

use Arkhe\Main\Email\Contracts\EmailBounceRepository;
use Arkhe\Main\Email\Contracts\EmailDomainRepository;
use Arkhe\Main\Email\DTOs\DomainEntry;
use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\Enums\BounceType;
use Arkhe\Main\Email\Enums\DomainSource;
use Arkhe\Main\Email\Enums\DomainStatus;
use Arkhe\Main\Email\Enums\EmailVerificationStatus;
use Arkhe\Main\Email\Listeners\ClearBouncesOnAddressChange;
use Arkhe\Main\Email\Rules\DeliverableEmail;
use Arkhe\Main\Email\Services\BounceIngestionService;
use Arkhe\Main\Email\Services\DomainLearningService;
use Arkhe\Main\Email\Services\DomainListExportService;
use Arkhe\Main\Email\Verifiers\BounceHistoryVerifier;
use Arkhe\Main\Events\UserUpdated;
use Arkhe\Main\Tests\Stubs\Email\SimpleMailable;
use Arkhe\Main\Tests\Stubs\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

function ingestBounce(string $email, BounceType $type = BounceType::Hard): void
{
    app(BounceIngestionService::class)->ingest($email, $type, 'manual', null, CarbonImmutable::now());
}

function bounces(): EmailBounceRepository
{
    return app(EmailBounceRepository::class);
}

function domainRepo(): EmailDomainRepository
{
    return app(EmailDomainRepository::class);
}

// ─── Suppression state (FRD § 8.2, § 8.3) ────────────────────────────────

it('suppresses on a single hard bounce or complaint', function (BounceType $type): void {
    ingestBounce('dead@example.test', $type);

    expect(bounces()->isSuppressed('Dead@Example.test'))->toBeTrue();
})->with([
    'hard' => [BounceType::Hard],
    'complaint' => [BounceType::Complaint],
]);

it('converts a run of soft bounces into a suppression at the threshold', function (): void {
    foreach (range(1, 4) as $i) {
        ingestBounce('full@example.test', BounceType::Soft);
    }

    expect(bounces()->isSuppressed('full@example.test'))->toBeFalse();

    ingestBounce('full@example.test', BounceType::Soft);

    expect(bounces()->isSuppressed('full@example.test'))->toBeTrue()
        // ...but soft trouble is still not evidence of nonexistence.
        ->and(bounces()->hasActiveHardBounce('full@example.test'))->toBeFalse();
});

// ─── Rehabilitation (FRD § 8.4) ──────────────────────────────────────────

it('rehabilitates a cleared address', function (): void {
    ingestBounce('back@example.test');
    bounces()->clear('back@example.test');

    expect(bounces()->isSuppressed('back@example.test'))->toBeFalse();
});

it('rehabilitates when the user edits their address', function (): void {
    ingestBounce('fixed@example.test');

    /** @var User $user */
    $user = User::query()->forceCreate([
        'first_name' => 'Re',
        'last_name' => 'Hab',
        'email' => 'old@example.test',
        'password' => Hash::make('secret123'),
    ]);

    $user->update(['email' => 'fixed@example.test']);

    app(ClearBouncesOnAddressChange::class)->handle(new UserUpdated($user));

    expect(bounces()->isSuppressed('fixed@example.test'))->toBeFalse();
});

// ─── BounceHistoryVerifier (FRD § 5.4) and the § 11 criterion ────────────

it('refuses a hard-bounced address at input time', function (): void {
    config()->set('arkhe-email.verifiers.pipeline', [BounceHistoryVerifier::class]);

    ingestBounce('dead@example.test');

    $verdict = app(BounceHistoryVerifier::class)->verify(new EmailCandidate('dead@example.test'));
    $validator = Validator::make(['email' => 'dead@example.test'], ['email' => [new DeliverableEmail]]);

    expect($verdict->status)->toBe(EmailVerificationStatus::Undeliverable)
        ->and($validator->fails())->toBeTrue();
});

it('has no opinion on soft-bounced or rehabilitated addresses', function (): void {
    ingestBounce('soft@example.test', BounceType::Soft);
    ingestBounce('cleared@example.test');
    bounces()->clear('cleared@example.test');

    $verifier = app(BounceHistoryVerifier::class);

    expect($verifier->verify(new EmailCandidate('soft@example.test'))->status)->toBe(EmailVerificationStatus::Unverifiable)
        ->and($verifier->verify(new EmailCandidate('cleared@example.test'))->status)->toBe(EmailVerificationStatus::Unverifiable);
});

// ─── Learning (FRD § 6.4) and the § 11 criterion ─────────────────────────

it('blocks an unknown domain after three hard bounces and exports it in the delta', function (): void {
    $this->artisan('arkhe:email:seed-domains')->assertSuccessful();

    foreach (['a', 'b', 'c'] as $localPart) {
        ingestBounce($localPart.'@foo.invalid');
    }

    $entry = domainRepo()->find('foo.invalid');

    expect($entry)->not->toBeNull()
        ->and($entry->status)->toBe(DomainStatus::Blocked)
        ->and($entry->source)->toBe(DomainSource::Learned);

    $delta = array_map(
        fn (DomainEntry $e): string => $e->domain,
        app(DomainListExportService::class)->export('delta')->entries,
    );

    expect($delta)->toContain('foo.invalid');
});

it('does not create a row before the threshold', function (): void {
    ingestBounce('a@fresh.invalid');
    ingestBounce('b@fresh.invalid');

    expect(domainRepo()->find('fresh.invalid'))->toBeNull();
});

it('never blocks a domain that has delivered', function (): void {
    domainRepo()->upsert(new DomainEntry('busy.fr', DomainStatus::Allowed, source: DomainSource::Seed));
    app(DomainLearningService::class)->recordDelivery('busy.fr');

    foreach (range(1, 5) as $i) {
        ingestBounce('user'.$i.'@busy.fr');
    }

    expect(domainRepo()->find('busy.fr')->status)->toBe(DomainStatus::Allowed);
});

it('counts on manual rows without ever changing them', function (): void {
    domainRepo()->upsert(new DomainEntry('decided.fr', DomainStatus::Allowed, source: DomainSource::Manual));

    foreach (range(1, 5) as $i) {
        ingestBounce('user'.$i.'@decided.fr');
    }

    $entry = domainRepo()->find('decided.fr');

    expect($entry->status)->toBe(DomainStatus::Allowed)
        ->and($entry->source)->toBe(DomainSource::Manual)
        ->and($entry->bounces)->toBe(5);
});

it('unblocks a learned block after a successful delivery', function (): void {
    foreach (['a', 'b', 'c'] as $localPart) {
        ingestBounce($localPart.'@flaky.fr');
    }

    expect(domainRepo()->find('flaky.fr')->status)->toBe(DomainStatus::Blocked);

    app(DomainLearningService::class)->recordDelivery('flaky.fr');

    $entry = domainRepo()->find('flaky.fr');

    expect($entry->status)->toBe(DomainStatus::Allowed)
        ->and($entry->source)->toBe(DomainSource::Learned);
});

it('creates an allowed learned row on a first delivery', function (): void {
    app(DomainLearningService::class)->recordDelivery('mairie-exemple.fr');

    $entry = domainRepo()->find('mairie-exemple.fr');

    expect($entry->status)->toBe(DomainStatus::Allowed)
        ->and($entry->source)->toBe(DomainSource::Learned)
        ->and($entry->deliveries)->toBe(1);
});

// ─── Manual command ──────────────────────────────────────────────────────

it('records a bounce from the command line', function (): void {
    $this->artisan('arkhe:email:record-bounce', ['email' => 'ndr@example.test', '--type' => 'hard'])
        ->assertSuccessful();

    expect(bounces()->isSuppressed('ndr@example.test'))->toBeTrue();
});

// ─── NFR-09: nothing listens while the module is disabled ────────────────

it('lets mail through to suppressed addresses while the module is off', function (): void {
    config()->set('mail.default', 'array');
    config()->set('mail.from.address', 'noreply@example.test');

    ingestBounce('dead@example.test');

    Mail::to('dead@example.test')->send(new SimpleMailable);

    expect(app('mailer')->getSymfonyTransport()->messages())->toHaveCount(1);
});
