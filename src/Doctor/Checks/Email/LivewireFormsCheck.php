<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\DoctorResult;
use Arkhe\Main\Doctor\Support\FileScanner;

class LivewireFormsCheck extends EmailCheck
{
    public function id(): string
    {
        return 'email.livewire_forms';
    }

    protected function execute(): DoctorResult
    {
        $offenders = [];

        foreach (FileScanner::phpFiles(app_path()) as $file) {
            $code = (string) file_get_contents($file);

            // A Livewire Form object: imports Livewire\Form (directly or via
            // `extends Form`) and declares email rules.
            if (! str_contains($code, 'Livewire\\Form')
                || ! str_contains($code, 'function rules')
                || preg_match('/[\'"]email[\'":]/', $code) !== 1) {
                continue;
            }

            if (str_contains($code, 'ArkheForm') || str_contains($code, 'WithoutEmailVerification')) {
                continue;
            }

            $offenders[] = FileScanner::relative($file);
        }

        if ($offenders !== []) {
            return $this->error(
                'Form(s) Livewire validant un e-mail sans hériter d’ArkheForm : '.implode(', ', $offenders).'.',
                'Faire hériter chaque classe listée de `Arkhe\Main\Livewire\ArkheForm` et poser `<x-arkhe::email-suggestion :form="$form" />` dans la vue.',
                targets: $offenders,
            );
        }

        return $this->ok('Tous les Forms Livewire à e-mail passent par ArkheForm.');
    }
}
