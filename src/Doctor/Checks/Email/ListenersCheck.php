<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\DoctorResult;
use Arkhe\Main\Email\Listeners\PreventSendingToSuppressedAddress;
use Arkhe\Main\Email\Listeners\RecordSuccessfulDelivery;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

class ListenersCheck extends EmailCheck
{
    public function __construct(private readonly Dispatcher $events) {}

    public function id(): string
    {
        return 'email.listeners';
    }

    protected function execute(): DoctorResult
    {
        $missing = [];

        foreach ([
            MessageSending::class => PreventSendingToSuppressedAddress::class,
            MessageSent::class => RecordSuccessfulDelivery::class,
        ] as $event => $listener) {
            if (! $this->listens($event, $listener)) {
                $missing[] = class_basename($listener);
            }
        }

        if ($missing !== []) {
            return $this->error(
                'Écouteur(s) d’envoi absent(s) : '.implode(', ', $missing).'.',
                'Le provider Arkhè ne s’est pas enregistré — vérifier la découverte de packages (`composer dump-autoload`) et que `arkhe-email.enabled` est lu avant le boot.',
                targets: $missing,
            );
        }

        return $this->ok('Écouteurs de suppression et d’apprentissage enregistrés.');
    }

    private function listens(string $event, string $listener): bool
    {
        // getRawListeners keeps the class-string form we registered with,
        // which is what makes "is OUR listener there" answerable at all.
        $raw = method_exists($this->events, 'getRawListeners') ? $this->events->getRawListeners() : [];

        return in_array($listener, $raw[$event] ?? [], true);
    }
}
