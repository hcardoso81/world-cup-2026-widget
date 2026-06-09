<?php

declare(strict_types=1);

namespace HernanCardoso\WorldCup2026Widget\Api;

use HernanCardoso\WorldCup2026Widget\Support\Logger;
use HernanCardoso\WorldCup2026Widget\Support\Settings;
use WP_Error;

final class ApiFootballClient
{
    private const BASE_URL = 'https://v3.football.api-sports.io';

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
            'scope' => 'date',
            'fixtures' => $fixtures,
            'fetched_at' => time(),
            'cache_ttl' => SyncPolicy::refreshInterval(),
        ];

        set_transient($cacheKey, $data, (int) $data['cache_ttl']);

        return $data;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function fixturesForSeason()
    {
        if ($this->settings->apiKey() === '') {
            return new WP_Error(
                'wc26_api_missing_key',
                __('API-Football key is not configured.', WC26_WIDGET_TEXT_DOMAIN)
            );
        }

        $url = $this->fixturesSeasonUrl();

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'x-apisports-key' => $this->settings->apiKey(),
            ],
        ]);

        if (is_wp_error($response)) {
            Logger::warning('API-Football season request failed', [
                'error' => $response->get_error_message(),
            ]);

            return $response;
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $payload = json_decode($body, true);

        if ($statusCode < 200 || $statusCode >= 300 || !is_array($payload)) {
            Logger::warning('API-Football returned an invalid season response', [
                'status_code' => $statusCode,
            ]);

            return new WP_Error(
                'wc26_api_invalid_response',
                __('API-Football returned an invalid response.', WC26_WIDGET_TEXT_DOMAIN)
            );
        }

        $apiErrors = $payload['errors'] ?? [];
        if (is_array($apiErrors) && $apiErrors !== []) {
            Logger::warning('API-Football returned season errors', [
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
            'scope' => 'season',
            'fixtures' => $fixtures,
            'fetched_at' => time(),
            'cache_ttl' => SyncPolicy::refreshInterval(),
        ];

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

    public static function seasonCacheKeyFor(int $leagueId, int $season): string
    {
        return sprintf('wc26_fixtures_%d_%d_all', $leagueId, $season);
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

    public function fixturesSeasonUrl(): string
    {
        return add_query_arg(
            [
                'league' => $this->settings->leagueId(),
                'season' => $this->settings->season(),
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

}
