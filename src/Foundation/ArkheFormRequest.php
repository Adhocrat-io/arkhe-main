<?php

declare(strict_types=1);

namespace Arkhe\Main\Foundation;

use Arkhe\Main\Email\Concerns\VerifiesEmailAttributes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The FormRequest every Arkhè application form should extend (FR-AI-01).
 * One job: after the child's rules() resolve, wire the email verification
 * pipeline into every attribute that validates an email — so the module
 * reaches forms that were written before it existed, and forms an
 * assistant writes tomorrow, without either knowing about it.
 *
 * Extending Laravel's FormRequest still works and changes nothing —
 * `arkhe:doctor` flags it (email.form_requests) so the migration is a
 * one-line parent swap, one file at a time (§ 14.3).
 */
abstract class ArkheFormRequest extends FormRequest
{
    use VerifiesEmailAttributes;

    /**
     * @return array<string, mixed>
     */
    protected function validationRules(): array
    {
        return $this->injectEmailVerification((array) parent::validationRules());
    }
}
