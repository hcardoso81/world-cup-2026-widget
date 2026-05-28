<?php

declare(strict_types=1);

namespace HernanCardoso\WorldCup2026Widget\Frontend;

use HernanCardoso\WorldCup2026Widget\Api\ApiFootballClient;
use HernanCardoso\WorldCup2026Widget\Support\Settings;
use WP_Error;

final class Shortcode
{
    private Settings $settings;
    private ApiFootballClient $api;
    private bool $stylesEnqueued = false;
    private bool $widgetScriptEnqueued = false;
    private static int $instance = 0;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->api = new ApiFootballClient($settings);
    }

    public function registerHooks(): void
    {
        add_shortcode('world_cup_2026', [$this, 'renderLegacy']);
        add_shortcode('world_cup_2026_page', [$this, 'renderDisabledOfficialWidget']);
        add_shortcode('world_cup_2026_league', [$this, 'renderDisabledOfficialWidget']);
        add_shortcode('world_cup_2026_standings', [$this, 'renderDisabledOfficialWidget']);
        add_shortcode('world_cup_2026_game', [$this, 'renderDisabledOfficialWidget']);
        add_shortcode('world_cup_2026_player', [$this, 'renderDisabledOfficialWidget']);
        add_shortcode('world_cup_2026_team', [$this, 'renderDisabledOfficialWidget']);
        add_shortcode('world_cup_2026_matches', [$this, 'renderMatches']);
    }

    /**
     * Backward-compatible shortcode. New pages should use the generated shortcodes
     * listed in the settings screen.
     *
     * @param array<string, mixed>|string $atts
     */
    public function renderLegacy($atts = []): string
    {
        return $this->renderMatches($atts);
    }

    public function renderDisabledOfficialWidget(): string
    {
        if (!current_user_can('manage_options')) {
            return '';
        }

        return $this->renderError(new WP_Error(
            'wc26_official_widget_disabled',
            __('This shortcode is disabled in this plugin build. Use [world_cup_2026_matches].', WC26_WIDGET_TEXT_DOMAIN)
        ));
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderPage($atts = []): string
    {
        $atts = shortcode_atts(
            [
                'game_id' => '',
            ],
            is_array($atts) ? $atts : [],
            'world_cup_2026_page'
        );

        $error = $this->apiKeyError();
        if ($error instanceof WP_Error) {
            return $this->renderError($error);
        }

        $this->enqueueAssets();

        $targetId = $this->nextId('wc26-game-content');
        $gameId = $this->sanitizeNumericId((string) $atts['game_id']);
        $gameId = $gameId !== '' ? $gameId : $this->settings->defaultGameId();

        ob_start();
        ?>
        <section class="wc26-widgets-page" aria-label="<?php echo esc_attr__('World Cup 2026 widgets page', WC26_WIDGET_TEXT_DOMAIN); ?>">
            <div class="wc26-widgets-page__grid">
                <div class="wc26-widget-panel">
                    <api-sports-widget data-type="league"></api-sports-widget>
                </div>

                <div id="<?php echo esc_attr($targetId); ?>" class="wc26-widget-panel">
                    <?php if ($gameId !== '') : ?>
                        <api-sports-widget data-type="game" data-game-id="<?php echo esc_attr($gameId); ?>"></api-sports-widget>
                    <?php else : ?>
                        <div class="wc26-widget-placeholder">
                            <?php echo esc_html__('Select a match from the schedule to see details.', WC26_WIDGET_TEXT_DOMAIN); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="wc26-widget-panel">
                    <api-sports-widget data-type="standings"></api-sports-widget>
                </div>
            </div>

            <?php echo $this->renderConfig('#' . $targetId); ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    public function renderLeague(): string
    {
        return $this->renderSingleWidget('league');
    }

    public function renderStandings(): string
    {
        return $this->renderSingleWidget('standings');
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderGame($atts = []): string
    {
        $atts = shortcode_atts(
            [
                'id' => $this->settings->defaultGameId(),
            ],
            is_array($atts) ? $atts : [],
            'world_cup_2026_game'
        );

        $gameId = $this->sanitizeNumericId((string) $atts['id']);

        if ($gameId === '') {
            return $this->renderError(new WP_Error(
                'wc26_widget_missing_game_id',
                __('Set a game ID in the shortcode or settings.', WC26_WIDGET_TEXT_DOMAIN)
            ));
        }

        return $this->renderSingleWidget('game', [
            'data-game-id' => $gameId,
        ]);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderPlayer($atts = []): string
    {
        $atts = shortcode_atts(
            [
                'id' => '',
            ],
            is_array($atts) ? $atts : [],
            'world_cup_2026_player'
        );

        $playerId = $this->sanitizeNumericId((string) $atts['id']);

        if ($playerId === '') {
            return $this->renderError(new WP_Error(
                'wc26_widget_missing_player_id',
                __('Set a player ID in the shortcode.', WC26_WIDGET_TEXT_DOMAIN)
            ));
        }

        return $this->renderSingleWidget('player', [
            'data-player-id' => $playerId,
        ]);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderTeam($atts = []): string
    {
        $atts = shortcode_atts(
            [
                'id' => '',
            ],
            is_array($atts) ? $atts : [],
            'world_cup_2026_team'
        );

        $teamId = $this->sanitizeNumericId((string) $atts['id']);

        if ($teamId === '') {
            return $this->renderError(new WP_Error(
                'wc26_widget_missing_team_id',
                __('Set a team ID in the shortcode.', WC26_WIDGET_TEXT_DOMAIN)
            ));
        }

        return $this->renderSingleWidget('team', [
            'data-team-id' => $teamId,
        ]);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderMatches($atts = []): string
    {
        $atts = shortcode_atts(
            [
                'date' => $this->settings->matchDate(),
            ],
            is_array($atts) ? $atts : [],
            'world_cup_2026_matches'
        );

        $date = $this->settings->sanitizeDate((string) $atts['date']);
        $data = $this->api->fixturesByDate($date);

        if ($data instanceof WP_Error) {
            return $this->renderError($data);
        }

        $this->enqueuePublicStyles();

        $fixtures = isset($data['fixtures']) && is_array($data['fixtures']) ? $data['fixtures'] : [];

        ob_start();
        ?>
        <section class="wc26-matches" aria-label="<?php echo esc_attr(sprintf(__('World Cup matches for %s', WC26_WIDGET_TEXT_DOMAIN), $date)); ?>">
            <?php if ($fixtures === []) : ?>
                <p class="wc26-matches__empty"><?php echo esc_html__('No matches found for this date.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
            <?php else : ?>
                <div class="wc26-matches__list">
                    <?php foreach ($fixtures as $fixture) : ?>
                        <?php if (is_array($fixture)) : ?>
                            <?php echo $this->renderFixture($fixture); ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, string> $attributes
     */
    private function renderSingleWidget(string $type, array $attributes = []): string
    {
        $error = $this->apiKeyError();
        if ($error instanceof WP_Error) {
            return $this->renderError($error);
        }

        $this->enqueueAssets();

        ob_start();
        ?>
        <section class="wc26-widget-embed wc26-widget-embed--<?php echo esc_attr($type); ?>">
            <api-sports-widget data-type="<?php echo esc_attr($type); ?>"<?php echo $this->renderAttributes($attributes); ?>></api-sports-widget>
            <?php echo $this->renderConfig('modal'); ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    private function enqueueAssets(): void
    {
        $this->enqueuePublicStyles();

        if (!$this->widgetScriptEnqueued) {
            wp_enqueue_script(
                'wc26-api-sports-widgets',
                'https://widgets.api-sports.io/3.1.0/widgets.js',
                [],
                null,
                true
            );

            $this->widgetScriptEnqueued = true;
        }
    }

    private function enqueuePublicStyles(): void
    {
        if ($this->stylesEnqueued) {
            return;
        }

        wp_enqueue_style(
            'wc26-widget-public',
            WC26_WIDGET_URL . 'assets/css/public.css',
            [],
            WC26_WIDGET_VERSION
        );

        $this->stylesEnqueued = true;
    }

    public function filterWidgetScriptTag(string $tag, string $handle, string $src): string
    {
        if ($handle !== 'wc26-api-sports-widgets') {
            return $tag;
        }

        return sprintf(
            '<script type="module" crossorigin src="%s"></script>' . "\n",
            esc_url($src)
        );
    }

    private function renderConfig(string $targetGame): string
    {
        ob_start();
        ?>
        <api-sports-widget
            data-type="config"
            data-key="<?php echo esc_attr($this->settings->apiKey()); ?>"
            data-sport="football"
            data-lang="<?php echo esc_attr($this->settings->widgetLanguage()); ?>"
            data-theme="<?php echo esc_attr($this->settings->widgetTheme()); ?>"
            data-show-errors="true"
            data-show-logos="true"
            data-refresh="<?php echo esc_attr($this->settings->widgetRefresh()); ?>"
            data-player-injuries="true"
            data-team-squad="true"
            data-team-statistics="true"
            data-player-statistics="true"
            data-game-tab="statistics"
            data-standings="true"
            data-target-standings="true"
            data-target-game="<?php echo esc_attr($targetGame); ?>"
            data-target-player="modal"
            data-target-team="modal"
            data-tab="results"
            data-league="<?php echo esc_attr((string) $this->settings->leagueId()); ?>"
            data-season="<?php echo esc_attr((string) $this->settings->season()); ?>"
        ></api-sports-widget>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, string> $attributes
     */
    private function renderAttributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            $html .= sprintf(' %s="%s"', esc_attr($name), esc_attr($value));
        }

        return $html;
    }

    private function apiKeyError(): ?WP_Error
    {
        if ($this->settings->apiKey() !== '') {
            return null;
        }

        return new WP_Error(
            'wc26_widget_missing_api_key',
            __('API-Football key is not configured.', WC26_WIDGET_TEXT_DOMAIN)
        );
    }

    private function renderError(WP_Error $error): string
    {
        if (current_user_can('manage_options')) {
            $message = $error->get_error_message();
        } else {
            $message = __('World Cup widget is temporarily unavailable.', WC26_WIDGET_TEXT_DOMAIN);
        }

        $html = '<div class="wc26-widget-error">' . esc_html($message);

        if (current_user_can('manage_options')) {
            $data = $error->get_error_data();

            if (is_array($data) && isset($data['request_url'])) {
                $html .= '<p><strong>' . esc_html__('API request:', WC26_WIDGET_TEXT_DOMAIN) . '</strong> <code>' . esc_html((string) $data['request_url']) . '</code></p>';
            }

            if (is_array($data) && isset($data['api_errors'])) {
                $html .= '<pre class="wc26-widget-error__details">' . esc_html(wp_json_encode($data['api_errors'], JSON_PRETTY_PRINT)) . '</pre>';
            }
        }

        $html .= '</div>';

        return $html;
    }

    private function nextId(string $prefix): string
    {
        self::$instance++;

        return sanitize_html_class($prefix . '-' . self::$instance);
    }

    private function sanitizeNumericId(string $id): string
    {
        return preg_replace('/[^0-9]/', '', $id) ?: '';
    }

    /**
     * @param array<string, mixed> $fixture
     */
    private function renderFixture(array $fixture): string
    {
        $match = $this->fixtureModel($fixture);
        $statusShort = $match['status'];
        $statusLabel = $this->statusLabel($statusShort);
        $elapsed = $match['elapsed'];
        $date = $match['date'];
        $isNotStarted = in_array($statusShort, ['NS', 'TBD'], true);
        $score = $isNotStarted ? 'VS' : $this->formatScore($match);

        ob_start();
        ?>
        <article class="wc26-match wc26-match--<?php echo esc_attr(strtolower($statusShort)); ?>">
            <div class="wc26-match__status">
                <span><?php echo esc_html(strtoupper($statusLabel)); ?></span>
                <?php if ($elapsed > 0 && !in_array($statusShort, ['FT', 'AET', 'PEN'], true)) : ?>
                    <strong><?php echo esc_html(sprintf("%d'", $elapsed)); ?></strong>
                <?php elseif ($isNotStarted || in_array($statusShort, ['FT', 'AET', 'PEN'], true)) : ?>
                    <strong><?php echo esc_html($this->formatFixtureTime($date)); ?></strong>
                <?php endif; ?>
            </div>

            <div class="wc26-match__scoreboard">
                <?php echo $this->renderScoreTeam($match['homeTeam'], $match['homeLogo'], 'home'); ?>
                <strong class="wc26-match__score"><?php echo esc_html($score); ?></strong>
                <?php echo $this->renderScoreTeam($match['awayTeam'], $match['awayLogo'], 'away'); ?>
            </div>

            <?php if ($match['stage'] !== '' || $match['stadium'] !== '') : ?>
                <div class="wc26-match__meta">
                    <?php echo esc_html(implode(' - ', array_filter([$match['stage'], $match['stadium']]))); ?>
                </div>
            <?php endif; ?>
        </article>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $fixture
     *
     * @return array{
     *     id:int,
     *     date:string,
     *     stage:string,
     *     stadium:string,
     *     homeTeam:string,
     *     homeLogo:string,
     *     homeScore:int|null,
     *     awayTeam:string,
     *     awayLogo:string,
     *     awayScore:int|null,
     *     status:string,
     *     statusLabel:string,
     *     elapsed:int,
     *     penalties:array{home:int|null,away:int|null}
     * }
     */
    private function fixtureModel(array $fixture): array
    {
        $fixtureData = is_array($fixture['fixture'] ?? null) ? $fixture['fixture'] : [];
        $league = is_array($fixture['league'] ?? null) ? $fixture['league'] : [];
        $venue = is_array($fixtureData['venue'] ?? null) ? $fixtureData['venue'] : [];
        $status = is_array($fixtureData['status'] ?? null) ? $fixtureData['status'] : [];
        $teams = is_array($fixture['teams'] ?? null) ? $fixture['teams'] : [];
        $home = is_array($teams['home'] ?? null) ? $teams['home'] : [];
        $away = is_array($teams['away'] ?? null) ? $teams['away'] : [];
        $goals = is_array($fixture['goals'] ?? null) ? $fixture['goals'] : [];
        $score = is_array($fixture['score'] ?? null) ? $fixture['score'] : [];
        $penalties = is_array($score['penalty'] ?? null) ? $score['penalty'] : [];
        $statusShort = (string) ($status['short'] ?? 'NS');

        return [
            'id' => isset($fixtureData['id']) ? absint($fixtureData['id']) : 0,
            'date' => isset($fixtureData['date']) ? (string) $fixtureData['date'] : '',
            'stage' => isset($league['round']) ? (string) $league['round'] : '',
            'stadium' => isset($venue['name']) ? (string) $venue['name'] : '',
            'homeTeam' => isset($home['name']) ? (string) $home['name'] : __('Team TBD', WC26_WIDGET_TEXT_DOMAIN),
            'homeLogo' => isset($home['logo']) ? (string) $home['logo'] : '',
            'homeScore' => $this->nullableInt($goals['home'] ?? null),
            'awayTeam' => isset($away['name']) ? (string) $away['name'] : __('Team TBD', WC26_WIDGET_TEXT_DOMAIN),
            'awayLogo' => isset($away['logo']) ? (string) $away['logo'] : '',
            'awayScore' => $this->nullableInt($goals['away'] ?? null),
            'status' => $statusShort,
            'statusLabel' => isset($status['long']) ? (string) $status['long'] : $this->statusLabel($statusShort),
            'elapsed' => isset($status['elapsed']) ? absint($status['elapsed']) : 0,
            'penalties' => [
                'home' => $this->nullableInt($penalties['home'] ?? null),
                'away' => $this->nullableInt($penalties['away'] ?? null),
            ],
        ];
    }

    /**
     * @param array{
     *     homeScore:int|null,
     *     awayScore:int|null,
     *     penalties:array{home:int|null,away:int|null}
     * } $match
     */
    private function formatScore(array $match): string
    {
        $homeScore = $match['homeScore'] === null ? '0' : (string) $match['homeScore'];
        $awayScore = $match['awayScore'] === null ? '0' : (string) $match['awayScore'];
        $homePenalties = $match['penalties']['home'];
        $awayPenalties = $match['penalties']['away'];

        if ($homePenalties !== null || $awayPenalties !== null) {
            return sprintf(
                '%s (%s) - %s (%s)',
                $homeScore,
                $homePenalties === null ? '0' : (string) $homePenalties,
                $awayScore,
                $awayPenalties === null ? '0' : (string) $awayPenalties
            );
        }

        return $homeScore . ' - ' . $awayScore;
    }

    /**
     * @param mixed $value
     */
    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return absint($value);
    }

    private function renderScoreTeam(string $name, string $logo, string $side): string
    {
        $code = $this->teamCode($name);

        ob_start();
        ?>
        <div class="wc26-match__team wc26-match__team--<?php echo esc_attr($side); ?>" title="<?php echo esc_attr($name); ?>">
            <span><?php echo esc_html($code); ?></span>
            <?php if ($logo !== '') : ?>
                <img src="<?php echo esc_url($logo); ?>" alt="" loading="lazy" />
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function teamCode(string $name): string
    {
        $normalized = trim(preg_replace('/[^A-Za-z0-9 ]/', '', $name) ?: $name);
        $words = preg_split('/\s+/', $normalized) ?: [];

        if (count($words) >= 2) {
            $code = '';

            foreach ($words as $word) {
                if ($word === '') {
                    continue;
                }

                $code .= strtoupper(substr($word, 0, 1));
            }

            return substr($code, 0, 3);
        }

        return strtoupper(substr($normalized, 0, 3));
    }

    private function statusLabel(string $status): string
    {
        $labels = [
            'TBD' => __('A definir', WC26_WIDGET_TEXT_DOMAIN),
            'NS' => __('No iniciado', WC26_WIDGET_TEXT_DOMAIN),
            '1H' => __('Primer tiempo', WC26_WIDGET_TEXT_DOMAIN),
            'HT' => __('Entretiempo', WC26_WIDGET_TEXT_DOMAIN),
            '2H' => __('Segundo tiempo', WC26_WIDGET_TEXT_DOMAIN),
            'ET' => __('Tiempo extra', WC26_WIDGET_TEXT_DOMAIN),
            'BT' => __('Descanso', WC26_WIDGET_TEXT_DOMAIN),
            'P' => __('Penales', WC26_WIDGET_TEXT_DOMAIN),
            'FT' => __('Finalizado', WC26_WIDGET_TEXT_DOMAIN),
            'AET' => __('Finalizado en tiempo extra', WC26_WIDGET_TEXT_DOMAIN),
            'PEN' => __('Finalizado por penales', WC26_WIDGET_TEXT_DOMAIN),
            'SUSP' => __('Suspendido', WC26_WIDGET_TEXT_DOMAIN),
            'INT' => __('Interrumpido', WC26_WIDGET_TEXT_DOMAIN),
            'PST' => __('Postergado', WC26_WIDGET_TEXT_DOMAIN),
            'CANC' => __('Cancelado', WC26_WIDGET_TEXT_DOMAIN),
            'ABD' => __('Abandonado', WC26_WIDGET_TEXT_DOMAIN),
        ];

        return $labels[$status] ?? $status;
    }

    private function formatDate(string $date): string
    {
        $timestamp = strtotime($date . ' 12:00:00');

        if ($timestamp === false) {
            return $date;
        }

        return date_i18n(get_option('date_format'), $timestamp);
    }

    private function formatFixtureTime(string $date): string
    {
        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return '';
        }

        return date_i18n(get_option('time_format'), $timestamp);
    }
}
