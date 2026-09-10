<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Http\Webhooks;

use Arkhe\Main\Email\Enums\BounceType;
use Arkhe\Main\Email\Services\BounceIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Mailgun → ingest(). Verify the HMAC signature, map the payload, nothing
 * else (FRD § 8.2) — every decision lives in the ingestion service.
 */
class MailgunWebhookController
{
    public function __invoke(Request $request, BounceIngestionService $ingestion): Response
    {
        $this->verifySignature($request);

        $event = (array) $request->input('event-data', []);

        $type = match ($event['event'] ?? null) {
            'failed' => ($event['severity'] ?? '') === 'permanent' ? BounceType::Hard : BounceType::Soft,
            'complained' => BounceType::Complaint,
            default => null,
        };

        $recipient = $event['recipient'] ?? null;

        if ($type !== null && is_string($recipient)) {
            $ingestion->ingest(
                email: $recipient,
                type: $type,
                provider: 'mailgun',
                reason: $event['delivery-status']['description'] ?? $event['reason'] ?? null,
                at: isset($event['timestamp'])
                    ? CarbonImmutable::createFromTimestamp((int) $event['timestamp'])
                    : CarbonImmutable::now(),
            );
        }

        return response()->noContent();
    }

    private function verifySignature(Request $request): void
    {
        $key = (string) config('arkhe-email.bounces.providers.mailgun.signing_key');
        $signature = (array) $request->input('signature', []);

        $expected = hash_hmac(
            'sha256',
            (string) ($signature['timestamp'] ?? '').(string) ($signature['token'] ?? ''),
            $key,
        );

        if ($key === '' || ! hash_equals($expected, (string) ($signature['signature'] ?? ''))) {
            abort(403, 'Invalid Mailgun signature.');
        }
    }
}
