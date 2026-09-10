<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\DoctorResult;
use Illuminate\Support\Facades\Route;

class AdminRoutesCheck extends EmailCheck
{
    public function id(): string
    {
        return 'email.admin_routes';
    }

    protected function execute(): DoctorResult
    {
        if (! Route::has('arkhe.email-domains.index')) {
            return $this->warning(
                'La route d’administration des domaines n’est pas enregistrée.',
                'Vider le cache de routes (`php artisan route:clear`) — la route est montée par Arkhè quand le module est actif.',
            );
        }

        return $this->ok('Écran d’administration des domaines routé.');
    }
}
