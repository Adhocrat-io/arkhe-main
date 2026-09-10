<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\DoctorResult;
use Arkhe\Main\Doctor\Support\FileScanner;

class RawMailCheck extends EmailCheck
{
    public function id(): string
    {
        return 'email.raw_mail';
    }

    protected function execute(): DoctorResult
    {
        $offenders = [];

        foreach (FileScanner::phpFiles(app_path()) as $file) {
            $code = (string) file_get_contents($file);

            if (str_contains($code, 'Mail::raw(') || str_contains($code, 'Mail::html(')) {
                $offenders[] = FileScanner::relative($file);
            }
        }

        if ($offenders !== []) {
            return $this->warning(
                'Envoi(s) hors Mailable détecté(s) : '.implode(', ', $offenders).' — ils échappent au typage des catégories (§ 8.3).',
                'Remplacer chaque `Mail::raw`/`Mail::html` par un Mailable.',
                targets: $offenders,
            );
        }

        return $this->ok('Tous les envois passent par des Mailables.');
    }
}
