<?php

if (!function_exists('pgeservicos_theme_normalize_hex')) {
    function pgeservicos_theme_normalize_hex($hex) {
        $hex = ltrim(trim((string)$hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return '#' . strtolower(substr($hex, 0, 6));
    }
}

if (!function_exists('pgeservicos_theme_resolve_scss_import')) {
    function pgeservicos_theme_resolve_scss_import($import, $base_dir) {
        $import = trim((string)$import, " \t\n\r\0\x0B'\"");

        if ($import === '' || preg_match('/^[a-z]+:/i', $import)) {
            return '';
        }

        $candidate = $base_dir . '/' . $import;
        $directory = dirname($candidate);
        $basename = basename($candidate);
        $paths = [
            $candidate,
            $candidate . '.scss',
            $directory . '/_' . $basename,
            $directory . '/_' . $basename . '.scss',
        ];
        $css_root = realpath(GLPI_ROOT . '/css') ?: '';

        foreach ($paths as $path) {
            $real = realpath($path);

            if ($real !== false && is_readable($real) && ($css_root === '' || strpos($real, $css_root) === 0)) {
                return $real;
            }
        }

        return '';
    }
}

if (!function_exists('pgeservicos_theme_read_scss_vars')) {
    function pgeservicos_theme_read_scss_vars($path, array &$visited = []) {
        $real = realpath($path);

        if ($real === false || !is_readable($real) || isset($visited[$real])) {
            return [];
        }

        $visited[$real] = true;
        $content = file_get_contents($real);
        $vars = [];

        if (preg_match_all('/@import\s+["\']([^"\']+)["\']\s*;/', $content, $imports)) {
            foreach ($imports[1] as $import) {
                $import_path = pgeservicos_theme_resolve_scss_import($import, dirname($real));

                if ($import_path !== '') {
                    $vars = array_merge($vars, pgeservicos_theme_read_scss_vars($import_path, $visited));
                }
            }
        }

        foreach ([
            'primary',
            'primary-fg',
            'secondary',
            'secondary-fg',
            'fg-secondary',
            'link-color',
            'mainmenu_bg',
            'mainmenu_fg',
        ] as $name) {
            if (preg_match('/\\$' . preg_quote($name, '/') . '\\s*:\\s*(#[0-9a-fA-F]{3,6})\\s*(?:!default)?\\s*;/', $content, $match)) {
                $vars[$name] = pgeservicos_theme_normalize_hex($match[1]);
            }
        }

        return $vars;
    }
}

if (!function_exists('pgeservicos_theme_read_palette_vars')) {
    function pgeservicos_theme_read_palette_vars($palette) {
        $palette = preg_replace('/[^a-z0-9_\\-]/i', '', (string)$palette);
        $path = GLPI_ROOT . '/css/palettes/' . $palette . '.scss';
        $visited = [];

        return pgeservicos_theme_read_scss_vars($path, $visited);
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

if (!function_exists('pgeservicos_theme_current_palette')) {
    function pgeservicos_theme_current_palette() {
        $palette = $_SESSION['glpipalette'] ?? '';

        if ($palette === '' && class_exists('Config')) {
            $palette = (string)Config::getConfigurationValue('core', 'palette');
        }

        return $palette !== '' ? $palette : 'auror';
    }
}

if (!function_exists('pgeservicos_theme_values')) {
    function pgeservicos_theme_values() {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $vars = pgeservicos_theme_read_palette_vars(pgeservicos_theme_current_palette());
        $primary = $vars['primary'] ?? '#fec95c';
        $primary_fg = $vars['primary-fg'] ?? pgeservicos_theme_best_text_on($primary);
        $menu_bg = $vars['mainmenu_bg'] ?? '#2f3f64';
        $menu_fg = $vars['mainmenu_fg'] ?? pgeservicos_theme_best_text_on($menu_bg);
        $link_color = $vars['link-color'] ?? $primary;
        $surface = '#ffffff';
        $body_text = '#1e293b';
        $heading = '#111827';
        $muted = '#5f6b7a';
        $border = '#e5e7eb';
        $border_light = '#f2f3f4';
        $bg = '#f5f7fb';
        $primary_rgb = implode(', ', pgeservicos_theme_rgb($primary));
        $surface_rgb = implode(', ', pgeservicos_theme_rgb($surface));
        $accent_text = pgeservicos_theme_contrast($link_color, $surface) >= 4.5
            ? $link_color
            : (pgeservicos_theme_contrast($primary, $surface) >= 4.5 ? $primary : $body_text);

        $cache = [
            '--pgeservicos-primary' => $primary,
            '--pgeservicos-primary-rgb' => $primary_rgb,
            '--pgeservicos-primary-soft' => 'rgba(' . $primary_rgb . ', 0.12)',
            '--pgeservicos-primary-softer' => 'rgba(' . $primary_rgb . ', 0.07)',
            '--pgeservicos-on-primary' => $primary_fg,
            '--pgeservicos-primary-contrast' => $primary_fg,
            '--pgeservicos-accent-text' => $accent_text,
            '--pgeservicos-menu-bg' => $menu_bg,
            '--pgeservicos-menu-color' => $menu_fg,
            '--pgeservicos-current-sidebar-bg' => $menu_bg,
            '--pgeservicos-current-sidebar-color' => $menu_fg,
            '--pgeservicos-action-bg' => $primary,
            '--pgeservicos-action-color' => $primary_fg,
            '--pgeservicos-action-readable-color' => $primary_fg,
            '--pgeservicos-action-accent' => $primary,
            '--pgeservicos-action-accent-rgb' => $primary_rgb,
            '--pgeservicos-action-accent-fg' => $primary_fg,
            '--pgeservicos-toast-bg' => $primary,
            '--pgeservicos-toast-color' => $primary_fg,
            '--pgeservicos-scroll-btn-bg' => $primary,
            '--pgeservicos-scroll-btn-color' => $primary_fg,
            '--pgeservicos-surface' => $surface,
            '--pgeservicos-surface-rgb' => $surface_rgb,
            '--pgeservicos-bg' => $bg,
            '--pgeservicos-text' => $body_text,
            '--pgeservicos-heading' => $heading,
            '--pgeservicos-muted' => $muted,
            '--pgeservicos-border' => $border,
            '--pgeservicos-border-light' => $border_light,
            '--pgeservicos-fc-primary' => $primary,
            '--pgeservicos-fc-primary-rgb' => $primary_rgb,
            '--pgeservicos-fc-primary-soft' => 'rgba(' . $primary_rgb . ', 0.12)',
            '--pgeservicos-fc-primary-softer' => 'rgba(' . $primary_rgb . ', 0.07)',
            '--pgeservicos-fc-on-primary' => $primary_fg,
            '--pgeservicos-fc-header-bg' => $menu_bg,
            '--pgeservicos-fc-header-color' => $menu_fg,
        ];

        return $cache;
    }
}

if (!function_exists('pgeservicos_theme_css_declarations')) {
    function pgeservicos_theme_css_declarations() {
        $declarations = '';

        foreach (pgeservicos_theme_values() as $name => $value) {
            $declarations .= $name . ':' . $value . ';';
        }

        return $declarations;
    }
}

if (!function_exists('pgeservicos_theme_style_attr')) {
    function pgeservicos_theme_style_attr() {
        return htmlspecialchars(pgeservicos_theme_css_declarations(), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('pgeservicos_theme_topbar_context_attr')) {
    function pgeservicos_theme_topbar_context_attr() {
        return " data-pgeservicos-custom-topbar='1'";
    }
}

if (!function_exists('pgeservicos_theme_print_vars')) {
    function pgeservicos_theme_print_vars($selector = '.pgeservicos-container, .pgegestor-page, .pgeservicos-formcreator-page, .pgeservicos-formcreator-shell, [data-pgeservicos-custom-topbar="1"]') {
        echo "<style>" . $selector . "{" . pgeservicos_theme_css_declarations() . "}</style>";
    }
}

if (!function_exists('pgeservicos_theme_print_sidebar_sync_script')) {
    function pgeservicos_theme_print_sidebar_sync_script($root_doc, $asset_version = '1') {
        echo "<script defer src='"
            . htmlspecialchars((string)$root_doc, ENT_QUOTES, 'UTF-8')
            . "/plugins/pgeservicos/js/shared/pgeservicos-theme.js?v="
            . htmlspecialchars((string)$asset_version, ENT_QUOTES, 'UTF-8')
            . "'></script>";
    }
}
