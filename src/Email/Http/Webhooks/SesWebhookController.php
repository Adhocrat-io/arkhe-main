<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Http\Webhooks;

use Arkhe\Main\Email\Enums\BounceType;
use Arkhe\Main\Email\Services\BounceIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Amazon SES via SNS → ingest().
 *
 * Full SNS signature verification needs the signing certificate fetched
 * over HTTPS — an outbound call NFR-01 forbids at runtime. The guard is a
 * shared URL token instead (`?token=` against `providers.ses.token`), and
 * a SubscriptionConfirmation is logged for a human to confirm out-of-band
 * rather than auto-fetched.
 */
class SesWebhookController
{
    public function __invoke(Request $request, BounceIngestionService $ingestion): Response
    {
        $this->verifyToken($request);

        $envelope = (array) json_decode((string) $request->getContent(), associative: true);

        if (($envelope['Type'] ?? null) === 'SubscriptionConfirmation') {
            Log::warning('[arkhe-email] SES/SNS subscription pending — confirm it manually.', [
                'subscribe_url' => $envelope['SubscribeURL'] ?? null,
            ]);

            return response()->noContent();
        }

        $message = (array) json_decode((string) ($envelope['Message'] ?? '{}'), associative: true);

        match ($message['notificationType'] ?? null) {
            'Bounce' => $this->ingestBounce($message, $ingestion),
            'Complaint' => $this->ingestComplaint($message, $ingestion),
            default => null,
        };

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function ingestBounce(array $message, BounceIngestionService $ingestion): void
    {
        $bounce = (array) ($message['bounce'] ?? []);
        $type = ($bounce['bounceType'] ?? '') === 'Permanent' ? BounceType::Hard : BounceType::Soft;

        foreach ((array) ($bounce['bouncedRecipients'] ?? []) as $recipient) {
            if (is_array($recipient) && is_string($recipient['emailAddress'] ?? null)) {
                $ingestion->ingest(
                    email: $recipient['emailAddress'],
                    type: $type,
                    provider: 'ses',
                    reason: $recipient['diagnosticCode'] ?? null,
                    at: $this->timestamp($bounce['timestamp'] ?? null),
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function ingestComplaint(array $message, BounceIngestionService $ingestion): void
    {
        $complaint = (array) ($message['complaint'] ?? []);

        foreach ((array) ($complaint['complainedRecipients'] ?? []) as $recipient) {
            if (is_array($recipient) && is_string($recipient['emailAddress'] ?? null)) {
                $ingestion->ingest(
                    email: $recipient['emailAddress'],
                    type: BounceType::Complaint,
                    provider: 'ses',
                    reason: $complaint['complaintFeedbackType'] ?? null,
                    at: $this->timestamp($complaint['timestamp'] ?? null),
                );
            }
        }
    }

    private function timestamp(mixed $value): CarbonImmutable
    {
        return is_string($value) ? CarbonImmutable::parse($value) : CarbonImmutable::now();
    }

    private function verifyToken(Request $request): void
    {
        $token = (string) config('arkhe-email.bounces.providers.ses.token');

        if ($token === '' || ! hash_equals($token, (string) $request->query('token'))) {
            abort(403, 'Invalid SES webhook token.');
        }
    }
}
