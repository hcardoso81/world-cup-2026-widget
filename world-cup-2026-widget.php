<?php
/**
 * Plugin Name: World Cup 2026 Widget
 * Description: WordPress shortcode that renders elegant FIFA World Cup match cards by date using API-Football fixtures.
 * Version: 1.0.4
 * Author: Hernan Cardoso
 * Author URI: https://www.linkedin.com/in/cardosohernan/
 * CV online: https://hcardoso81.github.io/professionalIdentity/
 * Text Domain: world-cup-2026-widget
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('WC26_WIDGET_VERSION', '1.0.4');
define('WC26_WIDGET_PATH', plugin_dir_path(__FILE__));
define('WC26_WIDGET_URL', plugin_dir_url(__FILE__));
define('WC26_WIDGET_BASENAME', plugin_basename(__FILE__));
define('WC26_WIDGET_TEXT_DOMAIN', 'world-cup-2026-widget');

require_once WC26_WIDGET_PATH . 'autoload.php';

register_shutdown_function(static function (): void {
    $error = error_get_last();

    if (!is_array($error)) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    $file = isset($error['file']) ? (string) $error['file'] : '';

    if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true) || strpos($file, WC26_WIDGET_PATH) !== 0) {
        return;
    }

    HernanCardoso\WorldCup2026Widget\Support\Logger::error('Fatal plugin error', [
        'message' => isset($error['message']) ? (string) $error['message'] : '',
        'file' => $file,
        'line' => isset($error['line']) ? (int) $error['line'] : 0,
    ]);
});

register_activation_hook(__FILE__, static function (): void {
    HernanCardoso\WorldCup2026Widget\Support\Logger::ensureDirectory();
    HernanCardoso\WorldCup2026Widget\Support\Logger::info('Plugin activated');
});

try {
    HernanCardoso\WorldCup2026Widget\Bootstrap\Plugin::boot();
} catch (Throwable $exception) {
    HernanCardoso\WorldCup2026Widget\Support\Logger::exception($exception, [
        'stage' => 'boot',
    ]);

    throw $exception;
}
