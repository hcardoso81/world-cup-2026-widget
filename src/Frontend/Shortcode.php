<?php

declare(strict_types=1);

namespace HernanCardoso\WorldCup2026Widget\Frontend;

use HernanCardoso\WorldCup2026Widget\Api\ApiFootballClient;
use HernanCardoso\WorldCup2026Widget\Api\FixturesEndpoint;
use HernanCardoso\WorldCup2026Widget\Api\FixturesSyncService;
use HernanCardoso\WorldCup2026Widget\Api\SyncPolicy;
use HernanCardoso\WorldCup2026Widget\Support\Settings;
use WP_Error;

final class Shortcode
{
    private Settings $settings;
    private ApiFootballClient $api;
    private FixturesSyncService $sync;
    private bool $stylesEnqueued = false;
    private bool $publicScriptEnqueued = false;
    private bool $widgetScriptEnqueued = false;
    private static int $instance = 0;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->api = new ApiFootballClient($settings);
        $this->sync = new FixturesSyncService($settings);
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
        add_shortcode('world_cup_2026_matches_banner', [$this, 'renderMatchesBanner']);
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
        return $this->renderMatchesCarousel($atts, 'world_cup_2026_matches', 'standard', 4, $this->settings->matchesPerLine());
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function renderMatchesBanner($atts = []): string
    {
        return $this->renderMatchesCarousel($atts, 'world_cup_2026_matches_banner', 'banner', 5, 5);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    private function renderMatchesCarousel($atts, string $shortcode, string $layout, int $maxPerLine, int $defaultPerLine): string
    {
        if (!$this->settings->isFrontendVisible()) {
            return '';
        }

        $atts = shortcode_atts(
            [
                'date' => $this->settings->shortcodeDate(),
                'amount_match_per_line' => '',
                'matches_per_line' => $defaultPerLine,
            ],
            is_array($atts) ? $atts : [],
            $shortcode
        );

        $matchesPerLine = (string) $atts['amount_match_per_line'] !== ''
            ? $this->sanitizeMatchesPerLine($atts['amount_match_per_line'], $maxPerLine)
            : $this->sanitizeMatchesPerLine($atts['matches_per_line'], $maxPerLine);
        $date = $this->settings->isSimulationEnabled()
            ? $this->settings->sanitizeDate((string) $atts['date'])
            : $this->settings->currentArgentinaDate();
        $data = $this->sync->fixtures();

        if ($data instanceof WP_Error) {
            return $this->renderError($data);
        }

        $this->enqueuePublicAssets();

        $fixtures = isset($data['fixtures']) && is_array($data['fixtures']) ? $data['fixtures'] : [];
        $fixturesByDate = $this->groupFixturesByDate($fixtures);
        $dates = array_keys($fixturesByDate);
        $activeDate = $this->activeCarouselDate($dates, $date);
        $carouselId = $this->nextId('wc26-matches-carousel');
        $currentTimestamp = $this->currentMatchTimestamp();
        $classes = ['wc26-matches', 'wc26-matches--' . $layout];
        $bannerMaxWidth = ($matchesPerLine * 220) + (($matchesPerLine - 1) * 8);

        ob_start();
        ?>
        <section id="<?php echo esc_attr($carouselId); ?>" class="<?php echo esc_attr(implode(' ', $classes)); ?>" style="--wc26-matches-per-line: <?php echo esc_attr((string) $matchesPerLine); ?>; --wc26-banner-max-width: <?php echo esc_attr((string) $bannerMaxWidth); ?>px;" data-wc26-carousel aria-label="<?php echo esc_attr__('World Cup matches by day', WC26_WIDGET_TEXT_DOMAIN); ?>">
            <?php if ($layout === 'banner') : ?>
                <div class="widget-title block-head block-head-ac block-head block-head-ac block-head-g is-left has-style">
                    <h5 class="heading"><?php echo esc_html__('Fixture Mundial 2026 | Edicion Especial de Campanas Mundialistas', WC26_WIDGET_TEXT_DOMAIN); ?></h5>
                </div>
            <?php endif; ?>

            <?php if ($fixturesByDate === []) : ?>
                <p class="wc26-matches__empty"><?php echo esc_html__('No matches found for this date.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
            <?php else : ?>
                <div class="wc26-matches__carousel">
                    <button class="wc26-matches__nav" type="button" data-wc26-prev aria-label="<?php echo esc_attr__('Ver dia anterior', WC26_WIDGET_TEXT_DOMAIN); ?>" title="<?php echo esc_attr__('Ver dia anterior', WC26_WIDGET_TEXT_DOMAIN); ?>" data-wc26-tooltip="<?php echo esc_attr__('Ver dia anterior', WC26_WIDGET_TEXT_DOMAIN); ?>">
                        <span aria-hidden="true">&lsaquo;</span>
                    </button>

                    <div class="wc26-matches__content">
                        <div class="wc26-matches__days">
                            <?php foreach ($fixturesByDate as $fixtureDate => $dayFixtures) : ?>
                                <div class="wc26-matches__day" data-wc26-day="<?php echo esc_attr($fixtureDate); ?>" data-wc26-label="<?php echo esc_attr($this->formatDate($fixtureDate)); ?>" <?php echo $fixtureDate === $activeDate ? '' : 'hidden'; ?>>
                                    <div class="wc26-matches__list">
                                        <?php foreach ($dayFixtures as $fixture) : ?>
                                            <?php echo $this->renderFixture($fixture, $currentTimestamp); ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <strong class="wc26-matches__date" data-wc26-date-label title="<?php echo esc_attr($this->formatDate($activeDate)); ?>" data-wc26-tooltip="<?php echo esc_attr($this->formatDate($activeDate)); ?>">
                                <?php echo esc_html($this->formatDate($activeDate)); ?>
                            </strong>
                        </div>
                    </div>

                    <button class="wc26-matches__nav" type="button" data-wc26-next aria-label="<?php echo esc_attr__('Ver dia siguiente', WC26_WIDGET_TEXT_DOMAIN); ?>" title="<?php echo esc_attr__('Ver dia siguiente', WC26_WIDGET_TEXT_DOMAIN); ?>" data-wc26-tooltip="<?php echo esc_attr__('Ver dia siguiente', WC26_WIDGET_TEXT_DOMAIN); ?>">
                        <span aria-hidden="true">&rsaquo;</span>
                    </button>
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

    private function enqueuePublicAssets(): void
    {
        $this->enqueuePublicStyles();

        if ($this->publicScriptEnqueued) {
            return;
        }

        wp_enqueue_script(
            'wc26-widget-public',
            WC26_WIDGET_URL . 'assets/js/public.js',
            [],
            WC26_WIDGET_VERSION,
            true
        );

        wp_add_inline_script(
            'wc26-widget-public',
            'window.WC26Widget = ' . wp_json_encode([
                'fixturesUrl' => esc_url_raw(rest_url(FixturesEndpoint::NAMESPACE . FixturesEndpoint::ROUTE)),
                'pollInterval' => SyncPolicy::refreshInterval(),
            ]) . ';',
            'before'
        );

        $this->publicScriptEnqueued = true;
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
     * @param mixed $amount
     */
    private function sanitizeMatchesPerLine($amount, int $max = 4): int
    {
        $amount = absint($amount);
        $max = max(1, $max);

        if ($amount < 1) {
            return 1;
        }

        if ($amount > $max) {
            return $max;
        }

        return $amount;
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

        foreach ($grouped as $date => $dayFixtures) {
            usort($dayFixtures, static function (array $first, array $second): int {
                return strcmp(
                    (string) ($first['fixture']['date'] ?? ''),
                    (string) ($second['fixture']['date'] ?? '')
                );
            });

            $grouped[$date] = $dayFixtures;
        }

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

    /**
     * @param array<int, string> $dates
     */
    private function activeCarouselDate(array $dates, string $targetDate): string
    {
        if ($dates === []) {
            return $targetDate;
        }

        if (in_array($targetDate, $dates, true)) {
            return $targetDate;
        }

        foreach ($dates as $date) {
            if ($date >= $targetDate) {
                return $date;
            }
        }

        return (string) end($dates);
    }

    /**
     * @param array<string, mixed> $fixture
     */
    private function renderFixture(array $fixture, int $currentTimestamp): string
    {
        $match = $this->fixtureModel($fixture, $currentTimestamp);
        $statusShort = $match['status'];
        $statusLabel = $match['isCurrent'] ? __('En juego', WC26_WIDGET_TEXT_DOMAIN) : $this->statusLabel($statusShort);
        $elapsed = $match['elapsed'];
        $date = $match['date'];
        $isNotStarted = in_array($statusShort, ['NS', 'TBD'], true) && !$match['isCurrent'];
        $score = $isNotStarted ? '-' : $this->formatScore($match);
        $classes = [
            'wc26-match',
            'wc26-match--' . strtolower($statusShort),
        ];

        if ($match['isCurrent']) {
            $classes[] = 'wc26-match--current';
        }

        ob_start();
        ?>
        <article class="<?php echo esc_attr(implode(' ', $classes)); ?>" data-wc26-match-id="<?php echo esc_attr((string) $match['id']); ?>" data-wc26-tooltip="<?php echo esc_attr($this->matchTooltip($match)); ?>">
            <div class="wc26-match__status">
                <span data-wc26-status-label><?php echo esc_html(strtoupper($statusLabel)); ?></span>
                <?php if ($elapsed > 0 && !in_array($statusShort, ['FT', 'AET', 'PEN'], true)) : ?>
                    <strong data-wc26-status-extra><?php echo esc_html(sprintf("%d'", $elapsed)); ?></strong>
                <?php elseif ($isNotStarted || in_array($statusShort, ['FT', 'AET', 'PEN'], true)) : ?>
                    <strong data-wc26-status-extra><?php echo esc_html($this->formatFixtureTime($date)); ?></strong>
                <?php endif; ?>
            </div>

            <div class="wc26-match__scoreboard">
                <?php echo $this->renderScoreTeam($match['homeTeam'], $match['homeLogo'], 'home'); ?>
                <strong class="wc26-match__score" data-wc26-score><?php echo esc_html($score); ?></strong>
                <?php echo $this->renderScoreTeam($match['awayTeam'], $match['awayLogo'], 'away'); ?>
            </div>

            <?php if ($match['stage'] !== '' || $match['stadium'] !== '') : ?>
                <div class="wc26-match__meta">
                    <?php if ($match['stage'] !== '') : ?>
                        <span><?php echo esc_html($match['stage']); ?></span>
                    <?php endif; ?>
                    <?php if ($match['stadium'] !== '') : ?>
                        <span><?php echo esc_html($match['stadium']); ?></span>
                    <?php endif; ?>
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
     *     isCurrent:bool,
     *     penalties:array{home:int|null,away:int|null}
     * }
     */
    private function fixtureModel(array $fixture, int $currentTimestamp): array
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
        $date = isset($fixtureData['date']) ? (string) $fixtureData['date'] : '';
        $kickoffTimestamp = $this->fixtureTimestamp($fixtureData, $date);
        $isCurrent = $this->settings->isSimulationEnabled()
            ? $this->isCurrentSimulatedMatch($statusShort, $date)
            : $this->isCurrentMatch($statusShort, $kickoffTimestamp, $currentTimestamp);
        $elapsed = isset($status['elapsed']) ? absint($status['elapsed']) : 0;

        if ($isCurrent && $elapsed === 0) {
            $elapsed = $this->settings->isSimulationEnabled()
                ? $this->simulatedElapsedMinutes($date)
                : max(1, (int) floor(($currentTimestamp - $kickoffTimestamp) / MINUTE_IN_SECONDS));
        }

        return [
            'id' => isset($fixtureData['id']) ? absint($fixtureData['id']) : 0,
            'date' => $date,
            'stage' => isset($league['round']) ? $this->localizeStage((string) $league['round']) : '',
            'stadium' => isset($venue['name']) ? $this->localizeVenue((string) $venue['name']) : '',
            'homeTeam' => isset($home['name']) ? $this->localizeTeamName((string) $home['name']) : __('Equipo por definir', WC26_WIDGET_TEXT_DOMAIN),
            'homeLogo' => isset($home['logo']) ? (string) $home['logo'] : '',
            'homeScore' => $this->nullableInt($goals['home'] ?? null),
            'awayTeam' => isset($away['name']) ? $this->localizeTeamName((string) $away['name']) : __('Equipo por definir', WC26_WIDGET_TEXT_DOMAIN),
            'awayLogo' => isset($away['logo']) ? (string) $away['logo'] : '',
            'awayScore' => $this->nullableInt($goals['away'] ?? null),
            'status' => $statusShort,
            'statusLabel' => isset($status['long']) ? (string) $status['long'] : $this->statusLabel($statusShort),
            'elapsed' => $elapsed,
            'isCurrent' => $isCurrent,
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

    private function currentMatchTimestamp(): int
    {
        if (!$this->settings->isSimulationEnabled()) {
            return $this->settings->currentTimestamp();
        }

        try {
            $timezone = function_exists('wp_timezone')
                ? wp_timezone()
                : new \DateTimeZone(date_default_timezone_get());
            $date = new \DateTimeImmutable($this->settings->matchDate() . ' ' . $this->settings->matchTime(), $timezone);

            return $date->getTimestamp();
        } catch (\Exception $exception) {
            return $this->settings->currentTimestamp();
        }
    }

    /**
     * @param array<string, mixed> $fixtureData
     */
    private function fixtureTimestamp(array $fixtureData, string $date): int
    {
        $timestamp = isset($fixtureData['timestamp']) ? absint($fixtureData['timestamp']) : 0;

        if ($timestamp > 0) {
            return $timestamp;
        }

        $parsed = strtotime($date);

        return $parsed === false ? 0 : $parsed;
    }

    private function isCurrentMatch(string $statusShort, int $kickoffTimestamp, int $currentTimestamp): bool
    {
        if (in_array($statusShort, ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'SUSP', 'INT', 'LIVE'], true)) {
            return true;
        }

        if (in_array($statusShort, ['FT', 'AET', 'PEN', 'PST', 'CANC', 'ABD'], true) || $kickoffTimestamp <= 0) {
            return false;
        }

        $matchWindowEnds = $kickoffTimestamp + (150 * MINUTE_IN_SECONDS);

        return $currentTimestamp >= $kickoffTimestamp && $currentTimestamp <= $matchWindowEnds;
    }

    private function isCurrentSimulatedMatch(string $statusShort, string $fixtureDate): bool
    {
        if (in_array($statusShort, ['1H', 'HT', '2H', 'ET', 'BT', 'P', 'SUSP', 'INT', 'LIVE'], true)) {
            return true;
        }

        if (in_array($statusShort, ['FT', 'AET', 'PEN', 'PST', 'CANC', 'ABD'], true)) {
            return false;
        }

        if ($this->fixtureDate(['fixture' => ['date' => $fixtureDate]]) !== $this->settings->matchDate()) {
            return false;
        }

        $fixtureMinutes = $this->fixtureDisplayMinutes($fixtureDate);
        $simulationMinutes = $this->timeToMinutes($this->settings->matchTime());

        if ($fixtureMinutes === null || $simulationMinutes === null) {
            return false;
        }

        return $simulationMinutes >= $fixtureMinutes && $simulationMinutes <= ($fixtureMinutes + 150);
    }

    private function simulatedElapsedMinutes(string $fixtureDate): int
    {
        $fixtureMinutes = $this->fixtureDisplayMinutes($fixtureDate);
        $simulationMinutes = $this->timeToMinutes($this->settings->matchTime());

        if ($fixtureMinutes === null || $simulationMinutes === null) {
            return 1;
        }

        return max(1, $simulationMinutes - $fixtureMinutes);
    }

    private function fixtureDisplayMinutes(string $fixtureDate): ?int
    {
        return $this->timeToMinutes($this->formatFixtureTime($fixtureDate));
    }

    private function timeToMinutes(string $time): ?int
    {
        $timestamp = strtotime('1970-01-01 ' . $time);

        if ($timestamp === false) {
            return null;
        }

        return ((int) gmdate('G', $timestamp) * 60) + (int) gmdate('i', $timestamp);
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
        <div class="wc26-match__team wc26-match__team--<?php echo esc_attr($side); ?>">
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
        $knownCodes = [
            'alemania' => 'ALE',
            'arabia saudita' => 'KSA',
            'argelia' => 'ARG',
            'argentina' => 'ARG',
            'australia' => 'AUS',
            'austria' => 'AUT',
            'belgica' => 'BEL',
            'brasil' => 'BRA',
            'camerun' => 'CAM',
            'canada' => 'CAN',
            'chile' => 'CHI',
            'colombia' => 'COL',
            'costa de marfil' => 'CIV',
            'corea del sur' => 'COR',
            'costa rica' => 'CRC',
            'croacia' => 'CRO',
            'cabo verde' => 'CPV',
            'dinamarca' => 'DIN',
            'ecuador' => 'ECU',
            'egipto' => 'EGI',
            'escocia' => 'ESC',
            'espana' => 'ESP',
            'estados unidos' => 'USA',
            'francia' => 'FRA',
            'gales' => 'GAL',
            'ghana' => 'GHA',
            'grecia' => 'GRE',
            'inglaterra' => 'ING',
            'iran' => 'IRN',
            'irlanda' => 'IRL',
            'italia' => 'ITA',
            'japon' => 'JPN',
            'jordania' => 'JOR',
            'marruecos' => 'MAR',
            'mexico' => 'MEX',
            'nigeria' => 'NGA',
            'noruega' => 'NOR',
            'nueva zelanda' => 'NZL',
            'paises bajos' => 'PBA',
            'paraguay' => 'PAR',
            'peru' => 'PER',
            'polonia' => 'POL',
            'portugal' => 'POR',
            'qatar' => 'QAT',
            'republica checa' => 'CZE',
            'senegal' => 'SEN',
            'serbia' => 'SRB',
            'sudafrica' => 'RSA',
            'suecia' => 'SUE',
            'suiza' => 'SUI',
            'tunez' => 'TUN',
            'turquia' => 'TUR',
            'ucrania' => 'UCR',
            'uruguay' => 'URU',
            'uzbekistan' => 'UZB',
        ];
        $key = $this->normalizeLookupKey($name);

        if (isset($knownCodes[$key])) {
            return $knownCodes[$key];
        }

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

    private function localizeTeamName(string $name): string
    {
        $teams = [
            'algeria' => 'Argelia',
            'argentina' => 'Argentina',
            'australia' => 'Australia',
            'austria' => 'Austria',
            'belgium' => 'Belgica',
            'brazil' => 'Brasil',
            'cameroon' => 'Camerun',
            'canada' => 'Canada',
            'cape verde' => 'Cabo Verde',
            'cape verde islands' => 'Cabo Verde',
            'chile' => 'Chile',
            'colombia' => 'Colombia',
            'costa rica' => 'Costa Rica',
            'croatia' => 'Croacia',
            'czech republic' => 'Republica Checa',
            'denmark' => 'Dinamarca',
            'ecuador' => 'Ecuador',
            'egypt' => 'Egipto',
            'england' => 'Inglaterra',
            'france' => 'Francia',
            'germany' => 'Alemania',
            'ghana' => 'Ghana',
            'greece' => 'Grecia',
            'iran' => 'Iran',
            'ireland' => 'Irlanda',
            'italy' => 'Italia',
            'ivory coast' => 'Costa de Marfil',
            'japan' => 'Japon',
            'jordan' => 'Jordania',
            'korea republic' => 'Corea del Sur',
            'mexico' => 'Mexico',
            'morocco' => 'Marruecos',
            'netherlands' => 'Paises Bajos',
            'new zealand' => 'Nueva Zelanda',
            'nigeria' => 'Nigeria',
            'norway' => 'Noruega',
            'paraguay' => 'Paraguay',
            'peru' => 'Peru',
            'poland' => 'Polonia',
            'portugal' => 'Portugal',
            'qatar' => 'Qatar',
            'saudi arabia' => 'Arabia Saudita',
            'scotland' => 'Escocia',
            'senegal' => 'Senegal',
            'serbia' => 'Serbia',
            'south africa' => 'Sudafrica',
            'spain' => 'Espana',
            'sweden' => 'Suecia',
            'switzerland' => 'Suiza',
            'tunisia' => 'Tunez',
            'turkey' => 'Turquia',
            'ukraine' => 'Ucrania',
            'united states' => 'Estados Unidos',
            'uruguay' => 'Uruguay',
            'uzbekistan' => 'Uzbekistan',
            'wales' => 'Gales',
        ];
        $key = $this->normalizeLookupKey($name);

        return $teams[$key] ?? $name;
    }

    private function localizeStage(string $stage): string
    {
        if (preg_match('/^Group Stage\s*-\s*(\d+)$/', $stage, $matches) === 1) {
            return sprintf('Fase de grupos - Fecha %d', absint($matches[1]));
        }

        $replacements = [
            'Group Stage' => 'Fase de grupos',
            'Matchday' => 'Fecha',
            'Round of 16' => 'Octavos de final',
            'Quarter-finals' => 'Cuartos de final',
            'Semi-finals' => 'Semifinales',
            '3rd Place Final' => 'Tercer puesto',
            'Final' => 'Final',
        ];

        return trim(str_replace(array_keys($replacements), array_values($replacements), $stage));
    }

    private function localizeVenue(string $venue): string
    {
        if (preg_match('/^Stadium\s+(.+)$/', $venue, $matches) === 1) {
            return 'Estadio ' . trim((string) $matches[1]);
        }

        if (preg_match('/^(.+)\s+Stadium$/', $venue, $matches) === 1) {
            return 'Estadio ' . trim((string) $matches[1]);
        }

        return str_replace(' Stadium', ' Estadio', $venue);
    }

    private function normalizeLookupKey(string $value): string
    {
        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        }

        $value = strtolower($value);

        return trim($value);
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

    /**
     * @param array<string, mixed> $match
     */
    private function matchTooltip(array $match): string
    {
        $parts = [
            (string) $match['homeTeam'] . ' vs ' . (string) $match['awayTeam'],
            $this->formatFixtureTime((string) $match['date']),
            (string) $match['stage'],
            (string) $match['stadium'],
        ];

        return implode(' - ', array_filter($parts));
    }
}
