<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Verifiers;

use Arkhe\Main\Email\Contracts\EmailVerifier;
use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\DTOs\EmailVerificationResult;
use Arkhe\Main\Email\Enums\EmailVerificationStatus;
use Arkhe\Main\Email\Support\StringDistance;
use Illuminate\Support\Str;

/**
 * Compares the local part to the first/last name the form collected, and
 * suggests a fix when one is a near-miss of the other (`jena` → `jean`).
 *
 * This driver can only ever answer `Risky` or `Unverifiable` — a local part
 * that does not look like the user's name is not evidence of anything
 * (`contact@`, shared mailboxes, aliases). FRD § 5.7.
 */
final class LocalPartNameVerifier implements EmailVerifier
{
    private const SEPARATORS = '/([._-])/';

    public function name(): string
    {
        return 'local_part_name';
    }

    public function verify(EmailCandidate $candidate): EmailVerificationResult
    {
        if (! $candidate->hasNames()) {
            return $this->unverifiable('no name provided');
        }

        $first = $this->normalizeName($candidate->firstName);
        $last = $this->normalizeName($candidate->lastName);

        // Two very short references would make every 3-letter local part
        // "close" to something; below 3 letters each, the comparison carries
        // no signal at all.
        if (strlen($first) < 3 && strlen($last) < 3) {
            return $this->unverifiable('names too short to compare');
        }

        [$base, $suffix] = $this->splitNumericSuffix($candidate->localPart);
        $compact = (string) preg_replace(self::SEPARATORS, '', $base);
        $forms = $this->canonicalForms($first, $last);

        // A local part that already IS a canonical form needs no help; asking
        // "did you mean…?" about an exact match would only erode trust in the
        // suggestions that matter.
        if (in_array($compact, $forms, true)) {
            return $this->unverifiable('matches a canonical form');
        }

        $corrected = $this->correctSegments($base, $first, $last)
            ?? $this->correctWhole($compact, $forms);

        if ($corrected === null) {
            return $this->unverifiable('no close canonical form');
        }

        return new EmailVerificationResult(
            status: EmailVerificationStatus::Risky,
            driver: $this->name(),
            suggestion: $candidate->withLocalPart($corrected.$suffix),
            reason: 'local part is a near-miss of the provided name',
        );
    }

    /**
     * Pass 1 — each `.`/`_`/`-` delimited segment is compared to the first
     * and last name; a near-miss segment is replaced, everything else
     * (separators included) is kept as typed. Returns null when no segment
     * needed correcting.
     */
    private function correctSegments(string $base, string $first, string $last): ?string
    {
        $parts = preg_split(self::SEPARATORS, $base, flags: PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return null;
        }

        $changed = false;

        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                continue; // separator captured by the split
            }

            $replacement = $this->closestName($part, $first, $last);

            if ($replacement !== null) {
                $parts[$i] = $replacement;
                $changed = true;
            }
        }

        return $changed ? implode('', $parts) : null;
    }

    /**
     * Pass 2 — the compact local part against the compact canonical forms.
     * Runs only when pass 1 found nothing: a segment-level fix is more
     * faithful to what the user typed (it preserves their separators), so it
     * always wins over a whole-string rewrite.
     */
    private function correctWhole(string $compact, array $forms): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($forms as $form) {
            $distance = StringDistance::damerauLevenshtein($compact, $form);

            if ($distance === 0 || $distance > $this->tolerance(strlen($form))) {
                continue;
            }

            // Closest first; on equal distance the LONGEST form wins — the
            // more of the name a form explains, the less likely the match is
            // an accident (FRD § 5.7).
            if ($distance < $bestDistance || ($distance === $bestDistance && strlen($form) > strlen((string) $best))) {
                $best = $form;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * The nearest of the two names within tolerance, or null. Distance 0 is
     * excluded on purpose: an exact match needs no correction, and replacing
     * it would suggest the user's own input back to them.
     */
    private function closestName(string $segment, string $first, string $last): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;

        foreach ([$first, $last] as $reference) {
            if ($reference === '') {
                continue;
            }

            $distance = StringDistance::damerauLevenshtein($segment, $reference);

            if ($distance === 0 || $distance > $this->tolerance(strlen($reference))) {
                continue;
            }

            if ($distance < $bestDistance) {
                $best = $reference;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * The six canonical shapes a name takes in a local part (FRD § 5.7),
     * compared and suggested in compact form: `prenom`, `nom`, `prenomnom`,
     * `nomprenom`, `pnom`, `prenomn`.
     *
     * @return list<string>
     */
    private function canonicalForms(string $first, string $last): array
    {
        $forms = [];

        if ($first !== '') {
            $forms[] = $first;
        }

        if ($last !== '') {
            $forms[] = $last;
        }

        if ($first !== '' && $last !== '') {
            $forms[] = $first.$last;
            $forms[] = $last.$first;
            $forms[] = $first[0].$last;
            $forms[] = $first.$last[0];
        }

        return array_values(array_unique($forms));
    }

    /**
     * Edit tolerance grows with the reference length: a 3-letter name must
     * match exactly (one edit rewrites a third of it), a long compound name
     * affords two slips (FRD § 5.7).
     */
    private function tolerance(int $referenceLength): int
    {
        return match (true) {
            $referenceLength < 4 => 0,
            $referenceLength <= 7 => 1,
            default => 2,
        };
    }

    /**
     * `jean.dupont75` → [`jean.dupont`, `75`]. The suffix is the user's own
     * disambiguator (birth year, lucky number) — it survives into the
     * suggestion untouched.
     *
     * @return array{string, string}
     */
    private function splitNumericSuffix(string $localPart): array
    {
        if (preg_match('/^(.*?)(\d+)$/', $localPart, $matches) === 1 && $matches[1] !== '') {
            return [$matches[1], $matches[2]];
        }

        return [$localPart, ''];
    }

    /**
     * Lowercase, transliterated to ASCII, stripped of everything that is not
     * a letter: `Marie-Hélène` → `mariehelene`. The comparison space and the
     * suggestion space are the same, so a corrected local part never
     * reintroduces accents into an email address.
     */
    private function normalizeName(?string $name): string
    {
        return (string) preg_replace('/[^a-z]/', '', mb_strtolower(Str::ascii((string) $name)));
    }

    private function unverifiable(string $reason): EmailVerificationResult
    {
        return new EmailVerificationResult(EmailVerificationStatus::Unverifiable, $this->name(), reason: $reason);
    }
}
