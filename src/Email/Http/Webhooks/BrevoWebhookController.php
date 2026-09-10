<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Http\Webhooks;

use Arkhe\Main\Email\Enums\BounceType;
use Arkhe\Main\Email\Services\BounceIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Brevo → ingest(). Brevo does not sign either; same shared-token guard as
 * Postmark, against `providers.brevo.secret`.
 */
class BrevoWebhookController
{
    public function __invoke(Request $request, BounceIngestionService $ingestion): Response
    {
        $this->verifyToken($request);

        $type = match ($request->input('event')) {
            'hard_bounce', 'invalid_email' => BounceType::Hard,
            'soft_bounce', 'blocked', 'deferred' => BounceType::Soft,
            'spam', 'complaint' => BounceType::Complaint,
            default => null,
        };

        $email = $request->input('email');

        if ($type !== null && is_string($email)) {
            $ingestion->ingest(
                email: $email,
                type: $type,
                provider: 'brevo',
                reason: is_string($request->input('reason')) ? $request->input('reason') : null,
                at: is_string($request->input('date'))
                    ? CarbonImmutable::parse($request->input('date'))
                    : CarbonImmutable::now(),
            );
        }

        return response()->noContent();
    }

    private function verifyToken(Request $request): void
    {
        $secret = (string) config('arkhe-email.bounces.providers.brevo.secret');
        $given = (string) ($request->query('token') ?? $request->header('X-Arkhe-Webhook-Token', ''));

        if ($secret === '' || ! hash_equals($secret, $given)) {
            abort(403, 'Invalid Brevo webhook token.');
        }
    }
}
