<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\DoctorResult;

class MxOptInCheck extends EmailCheck
{
    public function id(): string
    {
        return 'email.mx_opt_in';
    }

    protected function execute(): DoctorResult
    {
        return (bool) config('arkhe-email.verifiers.mx.enabled', false)
            ? $this->info('Driver MX ACTIVÉ : résolutions DNS autorisées (seule activité réseau du module).')
            : $this->info('Driver MX désactivé (le défaut) : aucune résolution DNS.');
    }
}
