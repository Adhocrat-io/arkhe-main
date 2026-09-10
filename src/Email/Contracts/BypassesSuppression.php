<?php

declare(strict_types=1);

namespace Arkhe\Main\Email\Contracts;

/**
 * Marker for Mailables that must leave even towards a suppressed address —
 * tax receipts, legal notices, anything a regulation obliges (FRD § 8.3).
 *
 * An interface rather than a config list on purpose: the exemption sits ON
 * the Mailable, visible in review, and survives class renames that a
 * config string would silently miss.
 */
interface BypassesSuppression {}
