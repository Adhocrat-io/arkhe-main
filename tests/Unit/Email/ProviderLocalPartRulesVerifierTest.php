<?php

declare(strict_types=1);

use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\Enums\EmailVerificationStatus;
use Arkhe\Main\Email\Verifiers\ProviderLocalPartRulesVerifier;

function verifyProviderRules(string $email): EmailVerificationStatus
{
    return (new ProviderLocalPartRulesVerifier)->verify(new EmailCandidate($email))->status;
}

// Certainties without network: local parts the provider would never have
// issued (FRD § 5.6). `ab@gmail.com` is an acceptance criterion (§ 11).
it('refuses local parts the provider could not have issued', function (string $email): void {
    expect(verifyProviderRules($email))->toBe(EmailVerificationStatus::Undeliverable);
})->with([
    'gmail below 6 chars' => ['ab@gmail.com'],
    'gmail forbidden underscore' => ['jean_dupont@gmail.com'],
    'gmail leading dot' => ['.jeandupont@gmail.com'],
    'gmail consecutive dots' => ['jean..dupont@gmail.com'],
    'hotmail leading dot via wildcard' => ['.jean@hotmail.fr'],
    'yahoo starting with a digit' => ['1jean@yahoo.fr'],
    'yahoo below 4 chars' => ['jea@yahoo.com'],
]);

it('stays silent on compliant local parts', function (string $email): void {
    expect(verifyProviderRules($email))->toBe(EmailVerificationStatus::Unverifiable);
})->with([
    'gmail' => ['jean.dupont@gmail.com'],
    'hotmail wildcard tld' => ['jean-dupont@hotmail.be'],
    'yahoo' => ['jean@yahoo.fr'],
    'orange' => ['jean.dupont@orange.fr'],
]);

// A rule violation is only meaningful on the provider it belongs to — a
// 2-character local part on an unknown domain proves nothing.
it('has no opinion on domains without a rule', function (): void {
    expect(verifyProviderRules('ab@une-mairie.fr'))->toBe(EmailVerificationStatus::Unverifiable);
});
