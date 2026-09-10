<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Contracts;

use Arkhe\Main\Email\DTOs\EmailCandidate;
use Arkhe\Main\Email\DTOs\EmailVerificationResult;
use Arkhe\Main\Email\Services\EmailVerificationService;

/**
 * A driver asks the candidate exactly one question and answers with a status.
 *
 * Drivers are composed by {@see EmailVerificationService}
 * in the order of `config('arkhe-email.verifiers.pipeline')`, cheapest first.
 * A driver does no network unless its documentation says so, and is then
 * disableable by configuration (FRD § 4).
 */
interface EmailVerifier
{
    /**
     * Stable identifier used in results, logs and reports.
     */
    public function name(): string;

    public function verify(EmailCandidate $candidate): EmailVerificationResult;
}
