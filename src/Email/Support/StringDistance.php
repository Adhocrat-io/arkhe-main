<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Support;

use Arkhe\Main\Email\Verifiers\LocalPartNameVerifier;

/**
 * Damerau-Levenshtein distance, OSA variant: an adjacent transposition
 * (`jena` → `jean`) costs 1 instead of the 2 plain Levenshtein charges.
 *
 * The transposition matters because it IS the typo this module hunts:
 * swapped neighbouring letters are the most common slip in a hand-typed
 * name, and counting them as two edits would push them past the tolerance
 * for short names — exactly the ones users type into email fields.
 *
 * Byte-oriented on purpose: every caller normalises to lowercase ASCII first
 * ({@see LocalPartNameVerifier}), so multi-byte
 * handling here would be dead code with a real CPU cost in a function that
 * runs inside a validation pipeline.
 */
final class StringDistance
{
    public static function damerauLevenshtein(string $a, string $b): int
    {
        if ($a === $b) {
            return 0;
        }

        $lenA = strlen($a);
        $lenB = strlen($b);

        if ($lenA === 0) {
            return $lenB;
        }

        if ($lenB === 0) {
            return $lenA;
        }

        /** @var array<int, array<int, int>> $d */
        $d = [];

        for ($i = 0; $i <= $lenA; $i++) {
            $d[$i][0] = $i;
        }

        for ($j = 0; $j <= $lenB; $j++) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $lenA; $i++) {
            for ($j = 1; $j <= $lenB; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;

                $d[$i][$j] = min(
                    $d[$i - 1][$j] + 1,        // deletion
                    $d[$i][$j - 1] + 1,        // insertion
                    $d[$i - 1][$j - 1] + $cost // substitution
                );

                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1); // transposition
                }
            }
        }

        return $d[$lenA][$lenB];
    }
}
