<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\DoctorResult;
use Arkhe\Main\Doctor\Support\FileScanner;

/**
 * FR-AI-07 `email.form_requests`: any FormRequest in app/ validating an
 * email must inherit ArkheFormRequest or carry the explicit opt-out. The
 * analysis is textual on purpose (héritage + string `email` in rules) —
 * and the baseline absorbs the legacy so only NEW offenders fail CI.
 */
class FormRequestsCheck extends EmailCheck
{
    public function id(): string
    {
        return 'email.form_requests';
    }

    protected function execute(): DoctorResult
    {
        $offenders = [];

        foreach (FileScanner::phpFiles(app_path('Http/Requests')) as $file) {
            $code = (string) file_get_contents($file);

            if (! str_contains($code, 'function rules')
                || preg_match('/[\'"]email[\'":]/', $code) !== 1) {
                continue;
            }

            if (str_contains($code, 'ArkheFormRequest') || str_contains($code, 'WithoutEmailVerification')) {
                continue;
            }

            $offenders[] = FileScanner::relative($file);
        }

        if ($offenders !== []) {
            return $this->error(
                'FormRequest(s) validant un e-mail sans hériter d’ArkheFormRequest : '.implode(', ', $offenders).'.',
                'Faire hériter chaque classe listée de `Arkhe\Main\Foundation\ArkheFormRequest` (ou poser `#[WithoutEmailVerification]` avec un commentaire).',
                targets: $offenders,
            );
        }

        return $this->ok('Tous les FormRequests à e-mail passent par ArkheFormRequest.');
    }
}
