<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Verifiers;

use Arkhe\Main\Email\Contracts\EmailVerifier;
use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\DTOs\EmailVerificationResult;
use Arkhe\Main\Email\Enums\EmailVerificationStatus;

/**
 * Form rules the big providers enforce at signup, applied only when the
 * domain matches (FRD § 5.6). A local part Gmail would never have issued —
 * `ab@gmail.com`, two characters — cannot receive mail there, so this is
 * one of the rare certainties available without any network.
 *
 * The rules are configuration (`arkhe-email.verifiers.provider_rules`), not
 * code: when a provider changes its policy, the fix is a config line.
 */
final class ProviderLocalPartRulesVerifier implements EmailVerifier
{
    public function name(): string
    {
        return 'provider_rules';
    }

    public function verify(EmailCandidate $candidate): EmailVerificationResult
    {
        $rule = $this->ruleFor($candidate->domain);

        if ($rule === null) {
            return new EmailVerificationResult(EmailVerificationStatus::Unverifiable, $this->name(), reason: 'no rule for this domain');
        }

        $violation = $this->violation($candidate->localPart, $rule);

        if ($violation === null) {
            return new EmailVerificationResult(EmailVerificationStatus::Unverifiable, $this->name(), reason: 'local part respects the provider rules');
        }

        return new EmailVerificationResult(EmailVerificationStatus::Undeliverable, $this->name(), reason: $violation);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ruleFor(string $domain): ?array
    {
        foreach ((array) config('arkhe-email.verifiers.provider_rules', []) as $rule) {
            foreach ((array) ($rule['domains'] ?? []) as $pattern) {
                if ($domain === $pattern || (str_contains((string) $pattern, '*') && fnmatch((string) $pattern, $domain))) {
                    return (array) $rule;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function violation(string $localPart, array $rule): ?string
    {
        $length = strlen($localPart);
        $min = (int) ($rule['min'] ?? 1);
        $max = (int) ($rule['max'] ?? 64);

        if ($length < $min || $length > $max) {
            return "local part length {$length} outside {$min}-{$max}";
        }

        $characters = (string) ($rule['characters'] ?? '');

        if ($characters !== '' && ! preg_match('/^['.$characters.']+$/', $localPart)) {
            return "local part uses characters outside [{$characters}]";
        }

        if (($rule['no_edge_dot'] ?? false) && (str_starts_with($localPart, '.') || str_ends_with($localPart, '.'))) {
            return 'local part starts or ends with a dot';
        }

        if (($rule['no_consecutive_dots'] ?? false) && str_contains($localPart, '..')) {
            return 'local part contains consecutive dots';
        }

        if (($rule['starts_with_letter'] ?? false) && ! preg_match('/^[a-z]/', $localPart)) {
            return 'local part does not start with a letter';
        }

        return null;
    }
}
