<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor;

/**
 * What a check found. `fix` is the line the assistant (or the human)
 * reads to repair: one concrete action, no jargon, one line (FR-AI-06).
 * `targets` name the offending files/tables so the baseline can tell an
 * old, tolerated failure from a new one (§ 14.4).
 */
final readonly class DoctorResult
{
    /**
     * @param  list<string>  $targets
     */
    public function __construct(
        public DoctorStatus $status,
        public string $message,
        public ?string $fix = null,
        public array $targets = [],
    ) {}
}
