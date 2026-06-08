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
        $schedule = $this->mockSchedule();
        $fixtures = [];

        if (isset($schedule[$date])) {
            foreach ($schedule[$date] as $index => $match) {
                $fixtures[] = $this->mockFixture(
                    202606080 + ((int) str_replace('-', '', $date) - 20260608) * 10 + $index + 1,
                    $date . 'T' . $match['time'] . ':00+00:00',
                    $match['status_long'],
                    $match['status_short'],
                    $match['elapsed'],
                    $match['venue'],
                    $match['round'],
                    $match['home_id'],
                    $match['home'],
                    $this->teamLogo($match['home_id']),
                    $match['away_id'],
                    $match['away'],
                    $this->teamLogo($match['away_id']),
                    $match['home_goals'],
                    $match['away_goals']
                );
            }
        }

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
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function mockSchedule(): array
    {
        return [
            '2026-06-08' => [
                $this->mockMatch('13:00', 'Match Finished', 'FT', 90, 'Estadio Azteca', 'Group Stage - Matchday 1', 26, 'Argentina', 13, 'Mexico', 2, 1),
                $this->mockMatch('16:00', 'Match Finished', 'FT', 90, 'BMO Field', 'Group Stage - Matchday 1', 6, 'Brazil', 9, 'Canada', 3, 0),
                $this->mockMatch('19:00', 'Second Half', '2H', 67, 'MetLife Stadium', 'Group Stage - Matchday 1', 2, 'France', 10, 'United States', 1, 1),
                $this->mockMatch('22:00', 'Not Started', 'NS', null, 'SoFi Stadium', 'Group Stage - Matchday 1', 27, 'Spain', 14, 'Japan', null, null),
            ],
            '2026-06-09' => [
                $this->mockMatch('13:00', 'Match Finished', 'FT', 90, 'AT&T Stadium', 'Group Stage - Matchday 1', 25, 'Germany', 20, 'Australia', 2, 0),
                $this->mockMatch('16:00', 'Match Finished', 'FT', 90, 'Mercedes-Benz Stadium', 'Group Stage - Matchday 1', 24, 'England', 1, 'Belgium', 1, 2),
                $this->mockMatch('19:00', 'First Half', '1H', 34, 'Hard Rock Stadium', 'Group Stage - Matchday 1', 4, 'Portugal', 31, 'Morocco', 0, 0),
                $this->mockMatch('22:00', 'Not Started', 'NS', null, 'NRG Stadium', 'Group Stage - Matchday 1', 21, 'Uruguay', 22, 'Korea Republic', null, null),
            ],
            '2026-06-10' => [
                $this->mockMatch('13:00', 'Match Finished', 'FT', 90, 'Lumen Field', 'Group Stage - Matchday 1', 3, 'Croatia', 8, 'Netherlands', 0, 1),
                $this->mockMatch('16:00', 'Match Finished', 'FT', 90, 'Levi\'s Stadium', 'Group Stage - Matchday 1', 7, 'Italy', 11, 'Switzerland', 2, 2),
                $this->mockMatch('19:00', 'Second Half', '2H', 73, 'BC Place', 'Group Stage - Matchday 1', 15, 'Senegal', 16, 'Ghana', 1, 0),
                $this->mockMatch('22:00', 'Not Started', 'NS', null, 'Gillette Stadium', 'Group Stage - Matchday 1', 17, 'Colombia', 18, 'Chile', null, null),
            ],
            '2026-06-11' => [
                $this->mockMatch('13:00', 'Match Finished', 'FT', 90, 'Estadio Akron', 'Group Stage - Matchday 1', 19, 'Denmark', 28, 'Serbia', 1, 0),
                $this->mockMatch('16:00', 'Match Finished', 'FT', 90, 'Lincoln Financial Field', 'Group Stage - Matchday 1', 29, 'Poland', 30, 'Austria', 0, 0),
                $this->mockMatch('19:00', 'Halftime', 'HT', 45, 'Arrowhead Stadium', 'Group Stage - Matchday 1', 32, 'Costa Rica', 33, 'Ecuador', 1, 1),
                $this->mockMatch('22:00', 'Not Started', 'NS', null, 'Snapdragon Stadium', 'Group Stage - Matchday 1', 34, 'Qatar', 35, 'Tunisia', null, null),
            ],
            '2026-06-12' => [
                $this->mockMatch('13:00', 'Match Finished', 'FT', 90, 'Estadio BBVA', 'Group Stage - Matchday 1', 36, 'Peru', 37, 'Paraguay', 2, 1),
                $this->mockMatch('16:00', 'Match Finished', 'FT', 90, 'Camping World Stadium', 'Group Stage - Matchday 1', 38, 'Cameroon', 39, 'Nigeria', 1, 3),
                $this->mockMatch('19:00', 'First Half', '1H', 22, 'Rose Bowl', 'Group Stage - Matchday 1', 40, 'Egypt', 41, 'Iran', 0, 1),
                $this->mockMatch('22:00', 'Not Started', 'NS', null, 'Allegiant Stadium', 'Group Stage - Matchday 1', 42, 'Sweden', 43, 'Norway', null, null),
            ],
            '2026-06-13' => [
                $this->mockMatch('13:00', 'Match Finished', 'FT', 90, 'Yankee Stadium', 'Group Stage - Matchday 1', 44, 'Turkey', 45, 'Greece', 2, 2),
                $this->mockMatch('16:00', 'Match Finished', 'FT', 90, 'Inter&Co Stadium', 'Group Stage - Matchday 1', 46, 'Wales', 47, 'Scotland', 1, 0),
                $this->mockMatch('19:00', 'Second Half', '2H', 58, 'Bank of America Stadium', 'Group Stage - Matchday 1', 48, 'Ireland', 49, 'Ukraine', 0, 0),
                $this->mockMatch('22:00', 'Not Started', 'NS', null, 'Audi Field', 'Group Stage - Matchday 1', 50, 'South Africa', 51, 'New Zealand', null, null),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mockMatch(
        string $time,
        string $statusLong,
        string $statusShort,
        ?int $elapsed,
        string $venue,
        string $round,
        int $homeId,
        string $home,
        int $awayId,
        string $away,
        ?int $homeGoals,
        ?int $awayGoals
    ): array {
        return [
            'time' => $time,
            'status_long' => $statusLong,
            'status_short' => $statusShort,
            'elapsed' => $elapsed,
            'venue' => $venue,
            'round' => $round,
            'home_id' => $homeId,
            'home' => $home,
            'away_id' => $awayId,
            'away' => $away,
            'home_goals' => $homeGoals,
            'away_goals' => $awayGoals,
        ];
    }

    private function teamLogo(int $teamId): string
    {
        return sprintf('https://media.api-sports.io/football/teams/%d.png', $teamId);
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
