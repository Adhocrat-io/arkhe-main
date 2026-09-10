<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Http\Webhooks;

use Arkhe\Main\Email\Enums\BounceType;
use Arkhe\Main\Email\Services\BounceIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Postmark → ingest(). Postmark does not sign its webhooks, so the guard
 * is a shared token carried in the URL (`?token=`) or the
 * `X-Arkhe-Webhook-Token` header, compared to the configured value.
 */
class PostmarkWebhookController
{
    public function __invoke(Request $request, BounceIngestionService $ingestion): Response
    {
        $this->verifyToken($request);

        $type = match ($request->input('RecordType')) {
            'Bounce' => match ($request->input('Type')) {
                'HardBounce', 'BadEmailAddress', 'ManuallyDeactivated' => BounceType::Hard,
                default => BounceType::Soft,
            },
            'SpamComplaint' => BounceType::Complaint,
            default => null,
        };

        $email = $request->input('Email');

        if ($type !== null && is_string($email)) {
            $ingestion->ingest(
                email: $email,
                type: $type,
                provider: 'postmark',
                reason: is_string($request->input('Description')) ? $request->input('Description') : null,
                at: is_string($request->input('BouncedAt'))
                    ? CarbonImmutable::parse($request->input('BouncedAt'))
                    : CarbonImmutable::now(),
            );
        }

        return response()->noContent();
    }

    private function verifyToken(Request $request): void
    {
        $token = (string) config('arkhe-email.bounces.providers.postmark.token');
        $given = (string) ($request->query('token') ?? $request->header('X-Arkhe-Webhook-Token', ''));

        if ($token === '' || ! hash_equals($token, $given)) {
            abort(403, 'Invalid Postmark webhook token.');
        }
    }
}
