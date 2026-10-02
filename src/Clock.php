<?php

declare(strict_types=1);

namespace Chess;

use DateTimeImmutable;
use DateTimeZone;

/** Single source of "now" for the app; always UTC so stored times are comparable. */
final class Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
