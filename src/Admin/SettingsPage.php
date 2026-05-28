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
        add_action('admin_menu', [$this, 'addOptionsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addOptionsPage(): void
    {
        add_options_page(
            __('World Cup 2026 Widget', WC26_WIDGET_TEXT_DOMAIN),
            __('World Cup 2026', WC26_WIDGET_TEXT_DOMAIN),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
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
        $sanitized = $this->settings->sanitize(is_array($input) ? $input : []);

        $this->clearFixturesCache($previous);
        $this->clearFixturesCache($sanitized);

        return $sanitized;
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->settings->all();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('World Cup 2026 Widget', WC26_WIDGET_TEXT_DOMAIN); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('wc26_widget_settings_group'); ?>

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
                    <tr>
                        <th scope="row">
                            <label for="wc26_widget_match_date"><?php echo esc_html__('Match date for custom shortcode', WC26_WIDGET_TEXT_DOMAIN); ?></label>
                        </th>
                        <td>
                            <input
                                id="wc26_widget_match_date"
                                class="regular-text"
                                type="date"
                                name="<?php echo esc_attr(Settings::OPTION_NAME); ?>[match_date]"
                                value="<?php echo esc_attr((string) $settings['match_date']); ?>"
                            />
                            <p class="description"><?php echo esc_html__('Used by [world_cup_2026_matches]. The API call is made server-side by WordPress, so it will not appear in the browser Network tab.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr />

            <h2><?php echo esc_html__('Ready-to-use shortcode', WC26_WIDGET_TEXT_DOMAIN); ?></h2>
            <?php if ($this->settings->apiKey() === '') : ?>
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
                <p>
                    <strong><?php echo esc_html__('Current API request:', WC26_WIDGET_TEXT_DOMAIN); ?></strong>
                    <code><?php echo esc_html((new ApiFootballClient($this->settings))->fixturesUrl($this->settings->matchDate())); ?></code>
                </p>
                <p class="description"><?php echo esc_html__('This request is executed by PHP with wp_remote_get and the API key in the x-apisports-key header.', WC26_WIDGET_TEXT_DOMAIN); ?></p>
            <?php endif; ?>

            <hr />

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
        </div>
        <?php
    }

    /**
     * @return array<string, string>
     */
    private function shortcodes(): array
    {
        return [
            __('Matches for selected backend date', WC26_WIDGET_TEXT_DOMAIN) => '[world_cup_2026_matches]',
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function clearFixturesCache(array $settings): void
    {
        $leagueId = isset($settings['league_id']) ? max(1, absint($settings['league_id'])) : Settings::DEFAULT_LEAGUE_ID;
        $season = isset($settings['season']) ? max(Settings::MIN_SEASON, absint($settings['season'])) : Settings::DEFAULT_SEASON;
        $date = isset($settings['match_date'])
            ? $this->settings->sanitizeDate((string) $settings['match_date'])
            : Settings::DEFAULT_MATCH_DATE;

        delete_transient(ApiFootballClient::cacheKeyFor($leagueId, $season, $date));
    }
}
