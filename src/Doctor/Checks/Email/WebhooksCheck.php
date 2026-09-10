<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\DoctorResult;

class WebhooksCheck extends EmailCheck
{
    private const SECRET_KEYS = [
        'mailgun' => 'signing_key',
        'postmark' => 'token',
        'ses' => 'token',
        'brevo' => 'secret',
    ];

    public function id(): string
    {
        return 'email.webhooks';
    }

    protected function execute(): DoctorResult
    {
        $bare = [];
        $enabled = 0;

        foreach (self::SECRET_KEYS as $provider => $key) {
            $config = (array) config("arkhe-email.bounces.providers.{$provider}", []);

            if (($config['enabled'] ?? false) !== true) {
                continue;
            }

            $enabled++;

            if (! is_string($config[$key] ?? null) || $config[$key] === '') {
                $bare[] = $provider;
            }
        }

        if ($bare !== []) {
            // An enabled webhook without its secret answers 403 to its own
            // provider — the bounce loop silently goes deaf.
            return $this->warning(
                'Fournisseur(s) webhook activé(s) sans clé/secret : '.implode(', ', $bare).'.',
                'Renseigner la variable d’environnement du secret de chaque fournisseur listé.',
                targets: $bare,
            );
        }

        return $enabled > 0
            ? $this->ok("{$enabled} fournisseur(s) webhook activé(s), secrets renseignés.")
            : $this->info('Aucun fournisseur webhook activé — la boucle de bounces attend sa configuration.');
    }
}
