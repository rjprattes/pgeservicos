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
$GLOBALS['pgeservicos_action_target_ob_level'] = ob_get_level();
ob_start();

function pgeservicos_action_target_response($payload, $status = 200) {
    $base_ob_level = $GLOBALS['pgeservicos_action_target_ob_level'] ?? 0;

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
    pgeservicos_action_target_response([
        'ok' => false,
        'results' => [],
        'message' => 'Token de segurança inválido. Recarregue a página e tente novamente.'
    ], 403);
}


function pgeservicos_action_target_clean_label($value) {
    $value = pgeservicos_ticket_view_decode_text(strip_tags((string)$value));
    $value = preg_replace('/^\s*»\s*/u', '', $value);
    $value = preg_replace('/\s+/u', ' ', $value);

    return trim((string)$value);
}

function pgeservicos_action_target_add_group_result(&$results, &$group_ids, $groups_id, $label, $entity = '') {
    $groups_id = (int)$groups_id;

    if ($groups_id <= 0 || isset($group_ids[$groups_id])) {
        return;
    }

    $group = new Group();
    if (
        !$group->getFromDB($groups_id)
        || (int)($group->fields['is_deleted'] ?? 0) === 1
        || (int)($group->fields['is_task'] ?? 0) !== 1
    ) {
        return;
    }

    $group_ids[$groups_id] = true;
    $results[] = [
        'id' => $groups_id,
        'value' => $groups_id,
        'type' => 'group',
        'type_label' => 'Grupo',
        'label' => pgeservicos_action_target_clean_label($label),
        'entity' => pgeservicos_action_target_clean_label($entity)
    ];
}

function pgeservicos_action_target_collect_native_groups($entries, &$results, &$group_ids, $entity = '') {
    foreach ((array)$entries as $entry) {
        $current_entity = $entity;

        if (($entry['itemtype'] ?? '') === 'Entity' || !empty($entry['children'])) {
            $current_entity = pgeservicos_action_target_clean_label($entry['text'] ?? $entity);
        }

        if (($entry['itemtype'] ?? '') === 'Group' || preg_match('/^Group_(\d+)$/', (string)($entry['id'] ?? ''), $match)) {
            $groups_id = (int)($entry['items_id'] ?? ($match[1] ?? 0));
            $label = $entry['text'] ?? $entry['label'] ?? '';

            pgeservicos_action_target_add_group_result($results, $group_ids, $groups_id, $label, $current_entity);
        }

        if (!empty($entry['children'])) {
            pgeservicos_action_target_collect_native_groups($entry['children'], $results, $group_ids, $current_entity);
        }
    }
}

$tickets_id = (int)($_GET['tickets_id'] ?? 0);
$target_type = preg_replace('/[^a-z_]/', '', (string)($_GET['target_type'] ?? ''));
$term = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 80, 'UTF-8');
$ticket = pgeservicos_ticket_view_get_ticket($tickets_id);

if (!in_array($target_type, ['user', 'group', 'document'], true)) {
    pgeservicos_action_target_response(['ok' => false, 'results' => [], 'message' => 'Tipo de pesquisa inválido.'], 400);
}

if ($ticket === null) {
    pgeservicos_action_target_response(['ok' => false, 'results' => [], 'message' => 'Chamado não encontrado.'], 404);
}

if ($ticket === false || !$ticket->canUpdateItem()) {
    pgeservicos_action_target_response(['ok' => false, 'results' => [], 'message' => 'Você não tem permissão para alterar este chamado.'], 403);
}

if ((int)($ticket->fields['status'] ?? 0) === Ticket::CLOSED) {
    pgeservicos_action_target_response(['ok' => false, 'results' => [], 'message' => 'Chamado fechado: esta ação não está disponível.'], 403);
}

$accessible_entities = function_exists('pgeservicos_get_accessible_entities')
    ? pgeservicos_get_accessible_entities()
    : [];

$ticket_entity = (int)($ticket->fields['entities_id'] ?? -1);
$entity_ids = [];

if ($ticket_entity >= 0) {
    $entity_ids[$ticket_entity] = $ticket_entity;

    foreach (getAncestorsOf('glpi_entities', $ticket_entity) as $ancestor_id) {
        $entity_ids[(int)$ancestor_id] = (int)$ancestor_id;
    }
}

foreach ($accessible_entities as $entities_id) {
    $entity_ids[(int)$entities_id] = (int)$entities_id;
}

$entity_ids = array_values(array_unique($entity_ids));
$like = '%' . $term . '%';
$search = $term !== '' && class_exists('Search') ? Search::makeTextSearchValue($term) : $like;
$results = [];

if ($target_type === 'document') {
    if (!Document::canView()) {
        pgeservicos_action_target_response(['ok' => false, 'results' => [], 'message' => 'Você não tem permissão para pesquisar documentos.'], 403);
    }

    if (!$ticket->canAddItem('Document')) {
        pgeservicos_action_target_response(['ok' => false, 'results' => [], 'message' => 'Você não tem permissão para vincular documentos a este chamado.'], 403);
    }

    $results[] = [
        'id' => 0,
        'value' => 0,
        'type' => 'document',
        'type_label' => 'Documento',
        'label' => '-----',
        'entity' => '',
        'empty' => true
    ];

    $used_document_ids = [];

    foreach ($DB->request([
        'SELECT' => ['documents_id'],
        'FROM'   => 'glpi_documents_items',
        'WHERE'  => [
            'itemtype' => Ticket::getType(),
            'items_id' => (int)$ticket->getID()
        ]
    ]) as $used_document) {
        $used_document_ids[] = (int)$used_document['documents_id'];
    }

    $document_entities = $ticket_entity >= 0 ? $ticket_entity : $entity_ids;
    $document_where = [
        'is_deleted' => 0
    ] + getEntitiesRestrictCriteria('glpi_documents', '', $document_entities, true);

    if ($term !== '') {
        $document_where['OR'] = [
            'name'     => ['LIKE', $search],
            'filename' => ['LIKE', $search],
            'filepath' => ['LIKE', $search]
        ];
    }

    if (!empty($used_document_ids)) {
        $document_where['NOT'] = ['id' => array_values(array_unique($used_document_ids))];
    }

    foreach ($DB->request([
        'SELECT' => ['id', 'name', 'filename', 'filepath', 'entities_id'],
        'FROM'   => 'glpi_documents',
        'WHERE'  => $document_where,
        'ORDER'  => ['name ASC', 'filename ASC'],
        'LIMIT'  => 30
    ]) as $document_row) {
        $documents_id = (int)$document_row['id'];
        $document = new Document();

        if (!$document->getFromDB($documents_id) || !$document->can($documents_id, READ)) {
            continue;
        }

        $filename = trim((string)($document_row['filename'] ?? ''));
        $filepath = trim((string)($document_row['filepath'] ?? ''));
        $name = trim((string)($document_row['name'] ?? ''));
        $label = $filename !== ''
            ? basename($filename)
            : ($name !== '' ? $name : ($filepath !== '' ? basename($filepath) : 'Documento #' . $documents_id));
        $entity = Dropdown::getDropdownName('glpi_entities', (int)($document_row['entities_id'] ?? 0));

        $results[] = [
            'id' => $documents_id,
            'value' => $documents_id,
            'type' => 'document',
            'type_label' => 'Documento',
            'label' => pgeservicos_action_target_clean_label($label),
            'entity' => pgeservicos_action_target_clean_label($entity)
        ];
    }
} elseif ($target_type === 'user') {
    if (empty($entity_ids)) {
        pgeservicos_action_target_response(['ok' => true, 'results' => [], 'message' => 'Nenhum usuário encontrado.']);
    }

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
            'glpi_profiles_users.entities_id' => $entity_ids,
            'OR' => [
                'glpi_users.name'      => ['LIKE', $search],
                'glpi_users.realname'  => ['LIKE', $search],
                'glpi_users.firstname' => ['LIKE', $search]
            ]
        ],
        'GROUPBY' => 'glpi_users.id',
        'ORDER' => ['glpi_users.realname ASC', 'glpi_users.name ASC'],
        'LIMIT' => 20
    ]) as $user) {
        $users_id = (int)$user['id'];
        $label = trim((string)($user['firstname'] ?? '') . ' ' . (string)($user['realname'] ?? ''));
        $label = $label !== '' ? $label : (string)$user['name'];

        $results[] = [
            'id' => $users_id,
            'value' => $users_id,
            'type' => 'user',
            'type_label' => 'Usuário',
            'label' => pgeservicos_ticket_view_decode_text($label)
        ];
    }
} else {
    if (empty($entity_ids)) {
        pgeservicos_action_target_response(['ok' => true, 'results' => [], 'message' => 'Nenhum grupo encontrado.']);
    }

    $ticket_entity = (int)($ticket->fields['entities_id'] ?? -1);
    $group_entities = $ticket_entity >= 0
        ? Session::getMatchingActiveEntities([$ticket_entity])
        : [];

    if (empty($group_entities)) {
        $group_entities = $entity_ids;
    }

    $group_ids = [];
    $native_post = [
        '_idor_token' => Session::getNewIDORToken('', ['actortype' => 'assign']),
        'actortype' => 'assign',
        'entity_restrict' => json_encode(array_values($group_entities)),
        'searchText' => $term,
        'returned_itemtypes' => ['Group'],
        'page_limit' => 30,
        'itiltemplate_class' => 'TicketTemplate',
        'itiltemplates_id' => 0
    ];

    try {
        ob_start();
        $native_groups = Dropdown::getDropdownActors($native_post, false);
        ob_end_clean();
    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        $native_groups = [];
    }

    if (is_array($native_groups) && !empty($native_groups['results'])) {
        pgeservicos_action_target_collect_native_groups($native_groups['results'], $results, $group_ids);
    }

    $group_where = [
        'is_deleted' => 0,
        'is_task' => 1,
        'OR' => [
            'name' => ['LIKE', $search],
            'completename' => ['LIKE', $search]
        ]
    ] + getEntitiesRestrictCriteria('glpi_groups', '', $group_entities, true);

    foreach ($DB->request([
        'SELECT' => ['id', 'name', 'completename', 'entities_id'],
        'FROM' => 'glpi_groups',
        'WHERE' => $group_where,
        'ORDER' => ['completename ASC', 'name ASC'],
        'LIMIT' => 20
    ]) as $group) {
        $label = (string)($group['completename'] ?: $group['name']);
        $entity = Dropdown::getDropdownName('glpi_entities', (int)($group['entities_id'] ?? 0));

        pgeservicos_action_target_add_group_result($results, $group_ids, (int)$group['id'], $label, $entity);
    }

    if (empty($group_ids) && $term !== '') {
        $loose_where = [
            'is_deleted' => 0,
            'OR' => [
                'name' => ['LIKE', $search],
                'completename' => ['LIKE', $search]
            ]
        ] + getEntitiesRestrictCriteria('glpi_groups', '', $group_entities, true);

        foreach ($DB->request([
            'SELECT' => ['id', 'name', 'completename', 'entities_id'],
            'FROM' => 'glpi_groups',
            'WHERE' => $loose_where,
            'ORDER' => ['completename ASC', 'name ASC'],
            'LIMIT' => 20
        ]) as $group) {
            $label = (string)($group['completename'] ?: $group['name']);
            $entity = Dropdown::getDropdownName('glpi_entities', (int)($group['entities_id'] ?? 0));

            pgeservicos_action_target_add_group_result($results, $group_ids, (int)$group['id'], $label, $entity);
        }
    }
}

$empty_message = 'Nenhum usuário encontrado.';

if ($target_type === 'group') {
    $empty_message = 'Nenhum grupo encontrado.';
} elseif ($target_type === 'document') {
    $empty_message = 'Nenhum documento encontrado.';
}

pgeservicos_action_target_response([
    'ok' => true,
    'results' => $results,
    'message' => empty($results) ? $empty_message : ''
]);
