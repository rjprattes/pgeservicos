<?php

require_once(__DIR__ . '/inc/ticket_view_state.php');

/**
 * Redireciona somente as telas iniciais nativas do GLPI para o portal do plugin.
 */
function plugin_pgeservicos_redirect_home() {
    global $CFG_GLPI;

    if (isCommandLine()) {
        return;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }

    if (!class_exists('Session') || empty($_SESSION['glpiID'])) {
        return;
    }

    $requested_with = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    $request_uri    = $_SERVER['REQUEST_URI'] ?? '';

    if ($requested_with === 'xmlhttprequest' || strpos($request_uri, '/ajax/') !== false) {
        return;
    }

    $route = pgeservicos_get_current_glpi_route();
    $home_routes = [
        '/front/central.php',
        '/front/helpdesk.public.php',
    ];

    if (!in_array($route, $home_routes, true)) {
        return;
    }

    if (count($_GET) > 0) {
        return;
    }

    $portal_file = __DIR__ . '/front/index.php';

    if (!is_file($portal_file)) {
        return;
    }

    Html::redirect(($CFG_GLPI['root_doc'] ?? '') . '/plugins/pgeservicos/front/index.php');
    exit;
}

/**
 * Retorna a rota atual sem o root_doc do GLPI.
 */
function pgeservicos_get_current_glpi_route() {
    global $CFG_GLPI;

    $path = parse_url($_SERVER['PHP_SELF'] ?? '', PHP_URL_PATH);
    $path = is_string($path) ? $path : '';
    $root_doc = rtrim((string)($CFG_GLPI['root_doc'] ?? ''), '/');

    if ($root_doc !== '' && strpos($path, $root_doc . '/') === 0) {
        $path = substr($path, strlen($root_doc));
    }

    return '/' . ltrim($path, '/');
}

/**
 * Executado ao instalar o plugin.
 */
function plugin_pgeservicos_install() {
    return pgeservicos_ensure_ticket_views_table();
}

/**
 * Executado ao atualizar o plugin.
 */
function plugin_pgeservicos_upgrade($old_version = '') {
    return pgeservicos_ensure_ticket_views_table();
}

/**
 * Executado ao desinstalar o plugin.
 */
function plugin_pgeservicos_uninstall() {
    return true;
}
