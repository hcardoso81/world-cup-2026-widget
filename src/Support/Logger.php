<?php

declare(strict_types=1);

namespace HernanCardoso\WorldCup2026Widget\Support;

use Throwable;

final class Logger
{
    private const LOG_FILE = 'plugin.log';
    private const MAX_BYTES = 1048576;

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function exception(Throwable $exception, array $context = []): void
    {
        self::write('exception', $exception->getMessage(), array_merge($context, [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]));
    }

    /**
     * @return array<int, string>
     */
    public static function recentLines(int $lines = 80): array
    {
        $path = self::path();

        if (!is_readable($path)) {
            return [];
        }

        $content = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if (!is_array($content)) {
            return [];
        }

        return array_slice($content, -absint($lines));
    }

    public static function path(): string
    {
        return WC26_WIDGET_PATH . 'logs/' . self::LOG_FILE;
    }

    public static function ensureDirectory(): void
    {
        $directory = WC26_WIDGET_PATH . 'logs';

        if (!is_dir($directory)) {
            wp_mkdir_p($directory);
        }

        $index = $directory . '/index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\n");
        }

        $htaccess = $directory . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n");
        }
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        if (!defined('WC26_WIDGET_PATH')) {
            return;
        }

        self::ensureDirectory();
        self::rotateIfNeeded();

        $entry = sprintf(
            "[%s] %s: %s %s\n",
            gmdate('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : wp_json_encode(self::sanitizeContext($context))
        );

        @file_put_contents(self::path(), $entry, FILE_APPEND | LOCK_EX);
    }

    private static function rotateIfNeeded(): void
    {
        $path = self::path();

        if (!file_exists($path) || filesize($path) < self::MAX_BYTES) {
            return;
        }

        @rename($path, $path . '.1');
    }

    private static function sanitizeContext(array $context): array
    {
        $redacted = [];

        foreach ($context as $key => $value) {
            $keyString = is_string($key) ? $key : (string) $key;

            if (preg_match('/key|token|secret|password/i', $keyString)) {
                $redacted[$keyString] = '[redacted]';
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $redacted[$keyString] = $value;
                continue;
            }

            $redacted[$keyString] = wp_json_encode($value);
        }

        return $redacted;
    }
}
