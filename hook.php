<?php

require_once(__DIR__ . '/inc/ticket_view_state.php');

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
