<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\DoctorResult;
use Arkhe\Main\Doctor\Support\FileScanner;

/**
 * The confidentiality constraint of § 1, made mechanical: no code or
 * config of the application may reference a third-party address
 * verification service. This is the check that backs the `.claude/rules`
 * file — the assistant is told the doctor WILL fail on it.
 */
class ExternalVerifierCheck extends EmailCheck
{
    public function id(): string
    {
        return 'email.external_verifier';
    }

    protected function execute(): DoctorResult
    {
        $needles = (array) config('arkhe-email.doctor.external_verifier_domains', []);
        $offenders = [];

        foreach ([app_path(), config_path()] as $dir) {
            foreach (FileScanner::phpFiles($dir) as $file) {
                // The needle list itself lives in the published module
                // config — the one file allowed to name these services.
                if (basename($file) === 'arkhe-email.php') {
                    continue;
                }

                $code = (string) file_get_contents($file);

                foreach ($needles as $needle) {
                    if (is_string($needle) && $needle !== '' && stripos($code, $needle) !== false) {
                        $offenders[] = FileScanner::relative($file).' ('.$needle.')';

                        break;
                    }
                }
            }
        }

        if ($offenders !== []) {
            return $this->error(
                'Référence(s) à un service de vérification externe : '.implode(', ', $offenders).' — contrainte de confidentialité (§ 1).',
                'Retirer l’appel et utiliser les drivers locaux (`Arkhe\Main\Email\Verifiers\*`) ; voir .claude/rules/arkhe-email.md.',
                targets: $offenders,
            );
        }

        return $this->ok('Aucun service de vérification externe référencé.');
    }
}
