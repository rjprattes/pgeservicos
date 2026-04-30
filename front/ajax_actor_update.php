<?php

if (!defined('GLPI_KEEP_CSRF_TOKEN')) {
    define('GLPI_KEEP_CSRF_TOKEN', true);
}

include('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkCSRF(['_glpi_csrf_token' => $_POST['_glpi_csrf_token'] ?? '']);

require_once(__DIR__ . '/../inc/tickets_catalog.php');
require_once(__DIR__ . '/../inc/ticket_view.php');
require_once(__DIR__ . '/../inc/ticket_actions.php');

header('Content-Type: application/json; charset=UTF-8');

function pgeservicos_ajax_actor_response($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function pgeservicos_ajax_actor_role_type($role) {
    $roles = [
        'requester' => CommonITILActor::REQUESTER,
        'observer'  => CommonITILActor::OBSERVER,
        'assign'    => CommonITILActor::ASSIGN
    ];

    return $roles[$role] ?? 0;
}

function pgeservicos_ajax_actor_find(Ticket $ticket, $role_type, $kind, $actor_id) {
    $actor_id = (int)$actor_id;

    if ($kind === 'user') {
        foreach ($ticket->getUsers($role_type) as $actor) {
            if ((int)($actor['users_id'] ?? 0) === $actor_id) {
                return [
                    'link_id' => (int)($actor['id'] ?? 0),
                    'actor_id' => $actor_id
                ];
            }
        }
    }

    if ($kind === 'group') {
        foreach ($ticket->getGroups($role_type) as $actor) {
            if ((int)($actor['groups_id'] ?? 0) === $actor_id) {
                return [
                    'link_id' => (int)($actor['id'] ?? 0),
                    'actor_id' => $actor_id
                ];
            }
        }
    }

    return null;
}

function pgeservicos_ajax_actor_payload($kind, $actor_id, $link_id, $role = '') {
    $payload = pgeservicos_ticket_view_actor_payload($kind, $actor_id, '', $role === 'requester');
    $payload['kind'] = $kind;
    $payload['actor_id'] = (int)$actor_id;
    $payload['link_id'] = (int)$link_id;

    return $payload;
}

$tickets_id = (int)($_POST['tickets_id'] ?? 0);
$ticket = pgeservicos_ticket_view_get_ticket($tickets_id);

if ($ticket === null) {
    pgeservicos_ajax_actor_response(['ok' => false, 'message' => 'Chamado não encontrado.'], 404);
}

if ($ticket === false || !pgeservicos_ticket_view_can_manage_actors($ticket)) {
    pgeservicos_ajax_actor_response(['ok' => false, 'message' => 'Você não tem permissão para alterar atores.'], 403);
}

$action = preg_replace('/[^a-z_]/', '', (string)($_POST['action'] ?? ''));
$role = pgeservicos_ticket_action_actor_key((string)($_POST['actor_role'] ?? ''));
$role_type = pgeservicos_ajax_actor_role_type($role);

if ($action === 'remove_actor') {
    $kind = (string)($_POST['actor_kind'] ?? '');
    $link_id = (int)($_POST['link_id'] ?? 0);

    if ($link_id <= 0 || !in_array($kind, ['user', 'group'], true)) {
        pgeservicos_ajax_actor_response(['ok' => false, 'message' => 'Ator inválido.'], 400);
    }

    $link = $kind === 'user' ? new Ticket_User() : new Group_Ticket();

    if (!$link->getFromDB($link_id) || (int)$link->fields['tickets_id'] !== $tickets_id) {
        pgeservicos_ajax_actor_response(['ok' => false, 'message' => 'Ator não pertence a este chamado.'], 404);
    }

    $link->check($link_id, DELETE);

    if ($link->delete(['id' => $link_id])) {
        pgeservicos_ajax_actor_response(['ok' => true, 'message' => 'Ator removido.', 'link_id' => $link_id]);
    }

    pgeservicos_ajax_actor_response(['ok' => false, 'message' => 'Não foi possível remover o ator.'], 500);
}

if ($role_type <= 0) {
    pgeservicos_ajax_actor_response(['ok' => false, 'message' => 'Papel inválido.'], 400);
}

$actor_value = (string)($_POST['actor_value'] ?? '');

if ($action === 'add_self') {
    $actor_value = 'user:' . (int)Session::getLoginUserID();
}

if (!preg_match('/^(user|group):(\\d+)$/', $actor_value, $matches)) {
    pgeservicos_ajax_actor_response(['ok' => false, 'message' => 'Usuário ou grupo inválido.'], 400);
}

$kind = $matches[1];
$actor_id = (int)$matches[2];

if (!pgeservicos_ticket_action_actor_is_accessible($kind, $actor_id, $ticket)) {
    pgeservicos_ajax_actor_response(['ok' => false, 'message' => 'Usuário ou grupo não disponível para este chamado.'], 403);
}

$existing = pgeservicos_ajax_actor_find($ticket, $role_type, $kind, $actor_id);

if (!empty($existing)) {
    pgeservicos_ajax_actor_response([
        'ok' => true,
        'duplicate' => true,
        'message' => 'Este ator já está associado.',
        'actor' => pgeservicos_ajax_actor_payload($kind, $actor_id, $existing['link_id'], $role)
    ]);
}

$link = $kind === 'user' ? new Ticket_User() : new Group_Ticket();
$input = [
    'tickets_id' => $tickets_id,
    'type'       => $role_type
];

if ($kind === 'user') {
    $input['users_id'] = $actor_id;
} else {
    $input['groups_id'] = $actor_id;
}

$link->check(-1, CREATE, $input);
$link_id = (int)$link->add($input);

if ($link_id > 0) {
    pgeservicos_ajax_actor_response([
        'ok' => true,
        'message' => 'Ator adicionado.',
        'actor' => pgeservicos_ajax_actor_payload($kind, $actor_id, $link_id, $role)
    ]);
}

pgeservicos_ajax_actor_response(['ok' => false, 'message' => 'Não foi possível adicionar o ator.'], 500);
