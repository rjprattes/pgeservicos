<?php

if (!defined('GLPI_KEEP_CSRF_TOKEN')) {
    define('GLPI_KEEP_CSRF_TOKEN', true);
}

include('../../../inc/includes.php');

require_once(__DIR__ . '/../inc/plugin_state.php');
pgeservicos_require_plugin_active();

Session::checkLoginUser();

global $DB;

require_once(__DIR__ . '/../inc/tickets_catalog.php');
require_once(__DIR__ . '/../inc/ticket_view.php');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (GLPI_USE_CSRF_CHECK && !Session::validateCSRF(['_glpi_csrf_token' => $_GET['_glpi_csrf_token'] ?? ''])) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'results' => [],
        'message' => 'Token de segurança inválido. Recarregue a página e tente novamente.'
    ]);
    exit;
}

$tickets_id = (int)($_GET['tickets_id'] ?? 0);
$ticket = pgeservicos_ticket_view_get_ticket($tickets_id);

if ($ticket === null || $ticket === false) {
    echo json_encode([
        'ok' => false,
        'results' => [],
        'message' => 'Chamado não encontrado ou inacessível.'
    ]);
    exit;
}

if ((int)($ticket->fields['status'] ?? 0) === Ticket::CLOSED) {
    echo json_encode([
        'ok' => false,
        'results' => [],
        'message' => 'Chamado fechado: a abertura está em modo somente leitura.'
    ]);
    exit;
}

if (!$ticket->can((int)$ticket->getID(), UPDATE) || (method_exists($ticket, 'canUpdateItem') && !$ticket->canUpdateItem())) {
    echo json_encode([
        'ok' => false,
        'results' => [],
        'message' => 'Você não tem permissão para editar a abertura deste chamado.'
    ]);
    exit;
}

$accessible_entities = pgeservicos_get_accessible_entities();

if (empty($accessible_entities)) {
    echo json_encode(['ok' => true, 'results' => []]);
    exit;
}

$term = trim((string)($_GET['q'] ?? ''));
$term = mb_substr($term, 0, 80, 'UTF-8');
$like = '%' . $term . '%';
$results = [];
$seen = [];

$query = [
    'SELECT' => [
        'glpi_users.id',
        'glpi_users.name',
        'glpi_users.realname',
        'glpi_users.firstname',
        'glpi_useremails.email'
    ],
    'FROM' => 'glpi_users',
    'INNER JOIN' => [
        'glpi_profiles_users' => [
            'ON' => [
                'glpi_profiles_users' => 'users_id',
                'glpi_users' => 'id'
            ]
        ]
    ],
    'LEFT JOIN' => [
        'glpi_useremails' => [
            'ON' => [
                'glpi_useremails' => 'users_id',
                'glpi_users' => 'id'
            ]
        ]
    ],
    'WHERE' => [
        'glpi_users.is_deleted' => 0,
        'glpi_users.is_active' => 1,
        'glpi_profiles_users.entities_id' => $accessible_entities,
        'OR' => [
            'glpi_users.name' => ['LIKE', $like],
            'glpi_users.realname' => ['LIKE', $like],
            'glpi_users.firstname' => ['LIKE', $like],
            'glpi_useremails.email' => ['LIKE', $like]
        ]
    ],
    'GROUPBY' => 'glpi_users.id',
    'ORDER' => ['glpi_users.realname ASC', 'glpi_users.firstname ASC', 'glpi_users.name ASC'],
    'LIMIT' => 15
];

foreach ($DB->request($query) as $user) {
    $users_id = (int)($user['id'] ?? 0);

    if ($users_id <= 0 || isset($seen[$users_id])) {
        continue;
    }

    $seen[$users_id] = true;
    $label = trim((string)($user['firstname'] ?? '') . ' ' . (string)($user['realname'] ?? ''));
    $label = $label !== '' ? $label : (string)($user['name'] ?? 'Usuário');
    $email = trim((string)($user['email'] ?? ''));

    $results[] = [
        'id' => $users_id,
        'items_id' => $users_id,
        'itemtype' => User::getType(),
        'type' => 'user',
        'label' => pgeservicos_ticket_view_decode_text($label),
        'login' => (string)($user['name'] ?? ''),
        'email' => $email,
        'subtitle' => $email !== '' ? $email : 'Usuário'
    ];
}

echo json_encode([
    'ok' => true,
    'results' => $results,
    'message' => empty($results) ? 'Nenhum usuário encontrado.' : ''
]);
