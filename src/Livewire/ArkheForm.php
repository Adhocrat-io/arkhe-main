<?php

declare(strict_types=1);

namespace Arkhe\Main\Livewire;

use Arkhe\Main\Email\Concerns\VerifiesEmailAttributes;
use Arkhe\Main\Email\Livewire\Concerns\SuggestsEmailCorrection;
use Arkhe\Main\Foundation\ArkheFormRequest;
use Livewire\Form;

/**
 * The Livewire Form base class (FR-AI-02): same rule injection as
 * {@see ArkheFormRequest}, plus the "did you
 * mean…?" state — the hosting component only has to drop
 * `<x-arkhe::email-suggestion :form="$form" />` under its email field.
 */
abstract class ArkheForm extends Form
{
    use SuggestsEmailCorrection;
    use VerifiesEmailAttributes;

    public function getRules(): array
    {
        return $this->injectEmailVerification((array) parent::getRules());
    }
}
