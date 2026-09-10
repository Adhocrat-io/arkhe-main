<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Concerns;

use Arkhe\Main\Email\Attributes\WithoutEmailVerification;
use Arkhe\Main\Email\Enums\EmailVerificationMode;
use Arkhe\Main\Email\Rules\DeliverableEmail;
use Arkhe\Main\Email\Support\EmailModule;
use Illuminate\Validation\Rules\Email as EmailRuleObject;
use ReflectionClass;

/**
 * The structural layer of the AI integration (FRD § 13.2): applied to the
 * base FormRequest and the base Livewire Form, so every form in every
 * Arkhè application verifies its email fields WITHOUT any code in the
 * form itself — an assistant that inherits the right base class cannot
 * forget to wire the module, because there is nothing to wire.
 *
 * After the form's own rules() resolve, every attribute whose rules ask
 * for `email` receives `DeliverableEmail` at the end of its list (preceded
 * by `bail` if absent), carrying the effective mode. Inert while the
 * module is disabled: rules pass through untouched, which is the NFR-09
 * contract.
 *
 * Escape hatches, all explicit and visible in review:
 *  - `#[WithoutEmailVerification]` on the class, or `$verifyEmails = false`;
 *  - `protected string $emailVerificationMode = 'enforce'` hardens one form
 *    while the application stays in observe (§ 14.2);
 *  - `protected array $emailNameFields = ['email' => ['prenom', 'nom']]`
 *    when the sibling name fields do not follow the conventions.
 */
trait VerifiesEmailAttributes
{
    /** @var array<class-string, bool> */
    private static array $optOutCache = [];

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function injectEmailVerification(array $rules): array
    {
        if (! EmailModule::enabled() || $this->emailVerificationDisabled()) {
            return $rules;
        }

        $mode = $this->effectiveEmailVerificationMode();

        foreach ($rules as $attribute => $attributeRules) {
            $list = $this->normalizeRuleList($attributeRules);

            if (! $this->rulesAskForEmail($list) || $this->alreadyCarriesDeliverable($list)) {
                continue;
            }

            if (! in_array('bail', $list, true)) {
                array_unshift($list, 'bail');
            }

            [$first, $last] = $this->emailNameFieldsFor((string) $attribute);

            $list[] = new DeliverableEmail(
                firstNameField: $first,
                lastNameField: $last,
                mode: $mode,
                context: static::class,
            );

            $rules[$attribute] = $list;
        }

        return $rules;
    }

    /**
     * @return list<mixed>
     */
    private function normalizeRuleList(mixed $attributeRules): array
    {
        if (is_string($attributeRules)) {
            return explode('|', $attributeRules);
        }

        return is_array($attributeRules) ? array_values($attributeRules) : [$attributeRules];
    }

    /**
     * @param  list<mixed>  $list
     */
    private function rulesAskForEmail(array $list): bool
    {
        foreach ($list as $rule) {
            if ($rule instanceof EmailRuleObject) {
                return true;
            }

            if (is_string($rule) && ($rule === 'email' || str_starts_with($rule, 'email:'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The lot-1-before-lot-0 case (§ 14.5): a rule already added by hand is
     * respected, never duplicated.
     *
     * @param  list<mixed>  $list
     */
    private function alreadyCarriesDeliverable(array $list): bool
    {
        foreach ($list as $rule) {
            if ($rule instanceof DeliverableEmail) {
                return true;
            }
        }

        return false;
    }

    private function emailVerificationDisabled(): bool
    {
        if (property_exists($this, 'verifyEmails') && $this->verifyEmails === false) {
            return true;
        }

        return self::$optOutCache[static::class] ??= (new ReflectionClass(static::class))
            ->getAttributes(WithoutEmailVerification::class) !== [];
    }

    /**
     * A class-level mode overrides the application's — how a brand-new
     * signup form runs in `enforce` while the legacy forms observe.
     */
    private function effectiveEmailVerificationMode(): EmailVerificationMode
    {
        if (property_exists($this, 'emailVerificationMode') && is_string($this->emailVerificationMode)) {
            $override = EmailVerificationMode::tryFrom(strtolower($this->emailVerificationMode));

            if ($override !== null) {
                return $override;
            }
        }

        return EmailModule::mode();
    }

    /**
     * @return array{?string, ?string}
     */
    private function emailNameFieldsFor(string $attribute): array
    {
        if (property_exists($this, 'emailNameFields') && is_array($this->emailNameFields)) {
            $fields = $this->emailNameFields[$attribute] ?? null;

            if (is_array($fields)) {
                return [
                    is_string($fields[0] ?? null) ? $fields[0] : null,
                    is_string($fields[1] ?? null) ? $fields[1] : null,
                ];
            }
        }

        // Null lets DeliverableEmail probe the conventional names from
        // `arkhe-email.name_fields` in the same data set.
        return [null, null];
    }
}
