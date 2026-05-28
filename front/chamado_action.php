<?php

if (!defined('GLPI_KEEP_CSRF_TOKEN')) {
    define('GLPI_KEEP_CSRF_TOKEN', true);
}

include('../../../inc/includes.php');

Session::checkLoginUser();

require_once(__DIR__ . '/../inc/tickets_catalog.php');
require_once(__DIR__ . '/../inc/ticket_view.php');
require_once(__DIR__ . '/../inc/ticket_actions.php');

$is_ajax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || strpos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
$base_ob_level = ob_get_level();

if ($is_ajax) {
    ob_start();
}

function pgeservicos_chamado_action_json($payload, $status = 200) {
    global $base_ob_level;

    while (ob_get_level() > $base_ob_level) {
        ob_end_clean();
    }

    if (isset($payload['ok']) && !isset($payload['success'])) {
        $payload['success'] = (bool)$payload['ok'];
    }

    if (isset($payload['success']) && !isset($payload['ok'])) {
        $payload['ok'] = (bool)$payload['success'];
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

function pgeservicos_chamado_action_success_message($action) {
    $messages = [
        'add_followup' => 'Acompanhamento adicionado.',
        'add_task' => 'Tarefa criada.',
        'add_solution' => 'Solução adicionada.',
        'add_document' => 'Documento anexado.',
        'add_validation' => 'Solicitação de validação enviada.',
        'solution_approval' => 'Avaliação da solução registrada.',
        'reopen_ticket' => 'Chamado reaberto.',
        'trash_ticket' => 'Chamado enviado para a lixeira.',
        'update_timeline' => 'Atualização salva.',
        'delete_timeline_item' => 'Item excluído com sucesso.',
        'delete_attachment' => 'Anexo removido com sucesso.',
    ];

    return $messages[$action] ?? 'Operação concluída.';
}

function pgeservicos_chamado_action_consume_glpi_messages() {
    $messages = $_SESSION['MESSAGE_AFTER_REDIRECT'] ?? [];
    $_SESSION['MESSAGE_AFTER_REDIRECT'] = [];

    if (!is_array($messages) || empty($messages)) {
        return [];
    }

    $flat = [];

    foreach ($messages as $type => $entries) {
        foreach ((array)$entries as $entry) {
            $flat[] = trim(strip_tags((string)$entry));
        }
    }

    return array_values(array_filter($flat));
}

if ($is_ajax) {
    if (GLPI_USE_CSRF_CHECK && !Session::validateCSRF(['_glpi_csrf_token' => $_POST['_glpi_csrf_token'] ?? ''])) {
        pgeservicos_chamado_action_json([
            'ok' => false,
            'message' => 'Token de segurança inválido. Recarregue a página e tente novamente.'
        ], 403);
    }
} else {
    Session::checkCSRF(['_glpi_csrf_token' => $_POST['_glpi_csrf_token'] ?? '']);
}

$tickets_id = isset($_POST['tickets_id']) ? (int)$_POST['tickets_id'] : (int)($_GET['tickets_id'] ?? 0);
$ticket = pgeservicos_ticket_view_get_ticket($tickets_id);

if ($ticket === null) {
    if ($is_ajax) {
        pgeservicos_chamado_action_json(['ok' => false, 'message' => 'Chamado não encontrado.'], 404);
    }

    Html::displayNotFoundError();
    exit;
}

if ($ticket === false) {
    if ($is_ajax) {
        pgeservicos_chamado_action_json(['ok' => false, 'message' => 'Você não tem permissão para acessar este chamado.'], 403);
    }

    Html::displayRightError();
    exit;
}

$action = preg_replace('/[^a-z_]/', '', (string)($_POST['pgeservicos_action'] ?? ''));

try {
    $result = pgeservicos_ticket_action_handle($ticket, $action);
} catch (Throwable $e) {
    if ($is_ajax) {
        $glpi_messages = pgeservicos_chamado_action_consume_glpi_messages();
        $message = $glpi_messages[0] ?? trim((string)$e->getMessage());

        pgeservicos_chamado_action_json([
            'ok' => false,
            'message' => $message !== '' ? $message : 'Não foi possível concluir a ação.'
        ], 500);
    }

    throw $e;
}

$glpi_messages = $is_ajax ? pgeservicos_chamado_action_consume_glpi_messages() : [];
$ajax_message = $glpi_messages[0] ?? pgeservicos_chamado_action_success_message($action);

if (!empty($result)) {
    pgeservicos_mark_ticket_seen($tickets_id);
}

if ($result === 'tickets_list') {
    $redirect = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/pgeservicos/front/meus_chamados.php';

    if ($is_ajax) {
        pgeservicos_chamado_action_json([
            'ok' => true,
            'message' => $ajax_message,
            'redirect' => $redirect
        ]);
    }

    Html::redirect($redirect);
}

if ($is_ajax) {
    pgeservicos_chamado_action_json([
        'ok' => (bool)$result,
        'message' => $result
            ? $ajax_message
            : ($glpi_messages[0] ?? 'Não foi possível concluir a ação.'),
        'reload' => (bool)$result
    ], $result ? 200 : 400);
}

pgeservicos_ticket_action_redirect($tickets_id);
