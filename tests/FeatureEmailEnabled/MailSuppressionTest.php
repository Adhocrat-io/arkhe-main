<?php

declare(strict_types=1);

use Arkhe\Main\Email\Contracts\EmailDomainRepository;
use Arkhe\Main\Email\Enums\BounceType;
use Arkhe\Main\Email\Services\BounceIngestionService;
use Arkhe\Main\Tests\Stubs\Email\ExemptMailable;
use Arkhe\Main\Tests\Stubs\Email\MarketingMailable;
use Arkhe\Main\Tests\Stubs\Email\SimpleMailable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Psr\Log\LoggerInterface;

function suppress(string $email): void
{
    app(BounceIngestionService::class)->ingest($email, BounceType::Hard, 'manual', null, CarbonImmutable::now());
}

function sentMessages(): array
{
    return app('mailer')->getSymfonyTransport()->messages()->all();
}

// ─── Suppression at send (FRD § 8.3) — module enabled, mode suggest ──────

it('cancels a send towards a suppressed address', function (): void {
    suppress('dead@example.test');

    Mail::to('dead@example.test')->send(new SimpleMailable);

    expect(sentMessages())->toHaveCount(0);
});

it('lets clean addresses through and learns from the delivery', function (): void {
    Mail::to('jean@mairie-exemple.fr')->send(new SimpleMailable);

    expect(sentMessages())->toHaveCount(1)
        ->and(app(EmailDomainRepository::class)->find('mairie-exemple.fr')->deliveries)->toBe(1);
});

// Acceptance § 11: no exempted Mailable ever goes to a suppressed address —
// and the exempted one always leaves.
it('lets a BypassesSuppression mailable through anyway', function (): void {
    suppress('dead@example.test');

    Mail::to('dead@example.test')->send(new ExemptMailable);

    expect(sentMessages())->toHaveCount(1);
});

it('ignores categories outside enforce_on', function (): void {
    config()->set('arkhe-email.bounces.enforce_on', ['transactional']);

    suppress('dead@example.test');

    Mail::to('dead@example.test')->send(new MarketingMailable);

    expect(sentMessages())->toHaveCount(1);
});

it('stands down entirely when enforce_on is empty', function (): void {
    config()->set('arkhe-email.bounces.enforce_on', []);

    suppress('dead@example.test');

    Mail::to('dead@example.test')->send(new SimpleMailable);

    expect(sentMessages())->toHaveCount(1);
});

// ─── Observe mode (FRD § 14.2, § 14.7) ───────────────────────────────────

// The § 14.7 criterion: in observe the message LEAVES, and a "would have
// suppressed" line lands in the journal — that line is how an existing app
// discovers which Mailables need the exemption before anything changes.
it('lets the message leave in observe mode and journals what it would have done', function (): void {
    config()->set('arkhe-email.mode', 'observe');

    suppress('dead@example.test');

    $channel = Mockery::mock(LoggerInterface::class);
    $channel->shouldReceive('info')->once()->withArgs(
        fn (string $message, array $context): bool => $message === 'suppression'
            && ($context['observed'] ?? false) === true
            && in_array('example.test', $context['domains'] ?? [], true),
    );
    Log::shouldReceive('channel')->once()->with('arkhe-email')->andReturn($channel);

    Mail::to('dead@example.test')->send(new SimpleMailable);

    expect(sentMessages())->toHaveCount(1);
});
