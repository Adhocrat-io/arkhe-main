<?php

declare(strict_types=1);

namespace Arkhe\Main\Doctor\Checks\Email;

use Arkhe\Main\Doctor\Contracts\DoctorCheck;
use Arkhe\Main\Doctor\DoctorResult;
use Arkhe\Main\Doctor\DoctorStatus;
use Arkhe\Main\Email\Support\EmailModule;

/**
 * Every email check self-skips while the module is disabled: the doctor
 * runs the same in every state, and "module off" is a healthy answer, not
 * a failure — that keeps `arkhe:doctor` callable in CI before, during and
 * after activation.
 */
abstract class EmailCheck implements DoctorCheck
{
    public function module(): string
    {
        return 'email';
    }

    public function run(): DoctorResult
    {
        if (! EmailModule::enabled()) {
            return new DoctorResult(DoctorStatus::Ok, 'Module e-mail inactif — vérification sans objet.');
        }

        return $this->execute();
    }

    abstract protected function execute(): DoctorResult;

    protected function ok(string $message): DoctorResult
    {
        return new DoctorResult(DoctorStatus::Ok, $message);
    }

    protected function info(string $message, ?string $fix = null): DoctorResult
    {
        return new DoctorResult(DoctorStatus::Info, $message, $fix);
    }

    /**
     * @param  list<string>  $targets
     */
    protected function warning(string $message, ?string $fix = null, array $targets = []): DoctorResult
    {
        return new DoctorResult(DoctorStatus::Warning, $message, $fix, $targets);
    }

    /**
     * @param  list<string>  $targets
     */
    protected function error(string $message, ?string $fix = null, array $targets = []): DoctorResult
    {
        return new DoctorResult(DoctorStatus::Error, $message, $fix, $targets);
    }
}
