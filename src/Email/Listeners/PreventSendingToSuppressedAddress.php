<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Listeners;

use Arkhe\Main\Email\Contracts\BypassesSuppression;
use Arkhe\Main\Email\Contracts\EmailBounceRepository;
use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\Enums\EmailVerificationMode;
use Arkhe\Main\Email\Support\EmailModule;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

/**
 * The suppression half of the bounce loop (FRD § 8.3): a message about to
 * leave towards an address the world already refused is cancelled — the
 * cheapest reputation protection there is, since every send to a dead
 * address is a strike against the sender.
 *
 * Registered by the module's provider only when the module is enabled; in
 * `observe` mode it journals what it WOULD have cancelled and lets the
 * message through, which is how an existing application discovers its
 * exempt-worthy Mailables before anything changes (§ 14.6).
 */
final class PreventSendingToSuppressedAddress
{
    public function __construct(private readonly EmailBounceRepository $bounces) {}

    /**
     * Returning false is how a Laravel mail listener cancels the send.
     */
    public function handle(MessageSending $event): ?bool
    {
        if ($this->bypasses($event) || ! $this->categoryEnforced($event)) {
            return null;
        }

        $suppressed = [];

        foreach ($event->message->getTo() as $address) {
            if ($this->bounces->isSuppressed($address->getAddress())) {
                $suppressed[] = (new EmailCandidate($address->getAddress()))->domain;
            }
        }

        if ($suppressed === []) {
            return null;
        }

        if (EmailModule::mode() === EmailVerificationMode::Observe) {
            // The "would have suppressed" journal line: what step 4 of the
            // migration procedure reads to decide which Mailables need the
            // BypassesSuppression exemption (§ 14.6).
            Log::channel((string) config('arkhe-email.log_channel', 'arkhe-email'))->info('suppression', [
                'observed' => true,
                'domains' => $suppressed,
            ]);

            return null;
        }

        Log::info('[arkhe-email] Send cancelled: recipient address is suppressed.', [
            'domains' => $suppressed,
        ]);

        return false;
    }

    /**
     * The Mailable class travels in the view data (`__laravel_mailable`),
     * so the exemption interface is checkable without holding the instance.
     */
    private function bypasses(MessageSending $event): bool
    {
        $mailable = $event->data['__laravel_mailable'] ?? null;

        return is_string($mailable) && is_a($mailable, BypassesSuppression::class, true);
    }

    /**
     * Category taken from the `X-Arkhe-Email-Category` header when a
     * Mailable declares one, `transactional` otherwise — then matched
     * against `arkhe-email.bounces.enforce_on`. An empty enforce_on list is
     * the config-level way to disable suppression entirely (FR-AI-03).
     */
    private function categoryEnforced(MessageSending $event): bool
    {
        $category = $event->message->getHeaders()->getHeaderBody('X-Arkhe-Email-Category') ?? 'transactional';

        return in_array((string) $category, (array) config('arkhe-email.bounces.enforce_on', []), true);
    }
}
