<?php

namespace App\Enums;

enum MatchPeriod: string
{
    case Pre = 'pre';
    case FirstHalf = 'first_half';
    case HalfTime = 'half_time';
    case SecondHalf = 'second_half';
    case ExtraTimeFirst = 'extra_time_first';
    case ExtraTimeSecond = 'extra_time_second';
    case Penalties = 'penalties';
    case FullTime = 'full_time';

    /**
     * Windows where the score/minute is most likely to change decisively,
     * worth spending scarce API-Football quota on.
     */
    public function isCriticalWindow(?int $minute): bool
    {
        return match ($this) {
            self::FirstHalf, self::SecondHalf => $minute !== null && $minute >= 35,
            self::ExtraTimeFirst, self::ExtraTimeSecond, self::Penalties => true,
            default => false,
        };
    }
}
