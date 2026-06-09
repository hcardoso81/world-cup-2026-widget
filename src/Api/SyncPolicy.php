<?php

declare(strict_types=1);

namespace HernanCardoso\WorldCup2026Widget\Api;

final class SyncPolicy
{
    private const LIVE_STATUSES = ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'SUSP', 'INT', 'LIVE'];
    private const HIGH_FREQUENCY_BEFORE = 2 * HOUR_IN_SECONDS;
    private const HIGH_FREQUENCY_AFTER_LAST_KICKOFF = 3 * HOUR_IN_SECONDS;
    private const MATCHDAY_WARMUP = 6 * HOUR_IN_SECONDS;

    /**
     * @param array<int, mixed> $fixtures
     */
    public static function ttlForFixtures(array $fixtures, ?int $now = null): int
    {
        $now = $now ?? time();

        if ($fixtures === []) {
            return 6 * HOUR_IN_SECONDS;
        }

        if (self::hasLiveFixture($fixtures)) {
            return MINUTE_IN_SECONDS;
        }

        $timestamps = self::fixtureTimestamps($fixtures);

        if ($timestamps === []) {
            return 15 * MINUTE_IN_SECONDS;
        }

        $firstKickoff = min($timestamps);
        $lastKickoff = max($timestamps);
        $highFrequencyStarts = $firstKickoff - self::HIGH_FREQUENCY_BEFORE;
        $highFrequencyEnds = $lastKickoff + self::HIGH_FREQUENCY_AFTER_LAST_KICKOFF;

        if ($now >= $highFrequencyStarts && $now <= $highFrequencyEnds) {
            return MINUTE_IN_SECONDS;
        }

        if ($now >= ($firstKickoff - self::MATCHDAY_WARMUP) && $now < $highFrequencyStarts) {
            return 15 * MINUTE_IN_SECONDS;
        }

        if ($now > $highFrequencyEnds && $now <= ($lastKickoff + 6 * HOUR_IN_SECONDS)) {
            return 15 * MINUTE_IN_SECONDS;
        }

        return 6 * HOUR_IN_SECONDS;
    }

    /**
     * @param array<int, mixed> $fixtures
     */
    private static function hasLiveFixture(array $fixtures): bool
    {
        foreach ($fixtures as $fixture) {
            if (!is_array($fixture)) {
                continue;
            }

            $short = (string) ($fixture['fixture']['status']['short'] ?? '');

            if (in_array($short, self::LIVE_STATUSES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $fixtures
     * @return array<int, int>
     */
    private static function fixtureTimestamps(array $fixtures): array
    {
        $timestamps = [];

        foreach ($fixtures as $fixture) {
            if (!is_array($fixture)) {
                continue;
            }

            $timestamp = isset($fixture['fixture']['timestamp']) ? absint($fixture['fixture']['timestamp']) : 0;

            if ($timestamp <= 0 && isset($fixture['fixture']['date'])) {
                $parsed = strtotime((string) $fixture['fixture']['date']);
                $timestamp = $parsed === false ? 0 : $parsed;
            }

            if ($timestamp > 0) {
                $timestamps[] = $timestamp;
            }
        }

        return $timestamps;
    }
}
