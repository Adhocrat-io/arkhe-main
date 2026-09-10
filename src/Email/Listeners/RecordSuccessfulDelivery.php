<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Listeners;

use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\Services\DomainLearningService;
use Illuminate\Mail\Events\MessageSent;

/**
 * The learning half of the loop (FRD § 8.3): a message the relay accepted
 * increments the recipient domains' `deliveries`. Acceptance by the relay
 * is not proof of receipt — it is however exactly enough signal for the
 * learning rules, which only ask "has this domain EVER taken a message?".
 */
final class RecordSuccessfulDelivery
{
    public function __construct(private readonly DomainLearningService $learning) {}

    public function handle(MessageSent $event): void
    {
        foreach ($event->message->getTo() as $address) {
            $domain = (new EmailCandidate($address->getAddress()))->domain;

            if ($domain !== '') {
                $this->learning->recordDelivery($domain);
            }
        }
    }
}
