<?php

declare(strict_types=1);

use Arkhe\Main\Email\Support\StringDistance;

// The OSA property this module depends on: an adjacent swap costs 1, where
// plain Levenshtein would charge 2 and push common typos past the tolerance.
it('counts an adjacent transposition as a single edit', function (): void {
    expect(StringDistance::damerauLevenshtein('jena', 'jean'))->toBe(1)
        ->and(StringDistance::damerauLevenshtein('jeandupnot', 'jeandupont'))->toBe(1);
});

it('measures classic edits', function (string $a, string $b, int $expected): void {
    expect(StringDistance::damerauLevenshtein($a, $b))->toBe($expected);
})->with([
    'identical' => ['jean', 'jean', 0],
    'substitution' => ['jaan', 'jean', 1],
    'insertion' => ['jdupnt', 'jdupont', 1],
    'deletion' => ['mariehelendurand', 'mariehelenedurand', 1],
    'empty against word' => ['', 'jean', 4],
    'word against empty' => ['jean', '', 4],
    'unrelated' => ['contact', 'jean', 6],
]);

it('is symmetric', function (): void {
    expect(StringDistance::damerauLevenshtein('dupont', 'jdupnt'))
        ->toBe(StringDistance::damerauLevenshtein('jdupnt', 'dupont'));
});
