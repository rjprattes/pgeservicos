<?php

if (!function_exists('pgeservicos_is_plugin_active')) {
    /**
     * Retorna true somente quando o plugin esta realmente ativo no GLPI.
     */
    function pgeservicos_is_plugin_active() {
        if (!class_exists('Plugin') || !method_exists('Plugin', 'isPluginActive')) {
            return false;
        }

        try {
            return Plugin::isPluginActive('pgeservicos');
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('pgeservicos_is_json_request')) {
    /**
     * Identifica endpoints que devem responder JSON quando o plugin esta inativo.
     */
    function pgeservicos_is_json_request() {
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
        $requested_with = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));

        return strpos($script, 'ajax_') === 0
            || $requested_with === 'xmlhttprequest'
            || strpos($accept, 'application/json') !== false;
    }
}

if (!function_exists('pgeservicos_require_plugin_active')) {
    /**
     * Bloqueia qualquer entrada do plugin quando ele esta instalado, mas desativado.
     */
    function pgeservicos_require_plugin_active($json_response = null) {
        global $CFG_GLPI;

        if (pgeservicos_is_plugin_active()) {
            return;
        }

        $json_response = $json_response ?? pgeservicos_is_json_request();

        if ($json_response) {
            if (!headers_sent()) {
                http_response_code(503);
                header('Content-Type: application/json; charset=UTF-8');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            }

            echo json_encode([
                'ok'      => false,
                'success' => false,
                'message' => 'Plugin desativado.',
            ]);
            exit;
        }

        $central_url = ($CFG_GLPI['root_doc'] ?? '') . '/front/central.php?redirect=';

        if (class_exists('Html') && !headers_sent()) {
            Html::redirect($central_url);
            exit;
        }

        echo 'O Portal de Serviços está desativado.';
        exit;
    }
}
