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
$list_url = $root_doc . '/plugins/pgeservicos/front/meus_chamados.php?satisfaction=1';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    Html::redirect($list_url);
    exit;
}

if (GLPI_USE_CSRF_CHECK && !Session::validateCSRF(['_glpi_csrf_token' => $_POST['_glpi_csrf_token'] ?? ''])) {
    Session::addMessageAfterRedirect('Token de segurança inválido. Recarregue a página e tente novamente.', false, ERROR);
    Html::redirect($list_url);
    exit;
}

$tickets_id = isset($_POST['tickets_id']) ? (int)$_POST['tickets_id'] : 0;
$answers = isset($_POST['answer']) && is_array($_POST['answer']) ? $_POST['answer'] : [];
$comment = (string)($_POST['comment'] ?? '');
$fallback_score = isset($_POST['satisfaction']) ? (int)$_POST['satisfaction'] : 3;
$result = pgeservicos_satisfaction_submit($tickets_id, $answers, $comment, $fallback_score);

if (!empty($result['ok'])) {
    $ticket = new Ticket();
    if ($ticket->getFromDB($tickets_id)) {
        \Glpi\Event::log(
            $tickets_id,
            'ticket',
            4,
            'tracking',
            sprintf(__('%s updates an item'), $_SESSION['glpiname'] ?? '')
        );
    }

    Session::addMessageAfterRedirect($result['message'] ?? 'Pesquisa enviada com sucesso.', true, INFO);
    Html::redirect($list_url);
    exit;
}

$message = (string)($result['message'] ?? 'Não foi possível enviar a pesquisa de satisfação.');
Session::addMessageAfterRedirect($message, false, ERROR);

$back_url = $root_doc . '/plugins/pgeservicos/front/pesquisa_satisfacao.php?tickets_id=' . (int)$tickets_id;
Html::redirect($back_url);
