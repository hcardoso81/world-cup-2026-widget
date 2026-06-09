<?php

declare(strict_types=1);

namespace HernanCardoso\WorldCup2026Widget\Api;

use HernanCardoso\WorldCup2026Widget\Support\Settings;

final class FixturesRepository
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(): ?array
    {
        $stored = get_option($this->optionName(), null);

        return is_array($stored) ? $stored : null;
    }

    public function isFresh(?array $cached): bool
    {
        if ($cached === null) {
            return false;
        }

        return isset($cached['expires_at']) && (int) $cached['expires_at'] > time();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data): array
    {
        $ttl = SyncPolicy::refreshInterval();
        $stored = [
            'fixtures' => isset($data['fixtures']) && is_array($data['fixtures']) ? $data['fixtures'] : [],
            'fetched_at' => isset($data['fetched_at']) ? absint($data['fetched_at']) : time(),
            'expires_at' => time() + $ttl,
            'cache_ttl' => $ttl,
            'source' => !empty($data['mock']) ? 'mock' : 'api-football',
            'mock' => !empty($data['mock']),
            'errors' => [],
        ];

        update_option($this->optionName(), $stored, false);

        return $stored;
    }

    /**
     * @param array<string, mixed> $error
     */
    public function saveError(array $error): void
    {
        $cached = $this->get();

        if ($cached === null) {
            $cached = [
                'fixtures' => [],
                'fetched_at' => 0,
                'expires_at' => 0,
                'cache_ttl' => 0,
                'source' => 'none',
                'mock' => false,
            ];
        }

        $cached['errors'][] = array_merge($error, ['time' => time()]);
        $cached['errors'] = array_slice((array) $cached['errors'], -5);

        update_option($this->optionName(), $cached, false);
    }

    public function delete(): void
    {
        delete_option($this->optionName());
        delete_transient($this->lockName());
    }

    public function acquireLock(): bool
    {
        if (get_transient($this->lockName()) !== false) {
            return false;
        }

        set_transient($this->lockName(), time(), SyncPolicy::refreshInterval());

        return true;
    }

    public function releaseLock(): void
    {
        delete_transient($this->lockName());
    }

    private function optionName(): string
    {
        return self::optionNameFor($this->settings->leagueId(), $this->settings->season());
    }

    private function lockName(): string
    {
        return self::lockNameFor($this->settings->leagueId(), $this->settings->season());
    }

    public static function optionNameFor(int $leagueId, int $season): string
    {
        return sprintf('wc26_fixtures_cache_%d_%d', $leagueId, $season);
    }

    public static function lockNameFor(int $leagueId, int $season): string
    {
        return sprintf(
            'wc26_fixtures_sync_lock_%d_%d',
            $leagueId,
            $season
        );
    }
}
