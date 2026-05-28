<?php

if (!defined('GLPI_KEEP_CSRF_TOKEN')) {
    define('GLPI_KEEP_CSRF_TOKEN', true);
}

include('../../../inc/includes.php');

Session::checkLoginUser();

require_once(__DIR__ . '/../inc/tickets_catalog.php');

header('Content-Type: application/json; charset=UTF-8');
$GLOBALS['pgeservicos_mark_updates_ob_level'] = ob_get_level();
ob_start();

function pgeservicos_mark_updates_response($payload, $status = 200) {
    $base_ob_level = $GLOBALS['pgeservicos_mark_updates_ob_level'] ?? 0;

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

if (GLPI_USE_CSRF_CHECK && !Session::validateCSRF(['_glpi_csrf_token' => $_POST['_glpi_csrf_token'] ?? ''])) {
    pgeservicos_mark_updates_response([
        'ok' => false,
        'message' => 'Token de segurança inválido. Recarregue a página e tente novamente.',
    ], 403);
}

try {
    $result = pgeservicos_mark_user_ticket_updates_read();

    if ($result === false) {
        pgeservicos_mark_updates_response([
            'ok' => false,
            'message' => 'Não foi possível marcar as atualizações como visualizadas.',
        ], 500);
    }

    $marked = (int)($result['marked'] ?? 0);
    $remaining = pgeservicos_count_user_ticket_updates();

    pgeservicos_mark_updates_response([
        'ok' => true,
        'marked' => $marked,
        'remaining_updates' => $remaining,
        'message' => $marked > 0
            ? 'Atualizações marcadas como visualizadas.'
            : 'Não há novas atualizações para marcar.',
    ]);
} catch (Throwable $e) {
    pgeservicos_mark_updates_response([
        'ok' => false,
        'message' => 'Não foi possível marcar as atualizações como visualizadas.',
    ], 500);
}
