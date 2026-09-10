<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\DTOs;

use Arkhe\Main\Email\Enums\EmailVerificationStatus;

final readonly class EmailVerificationResult
{
    public function __construct(
        public EmailVerificationStatus $status,
        public string $driver,
        /** Full corrected address, only ever set with a `Risky` status. */
        public ?string $suggestion = null,
        /** Technical wording for logs and debugging — never shown to the user. */
        public ?string $reason = null,
    ) {}
}
