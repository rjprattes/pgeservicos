<?php

if (!defined('GLPI_KEEP_CSRF_TOKEN')) {
    define('GLPI_KEEP_CSRF_TOKEN', true);
}

include('../../../inc/includes.php');

require_once(__DIR__ . '/../inc/plugin_state.php');
pgeservicos_require_plugin_active();

Session::checkLoginUser();

global $CFG_GLPI;

require_once(__DIR__ . '/../inc/satisfaction.php');

$root_doc = $CFG_GLPI['root_doc'] ?? '';
$manage_url = $root_doc . '/plugins/pgeservicos/front/satisfacao_surveys.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    Html::redirect($manage_url);
    exit;
}

if (GLPI_USE_CSRF_CHECK && !Session::validateCSRF(['_glpi_csrf_token' => $_POST['_glpi_csrf_token'] ?? ''])) {
    Session::addMessageAfterRedirect('Token de segurança inválido. Recarregue a página e tente novamente.', false, ERROR);
    Html::redirect($manage_url);
    exit;
}

if (!pgeservicos_satisfaction_user_can_create_surveys()) {
    Session::addMessageAfterRedirect('Apenas o perfil Super-Admin pode gerenciar pesquisas de satisfação.', false, ERROR);
    Html::redirect($manage_url);
    exit;
}

$action = (string)($_POST['action'] ?? '');
$result = ['ok' => false, 'message' => 'Ação inválida.'];

if ($action === 'toggle_status') {
    $result = pgeservicos_satisfaction_set_survey_active(
        (int)($_POST['survey_id'] ?? 0),
        !empty($_POST['is_active'])
    );
}

Session::addMessageAfterRedirect($result['message'] ?? 'Ação concluída.', !empty($result['ok']), !empty($result['ok']) ? INFO : ERROR);
Html::redirect($manage_url);
