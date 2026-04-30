<?php

if (!defined('GLPI_KEEP_CSRF_TOKEN')) {
    define('GLPI_KEEP_CSRF_TOKEN', true);
}

include('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkCSRF(['_glpi_csrf_token' => $_GET['_glpi_csrf_token'] ?? '']);

global $DB;

require_once(__DIR__ . '/../inc/tickets_catalog.php');
require_once(__DIR__ . '/../inc/ticket_view.php');

header('Content-Type: application/json; charset=UTF-8');

$tickets_id = (int)($_GET['tickets_id'] ?? 0);
$actor_role = preg_replace('/[^a-z_]/', '', (string)($_GET['actor_role'] ?? ''));
$ticket = pgeservicos_ticket_view_get_ticket($tickets_id);

if (
    $ticket === null
    || $ticket === false
    || !pgeservicos_ticket_view_can_manage_actors($ticket)
    || !in_array($actor_role, ['requester', 'observer', 'assign'], true)
) {
    echo json_encode(['results' => []]);
    exit;
}

$term = trim((string)($_GET['q'] ?? ''));
$term = mb_substr($term, 0, 80, 'UTF-8');
$like = '%' . $term . '%';
$accessible_entities = pgeservicos_get_accessible_entities();
$results = [];

if (empty($accessible_entities)) {
    echo json_encode(['results' => []]);
    exit;
}

$user_ids = [];

foreach ($DB->request([
    'SELECT' => ['glpi_users.id', 'glpi_users.name', 'glpi_users.realname', 'glpi_users.firstname'],
    'FROM'   => 'glpi_users',
    'INNER JOIN' => [
        'glpi_profiles_users' => [
            'ON' => [
                'glpi_profiles_users' => 'users_id',
                'glpi_users'          => 'id'
            ]
        ]
    ],
    'WHERE'  => [
        'glpi_users.is_deleted' => 0,
        'glpi_users.is_active'  => 1,
        'glpi_profiles_users.entities_id' => $accessible_entities,
        'OR' => [
            'glpi_users.name'      => ['LIKE', $like],
            'glpi_users.realname'  => ['LIKE', $like],
            'glpi_users.firstname' => ['LIKE', $like]
        ]
    ],
    'GROUPBY' => 'glpi_users.id',
    'ORDER' => ['glpi_users.realname ASC', 'glpi_users.name ASC'],
    'LIMIT' => 12
]) as $user) {
    $users_id = (int)$user['id'];

    if (isset($user_ids[$users_id])) {
        continue;
    }

    $user_ids[$users_id] = true;
    $label = trim((string)($user['firstname'] ?? '') . ' ' . (string)($user['realname'] ?? ''));
    $label = $label !== '' ? $label : (string)$user['name'];
    $payload = pgeservicos_ticket_view_actor_payload('user', $users_id, $label, $actor_role === 'requester');

    $results[] = [
        'id' => $users_id,
        'value' => 'user:' . $users_id,
        'type'  => 'user',
        'type_label' => $payload['type_label'],
        'label' => $payload['label'],
        'tooltip' => $payload['tooltip'],
        'vip'   => $actor_role === 'requester' ? $payload['vip'] : null
    ];
}

$group_entities = $accessible_entities;
$ticket_entity = (int)($ticket->fields['entities_id'] ?? -1);

if ($ticket_entity >= 0) {
    foreach (getAncestorsOf('glpi_entities', $ticket_entity) as $ancestor_id) {
        $group_entities[(int)$ancestor_id] = (int)$ancestor_id;
    }
}

foreach ($DB->request([
    'SELECT' => ['id', 'name', 'completename', 'entities_id', 'is_recursive'],
    'FROM'   => 'glpi_groups',
    'WHERE'  => [
        'is_deleted' => 0,
        'entities_id' => array_values(array_unique($group_entities)),
        'OR' => [
            'name' => ['LIKE', $like],
            'completename' => ['LIKE', $like]
        ]
    ],
    'ORDER' => ['completename ASC', 'name ASC'],
    'LIMIT' => 12
]) as $group) {
    $group_entity = (int)($group['entities_id'] ?? -1);

    if (!in_array($group_entity, $accessible_entities, true) && empty($group['is_recursive'])) {
        continue;
    }

    $groups_id = (int)$group['id'];
    $label = (string)($group['completename'] ?: $group['name']);
    $payload = pgeservicos_ticket_view_actor_payload('group', $groups_id, $label);

    $results[] = [
        'id' => $groups_id,
        'value' => 'group:' . $groups_id,
        'type'  => 'group',
        'type_label' => $payload['type_label'],
        'label' => $payload['label'],
        'tooltip' => $payload['tooltip'],
        'vip'   => null
    ];
}

echo json_encode(['results' => array_slice($results, 0, 20)]);
