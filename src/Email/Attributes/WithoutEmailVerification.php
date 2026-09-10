<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Attributes;

use Attribute;

/**
 * Opts a whole form class out of automatic email verification (FR-AI-01) —
 * technical addresses, bulk imports validated elsewhere. An attribute
 * rather than a config entry so the opt-out sits ON the class, visible in
 * review, next to the comment explaining why.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class WithoutEmailVerification {}
