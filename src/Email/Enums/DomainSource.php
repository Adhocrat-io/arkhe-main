<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Enums;

/**
 * Where a domain entry came from — the merge rules of import and learning
 * key off this (FRD § 6.4, § 7.3): `manual` is never touched by anything
 * automated, `learned` is never contradicted by a file (local field data
 * outranks a central list), `seed` and `imported` are freely updatable.
 */
enum DomainSource: string
{
    case Seed = 'seed';

    case Imported = 'imported';

    case Learned = 'learned';

    case Manual = 'manual';
}
