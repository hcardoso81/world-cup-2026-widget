<?php
/**
 * IDE-only WordPress function declarations.
 *
 * This file is not loaded by the plugin. It exists so static analyzers can
 * understand WordPress functions when the project is opened outside a full
 * WordPress installation.
 */

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file): string
    {
        return '';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file): string
    {
        return '';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file): string
    {
        return '';
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback): void
    {
    }
}
