<?php

declare(strict_types=1);

namespace HernanCardoso\WorldCup2026Widget\Api;

final class SyncPolicy
{
    private const REFRESH_INTERVAL = MINUTE_IN_SECONDS;

    public static function refreshInterval(): int
    {
        return self::REFRESH_INTERVAL;
    }

    public static function requestsPerDayAtRefreshInterval(): int
    {
        return (int) floor(DAY_IN_SECONDS / self::refreshInterval());
    }
}
