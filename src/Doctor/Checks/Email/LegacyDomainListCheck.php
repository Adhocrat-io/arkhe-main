<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\DoctorResult;
use Arkhe\Main\Doctor\Support\FileScanner;

/**
 * FR-AI-07 `email.legacy_domain_list`: a hard-coded domain list in app/ or
 * config/ is exactly what the table replaces. Heuristic per the FRD — a
 * file holding five or more quoted domain-looking strings — and a warning,
 * never an error: coexistence during `observe` is the § 14.5 plan.
 */
class LegacyDomainListCheck extends EmailCheck
{
    private const DOMAIN_PATTERN = '/[\'"](?:[a-z0-9-]+\.)+(?:com|net|org|fr|be|ch|de|es|it|nl|eu|io|co|me)[\'"]/';

    private const THRESHOLD = 5;

    public function id(): string
    {
        return 'email.legacy_domain_list';
    }

    protected function execute(): DoctorResult
    {
        $offenders = [];

        foreach ([app_path(), config_path()] as $dir) {
            foreach (FileScanner::phpFiles($dir) as $file) {
                if (basename($file) === 'arkhe-email.php') {
                    continue; // the module's own provider rules would match
                }

                $count = preg_match_all(self::DOMAIN_PATTERN, (string) file_get_contents($file));

                if ($count !== false && $count >= self::THRESHOLD) {
                    $offenders[] = FileScanner::relative($file);
                }
            }
        }

        if ($offenders !== []) {
            return $this->warning(
                'Liste de domaines en dur probable : '.implode(', ', $offenders).'.',
                'Convertir avec `php artisan arkhe:email:import-domains --from-php=<chemin> --dry-run` puis retirer le code une fois en mode suggest.',
                targets: $offenders,
            );
        }

        return $this->ok('Aucune liste de domaines en dur détectée.');
    }
}
