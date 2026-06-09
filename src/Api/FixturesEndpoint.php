<?php

declare(strict_types=1);

namespace HernanCardoso\WorldCup2026Widget\Api;

use HernanCardoso\WorldCup2026Widget\Support\Settings;
use WP_Error;
use WP_REST_Request;

final class FixturesEndpoint
{
    public const NAMESPACE = 'wc26/v1';
    public const ROUTE = '/fixtures';

    private Settings $settings;
    private FixturesSyncService $sync;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->sync = new FixturesSyncService($settings);
    }

    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, self::ROUTE, [
            'methods' => 'GET',
            'callback' => [$this, 'fixtures'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function fixtures(WP_REST_Request $request)
    {
        if (!$this->settings->isFrontendVisible()) {
            return [
                'visible' => false,
                'fixtures' => [],
                'fixtures_by_date' => [],
            ];
        }

        $data = $this->sync->fixtures();

        if ($data instanceof WP_Error) {
            return $data;
        }

        $fixtures = isset($data['fixtures']) && is_array($data['fixtures']) ? $data['fixtures'] : [];

        return [
            'visible' => true,
            'mock' => !empty($data['mock']),
            'source' => !empty($data['mock']) ? 'mock' : 'api-football',
            'scope' => isset($data['scope']) ? sanitize_key((string) $data['scope']) : 'season',
            'active_date' => $this->settings->shortcodeDate(),
            'fixtures' => $fixtures,
            'fixtures_by_date' => $this->groupFixturesByDate($fixtures),
            'fetched_at' => isset($data['fetched_at']) ? absint($data['fetched_at']) : time(),
            'expires_at' => isset($data['expires_at']) ? absint($data['expires_at']) : 0,
            'cache_hit' => !empty($data['cache_hit']),
            'stale' => !empty($data['stale']),
        ];
    }

    /**
     * @param array<int, mixed> $fixtures
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupFixturesByDate(array $fixtures): array
    {
        $grouped = [];

        foreach ($fixtures as $fixture) {
            if (!is_array($fixture)) {
                continue;
            }

            $date = $this->fixtureDate($fixture);

            if ($date === '') {
                continue;
            }

            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }

            $grouped[$date][] = $fixture;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @param array<string, mixed> $fixture
     */
    private function fixtureDate(array $fixture): string
    {
        $date = (string) ($fixture['fixture']['date'] ?? '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date) !== 1) {
            return '';
        }

        return substr($date, 0, 10);
    }
}
