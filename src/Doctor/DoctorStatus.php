<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor;

enum DoctorStatus: string
{
    case Ok = 'ok';

    case Info = 'info';

    case Warning = 'warning';

    case Error = 'error';

    /**
     * Only errors fail the run (exit code 1). Warnings inform, and a
     * baselined error is reported as a warning precisely so it stops
     * failing CI while remaining visible (FRD § 14.4).
     */
    public function fails(): bool
    {
        return $this === self::Error;
    }
}
