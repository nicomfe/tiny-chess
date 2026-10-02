<?php

declare(strict_types=1);

namespace Chess\Game;

/** The two time banks a challenge may be created with, in seconds per player. */
enum TimeControl: int
{
    case FiveMinutes = 300;
    case TenMinutes = 600;

    public static function fromMinutes(string $minutes): ?self
    {
        return match ($minutes) {
            '5' => self::FiveMinutes,
            '10' => self::TenMinutes,
            default => null,
        };
    }

    public function minutes(): int
    {
        return intdiv($this->value, 60);
    }

    public function label(): string
    {
        return $this->minutes() . ' minutes';
    }
}
