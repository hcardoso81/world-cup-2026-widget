<?php

declare(strict_types=1);

namespace HernanCardoso\WorldCup2026Widget\Api;

use HernanCardoso\WorldCup2026Widget\Support\Logger;
use HernanCardoso\WorldCup2026Widget\Support\Settings;
use WP_Error;

final class ApiFootballClient
{
    private const BASE_URL = 'https://v3.football.api-sports.io';
    private const LIVE_STATUSES = ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'SUSP', 'INT', 'LIVE'];

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function fixturesByDate(string $date)
    {
        $date = $this->settings->sanitizeDate($date);

        if ($this->settings->shouldUseMockFixtures() || strtolower($this->settings->apiKey()) === 'mock') {
            return $this->mockFixturesByDate($date);
        }

        $cacheKey = $this->cacheKey($date);
        $cached = get_transient($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        if ($this->settings->apiKey() === '') {
            return new WP_Error(
                'wc26_api_missing_key',
                __('API-Football key is not configured.', WC26_WIDGET_TEXT_DOMAIN)
            );
        }

        $url = $this->fixturesUrl($date);

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'x-apisports-key' => $this->settings->apiKey(),
            ],
        ]);

        if (is_wp_error($response)) {
            Logger::warning('API-Football request failed', [
                'date' => $date,
                'error' => $response->get_error_message(),
            ]);

            return $response;
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $payload = json_decode($body, true);

        if ($statusCode < 200 || $statusCode >= 300 || !is_array($payload)) {
            Logger::warning('API-Football returned an invalid response', [
                'date' => $date,
                'status_code' => $statusCode,
            ]);

            return new WP_Error(
                'wc26_api_invalid_response',
                __('API-Football returned an invalid response.', WC26_WIDGET_TEXT_DOMAIN)
            );
        }

        $apiErrors = $payload['errors'] ?? [];
        if (is_array($apiErrors) && $apiErrors !== []) {
            Logger::warning('API-Football returned errors', [
                'date' => $date,
                'url' => $url,
                'errors' => $apiErrors,
            ]);

            return new WP_Error(
                'wc26_api_error',
                __('API-Football rejected the request. Check the API key and account limits.', WC26_WIDGET_TEXT_DOMAIN),
                [
                    'request_url' => $url,
                    'api_errors' => $apiErrors,
                ]
            );
        }

        $fixtures = isset($payload['response']) && is_array($payload['response']) ? $payload['response'] : [];
        $data = [
            'date' => $date,
            'fixtures' => $fixtures,
            'fetched_at' => time(),
            'cache_ttl' => $this->cacheTtl($fixtures),
        ];

        set_transient($cacheKey, $data, (int) $data['cache_ttl']);

        return $data;
    }

    private function cacheKey(string $date): string
    {
        return self::cacheKeyFor(
            $this->settings->leagueId(),
            $this->settings->season(),
            $date
        );
    }

    public static function cacheKeyFor(int $leagueId, int $season, string $date): string
    {
        return sprintf(
            'wc26_fixtures_%d_%d_%s',
            $leagueId,
            $season,
            str_replace('-', '', $date)
        );
    }

    public function fixturesUrl(string $date): string
    {
        return add_query_arg(
            [
                'league' => $this->settings->leagueId(),
                'season' => $this->settings->season(),
                'date' => $this->settings->sanitizeDate($date),
                'timezone' => $this->timezone(),
            ],
            self::BASE_URL . '/fixtures'
        );
    }

    private function timezone(): string
    {
        if (function_exists('wp_timezone_string')) {
            $timezone = wp_timezone_string();

            if (is_string($timezone) && $timezone !== '') {
                return $timezone;
            }
        }

        return 'UTC';
    }

    /**
     * @param array<int, mixed> $fixtures
     */
    private function cacheTtl(array $fixtures): int
    {
        foreach ($fixtures as $fixture) {
            if (!is_array($fixture)) {
                continue;
            }

            $short = (string) ($fixture['fixture']['status']['short'] ?? '');

            if (in_array($short, self::LIVE_STATUSES, true)) {
                return 60;
            }
        }

        return 15 * MINUTE_IN_SECONDS;
    }

    /**
     * @return array<string, mixed>
     */
    private function mockFixturesByDate(string $date): array
    {
        $fixtures = [
            $this->mockFixture(
                855737,
                $date . 'T10:00:00+00:00',
                'Match Finished',
                'FT',
                90,
                'Lusail Iconic Stadium',
                'Group Stage - 1',
                26,
                'Argentina',
                'https://media.api-sports.io/football/teams/26.png',
                23,
                'Saudi Arabia',
                'https://media.api-sports.io/football/teams/23.png',
                1,
                2
            ),
            $this->mockFixture(
                855751,
                $date . 'T12:00:00+00:00',
                'Match Finished',
                'PEN',
                120,
                'Lusail Iconic Stadium',
                'Final',
                26,
                'Argentina',
                'https://media.api-sports.io/football/teams/26.png',
                2,
                'France',
                'https://media.api-sports.io/football/teams/2.png',
                3,
                3,
                4,
                2
            ),
            $this->mockFixture(
                855752,
                $date . 'T14:00:00+00:00',
                'First Half',
                '1H',
                27,
                'Al Janoub Stadium',
                'Group Stage - 1',
                2,
                'France',
                'https://media.api-sports.io/football/teams/2.png',
                20,
                'Australia',
                'https://media.api-sports.io/football/teams/20.png',
                0,
                1
            ),
            $this->mockFixture(
                855758,
                $date . 'T16:00:00+00:00',
                'Not Started',
                'NS',
                null,
                'Al Bayt Stadium',
                'Semi-finals',
                26,
                'Argentina',
                'https://media.api-sports.io/football/teams/26.png',
                3,
                'Croatia',
                'https://media.api-sports.io/football/teams/3.png',
                null,
                null
            ),
        ];

        usort($fixtures, static function (array $first, array $second): int {
            return strcmp(
                (string) ($first['fixture']['date'] ?? ''),
                (string) ($second['fixture']['date'] ?? '')
            );
        });

        return [
            'get' => 'fixtures',
            'parameters' => [
                'date' => $date,
            ],
            'errors' => [],
            'results' => count($fixtures),
            'paging' => [
                'current' => 1,
                'total' => 1,
            ],
            'date' => $date,
            'fixtures' => $fixtures,
            'response' => $fixtures,
            'fetched_at' => time(),
            'cache_ttl' => $this->cacheTtl($fixtures),
            'mock' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mockFixture(
        int $id,
        string $date,
        string $statusLong,
        string $statusShort,
        ?int $elapsed,
        string $venue,
        string $round,
        int $homeId,
        string $home,
        string $homeLogo,
        int $awayId,
        string $away,
        string $awayLogo,
        ?int $homeGoals,
        ?int $awayGoals,
        ?int $homePenalties = null,
        ?int $awayPenalties = null
    ): array {
        $isFinished = in_array($statusShort, ['FT', 'AET', 'PEN'], true);

        return [
            'fixture' => [
                'id' => $id,
                'referee' => null,
                'timezone' => 'UTC',
                'date' => $date,
                'timestamp' => strtotime($date),
                'periods' => [
                    'first' => $statusShort === '2H' ? strtotime($date) : null,
                    'second' => $statusShort === '2H' ? strtotime($date) + HOUR_IN_SECONDS : null,
                ],
                'venue' => [
                    'id' => null,
                    'name' => $venue,
                    'city' => null,
                ],
                'status' => [
                    'long' => $statusLong,
                    'short' => $statusShort,
                    'elapsed' => $elapsed,
                    'extra' => null,
                ],
            ],
            'league' => [
                'id' => 1,
                'name' => 'World Cup',
                'country' => 'World',
                'logo' => 'https://media.api-sports.io/football/leagues/1.png',
                'flag' => null,
                'season' => 2022,
                'round' => $round,
                'standings' => false,
            ],
            'teams' => [
                'home' => [
                    'id' => $homeId,
                    'name' => $home,
                    'logo' => $homeLogo,
                    'winner' => $homeGoals !== null && $awayGoals !== null ? $homeGoals > $awayGoals : null,
                ],
                'away' => [
                    'id' => $awayId,
                    'name' => $away,
                    'logo' => $awayLogo,
                    'winner' => $homeGoals !== null && $awayGoals !== null ? $awayGoals > $homeGoals : null,
                ],
            ],
            'goals' => [
                'home' => $homeGoals,
                'away' => $awayGoals,
            ],
            'score' => [
                'halftime' => [
                    'home' => $homeGoals === null ? null : min($homeGoals, 1),
                    'away' => $awayGoals === null ? null : min($awayGoals, 1),
                ],
                'fulltime' => [
                    'home' => $isFinished ? $homeGoals : null,
                    'away' => $isFinished ? $awayGoals : null,
                ],
                'extratime' => [
                    'home' => null,
                    'away' => null,
                ],
                'penalty' => [
                    'home' => $homePenalties,
                    'away' => $awayPenalties,
                ],
            ],
        ];
    }
}
