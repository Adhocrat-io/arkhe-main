<?php

declare(strict_types=1);

use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\DTOs\EmailVerificationResult;
use Arkhe\Main\Email\Enums\EmailVerificationStatus;
use Arkhe\Main\Email\Verifiers\LocalPartNameVerifier;

function verifyName(string $email, ?string $first = null, ?string $last = null): EmailVerificationResult
{
    return (new LocalPartNameVerifier)->verify(new EmailCandidate($email, $first, $last));
}

// ─── The FRD § 5.7 reference table, verbatim ─────────────────────────────

it('corrects the reference cases', function (string $email, string $expected): void {
    $result = verifyName($email, 'Jean', 'Dupont');

    expect($result->status)->toBe(EmailVerificationStatus::Risky)
        ->and($result->suggestion)->toBe($expected);
})->with([
    'transposed first name' => ['jena@x.fr', 'jean@x.fr'],
    'numeric suffix survives' => ['jena.dupont75@x.fr', 'jean.dupont75@x.fr'],
    'compact first+last' => ['jeandupnot@x.fr', 'jeandupont@x.fr'],
    'initial + last name' => ['jdupnt@x.fr', 'jdupont@x.fr'],
]);

it('stays silent on the reference non-cases', function (string $email): void {
    expect(verifyName($email, 'Jean', 'Dupont')->status)->toBe(EmailVerificationStatus::Unverifiable);
})->with([
    'canonical first.last' => ['jean.dupont@x.fr'],
    'canonical initial+last' => ['jdupont@x.fr'],
    'unrelated mailbox' => ['contact@x.fr'],
]);

// Accents are transliterated before comparison AND before suggestion — a
// corrected address must never contain an accent the mail world would choke on.
it('handles accented compound names', function (): void {
    $result = verifyName('mariehelendurand@x.fr', 'Marie-Hélène', 'Durand');

    expect($result->status)->toBe(EmailVerificationStatus::Risky)
        ->and($result->suggestion)->toBe('mariehelenedurand@x.fr');
});

// ─── Guard rails ─────────────────────────────────────────────────────────

it('has no opinion without a name to compare against', function (): void {
    expect(verifyName('jena@x.fr')->status)->toBe(EmailVerificationStatus::Unverifiable);
});

it('has no opinion when both names are too short to carry signal', function (): void {
    expect(verifyName('alx@x.fr', 'Al', 'Bo')->status)->toBe(EmailVerificationStatus::Unverifiable);
});

// A short reference (< 4 letters) tolerates zero edits: near-misses of tiny
// names are indistinguishable from other words.
it('never suggests from a short reference', function (): void {
    expect(verifyName('lac@x.fr', 'Luc', 'Dupont')->status)->toBe(EmailVerificationStatus::Unverifiable);
});

it('never returns undeliverable', function (): void {
    // Even garbage input: this driver suggests or abstains, nothing else.
    expect(verifyName('zzzzzz@x.fr', 'Jean', 'Dupont')->status)->toBe(EmailVerificationStatus::Unverifiable);
});

// Segment pass keeps the user's separators and everything it did not correct.
it('preserves untouched segments and separators', function (): void {
    $result = verifyName('jena_dupont@x.fr', 'Jean', 'Dupont');

    expect($result->suggestion)->toBe('jean_dupont@x.fr');
});

it('does not resuggest the input itself', function (): void {
    // `jean75` compacts to a canonical form plus suffix — nothing to fix.
    expect(verifyName('jean75@x.fr', 'Jean', 'Dupont')->status)->toBe(EmailVerificationStatus::Unverifiable);
});
