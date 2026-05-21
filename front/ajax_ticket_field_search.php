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

function pgeservicos_ticket_field_search_response($payload) {
    echo json_encode($payload);
    exit;
}

$tickets_id = (int)($_GET['tickets_id'] ?? 0);
$field = preg_replace('/[^a-z0-9_]/', '', (string)($_GET['field'] ?? ''));
$term = trim((string)($_GET['q'] ?? ''));
$term = mb_substr($term, 0, 80, 'UTF-8');
$ticket = pgeservicos_ticket_view_get_ticket($tickets_id);

if ($ticket !== null && $ticket !== false && (int)($ticket->fields['status'] ?? 0) === Ticket::CLOSED) {
    pgeservicos_ticket_field_search_response([
        'ok' => false,
        'results' => [],
        'message' => 'Chamado fechado: este campo está em modo somente leitura.'
    ]);
}

if ($ticket === null || $ticket === false || !$ticket->canUpdateItem()) {
    pgeservicos_ticket_field_search_response([
        'ok' => false,
        'results' => [],
        'message' => 'Você não tem permissão para alterar este chamado.'
    ]);
}

$maps = [
    'itilcategories_id' => [
        'table' => 'glpi_itilcategories',
        'label' => 'Categoria',
        'entity_restrict' => true
    ],
    'requesttypes_id' => [
        'table' => 'glpi_requesttypes',
        'label' => 'Origem da requisição',
        'entity_restrict' => false
    ],
    'locations_id' => [
        'table' => 'glpi_locations',
        'label' => 'Localização',
        'entity_restrict' => true
    ]
];

if (!isset($maps[$field]) || !$DB->tableExists($maps[$field]['table'])) {
    pgeservicos_ticket_field_search_response([
        'ok' => false,
        'results' => [],
        'message' => 'Campo inválido para pesquisa.'
    ]);
}

$table = $maps[$field]['table'];
$where = [];

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

if (!empty($maps[$field]['entity_restrict']) && $DB->fieldExists($table, 'entities_id')) {
    $ticket_entity = (int)($ticket->fields['entities_id'] ?? -1);
    $entities = $ticket_entity >= 0 ? Session::getMatchingActiveEntities([$ticket_entity]) : [];

    if (empty($entities)) {
        $entities = pgeservicos_get_accessible_entities();
    }

    $where += getEntitiesRestrictCriteria($table, '', $entities, true);
}

$select = ['id', 'name'];

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
    'LIMIT'  => 20
]) as $row) {
    $id = (int)$row['id'];
    $label = (string)($row['completename'] ?? $row['name'] ?? '');

    if ($label === '') {
        $label = pgeservicos_ticket_view_dropdown_name($table, $id);
    }

    $label = pgeservicos_ticket_view_decode_text($label);

    if ($label === '-' || $label === '') {
        continue;
    }

    $results[] = [
        'id' => $id,
        'label' => $label
    ];
}

pgeservicos_ticket_field_search_response([
    'ok' => true,
    'results' => $results
]);
