<?php

if (!defined('GLPI_KEEP_CSRF_TOKEN')) {
    define('GLPI_KEEP_CSRF_TOKEN', true);
}

include('../../../inc/includes.php');

Session::checkLoginUser();

global $DB;

require_once(__DIR__ . '/../inc/tickets_catalog.php');
require_once(__DIR__ . '/../inc/ticket_view.php');

header('Content-Type: application/json; charset=UTF-8');
$GLOBALS['pgeservicos_sla_search_ob_level'] = ob_get_level();
ob_start();

function pgeservicos_sla_search_response($payload, $status = 200) {
    $base_ob_level = $GLOBALS['pgeservicos_sla_search_ob_level'] ?? 0;

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

if (GLPI_USE_CSRF_CHECK && !Session::validateCSRF(['_glpi_csrf_token' => $_GET['_glpi_csrf_token'] ?? ''])) {
    pgeservicos_sla_search_response([
        'ok' => false,
        'results' => [],
        'message' => 'Token de segurança inválido. Recarregue a página e tente novamente.'
    ], 403);
}

$tickets_id = (int)($_GET['tickets_id'] ?? 0);
$field = preg_replace('/[^a-z0-9_]/', '', (string)($_GET['field'] ?? ''));
$term = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80, 'UTF-8');
$config = pgeservicos_ticket_view_service_level_config($field);
$ticket = pgeservicos_ticket_view_get_ticket($tickets_id);

if ($config === null) {
    pgeservicos_sla_search_response(['ok' => false, 'results' => [], 'message' => 'Prazo inválido.'], 400);
}

if ($ticket !== null && $ticket !== false && (int)($ticket->fields['status'] ?? 0) === Ticket::CLOSED) {
    pgeservicos_sla_search_response([
        'ok' => false,
        'results' => [],
        'message' => 'Chamado fechado: os prazos estão em modo somente leitura.'
    ], 403);
}

if ($ticket === null || $ticket === false || !$ticket->canUpdateItem()) {
    pgeservicos_sla_search_response([
        'ok' => false,
        'results' => [],
        'message' => 'Você não tem permissão para alterar os prazos deste chamado.'
    ], 403);
}

$table = $config['agreement_table'];

if (!$DB->tableExists($table)) {
    pgeservicos_sla_search_response(['ok' => false, 'results' => [], 'message' => 'Tabela de SLA/OLA indisponível.'], 500);
}

$where = [
    'type' => (int)$config['slm_type']
];

if ($DB->fieldExists($table, 'is_deleted')) {
    $where['is_deleted'] = 0;
}

if ($term !== '') {
    $search = class_exists('Search') ? Search::makeTextSearchValue($term) : ('%' . $term . '%');
    $or = ['name' => ['LIKE', $search]];

    if ($DB->fieldExists($table, 'completename')) {
        $or['completename'] = ['LIKE', $search];
    }

    $where['OR'] = $or;
}

$ticket_entity = (int)($ticket->fields['entities_id'] ?? -1);

if ($ticket_entity >= 0 && $DB->fieldExists($table, 'entities_id')) {
    $entities = [$ticket_entity => $ticket_entity];

    foreach (getAncestorsOf('glpi_entities', $ticket_entity) as $ancestor_id) {
        $entities[(int)$ancestor_id] = (int)$ancestor_id;
    }

    if (function_exists('pgeservicos_get_accessible_entities')) {
        foreach (pgeservicos_get_accessible_entities() as $entities_id) {
            $entities[(int)$entities_id] = (int)$entities_id;
        }
    }

    $where['entities_id'] = array_values(array_unique($entities));
}

$select = ['id', 'name'];

if ($DB->fieldExists($table, 'entities_id')) {
    $select[] = 'entities_id';
}

if ($DB->fieldExists($table, 'completename')) {
    $select[] = 'completename';
}

$results = [];

foreach ($DB->request([
    'SELECT' => $select,
    'FROM'   => $table,
    'WHERE'  => $where,
    'ORDER'  => $DB->fieldExists($table, 'completename')
        ? ['completename ASC', 'name ASC']
        : ['name ASC'],
    'LIMIT'  => 40
]) as $row) {
    $id = (int)$row['id'];
    $label = (string)($row['completename'] ?? $row['name'] ?? '');

    if ($label === '') {
        $label = pgeservicos_ticket_view_dropdown_name($table, $id);
    }

    $label = pgeservicos_ticket_view_decode_text($label);

    if ($id <= 0 || $label === '' || $label === '-') {
        continue;
    }

    $entity_label = '';
    if (isset($row['entities_id'])) {
        $entity_label = pgeservicos_ticket_view_entity_display_name((int)$row['entities_id']);
    }

    $results[] = [
        'id' => $id,
        'label' => $label,
        'entity' => $entity_label !== '-' ? $entity_label : '',
        'agreement_label' => $config['agreement_label'],
        'field' => $field,
    ];
}

pgeservicos_sla_search_response([
    'ok' => true,
    'results' => $results,
    'message' => empty($results) ? 'Nenhuma SLA/OLA encontrada.' : ''
]);
