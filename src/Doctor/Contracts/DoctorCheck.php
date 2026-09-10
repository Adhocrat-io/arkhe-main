<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Contracts;

use Arkhe\Main\Doctor\DoctorResult;

/**
 * One diagnostic question about the application's wiring (FRD § 13.3).
 * Modules contribute implementations by tagging them `arkhe.doctor.checks`
 * in their service provider; `arkhe:doctor` runs whatever is tagged.
 */
interface DoctorCheck
{
    /**
     * Stable identifier, `module.subject` (ex: `email.migrations`).
     */
    public function id(): string;

    /**
     * The module this check belongs to — what `--module=` filters on.
     */
    public function module(): string;

    public function run(): DoctorResult;
}
