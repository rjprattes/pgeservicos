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
$form_url = $root_doc . '/plugins/pgeservicos/front/satisfacao_survey_form.php';
$manage_url = $root_doc . '/plugins/pgeservicos/front/satisfacao_surveys.php';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    Html::redirect($manage_url);
    exit;
}

$survey_id = isset($_POST['survey_id']) ? (int)$_POST['survey_id'] : 0;
$error_url = $form_url . ($survey_id > 0 ? '?survey_id=' . $survey_id : '');

if (GLPI_USE_CSRF_CHECK && !Session::validateCSRF(['_glpi_csrf_token' => $_POST['_glpi_csrf_token'] ?? ''])) {
    Session::addMessageAfterRedirect('Token de segurança inválido. Recarregue a página e tente novamente.', false, ERROR);
    Html::redirect($error_url);
    exit;
}

$result = pgeservicos_satisfaction_create_survey($_POST);

if (!empty($result['ok'])) {
    Session::addMessageAfterRedirect($result['message'] ?? 'Pesquisa salva com sucesso.', true, INFO);
    Html::redirect($manage_url);
    exit;
}

Session::addMessageAfterRedirect($result['message'] ?? 'Não foi possível salvar a pesquisa.', false, ERROR);
Html::redirect($error_url);
