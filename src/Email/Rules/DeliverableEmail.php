<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Rules;

use Arkhe\Main\Email\Concerns\VerifiesEmailAttributes;
use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\Enums\EmailVerificationMode;
use Arkhe\Main\Email\Services\EmailVerificationService;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fails ONLY on `Undeliverable` — `Risky`, `Deliverable` and `Unverifiable`
 * all pass (FRD § 5.9). Blocking is reserved for certainty; a suggestion is
 * an interface concern, not a validation error.
 *
 * The optional mode lets {@see VerifiesEmailAttributes}
 * inject this rule during the `observe` and `suggest` rollout phases, where
 * the pipeline must run (for the journal) without failing anyone. Used by
 * hand, the rule blocks: a developer who writes it in a rules() array means
 * it.
 */
final class DeliverableEmail implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(
        private readonly ?string $firstNameField = null,
        private readonly ?string $lastNameField = null,
        private readonly ?EmailVerificationMode $mode = null,
        private readonly ?string $context = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return; // `required` / `email` own that verdict
        }

        $candidate = new EmailCandidate(
            email: $value,
            firstName: $this->fieldValue($this->firstNameField, 'first'),
            lastName: $this->fieldValue($this->lastNameField, 'last'),
        );

        $result = app(EmailVerificationService::class)->verify($candidate, $this->context);

        $mode = $this->mode ?? EmailVerificationMode::Enforce;

        if (! $result->status->blocks() || ! $mode->blocksUndeliverable()) {
            return;
        }

        $message = (string) __('arkhe::arkhe.email.undeliverable');

        if ($result->suggestion !== null) {
            $message .= ' '.__('arkhe::arkhe.email.did_you_mean', ['suggestion' => $result->suggestion]);
        }

        $fail($message);
    }

    /**
     * An explicit field name wins; left null, the conventional names from
     * `arkhe-email.name_fields` are probed so the rule works out of the box
     * on forms that follow them (FRD FR-AI-01).
     */
    private function fieldValue(?string $field, string $kind): ?string
    {
        $candidates = $field !== null
            ? [$field]
            : (array) config("arkhe-email.name_fields.{$kind}", []);

        foreach ($candidates as $name) {
            $value = $this->data[$name] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }
}
