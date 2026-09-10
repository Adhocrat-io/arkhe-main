<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Enums;

/**
 * How the receiving side refused (FRD § 8.1). A `complaint` (spam report)
 * is treated with hard severity: keeping on mailing someone who flagged us
 * costs reputation faster than any invalid address.
 */
enum BounceType: string
{
    case Hard = 'hard';

    case Soft = 'soft';

    case Complaint = 'complaint';

    /**
     * Does one occurrence suffice to suppress the address?
     */
    public function suppressesImmediately(): bool
    {
        return $this !== self::Soft;
    }
}
