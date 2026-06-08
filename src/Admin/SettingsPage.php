<?php

declare(strict_types=1);

namespace HernanCardoso\WorldCup2026Widget\Admin;

use HernanCardoso\WorldCup2026Widget\Api\ApiFootballClient;
use HernanCardoso\WorldCup2026Widget\Support\Logger;
use HernanCardoso\WorldCup2026Widget\Support\Settings;

final class SettingsPage
{
    private const PAGE_SLUG = 'wc26-widget-settings';

    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenuPage(): void
    {
        add_menu_page(
            __('World Cup 2026 Widget', WC26_WIDGET_TEXT_DOMAIN),
            __('World Cup 2026', WC26_WIDGET_TEXT_DOMAIN),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render'],
            $this->menuIcon(),
            58
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            'wc26_widget_settings_group',
            Settings::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default' => $this->settings->defaults(),
            ]
        );
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public function sanitize($input): array
    {
        $previous = $this->settings->all();
        $raw = array_replace($previous, is_array($input) ? $input : []);
        $sanitized = $this->settings->sanitize($raw);

        $this->clearFixturesCache($previous);
        $this->clearFixturesCache($sanitized);
        $this->clearFixturesCacheForDate($previous, $this->settings->currentArgentinaDate());
        $this->clearFixturesCacheForDate($sanitized, $this->settings->currentArgentinaDate());

        return $sanitized;
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->settings->all();
        $activeTab = $this->activeTab();
        ?>
        <div class="wrap">
            <?php $this->renderAdminStyles(); ?>

            <div class="wc26-admin-header">
                <span class="wc26-admin-header__icon" aria-hidden="true"><?php echo $this->trophySvg(); ?></span>
                <div>
                    <h1><?php echo esc_html__('World Cup 2026 Widget', WC26_WIDGET_TEXT_DOMAIN); ?></h1>
                    <p><?php echo esc_html__('Configuracion del shortcode propio de partidos.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
                </div>
            </div>

            <?php settings_errors(Settings::OPTION_NAME); ?>

            <nav class="nav-tab-wrapper wc26-admin-tabs" aria-label="<?php echo esc_attr__('World Cup 2026 settings tabs', WC26_WIDGET_TEXT_DOMAIN); ?>">
                <a class="nav-tab <?php echo $activeTab === 'backend' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($this->tabUrl('backend')); ?>">
                    <?php echo esc_html__('Backend', WC26_WIDGET_TEXT_DOMAIN); ?>
                </a>
                <a class="nav-tab <?php echo $activeTab === 'frontend' ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($this->tabUrl('frontend')); ?>">
                    <?php echo esc_html__('Front end', WC26_WIDGET_TEXT_DOMAIN); ?>
                </a>
            </nav>

            <form method="post" action="options.php">
                <?php settings_fields('wc26_widget_settings_group'); ?>

                <?php if ($activeTab === 'backend') : ?>
                    <?php $this->renderBackendTab($settings); ?>
                <?php else : ?>
                    <?php $this->renderFrontendTab($settings); ?>
                <?php endif; ?>

                <?php submit_button(); ?>
            </form>

            <?php if ($activeTab === 'backend') : ?>
                <?php $this->renderLogs(); ?>
            <?php else : ?>
                <?php $this->renderShortcodeInfo(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function renderBackendTab(array $settings): void
    {
        ?>
        <section class="wc26-admin-panel">
            <h2><?php echo esc_html__('Backend', WC26_WIDGET_TEXT_DOMAIN); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="wc26_widget_api_key"><?php echo esc_html__('API-Football key', WC26_WIDGET_TEXT_DOMAIN); ?></label>
                    </th>
                    <td>
                        <input
                            id="wc26_widget_api_key"
                            class="regular-text"
                            type="password"
                            name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[api_key]"
                            value="<?php echo esc_attr((string) $settings['api_key']); ?>"
                            autocomplete="off"
                        />
                        <p class="description"><?php echo esc_html__('Paste your API-Sports / API-Football key here.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wc26_widget_league_id"><?php echo esc_html__('League ID', WC26_WIDGET_TEXT_DOMAIN); ?></label>
                    </th>
                    <td>
                        <input
                            id="wc26_widget_league_id"
                            class="small-text"
                            type="number"
                            min="1"
                            name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[league_id]"
                            value="<?php echo esc_attr((string) $settings['league_id']); ?>"
                        />
                        <p class="description"><?php echo esc_html__('API-Football league ID. World Cup is usually 1; use another ID only if your plan supports that league.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wc26_widget_season"><?php echo esc_html__('Season', WC26_WIDGET_TEXT_DOMAIN); ?></label>
                    </th>
                    <td>
                        <input
                            id="wc26_widget_season"
                            class="small-text"
                            type="number"
                            min="<?php echo esc_attr((string) Settings::MIN_SEASON); ?>"
                            name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[season]"
                            value="<?php echo esc_attr((string) $settings['season']); ?>"
                        />
                        <p class="description"><?php echo esc_html__('API-Football season. It must be allowed by your plan for the selected league and date.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
            </table>

            <details class="wc26-admin-accordion" <?php echo !empty($settings['simulation_enabled']) ? 'open' : ''; ?>>
                <summary><?php echo esc_html__('Simulacion', WC26_WIDGET_TEXT_DOMAIN); ?></summary>
                <div class="wc26-admin-accordion__body">
                    <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[simulation_enabled]" value="0" />
                    <input
                        id="wc26_widget_simulation_enabled"
                        class="wc26-admin-check__input"
                        type="checkbox"
                        name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[simulation_enabled]"
                        value="1"
                        <?php checked(!empty($settings['simulation_enabled'])); ?>
                    />
                    <label class="wc26-admin-check" for="wc26_widget_simulation_enabled">
                        <span><?php echo esc_html__('Habilitar simulacion', WC26_WIDGET_TEXT_DOMAIN); ?></span>
                    </label>
                    <div class="wc26-simulation-date">
                        <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[simulation_mock_enabled]" value="0" />
                        <label class="wc26-admin-check" for="wc26_widget_simulation_mock_enabled">
                            <input
                                id="wc26_widget_simulation_mock_enabled"
                                type="checkbox"
                                name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[simulation_mock_enabled]"
                                value="1"
                                <?php checked(!empty($settings['simulation_mock_enabled'])); ?>
                            />
                            <span><?php echo esc_html__('Usar mockup con datos hardcodeados', WC26_WIDGET_TEXT_DOMAIN); ?></span>
                        </label>
                        <p class="description"><?php echo esc_html__('Cuando esta opcion esta activa, el shortcode usa fixtures de prueba de la Copa y no llama a API-Football.', WC26_WIDGET_TEXT_DOMAIN); ?></p>

                        <label for="wc26_widget_match_date"><?php echo esc_html__('Match date for custom shortcode', WC26_WIDGET_TEXT_DOMAIN); ?></label>
                        <input
                            id="wc26_widget_match_date"
                            class="regular-text"
                            type="date"
                            name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[match_date]"
                            value="<?php echo esc_attr((string) $settings['match_date']); ?>"
                        />
                        <p class="description"><?php echo esc_html__('Solo se usa cuando la simulacion esta habilitada. Con simulacion apagada, el shortcode usa la fecha actual de Argentina.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
                    </div>
                </div>
            </details>
        </section>
        <?php
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function renderFrontendTab(array $settings): void
    {
        ?>
        <section class="wc26-admin-panel">
            <h2><?php echo esc_html__('Front end', WC26_WIDGET_TEXT_DOMAIN); ?></h2>
            <input type="hidden" name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[frontend_visible]" value="0" />
            <label class="wc26-admin-check" for="wc26_widget_frontend_visible">
                <input
                    id="wc26_widget_frontend_visible"
                    type="checkbox"
                    name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[frontend_visible]"
                    value="1"
                    <?php checked(!empty($settings['frontend_visible'])); ?>
                />
                <span><?php echo esc_html__('Mostrar widget en el shortcode', WC26_WIDGET_TEXT_DOMAIN); ?></span>
            </label>
            <p class="description"><?php echo esc_html__('Si esta opcion esta desmarcada, [world_cup_2026_matches] no renderiza contenido.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
            <p class="description">
                <?php echo esc_html__('Fecha normal del shortcode:', WC26_WIDGET_TEXT_DOMAIN); ?>
                <code><?php echo esc_html($this->settings->currentArgentinaDate()); ?></code>
                <?php echo esc_html__('(Argentina)', WC26_WIDGET_TEXT_DOMAIN); ?>
            </p>
        </section>
        <?php
    }

    private function renderShortcodeInfo(): void
    {
        ?>
        <section class="wc26-admin-panel">
            <h2><?php echo esc_html__('Ready-to-use shortcode', WC26_WIDGET_TEXT_DOMAIN); ?></h2>
            <?php if ($this->settings->apiKey() === '' && !$this->settings->shouldUseMockFixtures()) : ?>
                <p><?php echo esc_html__('Save your API-Football key to generate the copy/paste shortcode.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
            <?php else : ?>
                <p><?php echo esc_html__('Copy this shortcode and paste it into any page, post, widget area or block that supports shortcodes.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
                <table class="widefat striped wc26-shortcodes-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Use case', WC26_WIDGET_TEXT_DOMAIN); ?></th>
                            <th><?php echo esc_html__('Shortcode', WC26_WIDGET_TEXT_DOMAIN); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->shortcodes() as $label => $shortcode) : ?>
                            <tr>
                                <td><?php echo esc_html($label); ?></td>
                                <td>
                                    <input class="large-text code" readonly type="text" value="<?php echo esc_attr($shortcode); ?>" onclick="this.select();" />
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($this->settings->shouldUseMockFixtures()) : ?>
                    <p>
                        <strong><?php echo esc_html__('Current data source:', WC26_WIDGET_TEXT_DOMAIN); ?></strong>
                        <code><?php echo esc_html__('Mockup hardcodeado', WC26_WIDGET_TEXT_DOMAIN); ?></code>
                    </p>
                    <p class="description"><?php echo esc_html__('No API-Football request is executed while simulation mockup is enabled.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
                <?php else : ?>
                    <p>
                        <strong><?php echo esc_html__('Current API request:', WC26_WIDGET_TEXT_DOMAIN); ?></strong>
                        <code><?php echo esc_html((new ApiFootballClient($this->settings))->fixturesUrl($this->settings->shortcodeDate())); ?></code>
                    </p>
                    <p class="description"><?php echo esc_html__('This request is executed by PHP with wp_remote_get and the API key in the x-apisports-key header.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php
    }

    private function renderLogs(): void
    {
        ?>
        <section class="wc26-admin-panel">
            <h2><?php echo esc_html__('Plugin logs', WC26_WIDGET_TEXT_DOMAIN); ?></h2>
            <p>
                <?php echo esc_html__('Recent plugin errors and warnings are stored here:', WC26_WIDGET_TEXT_DOMAIN); ?>
                <code><?php echo esc_html(Logger::path()); ?></code>
            </p>

            <?php $logLines = Logger::recentLines(80); ?>
            <?php if ($logLines === []) : ?>
                <p><?php echo esc_html__('No log entries yet.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
            <?php else : ?>
                <textarea class="large-text code" rows="14" readonly><?php echo esc_textarea(implode("\n", $logLines)); ?></textarea>
            <?php endif; ?>
        </section>
        <?php
    }

    /**
     * @return array<string, string>
     */
    private function shortcodes(): array
    {
        return [
            __('Matches for current Argentina date', WC26_WIDGET_TEXT_DOMAIN) => '[world_cup_2026_matches]',
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function clearFixturesCache(array $settings): void
    {
        $this->clearFixturesCacheForDate(
            $settings,
            isset($settings['match_date'])
                ? $this->settings->sanitizeDate((string) $settings['match_date'])
                : Settings::DEFAULT_MATCH_DATE
        );
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function clearFixturesCacheForDate(array $settings, string $date): void
    {
        $leagueId = isset($settings['league_id']) ? max(1, absint($settings['league_id'])) : Settings::DEFAULT_LEAGUE_ID;
        $season = isset($settings['season']) ? max(Settings::MIN_SEASON, absint($settings['season'])) : Settings::DEFAULT_SEASON;

        delete_transient(ApiFootballClient::cacheKeyFor($leagueId, $season, $date));
    }

    private function activeTab(): string
    {
        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'backend';

        return in_array($tab, ['backend', 'frontend'], true) ? $tab : 'backend';
    }

    private function tabUrl(string $tab): string
    {
        return add_query_arg(
            [
                'page' => self::PAGE_SLUG,
                'tab' => $tab,
            ],
            admin_url('admin.php')
        );
    }

    private function menuIcon(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M6 2h8v2h3v3c0 2.4-1.7 4.4-4 4.9V14h2v2H5v-2h2v-2.1C4.7 11.4 3 9.4 3 7V4h3V2Zm2 2v4c0 1.1.9 2 2 2s2-.9 2-2V4H8ZM5 6v1c0 1.2.8 2.3 2 2.8V6H5Zm8 3.8c1.2-.5 2-1.6 2-2.8V6h-2v3.8Z"/></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    private function trophySvg(): string
    {
        return '<svg viewBox="0 0 20 20" focusable="false" aria-hidden="true"><path d="M6 2h8v2h3v3c0 2.4-1.7 4.4-4 4.9V14h2v2H5v-2h2v-2.1C4.7 11.4 3 9.4 3 7V4h3V2Zm2 2v4c0 1.1.9 2 2 2s2-.9 2-2V4H8ZM5 6v1c0 1.2.8 2.3 2 2.8V6H5Zm8 3.8c1.2-.5 2-1.6 2-2.8V6h-2v3.8Z"/></svg>';
    }

    private function renderAdminStyles(): void
    {
        ?>
        <style>
            .wc26-admin-header {
                align-items: center;
                background: #0b1f18;
                border-radius: 8px;
                color: #fff;
                display: flex;
                gap: 16px;
                margin: 18px 0;
                padding: 18px 20px;
            }

            .wc26-admin-header h1 {
                color: #fff;
                margin: 0;
                padding: 0;
            }

            .wc26-admin-header p {
                color: #d9efe7;
                margin: 4px 0 0;
            }

            .wc26-admin-header__icon {
                align-items: center;
                background: #d6ad42;
                border-radius: 8px;
                display: flex;
                height: 44px;
                justify-content: center;
                width: 44px;
            }

            .wc26-admin-header__icon svg {
                fill: #0b1f18;
                height: 28px;
                width: 28px;
            }

            .wc26-admin-tabs {
                margin-bottom: 0;
            }

            .wc26-admin-panel {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-top: 0;
                margin: 0 0 20px;
                max-width: 980px;
                padding: 20px;
            }

            .wc26-admin-panel h2 {
                margin-top: 0;
            }

            .wc26-admin-check {
                align-items: center;
                display: inline-flex;
                font-weight: 600;
                gap: 8px;
                margin: 8px 0;
            }

            .wc26-admin-accordion {
                border: 1px solid #dcdcde;
                border-radius: 8px;
                margin-top: 16px;
                overflow: hidden;
            }

            .wc26-admin-accordion summary {
                background: #f6f7f7;
                cursor: pointer;
                font-weight: 700;
                padding: 14px 16px;
            }

            .wc26-admin-accordion__body {
                padding: 16px;
            }

            .wc26-simulation-date {
                display: none;
                margin-top: 14px;
            }

            #wc26_widget_simulation_enabled:checked ~ .wc26-simulation-date {
                display: block;
            }

            .wc26-simulation-date label {
                display: block;
                font-weight: 600;
                margin-bottom: 6px;
            }
        </style>
        <?php
    }
}
