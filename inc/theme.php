<?php

if (!function_exists('pgeservicos_theme_read_palette_vars')) {
    function pgeservicos_theme_read_palette_vars($palette) {
        $palette = preg_replace('/[^a-z0-9_\\-]/i', '', (string)$palette);
        $path = GLPI_ROOT . '/css/palettes/' . $palette . '.scss';

        if (!is_readable($path)) {
            return [];
        }

        $content = file_get_contents($path);
        $vars = [];

        foreach (['primary', 'primary-fg', 'link-color'] as $name) {
            if (preg_match('/\\$' . preg_quote($name, '/') . '\\s*:\\s*(#[0-9a-fA-F]{3,6})\\s*;/', $content, $match)) {
                $vars[$name] = pgeservicos_theme_normalize_hex($match[1]);
            }
        }

        return $vars;
    }
}

if (!function_exists('pgeservicos_theme_normalize_hex')) {
    function pgeservicos_theme_normalize_hex($hex) {
        $hex = ltrim(trim((string)$hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return '#' . strtolower(substr($hex, 0, 6));
    }
}

if (!function_exists('pgeservicos_theme_rgb')) {
    function pgeservicos_theme_rgb($hex) {
        $hex = ltrim(pgeservicos_theme_normalize_hex($hex), '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}

if (!function_exists('pgeservicos_theme_luminance')) {
    function pgeservicos_theme_luminance($hex) {
        $rgb = pgeservicos_theme_rgb($hex);
        $linear = [];

        foreach ($rgb as $channel) {
            $value = $channel / 255;
            $linear[] = $value <= 0.03928
                ? $value / 12.92
                : pow(($value + 0.055) / 1.055, 2.4);
        }

        return (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);
    }
}

if (!function_exists('pgeservicos_theme_contrast')) {
    function pgeservicos_theme_contrast($first, $second) {
        $first_luminance = pgeservicos_theme_luminance($first);
        $second_luminance = pgeservicos_theme_luminance($second);
        $lighter = max($first_luminance, $second_luminance);
        $darker = min($first_luminance, $second_luminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}

if (!function_exists('pgeservicos_theme_best_text_on')) {
    function pgeservicos_theme_best_text_on($background) {
        return pgeservicos_theme_contrast('#000000', $background) >= pgeservicos_theme_contrast('#ffffff', $background)
            ? '#000000'
            : '#ffffff';
    }
}

if (!function_exists('pgeservicos_theme_print_vars')) {
    function pgeservicos_theme_print_vars($selector = '.pgeservicos-container, .pgegestor-page') {
        $palette = $_SESSION['glpipalette'] ?? 'auror';
        $vars = pgeservicos_theme_read_palette_vars($palette);

        $primary = $vars['primary'] ?? '#2f7ecb';
        $primary_fg = $vars['primary-fg'] ?? pgeservicos_theme_best_text_on($primary);
        $link_color = $vars['link-color'] ?? $primary;
        $surface = '#ffffff';
        $body_text = '#1e293b';

        $accent_text = pgeservicos_theme_contrast($link_color, $surface) >= 4.5
            ? $link_color
            : (pgeservicos_theme_contrast($primary, $surface) >= 4.5 ? $primary : $body_text);

        echo "<style>"
            . $selector
            . "{--pgeservicos-primary:" . $primary . ";"
            . "--pgeservicos-on-primary:" . $primary_fg . ";"
            . "--pgeservicos-accent-text:" . $accent_text . ";}"
            . "</style>";
    }
}
