<?php

require_once(__DIR__ . '/inc/plugin_state.php');
require_once(__DIR__ . '/inc/ticket_view_state.php');

/**
 * Redireciona somente as telas iniciais nativas do GLPI para o portal do plugin.
 */
function plugin_pgeservicos_redirect_home() {
    global $CFG_GLPI;

    if (!pgeservicos_is_plugin_active()) {
        return;
    }

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

    if (!pgeservicos_is_native_home_route($route)) {
        return;
    }

    if (pgeservicos_home_request_has_specific_target($_GET)) {
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
 * Rotas que representam home/entrada nativa do GLPI ou do catálogo helpdesk.
 */
function pgeservicos_is_native_home_route($route) {
    $home_routes = [
        '/front/central.php',
        '/front/helpdesk.public.php',
        '/front/helpdesk.php',
        '/front/dashboard_helpdesk.php',
        '/plugins/formcreator/front/wizard.php',
        '/plugins/formcreator/front/formlist.php',
        '/plugins/formcreator/front/issue.php',
    ];

    return in_array($route, $home_routes, true);
}

/**
 * Mantem acesso direto a recursos especificos do GLPI sem sequestrar a navegacao.
 */
function pgeservicos_home_request_has_specific_target(array $query) {
    if (count($query) === 0) {
        return false;
    }

    $specific_keys = [
        'id',
        'tickets_id',
        'items_id',
        'itemtype',
        'form_id',
        'forms_id',
        'reservationitems_id',
        'create_ticket',
        'active_entity',
        'newprofile',
        'redirect',
        'forcetab',
        'glpi_tab',
        'action',
        'add',
        'delete',
        'update',
        'criteria',
        'search',
        'start',
    ];

    foreach ($specific_keys as $key) {
        if (array_key_exists($key, $query)) {
            return true;
        }
    }

    return false;
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
