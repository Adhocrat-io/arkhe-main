<?php

declare(strict_types=1);

use Arkhe\Main\Email\Contracts\EmailBounceRepository;
use Arkhe\Main\Email\Models\EmailBounce;

// The EmailEnabledTestCase turns mailgun and postmark on (with test
// credentials) and leaves ses and brevo off — both halves matter.

function mailgunPayload(string $recipient, string $severity = 'permanent'): array
{
    $timestamp = (string) time();
    $token = 'test-token';

    return [
        'signature' => [
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp.$token, 'mg-test-key'),
        ],
        'event-data' => [
            'event' => 'failed',
            'severity' => $severity,
            'recipient' => $recipient,
            'reason' => 'suppress-bounce',
        ],
    ];
}

it('records a mailgun hard bounce through the webhook', function (): void {
    $this->postJson('/arkhe/email/webhooks/mailgun', mailgunPayload('dead@example.test'))
        ->assertNoContent();

    expect(app(EmailBounceRepository::class)->isSuppressed('dead@example.test'))->toBeTrue()
        ->and(EmailBounce::query()->where('provider', 'mailgun')->count())->toBe(1);
});

it('refuses a mailgun payload with a bad signature', function (): void {
    $payload = mailgunPayload('dead@example.test');
    $payload['signature']['signature'] = 'forged';

    $this->postJson('/arkhe/email/webhooks/mailgun', $payload)->assertForbidden();

    expect(EmailBounce::query()->count())->toBe(0);
});

it('records a postmark bounce behind its shared token', function (): void {
    $this->postJson('/arkhe/email/webhooks/postmark?token=pm-test-token', [
        'RecordType' => 'Bounce',
        'Type' => 'HardBounce',
        'Email' => 'gone@example.test',
        'Description' => 'The server was unable to deliver your message',
    ])->assertNoContent();

    expect(app(EmailBounceRepository::class)->isSuppressed('gone@example.test'))->toBeTrue();
});

it('refuses postmark without the token', function (): void {
    $this->postJson('/arkhe/email/webhooks/postmark', [
        'RecordType' => 'Bounce', 'Type' => 'HardBounce', 'Email' => 'gone@example.test',
    ])->assertForbidden();
});

// A disabled provider has NO route: the URL answers 404 and leaks nothing
// about the stack (FRD § 8.2).
it('mounts no route for disabled providers', function (string $provider): void {
    $this->postJson('/arkhe/email/webhooks/'.$provider, [])->assertNotFound();
})->with(['ses', 'brevo']);
