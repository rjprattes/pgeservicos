<?php

define('PLUGIN_PGESERVICOS_VERSION', '0.0.10');

/**
 * Inicialização do plugin.
 */
function plugin_init_pgeservicos() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['pgeservicos'] = true;

    require_once(__DIR__ . '/inc/plugin_state.php');

    if (!pgeservicos_is_plugin_active()) {
        return;
    }

    Plugin::registerClass('PluginPgeservicosPortal');

    Plugin::registerClass('PluginPgeservicosProfile', [
        'addtabon' => ['Profile']
    ]);

    $PLUGIN_HOOKS['menu_toadd']['pgeservicos'] = [
        'tools' => 'PluginPgeservicosPortal'
    ];

    $PLUGIN_HOOKS['post_init']['pgeservicos'] = 'plugin_pgeservicos_redirect_home';

    $PLUGIN_HOOKS['add_css']['pgeservicos'][] = 'css/formcreator-pge.css';
    $PLUGIN_HOOKS['add_css']['pgeservicos'][] = 'css/shared/pgeservicos-topbar.css';
    $PLUGIN_HOOKS['add_javascript']['pgeservicos'][] = 'js/formcreator-pge.js';
    $PLUGIN_HOOKS['add_javascript']['pgeservicos'][] = 'js/shared/pgeservicos-topbar.js';
    $PLUGIN_HOOKS['add_javascript']['pgeservicos'][] = 'js/shared/pgeservicos-sidebar-links.js';
}

/**
 * Informações exibidas em Configurar > Plugins.
 */
function plugin_version_pgeservicos() {
    return [
        'name'           => 'PGE Serviços',
        'version'        => PLUGIN_PGESERVICOS_VERSION,
        'author'         => 'PGE-ES / GIN',
        'license'        => 'GPLv2+',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => '10.0.0',
                'max' => '11.0.0'
            ]
        ]
    ];
}

/**
 * Verifica pré-requisitos antes da instalação.
 */
function plugin_pgeservicos_check_prerequisites() {
    return true;
}

/**
 * Verifica configuração do plugin.
 */
function plugin_pgeservicos_check_config($verbose = false) {
    return true;
}
