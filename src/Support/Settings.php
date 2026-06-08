<?php

declare(strict_types=1);

namespace HernanCardoso\WorldCup2026Widget\Support;

final class Settings
{
    public const OPTION_NAME = 'wc26_widget_settings';
    public const DEFAULT_LEAGUE_ID = 1;
    public const DEFAULT_SEASON = 2026;
    public const MIN_SEASON = 1930;
    public const DEFAULT_WIDGET_LANGUAGE = 'es';
    public const DEFAULT_WIDGET_THEME = 'white';
    public const DEFAULT_WIDGET_REFRESH = 60;
    public const DEFAULT_MATCH_DATE = '2022-11-20';
    private const ARGENTINA_TIMEZONE = 'America/Argentina/Buenos_Aires';

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'api_key' => '',
            'league_id' => self::DEFAULT_LEAGUE_ID,
            'season' => self::DEFAULT_SEASON,
            'widget_language' => self::DEFAULT_WIDGET_LANGUAGE,
            'widget_theme' => self::DEFAULT_WIDGET_THEME,
            'widget_refresh' => self::DEFAULT_WIDGET_REFRESH,
            'default_game_id' => '',
            'match_date' => self::DEFAULT_MATCH_DATE,
            'frontend_visible' => 1,
            'simulation_enabled' => 0,
            'simulation_mock_enabled' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = get_option(self::OPTION_NAME, []);

        if (!is_array($stored)) {
            $stored = [];
        }

        return wp_parse_args($stored, $this->defaults());
    }

    public function apiKey(): string
    {
        $settings = $this->all();

        return is_string($settings['api_key']) ? trim($settings['api_key']) : '';
    }

    public function leagueId(): int
    {
        $settings = $this->all();

        return max(1, absint($settings['league_id']));
    }

    public function season(): int
    {
        $settings = $this->all();

        return max(self::MIN_SEASON, absint($settings['season']));
    }

    public function widgetLanguage(): string
    {
        $settings = $this->all();
        $language = is_string($settings['widget_language']) ? sanitize_key($settings['widget_language']) : self::DEFAULT_WIDGET_LANGUAGE;

        return $language !== '' ? $language : self::DEFAULT_WIDGET_LANGUAGE;
    }

    public function widgetTheme(): string
    {
        $settings = $this->all();
        $theme = is_string($settings['widget_theme']) ? sanitize_key($settings['widget_theme']) : self::DEFAULT_WIDGET_THEME;
        $allowed = ['white', 'grey', 'dark', 'blue'];

        return in_array($theme, $allowed, true) ? $theme : self::DEFAULT_WIDGET_THEME;
    }

    public function widgetRefresh(): string
    {
        $settings = $this->all();
        $refresh = $settings['widget_refresh'] ?? self::DEFAULT_WIDGET_REFRESH;

        if ($refresh === 'false') {
            return 'false';
        }

        return (string) max(15, absint($refresh));
    }

    public function defaultGameId(): string
    {
        $settings = $this->all();

        return isset($settings['default_game_id']) ? preg_replace('/[^0-9]/', '', (string) $settings['default_game_id']) : '';
    }

    public function matchDate(): string
    {
        $settings = $this->all();
        $date = isset($settings['match_date']) ? (string) $settings['match_date'] : self::DEFAULT_MATCH_DATE;

        return $this->sanitizeDate($date);
    }

    public function isFrontendVisible(): bool
    {
        $settings = $this->all();

        return !empty($settings['frontend_visible']);
    }

    public function isSimulationEnabled(): bool
    {
        $settings = $this->all();

        return !empty($settings['simulation_enabled']);
    }

    public function isSimulationMockEnabled(): bool
    {
        $settings = $this->all();

        return !empty($settings['simulation_mock_enabled']);
    }

    public function shouldUseMockFixtures(): bool
    {
        return $this->isSimulationEnabled() && $this->isSimulationMockEnabled();
    }

    public function shortcodeDate(): string
    {
        if ($this->isSimulationEnabled()) {
            return $this->matchDate();
        }

        return $this->currentArgentinaDate();
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function sanitize(array $input): array
    {
        return [
            'api_key' => isset($input['api_key']) ? sanitize_text_field((string) $input['api_key']) : '',
            'league_id' => isset($input['league_id']) ? max(1, absint($input['league_id'])) : self::DEFAULT_LEAGUE_ID,
            'season' => isset($input['season']) ? max(self::MIN_SEASON, absint($input['season'])) : self::DEFAULT_SEASON,
            'widget_language' => isset($input['widget_language']) ? sanitize_key((string) $input['widget_language']) : self::DEFAULT_WIDGET_LANGUAGE,
            'widget_theme' => $this->sanitizeWidgetTheme($input['widget_theme'] ?? self::DEFAULT_WIDGET_THEME),
            'widget_refresh' => $this->sanitizeWidgetRefresh($input['widget_refresh'] ?? self::DEFAULT_WIDGET_REFRESH),
            'default_game_id' => isset($input['default_game_id']) ? preg_replace('/[^0-9]/', '', (string) $input['default_game_id']) : '',
            'match_date' => $this->sanitizeDate(isset($input['match_date']) ? (string) $input['match_date'] : self::DEFAULT_MATCH_DATE),
            'frontend_visible' => !empty($input['frontend_visible']) ? 1 : 0,
            'simulation_enabled' => !empty($input['simulation_enabled']) ? 1 : 0,
            'simulation_mock_enabled' => !empty($input['simulation_mock_enabled']) ? 1 : 0,
        ];
    }

    public function currentArgentinaDate(): string
    {
        try {
            $timezone = new \DateTimeZone(self::ARGENTINA_TIMEZONE);
            $date = new \DateTimeImmutable('now', $timezone);

            return $date->format('Y-m-d');
        } catch (\Exception $exception) {
            return gmdate('Y-m-d');
        }
    }

    public function sanitizeDate(string $date): string
    {
        $date = sanitize_text_field($date);

        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $date) === 1) {
            [$first, $second, $year] = array_map('absint', explode('/', $date));
            $month = $first > 12 ? $second : $first;
            $day = $first > 12 ? $first : $second;

            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return self::DEFAULT_MATCH_DATE;
        }

        [$year, $month, $day] = array_map('absint', explode('-', $date));

        if (!checkdate($month, $day, $year)) {
            return self::DEFAULT_MATCH_DATE;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * @param mixed $theme
     */
    private function sanitizeWidgetTheme($theme): string
    {
        $theme = sanitize_key((string) $theme);
        $allowed = ['white', 'grey', 'dark', 'blue'];

        return in_array($theme, $allowed, true) ? $theme : self::DEFAULT_WIDGET_THEME;
    }

    /**
     * @param mixed $refresh
     * @return int|string
     */
    private function sanitizeWidgetRefresh($refresh)
    {
        if ((string) $refresh === 'false') {
            return 'false';
        }

        return max(15, absint($refresh));
    }
}
