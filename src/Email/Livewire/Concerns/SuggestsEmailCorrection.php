<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Livewire\Concerns;

use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\Enums\EmailVerificationStatus;
use Arkhe\Main\Email\Services\EmailVerificationService;
use Arkhe\Main\Email\Support\EmailModule;

/**
 * Livewire half of the "did you mean…?" flow (FRD § 5.10). Applies to a
 * component or to a Form object — `$suggestedEmail` is a public property
 * either way, so Livewire keeps it across requests.
 *
 * The suggestion is only ever an offer: `acceptSuggestedEmail()` rewrites
 * the field, `dismissSuggestedEmail()` drops it, and nothing here can block
 * a submission — that separation is what allows the verifier to be wrong
 * without ever costing a legitimate signup.
 */
trait SuggestsEmailCorrection
{
    public ?string $suggestedEmail = null;

    public function verifyEmailCandidate(string $email, ?string $firstName = null, ?string $lastName = null): void
    {
        if (! EmailModule::enabled()) {
            $this->suggestedEmail = null;

            return;
        }

        $result = app(EmailVerificationService::class)->verify(
            new EmailCandidate($email, $firstName, $lastName),
            static::class,
        );

        // In `observe` the pipeline ran (and journaled) but the user sees
        // nothing: that invisibility is the mode's contract (FRD § 14.2).
        $this->suggestedEmail = EmailModule::mode()->showsSuggestions()
            && $result->status === EmailVerificationStatus::Risky
                ? $result->suggestion
                : null;
    }

    public function acceptSuggestedEmail(): void
    {
        if ($this->suggestedEmail === null) {
            return;
        }

        $field = $this->emailSuggestionField();
        $this->{$field} = $this->suggestedEmail;
        $this->suggestedEmail = null;
    }

    public function dismissSuggestedEmail(): void
    {
        $this->suggestedEmail = null;
    }

    /**
     * Which property `acceptSuggestedEmail()` rewrites. Override the
     * `$emailField` property when the form names it differently.
     */
    protected function emailSuggestionField(): string
    {
        return property_exists($this, 'emailField') ? (string) $this->emailField : 'email';
    }
}
