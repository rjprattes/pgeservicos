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
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function pgeservicos_ajax_actor_debug_sample_groups($term, array $where, $limit = 10) {
    global $DB;

    $groups = [];

    try {
        foreach ($DB->request([
            'SELECT' => ['id', 'name', 'completename', 'entities_id', 'is_recursive', 'is_requester', 'is_watcher', 'is_assign'],
            'FROM'   => 'glpi_groups',
            'WHERE'  => $where,
            'ORDER'  => ['completename ASC', 'name ASC'],
            'LIMIT'  => $limit
        ]) as $group) {
            $groups[] = $group;
        }
    } catch (Throwable $e) {
        $groups[] = ['error' => $e->getMessage()];
    }

    return $groups;
}

function pgeservicos_ajax_actor_sort_results(array $results) {
    usort($results, static function ($a, $b) {
        $label_a = mb_strtolower((string)($a['label'] ?? ''), 'UTF-8');
        $label_b = mb_strtolower((string)($b['label'] ?? ''), 'UTF-8');

        if ($label_a === $label_b) {
            $type_a = (string)($a['type'] ?? '');
            $type_b = (string)($b['type'] ?? '');

            if ($type_a === $type_b) {
                return (int)($a['items_id'] ?? 0) <=> (int)($b['items_id'] ?? 0);
            }

            return $type_a === 'group' ? -1 : 1;
        }

        return strnatcasecmp($label_a, $label_b);
    });

    return $results;
}

function pgeservicos_ajax_actor_debug_safe_array(array $values) {
    foreach (['_glpi_csrf_token', '_idor_token'] as $token_key) {
        if (isset($values[$token_key])) {
            $values[$token_key] = '[masked]';
        }
    }

    return $values;
}

function pgeservicos_ajax_actor_clean_label($value) {
    $value = pgeservicos_ticket_view_decode_text(strip_tags((string)$value));
    $value = preg_replace('/^\s*»\s*/u', '', $value);
    $value = preg_replace('/\s+/u', ' ', $value);

    return trim((string)$value);
}

function pgeservicos_ajax_actor_add_group_result(&$results, &$group_ids, $groups_id, $label, $entity = '') {
    $groups_id = (int)$groups_id;

    if ($groups_id <= 0 || isset($group_ids[$groups_id])) {
        return;
    }

    $group_ids[$groups_id] = true;
    $label = pgeservicos_ajax_actor_clean_label($label);
    $payload = pgeservicos_ticket_view_actor_payload('group', $groups_id, $label);

    $results[] = [
        'id' => 'Group:' . $groups_id,
        'items_id' => $groups_id,
        'itemtype' => 'Group',
        'value' => 'group:' . $groups_id,
        'type'  => 'group',
        'type_label' => $payload['type_label'],
        'subtitle' => $payload['type_label'],
        'label' => $payload['label'],
        'complete_name' => $label,
        'entity' => pgeservicos_ajax_actor_clean_label($entity),
        'tooltip' => $payload['tooltip'],
        'vip'   => null
    ];
}

function pgeservicos_ajax_actor_collect_native_groups($entries, &$results, &$group_ids, $entity = '') {
    foreach ((array)$entries as $entry) {
        $current_entity = $entity;

        if (($entry['itemtype'] ?? '') === 'Entity' || !empty($entry['children'])) {
            $current_entity = pgeservicos_ajax_actor_clean_label($entry['text'] ?? $entity);
        }

        if (($entry['itemtype'] ?? '') === 'Group' || preg_match('/^Group_(\d+)$/', (string)($entry['id'] ?? ''), $match)) {
            $groups_id = (int)($entry['items_id'] ?? ($match[1] ?? 0));
            $label = $entry['text'] ?? $entry['label'] ?? '';

            pgeservicos_ajax_actor_add_group_result($results, $group_ids, $groups_id, $label, $current_entity);
        }

        if (!empty($entry['children'])) {
            pgeservicos_ajax_actor_collect_native_groups($entry['children'], $results, $group_ids, $current_entity);
        }
    }
}

$tickets_id = (int)($_GET['tickets_id'] ?? 0);
$actor_role = preg_replace('/[^a-z_]/', '', (string)($_GET['actor_role'] ?? ''));
$ticket = pgeservicos_ticket_view_get_ticket($tickets_id);

if ($ticket === null || $ticket === false || !in_array($actor_role, ['requester', 'observer', 'assign'], true)) {
    echo json_encode([
        'ok' => false,
        'results' => [],
        'message' => 'Não foi possível pesquisar atores para este chamado.'
    ]);
    exit;
}

if ((int)($ticket->fields['status'] ?? 0) === Ticket::CLOSED) {
    echo json_encode([
        'ok' => false,
        'results' => [],
        'message' => 'Chamado fechado: os atores estão em modo somente leitura.'
    ]);
    exit;
}

if (!pgeservicos_ticket_view_can_manage_actors($ticket)) {
    echo json_encode([
        'ok' => false,
        'results' => [],
        'message' => 'Você não tem permissão para alterar atores deste chamado.'
    ]);
    exit;
}

$term = trim((string)($_GET['q'] ?? ''));
$term = mb_substr($term, 0, 80, 'UTF-8');
$like = '%' . $term . '%';
$accessible_entities = pgeservicos_get_accessible_entities();
$results = [];
$group_ids = [];
$debug_requested = (string)($_GET['debug'] ?? '') === '1';
$debug = [
    'raw_query' => pgeservicos_ajax_actor_debug_safe_array($_GET),
    'term' => $term,
    'tickets_id' => $tickets_id,
    'actor_role_raw' => (string)($_GET['actor_role'] ?? ''),
    'actor_role' => $actor_role,
    'ticket_entity' => (int)($ticket->fields['entities_id'] ?? -1),
    'active_entity' => (int)($_SESSION['glpiactive_entity'] ?? -1),
    'active_entities' => $_SESSION['glpiactiveentities'] ?? [],
    'profile' => $_SESSION['glpiactiveprofile']['name'] ?? '',
    'login_user_id' => (int)Session::getLoginUserID(),
    'accessible_entities' => $accessible_entities,
    'users_count' => 0,
    'native_groups_called' => false,
    'native_groups_count' => 0,
    'manual_groups_count' => 0,
    'manual_loose_groups_count' => 0,
];

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

    $debug['users_count']++;

    $results[] = [
        'id' => 'User:' . $users_id,
        'items_id' => $users_id,
        'itemtype' => 'User',
        'value' => 'user:' . $users_id,
        'type'  => 'user',
        'type_label' => $payload['type_label'],
        'subtitle' => $payload['type_label'],
        'label' => $payload['label'],
        'tooltip' => $payload['tooltip'],
        'vip'   => $actor_role === 'requester' ? $payload['vip'] : null
    ];
}

$ticket_entity = (int)($ticket->fields['entities_id'] ?? -1);
$entity_ids = [];

if ($ticket_entity >= 0) {
    $entity_ids[$ticket_entity] = $ticket_entity;

    foreach (getAncestorsOf('glpi_entities', $ticket_entity) as $ancestor_id) {
        $entity_ids[(int)$ancestor_id] = (int)$ancestor_id;
    }

    $matching_entities = Session::getMatchingActiveEntities([$ticket_entity]);

    foreach ((array)$matching_entities as $matched_entity) {
        $entity_ids[(int)$matched_entity] = (int)$matched_entity;
    }
}

$group_entities = array_values(array_unique($entity_ids));

if (empty($group_entities)) {
    $group_entities = $accessible_entities;
}

$debug['group_entities'] = array_values($group_entities);
$debug['group_columns'] = array_keys((array)$DB->listFields('glpi_groups'));

$entity_restrict = json_encode(array_values($group_entities));
$native_post = [
    '_idor_token' => Session::getNewIDORToken('', ['actortype' => $actor_role]),
    'actortype' => $actor_role,
    'entity_restrict' => $entity_restrict,
    'searchText' => $term,
    'returned_itemtypes' => ['Group'],
    'page_limit' => 30,
    'itiltemplate_class' => 'TicketTemplate',
    'itiltemplates_id' => 0
];
$native_groups = [];
$debug['native_post'] = pgeservicos_ajax_actor_debug_safe_array($native_post);
$debug['native_groups_called'] = true;

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
    $before_native_groups = count($group_ids);
    pgeservicos_ajax_actor_collect_native_groups($native_groups['results'], $results, $group_ids);
    $debug['native_groups_count'] = count($group_ids) - $before_native_groups;
}

$debug['native_groups_raw_sample'] = is_array($native_groups)
    ? array_slice($native_groups['results'] ?? [], 0, 10)
    : $native_groups;

$group_role_columns = [
    'requester' => 'is_requester',
    'observer'  => 'is_watcher',
    'assign'    => 'is_assign'
];
$group_role_column = $group_role_columns[$actor_role] ?? 'is_assign';
$group_search = $like;
$debug['group_role_column'] = $group_role_column;
$debug['groups_before_role_filter'] = pgeservicos_ajax_actor_debug_sample_groups($term, [
    'OR' => [
        'name' => ['LIKE', $group_search],
        'completename' => ['LIKE', $group_search]
    ]
] + getEntitiesRestrictCriteria('glpi_groups', '', $group_entities, true));

$group_where = [
    $group_role_column => 1,
    'OR' => [
        'name' => ['LIKE', $group_search],
        'completename' => ['LIKE', $group_search]
    ]
] + getEntitiesRestrictCriteria('glpi_groups', '', $group_entities, true);
$debug['manual_group_filter_summary'] = [
    'role_column' => $group_role_column,
    'like' => $group_search,
    'entities' => $group_entities,
];
$debug['groups_after_role_filter'] = pgeservicos_ajax_actor_debug_sample_groups($term, $group_where);

foreach ($DB->request([
    'SELECT' => ['id', 'name', 'completename', 'entities_id', 'is_recursive'],
    'FROM'   => 'glpi_groups',
    'WHERE'  => $group_where,
    'ORDER' => ['completename ASC', 'name ASC'],
    'LIMIT' => 16
]) as $group) {
    $groups_id = (int)$group['id'];
    $label = (string)($group['completename'] ?: $group['name']);
    $entity = Dropdown::getDropdownName('glpi_entities', (int)($group['entities_id'] ?? 0));

    $before_manual_count = count($group_ids);
    pgeservicos_ajax_actor_add_group_result($results, $group_ids, $groups_id, $label, $entity);
    if (count($group_ids) > $before_manual_count) {
        $debug['manual_groups_count']++;
    }
}

if (empty($group_ids) && $term !== '') {
    $loose_group_where = [
        'OR' => [
            'name' => ['LIKE', $like],
            'completename' => ['LIKE', $like]
        ]
    ] + getEntitiesRestrictCriteria('glpi_groups', '', $group_entities, true);

    foreach ($DB->request([
        'SELECT' => ['id', 'name', 'completename', 'entities_id', 'is_recursive'],
        'FROM'   => 'glpi_groups',
        'WHERE'  => $loose_group_where,
        'ORDER' => ['completename ASC', 'name ASC'],
        'LIMIT' => 16
    ]) as $group) {
        $groups_id = (int)$group['id'];
        $label = (string)($group['completename'] ?: $group['name']);
        $entity = Dropdown::getDropdownName('glpi_entities', (int)($group['entities_id'] ?? 0));

        $before_loose_count = count($group_ids);
        pgeservicos_ajax_actor_add_group_result($results, $group_ids, $groups_id, $label, $entity);
        if (count($group_ids) > $before_loose_count) {
            $debug['manual_loose_groups_count']++;
        }
    }
}

$results = pgeservicos_ajax_actor_sort_results($results);
$final_results = array_slice($results, 0, 30);
$response = ['ok' => true, 'results' => $final_results];

if ($debug_requested) {
    $debug['final_count'] = count($final_results);
    $debug['final_group_count'] = count(array_filter($final_results, static function ($item) {
        return ($item['type'] ?? '') === 'group';
    }));
    $debug['final_results'] = $final_results;
    $response['debug'] = $debug;
}

echo json_encode($response);
