<?php

declare(strict_types=1);

namespace HernanCardoso\WorldCup2026Widget\Api;

use HernanCardoso\WorldCup2026Widget\Support\Logger;
use HernanCardoso\WorldCup2026Widget\Support\Settings;
use WP_Error;

final class FixturesSyncService
{
    public const CRON_HOOK = 'wc26_widget_sync_fixtures';
    private const CRON_SCHEDULE = 'wc26_fixed_refresh_interval';

    private Settings $settings;
    private ApiFootballClient $api;
    private FixturesRepository $repository;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->api = new ApiFootballClient($settings);
        $this->repository = new FixturesRepository($settings);
    }

    public function registerHooks(): void
    {
        add_filter('cron_schedules', [$this, 'addCronSchedules']);
        add_action('init', [$this, 'scheduleCron']);
        add_action(self::CRON_HOOK, [$this, 'sync']);
    }

    /**
     * @param array<string, mixed> $schedules
     * @return array<string, mixed>
     */
    public function addCronSchedules(array $schedules): array
    {
        $schedules[self::CRON_SCHEDULE] = [
            'interval' => SyncPolicy::refreshInterval(),
            'display' => sprintf(
                /* translators: %d: maximum theoretical requests per day. */
                __('Every minute (%d requests/day max)', WC26_WIDGET_TEXT_DOMAIN),
                SyncPolicy::requestsPerDayAtRefreshInterval()
            ),
        ];

        return $schedules;
    }

    public function scheduleCron(): void
    {
        $currentSchedule = wp_get_schedule(self::CRON_HOOK);

        if ($currentSchedule !== false && $currentSchedule !== self::CRON_SCHEDULE) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + SyncPolicy::refreshInterval(), self::CRON_SCHEDULE, self::CRON_HOOK);
        }
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function fixtures()
    {
        $cached = $this->repository->get();

        if ($this->repository->isFresh($cached)) {
            $cached['cache_hit'] = true;
            $cached['stale'] = false;

            return $cached;
        }

        if (!$this->repository->acquireLock()) {
            if ($cached !== null) {
                $cached['cache_hit'] = true;
                $cached['stale'] = true;

                return $cached;
            }

            return new WP_Error(
                'wc26_sync_locked',
                __('Fixture sync is already running.', WC26_WIDGET_TEXT_DOMAIN)
            );
        }

        try {
            $fresh = $this->fetchAndStore();

            if ($fresh instanceof WP_Error) {
                if ($cached !== null) {
                    $cached['cache_hit'] = true;
                    $cached['stale'] = true;
                    $cached['sync_error'] = $fresh->get_error_message();

                    return $cached;
                }

                return $fresh;
            }

            $fresh['cache_hit'] = false;
            $fresh['stale'] = false;

            return $fresh;
        } finally {
            $this->repository->releaseLock();
        }
    }

    public function sync(): void
    {
        if ($this->repository->isFresh($this->repository->get())) {
            return;
        }

        if (!$this->repository->acquireLock()) {
            return;
        }

        try {
            $result = $this->fetchAndStore();

            if ($result instanceof WP_Error) {
                Logger::warning('World Cup fixture sync failed', [
                    'error' => $result->get_error_message(),
                ]);
            }
        } finally {
            $this->repository->releaseLock();
        }
    }

    public function clear(): void
    {
        $this->repository->delete();
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function fetchAndStore()
    {
        $data = $this->api->fixturesForSeason();

        if ($data instanceof WP_Error) {
            $this->repository->saveError([
                'code' => $data->get_error_code(),
                'message' => $data->get_error_message(),
            ]);

            return $data;
        }

        return $this->repository->save($data);
    }
}
