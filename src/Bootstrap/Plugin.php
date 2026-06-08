<?php

declare(strict_types=1);

namespace HernanCardoso\WorldCup2026Widget\Bootstrap;

use HernanCardoso\WorldCup2026Widget\Api\FixturesEndpoint;
use HernanCardoso\WorldCup2026Widget\Admin\SettingsPage;
use HernanCardoso\WorldCup2026Widget\Frontend\Shortcode;
use HernanCardoso\WorldCup2026Widget\Support\Settings;

final class Plugin
{
    public static function boot(): void
    {
        $plugin = new self();
        $plugin->registerHooks();
    }

    public function registerHooks(): void
    {
        $settings = new Settings();

        if (is_admin()) {
            (new SettingsPage($settings))->registerHooks();
        }

        (new Shortcode($settings))->registerHooks();
        (new FixturesEndpoint($settings))->registerHooks();
    }
}
