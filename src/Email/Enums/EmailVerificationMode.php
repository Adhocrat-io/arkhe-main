<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Enums;

/**
 * Rollout mode of the module on a given application (FRD § 14.2).
 *
 * The three modes exist so an existing application can adopt the module
 * without ever surprising its users: `observe` journals what *would* happen,
 * `suggest` shows corrections without blocking, `enforce` finally refuses
 * undeliverable addresses. The decision to move up is taken by a human on the
 * basis of `arkhe:email:report`, never by code.
 */
enum EmailVerificationMode: string
{
    case Observe = 'observe';

    case Suggest = 'suggest';

    case Enforce = 'enforce';

    /**
     * An unrecognised configured value resolves to `Observe`: the mode
     * mistyped must degrade towards the least intrusive behaviour, not
     * towards blocking users on a typo in a config file.
     */
    public static function fromConfig(mixed $value): self
    {
        return is_string($value)
            ? (self::tryFrom(strtolower(trim($value))) ?? self::Observe)
            : self::Observe;
    }

    public function showsSuggestions(): bool
    {
        return $this !== self::Observe;
    }

    public function blocksUndeliverable(): bool
    {
        return $this === self::Enforce;
    }
}
