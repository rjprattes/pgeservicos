<?php

include('../../../inc/includes.php');

Session::checkLoginUser();

global $DB, $CFG_GLPI;

require_once(__DIR__ . '/../inc/theme.php');

const PGEGESTOR_CSRF_SESSION_KEY = 'pgegestor_reserva_csrf_token';

function pgegestor_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pgegestor_generate_csrf_token($force = false) {
    if (
        $force
        || empty($_SESSION[PGEGESTOR_CSRF_SESSION_KEY])
        || !is_string($_SESSION[PGEGESTOR_CSRF_SESSION_KEY])
    ) {
        $_SESSION[PGEGESTOR_CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
    }

    return $_SESSION[PGEGESTOR_CSRF_SESSION_KEY];
}

function pgegestor_validate_csrf_token($token) {
    if (
        empty($token)
        || empty($_SESSION[PGEGESTOR_CSRF_SESSION_KEY])
        || !is_string($_SESSION[PGEGESTOR_CSRF_SESSION_KEY])
    ) {
        return false;
    }

    return hash_equals($_SESSION[PGEGESTOR_CSRF_SESSION_KEY], (string)$token);
}

function pgegestor_sql_datetime_from_local($value) {
    if (empty($value)) {
        return null;
    }

    $value = str_replace('T', ' ', trim($value));

    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        $value .= ':00';
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
        return null;
    }

    try {
        return (new DateTime($value))->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

function pgegestor_to_datetime_local($value) {
    if (empty($value)) {
        return '';
    }

    try {
        return (new DateTime($value))->format('Y-m-d\TH:i');
    } catch (Exception $e) {
        return '';
    }
}

function pgegestor_get_user_name($users_id) {
    $users_id = (int)$users_id;

    if ($users_id <= 0) {
        return '-';
    }

    if (function_exists('getUserName')) {
        return getUserName($users_id);
    }

    $user = new User();

    if ($user->getFromDB($users_id)) {
        return $user->getFriendlyName();
    }

    return 'Usuário #' . $users_id;
}

function pgegestor_get_reservable_item_info($itemtype, $items_id) {
    $info = [
        'type_label' => $itemtype,
        'name'       => $itemtype . ' #' . (int)$items_id
    ];

    if (!class_exists($itemtype)) {
        return $info;
    }

    $item = getItemForItemtype($itemtype);

    if (!$item || !$item->getFromDB((int)$items_id)) {
        return $info;
    }

    if (method_exists($itemtype, 'getTypeName')) {
        $info['type_label'] = call_user_func([$itemtype, 'getTypeName'], 1);
    }

    if (!empty($item->fields['name'])) {
        $info['name'] = $item->fields['name'];
    } elseif (method_exists($item, 'getName')) {
        $info['name'] = $item->getName();
    }

    return $info;
}

function pgegestor_get_item_display_name($reservationitem_row) {
    $info = pgegestor_get_reservable_item_info(
        $reservationitem_row['itemtype'],
        (int)$reservationitem_row['items_id']
    );

    $display_name = $info['type_label'] . ' - ' . $info['name'];
    $display_name = preg_replace('/^\s*Dispositivo\s*-\s*/iu', '', $display_name);

    return trim($display_name);
}

function pgegestor_get_original_item_comment($itemtype, $items_id) {
    if (!class_exists($itemtype)) {
        return '';
    }

    $item = getItemForItemtype($itemtype);

    if (!$item || !$item->getFromDB((int)$items_id)) {
        return '';
    }

    return $item->fields['comment'] ?? '';
}

function pgegestor_normalize_text_for_number($value) {
    $value = (string)$value;

    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);
    $value = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);

    return trim($value);
}

function pgegestor_extract_first_integer($value) {
    $value = pgegestor_normalize_text_for_number($value);

    if (preg_match('/\d+/', $value, $matches)) {
        return (int)$matches[0];
    }

    return 0;
}

function pgegestor_extract_capacity_from_reservation_item($reservation_item) {
    $comments_to_check = [];

    if (!empty($reservation_item['comment'])) {
        $comments_to_check[] = $reservation_item['comment'];
    }

    if (!empty($reservation_item['itemtype']) && !empty($reservation_item['items_id'])) {
        $original_item_comment = pgegestor_get_original_item_comment(
            $reservation_item['itemtype'],
            (int)$reservation_item['items_id']
        );

        if (!empty($original_item_comment)) {
            $comments_to_check[] = $original_item_comment;
        }
    }

    foreach ($comments_to_check as $comment) {
        $capacity = pgegestor_extract_first_integer($comment);

        if ($capacity > 0) {
            return $capacity;
        }
    }

    return 0;
}

function pgegestor_extract_ticket_ids_from_comment($comment) {
    $ticket_ids = [];

    $comment = (string)$comment;

    if (preg_match('/Chamados gerados:\s*(.+)/i', $comment, $matches)) {
        if (preg_match_all('/#?(\d+)/', $matches[1], $ids)) {
            foreach ($ids[1] as $id) {
                $id = (int)$id;

                if ($id > 0) {
                    $ticket_ids[] = $id;
                }
            }
        }
    }

    return array_values(array_unique($ticket_ids));
}

function pgegestor_append_ticket_ids_to_comment_lines($comment_lines, $ticket_ids) {
    $ticket_ids = array_filter(array_map('intval', (array)$ticket_ids));

    if (empty($ticket_ids)) {
        return $comment_lines;
    }

    $formatted_ids = [];

    foreach ($ticket_ids as $ticket_id) {
        if ($ticket_id > 0) {
            $formatted_ids[] = '#' . $ticket_id;
        }
    }

    if (!empty($formatted_ids)) {
        $comment_lines[] = '';
        $comment_lines[] = 'Chamados gerados: ' . implode(', ', $formatted_ids);
    }

    return $comment_lines;
}

function pgegestor_append_ticket_metadata_to_comment_lines($comment_lines, $ticket_map) {
    $ticket_map = is_array($ticket_map) ? $ticket_map : [];

    $ticket_ids = [];

    if (!empty($ticket_map['tic'])) {
        $ticket_ids[] = (int)$ticket_map['tic'];
    }

    if (!empty($ticket_map['copa'])) {
        $ticket_ids[] = (int)$ticket_map['copa'];
    }

    $comment_lines = pgegestor_append_ticket_ids_to_comment_lines($comment_lines, $ticket_ids);

    if (!empty($ticket_map['tic'])) {
        $comment_lines[] = 'Chamado TIC: #' . (int)$ticket_map['tic'];
    }

    if (!empty($ticket_map['copa'])) {
        $comment_lines[] = 'Chamado Copa: #' . (int)$ticket_map['copa'];
    }

    return $comment_lines;
}

function pgegestor_parse_comment($comment) {
    $data = [
        'titulo'        => '',
        'participantes' => '',
        'zoom'          => false,
        'apoio_ti'      => false,
        'copa'          => false,
        'ticket_ids'    => [],
        'ticket_map'    => [
            'tic'  => 0,
            'copa' => 0
        ]
    ];

    $lines = preg_split('/\r\n|\r|\n/', (string)$comment);

    foreach ($lines as $line) {
        $line = trim($line);

        if (stripos($line, 'Título da reunião:') === 0) {
            $data['titulo'] = trim(substr($line, strlen('Título da reunião:')));
        }

        if (stripos($line, 'Número de participantes:') === 0) {
            $data['participantes'] = trim(substr($line, strlen('Número de participantes:')));
        }

        if (stripos($line, '- Link do Zoom:') === 0) {
            $data['zoom'] = stripos($line, 'Sim') !== false;
        }

        if (stripos($line, '- Apoio técnico de TI:') === 0) {
            $data['apoio_ti'] = stripos($line, 'Sim') !== false;
        }

        if (
            stripos($line, '- Serviços de copa:') === 0
            || stripos($line, '- Serviços de copa (café/água):') === 0
        ) {
            $data['copa'] = stripos($line, 'Sim') !== false;
        }

        if (preg_match('/^Chamado\s+TIC:\s*#?(\d+)/iu', $line, $matches)) {
            $data['ticket_map']['tic'] = (int)$matches[1];
        }

        if (preg_match('/^Chamado\s+Copa:\s*#?(\d+)/iu', $line, $matches)) {
            $data['ticket_map']['copa'] = (int)$matches[1];
        }
    }

    $data['ticket_ids'] = pgegestor_extract_ticket_ids_from_comment($comment);

    foreach ($data['ticket_map'] as $mapped_ticket_id) {
        $mapped_ticket_id = (int)$mapped_ticket_id;

        if ($mapped_ticket_id > 0) {
            $data['ticket_ids'][] = $mapped_ticket_id;
        }
    }

    $data['ticket_ids'] = array_values(array_unique(array_filter(array_map('intval', $data['ticket_ids']))));

    return $data;
}

function pgegestor_get_reservation_item($reservationitems_id) {
    global $DB;

    $reservationitems_id = (int)$reservationitems_id;

    if ($reservationitems_id <= 0) {
        return null;
    }

    $iterator = $DB->request([
        'SELECT' => [
            'id',
            'itemtype',
            'items_id',
            'entities_id',
            'comment'
        ],
        'FROM'  => 'glpi_reservationitems',
        'WHERE' => [
            'id'        => $reservationitems_id,
            'is_active' => 1
        ] + getEntitiesRestrictCriteria(
            'glpi_reservationitems',
            'entities_id',
            '',
            true
        ),
        'LIMIT' => 1
    ]);

    return $iterator->current();
}

function pgegestor_get_reservable_items() {
    global $DB;

    $iterator = $DB->request([
        'SELECT' => [
            'id',
            'itemtype',
            'items_id',
            'entities_id',
            'comment'
        ],
        'FROM'  => 'glpi_reservationitems',
        'WHERE' => [
            'is_active' => 1
        ] + getEntitiesRestrictCriteria(
            'glpi_reservationitems',
            'entities_id',
            '',
            true
        )
    ]);

    $items = [];

    foreach ($iterator as $row) {
        $items[] = [
            'id'       => (int)$row['id'],
            'label'    => pgegestor_get_item_display_name($row),
            'entity'   => Dropdown::getDropdownName(
                'glpi_entities',
                (int)$row['entities_id']
            ),
            'capacity' => pgegestor_extract_capacity_from_reservation_item($row)
        ];
    }

    usort($items, function ($a, $b) {
        return strnatcasecmp($a['label'], $b['label']);
    });

    return $items;
}

function pgegestor_get_reservation($reservations_id) {
    global $DB;

    $iterator = $DB->request([
        'SELECT' => [
            'glpi_reservations.id AS reservations_id',
            'glpi_reservations.reservationitems_id',
            'glpi_reservations.users_id',
            'glpi_reservations.begin',
            'glpi_reservations.end',
            'glpi_reservations.comment',
            'glpi_reservationitems.itemtype',
            'glpi_reservationitems.items_id',
            'glpi_reservationitems.entities_id',
            'glpi_reservationitems.comment AS reservationitem_comment'
        ],
        'FROM' => 'glpi_reservations',
        'INNER JOIN' => [
            'glpi_reservationitems' => [
                'ON' => [
                    'glpi_reservations'     => 'reservationitems_id',
                    'glpi_reservationitems' => 'id'
                ]
            ]
        ],
        'WHERE' => [
            'glpi_reservations.id' => (int)$reservations_id
        ] + getEntitiesRestrictCriteria(
            'glpi_reservationitems',
            'entities_id',
            '',
            true
        ),
        'LIMIT' => 1
    ]);

    return $iterator->current();
}

function pgegestor_has_conflict($reservationitems_id, $begin, $end, $ignore_reservations_id = 0) {
    global $DB;

    $where = [
        'reservationitems_id' => (int)$reservationitems_id,
        'begin'              => ['<', $end],
        'end'                => ['>', $begin]
    ];

    if ((int)$ignore_reservations_id > 0) {
        $where['NOT'] = [
            'id' => (int)$ignore_reservations_id
        ];
    }

    $iterator = $DB->request([
        'SELECT' => [
            'id',
            'begin',
            'end',
            'users_id'
        ],
        'FROM'  => 'glpi_reservations',
        'WHERE' => $where,
        'LIMIT' => 1
    ]);

    return $iterator->current();
}

function pgegestor_get_entity_by_completename_or_name($completename) {
    global $DB;

    $iterator = $DB->request([
        'SELECT' => [
            'id',
            'name',
            'completename',
            'entities_id'
        ],
        'FROM'  => 'glpi_entities',
        'WHERE' => [
            'completename' => $completename
        ],
        'LIMIT' => 1
    ]);

    $entity = $iterator->current();

    if ($entity && array_key_exists('id', $entity)) {
        return $entity;
    }

    $parts = explode(' > ', $completename);
    $last_name = trim(end($parts));

    if ($last_name !== '') {
        $iterator = $DB->request([
            'SELECT' => [
                'id',
                'name',
                'completename',
                'entities_id'
            ],
            'FROM'  => 'glpi_entities',
            'WHERE' => [
                'name' => $last_name
            ],
            'LIMIT' => 1
        ]);

        $entity = $iterator->current();

        if ($entity && array_key_exists('id', $entity)) {
            return $entity;
        }
    }

    return null;
}

function pgegestor_get_entity_id_by_completename($completename) {
    $entity = pgegestor_get_entity_by_completename_or_name($completename);

    if ($entity && array_key_exists('id', $entity)) {
        return (int)$entity['id'];
    }

    return null;
}

function pgegestor_get_parent_entity_id_from_child($child_completename) {
    $child_entity = pgegestor_get_entity_by_completename_or_name($child_completename);

    if (!$child_entity || !array_key_exists('entities_id', $child_entity)) {
        return null;
    }

    return (int)$child_entity['entities_id'];
}

function pgegestor_get_group_by_completename_or_name($group_name) {
    global $DB;

    $iterator = $DB->request([
        'SELECT' => [
            'id',
            'name',
            'completename'
        ],
        'FROM'  => 'glpi_groups',
        'WHERE' => [
            'completename' => $group_name
        ],
        'LIMIT' => 1
    ]);

    $group = $iterator->current();

    if ($group && isset($group['id'])) {
        return $group;
    }

    $parts = explode(' > ', $group_name);
    $last_name = trim(end($parts));

    if ($last_name !== '') {
        $iterator = $DB->request([
            'SELECT' => [
                'id',
                'name',
                'completename'
            ],
            'FROM'  => 'glpi_groups',
            'WHERE' => [
                'name' => $last_name
            ],
            'LIMIT' => 1
        ]);

        $group = $iterator->current();

        if ($group && isset($group['id'])) {
            return $group;
        }
    }

    return null;
}

function pgegestor_get_group_id_by_completename_or_name($group_name) {
    $group = pgegestor_get_group_by_completename_or_name($group_name);

    if ($group && isset($group['id'])) {
        return (int)$group['id'];
    }

    return 0;
}

function pgegestor_actor_type($type) {
    $fallback = [
        'requester' => 1,
        'assign'    => 2,
        'observer'  => 3
    ];

    $constants = [
        'requester' => 'CommonITILActor::REQUESTER',
        'assign'    => 'CommonITILActor::ASSIGN',
        'observer'  => 'CommonITILActor::OBSERVER'
    ];

    if (isset($constants[$type]) && defined($constants[$type])) {
        return constant($constants[$type]);
    }

    return $fallback[$type] ?? 1;
}

function pgegestor_add_group_to_ticket($tickets_id, $groups_id, $actor_type) {
    global $DB;

    $tickets_id = (int)$tickets_id;
    $groups_id = (int)$groups_id;
    $actor_type = (int)$actor_type;

    if ($tickets_id <= 0 || $groups_id <= 0 || $actor_type <= 0) {
        return false;
    }

    $exists = $DB->request([
        'FROM'  => 'glpi_groups_tickets',
        'WHERE' => [
            'tickets_id' => $tickets_id,
            'groups_id'  => $groups_id,
            'type'       => $actor_type
        ],
        'LIMIT' => 1
    ])->current();

    if ($exists) {
        return true;
    }

    if (class_exists('Group_Ticket')) {
        $group_ticket = new Group_Ticket();

        $added = $group_ticket->add([
            'tickets_id' => $tickets_id,
            'groups_id'  => $groups_id,
            'type'       => $actor_type
        ], [
            'skip_checks' => true
        ]);

        return !empty($added);
    }

    return $DB->insert('glpi_groups_tickets', [
        'tickets_id' => $tickets_id,
        'groups_id'  => $groups_id,
        'type'       => $actor_type
    ]);
}

function pgegestor_find_ticket_ids_by_reservation_marker($reservations_id) {
    global $DB;

    $reservations_id = (int)$reservations_id;

    if ($reservations_id <= 0) {
        return [];
    }

    $marker = 'PGEGESTOR_RESERVATION_ID:' . $reservations_id;

    $iterator = $DB->request([
        'SELECT' => [
            'id'
        ],
        'FROM'  => 'glpi_tickets',
        'WHERE' => [
            'content' => ['LIKE', '%' . $marker . '%']
        ]
    ]);

    $ticket_ids = [];

    foreach ($iterator as $row) {
        $ticket_ids[] = (int)$row['id'];
    }

    return array_values(array_unique(array_filter($ticket_ids)));
}

function pgegestor_get_related_ticket_ids_for_reservation($reservation) {
    if (!$reservation || empty($reservation['reservations_id'])) {
        return [];
    }

    $reservations_id = (int)$reservation['reservations_id'];

    $ticket_ids = pgegestor_extract_ticket_ids_from_comment($reservation['comment'] ?? '');

    if (empty($ticket_ids)) {
        $ticket_ids = pgegestor_find_ticket_ids_by_reservation_marker($reservations_id);
    }

    return array_values(array_unique(array_filter(array_map('intval', $ticket_ids))));
}

function pgegestor_get_ticket_ids_for_display($form) {
    $ticket_ids = [];

    if (!empty($form['ticket_ids']) && is_array($form['ticket_ids'])) {
        $ticket_ids = array_merge($ticket_ids, $form['ticket_ids']);
    }

    if (!empty($form['ticket_map']) && is_array($form['ticket_map'])) {
        foreach ($form['ticket_map'] as $ticket_id) {
            if ((int)$ticket_id > 0) {
                $ticket_ids[] = (int)$ticket_id;
            }
        }
    }

    return array_values(array_unique(array_filter(array_map('intval', $ticket_ids))));
}

function pgegestor_get_ticket_url($tickets_id) {
    global $CFG_GLPI;

    return ($CFG_GLPI['root_doc'] ?? '')
        . '/plugins/pgeservicos/front/abrir_chamado.php?tickets_id='
        . (int)$tickets_id;
}

function pgegestor_get_calendar_redirect_url($reservationitems_id, $month, $return_calendar = 'item') {
    global $CFG_GLPI;

    $params = [
        'month' => $month
    ];

    if ($return_calendar !== 'full') {
        $params['reservationitems_id'] = (int)$reservationitems_id;
    }

    return ($CFG_GLPI['root_doc'] ?? '')
        . '/plugins/pgeservicos/front/calendario.php?'
        . http_build_query($params);
}

function pgegestor_build_ticket_links_html($ticket_ids) {
    $ticket_ids = array_values(array_unique(array_filter(array_map('intval', (array)$ticket_ids))));

    if (empty($ticket_ids)) {
        return '';
    }

    $links = [];

    foreach ($ticket_ids as $ticket_id) {
        $links[] = "<a href='" . pgegestor_h(pgegestor_get_ticket_url($ticket_id)) . "' target='_blank'>#" . (int)$ticket_id . "</a>";
    }

    return implode(', ', $links);
}

function pgegestor_ticket_status_solved() {
    if (defined('CommonITILObject::SOLVED')) {
        return constant('CommonITILObject::SOLVED');
    }

    if (defined('Ticket::SOLVED')) {
        return constant('Ticket::SOLVED');
    }

    return 5;
}

function pgegestor_ticket_status_closed() {
    if (defined('CommonITILObject::CLOSED')) {
        return constant('CommonITILObject::CLOSED');
    }

    if (defined('Ticket::CLOSED')) {
        return constant('Ticket::CLOSED');
    }

    return 6;
}

function pgegestor_add_ticket_followup($tickets_id, $content) {
    $tickets_id = (int)$tickets_id;

    if ($tickets_id <= 0 || trim((string)$content) === '') {
        return false;
    }

    if (!class_exists('ITILFollowup')) {
        return false;
    }

    $followup = new ITILFollowup();

    $input = [
        'itemtype' => 'Ticket',
        'items_id' => $tickets_id,
        'content'  => $content
    ];

    if (isset($_SESSION['glpiID'])) {
        $input['users_id'] = (int)$_SESSION['glpiID'];
    }

    $added = $followup->add($input, [
        'skip_checks' => true
    ]);

    return !empty($added);
}

function pgegestor_add_ticket_solution($tickets_id, $content) {
    $tickets_id = (int)$tickets_id;

    if ($tickets_id <= 0 || trim((string)$content) === '') {
        return false;
    }

    if (class_exists('ITILSolution')) {
        $solution = new ITILSolution();

        $input = [
            'itemtype'         => 'Ticket',
            'items_id'         => $tickets_id,
            'solutiontypes_id' => 0,
            'content'          => $content
        ];

        $added = $solution->add($input, [
            'skip_checks' => true
        ]);

        if (!empty($added)) {
            return true;
        }
    }

    return pgegestor_add_ticket_followup($tickets_id, $content);
}

function pgegestor_mark_ticket_as_solved($tickets_id) {
    global $DB;

    $tickets_id = (int)$tickets_id;

    if ($tickets_id <= 0) {
        return false;
    }

    // Para reservas canceladas ou serviços que deixaram de ser necessários,
    // o chamado deve receber a solução e ir diretamente para Fechado.
    $status_closed = pgegestor_ticket_status_closed();

    $ticket = new Ticket();

    if ($ticket->getFromDB($tickets_id)) {
        $updated = $ticket->update([
            'id'     => $tickets_id,
            'status' => $status_closed
        ], false, [
            'skip_checks' => true
        ]);

        if ($updated) {
            return true;
        }
    }

    return $DB->update(
        'glpi_tickets',
        [
            'status'   => $status_closed,
            'date_mod' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s')
        ],
        [
            'id' => $tickets_id
        ]
    );
}

function pgegestor_build_reservation_form_from_reservation($reservation) {
    $comment_data = pgegestor_parse_comment($reservation['comment'] ?? '');

    return [
        'reservationitems_id' => (int)($reservation['reservationitems_id'] ?? 0),
        'titulo'              => $comment_data['titulo'],
        'participantes'       => $comment_data['participantes'],
        'zoom'                => $comment_data['zoom'],
        'apoio_ti'            => $comment_data['apoio_ti'],
        'copa'                => $comment_data['copa'],
        'ticket_ids'          => $comment_data['ticket_ids'],
        'ticket_map'          => $comment_data['ticket_map']
    ];
}

function pgegestor_build_ticket_content($reservation_id, $item_name, $form, $begin_sql, $end_sql) {
    $user_name = pgegestor_get_user_name((int)$_SESSION['glpiID']);

    $lines = [
        '<!-- PGEGESTOR_RESERVATION_ID:' . (int)$reservation_id . ' -->',
        '<strong>Local do evento:</strong> ' . pgegestor_h($item_name),
        '<strong>Título da reunião:</strong> ' . pgegestor_h($form['titulo']),
        '<strong>Número de participantes:</strong> ' . (int)$form['participantes'],
        '<strong>Data/hora inicial:</strong> ' . date('d/m/Y H:i', strtotime($begin_sql)),
        '<strong>Data/hora final:</strong> ' . date('d/m/Y H:i', strtotime($end_sql)),
        '<strong>Reservado por:</strong> ' . pgegestor_h($user_name),
        '',
        '<strong>Solicitações adicionais (opcionais):</strong>',
        'Link do Zoom: ' . ($form['zoom'] ? 'Sim' : 'Não'),
        'Apoio técnico de TI: ' . ($form['apoio_ti'] ? 'Sim' : 'Não'),
        'Serviços de copa (café/água): ' . ($form['copa'] ? 'Sim' : 'Não')
    ];

    return implode('<br>', $lines);
}

function pgegestor_build_reservation_update_message($reservation_id, $item_name, $form, $begin_sql, $end_sql) {
    $lines = [
        '<strong>Esta reserva foi alterada.</strong>',
        'Seguem os dados atualizados da reserva:',
        '',
        pgegestor_build_ticket_content(
            $reservation_id,
            $item_name,
            $form,
            $begin_sql,
            $end_sql
        )
    ];

    return implode('<br>', $lines);
}

function pgegestor_build_reservation_cancel_message($reservation_id, $item_name, $form, $begin_sql, $end_sql) {
    return '<strong>Esta reserva foi cancelada.</strong><br>'
        . 'Os serviços vinculados a esta reserva não deverão mais ser executados.';
}

function pgegestor_update_generated_tickets_for_reservation($reservation, $item_name, $form, $begin_sql, $end_sql, $exclude_ticket_ids = []) {
    $result = [
        'updated' => [],
        'errors'  => []
    ];

    $ticket_ids = pgegestor_get_related_ticket_ids_for_reservation($reservation);
    $exclude_ticket_ids = array_values(array_unique(array_filter(array_map('intval', (array)$exclude_ticket_ids))));

    if (!empty($exclude_ticket_ids)) {
        $ticket_ids = array_values(array_diff($ticket_ids, $exclude_ticket_ids));
    }

    if (empty($ticket_ids)) {
        return $result;
    }

    $content = pgegestor_build_reservation_update_message(
        (int)$reservation['reservations_id'],
        $item_name,
        $form,
        $begin_sql,
        $end_sql
    );

    foreach ($ticket_ids as $ticket_id) {
        $ok = pgegestor_add_ticket_followup($ticket_id, $content);

        if ($ok) {
            $result['updated'][] = $ticket_id;
        } else {
            $result['errors'][] = 'Não foi possível atualizar o chamado #' . $ticket_id . ' com os novos dados da reserva.';
        }
    }

    return $result;
}

function pgegestor_get_ticket_name($tickets_id) {
    $ticket = new Ticket();

    if ($ticket->getFromDB((int)$tickets_id)) {
        return (string)($ticket->fields['name'] ?? '');
    }

    return '';
}

function pgegestor_get_copa_ticket_ids_for_reservation($reservation) {
    if (!$reservation) {
        return [];
    }

    $comment_data = pgegestor_parse_comment($reservation['comment'] ?? '');

    if (!empty($comment_data['ticket_map']['copa'])) {
        return [(int)$comment_data['ticket_map']['copa']];
    }

    $ticket_ids = pgegestor_get_related_ticket_ids_for_reservation($reservation);
    $copa_ticket_ids = [];

    foreach ($ticket_ids as $ticket_id) {
        $ticket_name = pgegestor_get_ticket_name($ticket_id);

        if (
            stripos($ticket_name, 'copa') !== false
            || stripos($ticket_name, 'café') !== false
            || stripos($ticket_name, 'cafe') !== false
            || stripos($ticket_name, 'água') !== false
            || stripos($ticket_name, 'agua') !== false
        ) {
            $copa_ticket_ids[] = (int)$ticket_id;
        }
    }

    return array_values(array_unique(array_filter($copa_ticket_ids)));
}

function pgegestor_solve_ticket_ids_as_cancelled($ticket_ids) {
    $result = [
        'solved' => [],
        'errors' => []
    ];

    $ticket_ids = array_values(array_unique(array_filter(array_map('intval', (array)$ticket_ids))));

    if (empty($ticket_ids)) {
        return $result;
    }

    $content = pgegestor_build_reservation_cancel_message(0, '', [], '', '');

    foreach ($ticket_ids as $ticket_id) {
        $solution_added = pgegestor_add_ticket_solution($ticket_id, $content);
        $status_updated = pgegestor_mark_ticket_as_solved($ticket_id);

        if ($solution_added && $status_updated) {
            $result['solved'][] = $ticket_id;
        } else {
            $result['errors'][] = 'Não foi possível solucionar automaticamente o chamado #' . $ticket_id . '.';
        }
    }

    return $result;
}

function pgegestor_solve_generated_tickets_for_cancelled_reservation($reservation) {
    $result = [
        'solved' => [],
        'errors' => []
    ];

    if (!$reservation || empty($reservation['reservations_id'])) {
        $result['errors'][] = 'Não foi possível identificar a reserva para solucionar os chamados vinculados.';
        return $result;
    }

    $ticket_ids = pgegestor_get_related_ticket_ids_for_reservation($reservation);

    if (empty($ticket_ids)) {
        return $result;
    }

    $reservation_item = [
        'id'          => (int)$reservation['reservationitems_id'],
        'itemtype'    => $reservation['itemtype'],
        'items_id'    => (int)$reservation['items_id'],
        'entities_id' => (int)$reservation['entities_id'],
        'comment'     => $reservation['reservationitem_comment'] ?? ''
    ];

    $item_name = pgegestor_get_item_display_name($reservation_item);
    $form = pgegestor_build_reservation_form_from_reservation($reservation);

    $content = pgegestor_build_reservation_cancel_message(
        (int)$reservation['reservations_id'],
        $item_name,
        $form,
        $reservation['begin'],
        $reservation['end']
    );

    foreach ($ticket_ids as $ticket_id) {
        $solution_added = pgegestor_add_ticket_solution($ticket_id, $content);
        $status_updated = pgegestor_mark_ticket_as_solved($ticket_id);

        if ($solution_added && $status_updated) {
            $result['solved'][] = $ticket_id;
        } else {
            $result['errors'][] = 'Não foi possível solucionar automaticamente o chamado #' . $ticket_id . '.';
        }
    }

    return $result;
}

function pgegestor_profile_right($method, $default = false, ...$args) {
    $classes = [
        'PluginPgeservicosProfile',
        'PluginPgegestorProfile'
    ];

    foreach ($classes as $class) {
        if (class_exists($class) && method_exists($class, $method)) {
            return (bool)call_user_func_array([$class, $method], $args);
        }
    }

    return (bool)$default;
}

function pgegestor_create_ticket(
    $entities_id,
    $title,
    $content,
    $observer_group_name = '',
    $assign_group_name = ''
) {
    $result = [
        'ticket_id' => 0,
        'errors'    => []
    ];

    if ($entities_id === null || !is_numeric($entities_id) || (int)$entities_id < 0) {
        $result['errors'][] = 'Entidade inválida para abertura do chamado.';
        return $result;
    }

    $ticket = new Ticket();

    $input = [
        'entities_id'         => (int)$entities_id,
        'name'                => $title,
        'content'             => $content,
        'type'                => Ticket::DEMAND_TYPE,
        'status'              => Ticket::INCOMING,
        'users_id_recipient'  => (int)$_SESSION['glpiID'],
        '_users_id_requester' => (int)$_SESSION['glpiID'],
        'requesttypes_id'     => 1,
        'urgency'             => 3,
        'impact'              => 3,
        'priority'            => 3
    ];

    $ticket_id = $ticket->add($input, [
        'skip_checks' => true
    ]);

    if (!$ticket_id) {
        $result['errors'][] = 'Falha ao criar o chamado no GLPI.';
        return $result;
    }

    $result['ticket_id'] = (int)$ticket_id;

    if ($observer_group_name !== '') {
        $observer_group_id = pgegestor_get_group_id_by_completename_or_name($observer_group_name);

        if ($observer_group_id <= 0) {
            $result['errors'][] = 'Chamado #' . (int)$ticket_id . ': não foi possível localizar o grupo observador "' . $observer_group_name . '".';
        } else {
            $ok = pgegestor_add_group_to_ticket(
                (int)$ticket_id,
                $observer_group_id,
                pgegestor_actor_type('observer')
            );

            if (!$ok) {
                $result['errors'][] = 'Chamado #' . (int)$ticket_id . ': não foi possível adicionar o grupo "' . $observer_group_name . '" como Observador.';
            }
        }
    }

    if ($assign_group_name !== '') {
        $assign_group_id = pgegestor_get_group_id_by_completename_or_name($assign_group_name);

        if ($assign_group_id <= 0) {
            $result['errors'][] = 'Chamado #' . (int)$ticket_id . ': não foi possível localizar o grupo atribuído "' . $assign_group_name . '".';
        } else {
            $ok = pgegestor_add_group_to_ticket(
                (int)$ticket_id,
                $assign_group_id,
                pgegestor_actor_type('assign')
            );

            if (!$ok) {
                $result['errors'][] = 'Chamado #' . (int)$ticket_id . ': não foi possível adicionar o grupo "' . $assign_group_name . '" como Atribuído.';
            }
        }
    }

    return $result;
}

function pgegestor_create_reservation_tickets($reservation_id, $item_name, $form, $begin_sql, $end_sql) {
    $created_tickets = [];
    $ticket_map = [
        'tic'  => 0,
        'copa' => 0
    ];
    $errors = [];

    $content = pgegestor_build_ticket_content(
        $reservation_id,
        $item_name,
        $form,
        $begin_sql,
        $end_sql
    );

    $copa_entity_reference = 'PGE - ES > Suporte Administrativo';

    $tic_entity_id = pgegestor_get_parent_entity_id_from_child($copa_entity_reference);

    $tic_title = 'Preparação do ambiente de TIC para execução de eventos agendados, podendo necessitar de um técnico para acompanhar a execução do evento';

    if ($tic_entity_id === null) {
        $errors[] = 'Não foi possível localizar a entidade mãe de PGE - ES > Suporte Administrativo para abertura do chamado de TIC.';
    } else {
        $tic_ticket_result = pgegestor_create_ticket(
            $tic_entity_id,
            $tic_title,
            $content
        );

        if ($tic_ticket_result['ticket_id'] > 0) {
            $created_tickets[] = $tic_ticket_result['ticket_id'];
            $ticket_map['tic'] = (int)$tic_ticket_result['ticket_id'];
        }

        foreach ($tic_ticket_result['errors'] as $ticket_error) {
            $errors[] = $ticket_error;
        }
    }

    if (!empty($form['copa'])) {
        $copa_entity_id = pgegestor_get_entity_id_by_completename($copa_entity_reference);

        $copa_title = 'Solicitação de serviço de copa (café/água)';

        if ($copa_entity_id === null || $copa_entity_id < 0) {
            $errors[] = 'Não foi possível localizar a entidade PGE - ES > Suporte Administrativo para abertura do chamado de copa.';
        } else {
            $copa_ticket_result = pgegestor_create_ticket(
                $copa_entity_id,
                $copa_title,
                $content,
                'GEAD',
                'Contrato > Copeiras'
            );

            if ($copa_ticket_result['ticket_id'] > 0) {
                $created_tickets[] = $copa_ticket_result['ticket_id'];
                $ticket_map['copa'] = (int)$copa_ticket_result['ticket_id'];
            }

            foreach ($copa_ticket_result['errors'] as $ticket_error) {
                $errors[] = $ticket_error;
            }
        }
    }

    return [
        'tickets'    => $created_tickets,
        'ticket_map' => $ticket_map,
        'errors'     => $errors
    ];
}

function pgegestor_create_copa_ticket_for_reservation($reservation_id, $item_name, $form, $begin_sql, $end_sql) {
    $result = [
        'ticket_id' => 0,
        'errors'    => []
    ];

    $copa_entity_reference = 'PGE - ES > Suporte Administrativo';
    $copa_entity_id = pgegestor_get_entity_id_by_completename($copa_entity_reference);

    if ($copa_entity_id === null || $copa_entity_id < 0) {
        $result['errors'][] = 'Não foi possível localizar a entidade PGE - ES > Suporte Administrativo para abertura do chamado de copa.';
        return $result;
    }

    $content = pgegestor_build_ticket_content(
        $reservation_id,
        $item_name,
        $form,
        $begin_sql,
        $end_sql
    );

    $ticket_result = pgegestor_create_ticket(
        $copa_entity_id,
        'Solicitação de serviço de copa (café/água)',
        $content,
        'GEAD',
        'Contrato > Copeiras'
    );

    $result['ticket_id'] = (int)($ticket_result['ticket_id'] ?? 0);
    $result['errors'] = $ticket_result['errors'] ?? [];

    return $result;
}

$now_dt = new DateTime();

$can_create_reservation = pgegestor_profile_right('canCreateReservation', true);
$can_update_reservation = false;
$can_delete_reservation = false;
$can_manage_past_reservation = pgegestor_profile_right('canManagePastReservations', false);

$reservations_id = isset($_GET['reservations_id'])
    ? (int)$_GET['reservations_id']
    : (int)($_POST['reservations_id'] ?? 0);

$is_edit = $reservations_id > 0;

$reservation = null;
$reservation_item = null;
$reservation_is_past = false;

if ($is_edit) {
    $reservation = pgegestor_get_reservation($reservations_id);

    if (!$reservation) {
        Html::displayErrorAndDie('Reserva não encontrada ou sem permissão de acesso.');
    }

    $reservation_begin_dt = new DateTime($reservation['begin']);
    $reservation_is_past = $reservation_begin_dt < $now_dt;

    $reservation_item = [
        'id'          => (int)$reservation['reservationitems_id'],
        'itemtype'    => $reservation['itemtype'],
        'items_id'    => (int)$reservation['items_id'],
        'entities_id' => (int)$reservation['entities_id'],
        'comment'     => $reservation['reservationitem_comment'] ?? ''
    ];

    $can_update_reservation = pgegestor_profile_right('canEditReservation', false, (int)$reservation['users_id']);
    $can_delete_reservation = pgegestor_profile_right('canDeleteReservation', false, (int)$reservation['users_id']);
}

$date = $_GET['date'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$get_reservationitems_id = isset($_GET['reservationitems_id'])
    ? (int)$_GET['reservationitems_id']
    : 0;

$return_calendar = $_GET['return_calendar']
    ?? $_POST['return_calendar']
    ?? 'item';

$return_calendar = $return_calendar === 'full' ? 'full' : 'item';

$default_begin = $date . 'T08:00';
$default_end   = $date . 'T09:00';

$errors = [];

if ($is_edit) {
    $comment_data = pgegestor_parse_comment($reservation['comment'] ?? '');

    $form = [
        'reservationitems_id' => (int)$reservation['reservationitems_id'],
        'titulo'              => $comment_data['titulo'],
        'participantes'       => $comment_data['participantes'],
        'begin'               => pgegestor_to_datetime_local($reservation['begin']),
        'end'                 => pgegestor_to_datetime_local($reservation['end']),
        'zoom'                => $comment_data['zoom'],
        'apoio_ti'            => $comment_data['apoio_ti'],
        'copa'                => $comment_data['copa'],
        'ticket_ids'          => $comment_data['ticket_ids'],
        'ticket_map'          => $comment_data['ticket_map']
    ];
} else {
    $form = [
        'reservationitems_id' => $get_reservationitems_id,
        'titulo'              => '',
        'participantes'       => '',
        'begin'               => $default_begin,
        'end'                 => $default_end,
        'zoom'                => false,
        'apoio_ti'            => false,
        'copa'                => false,
        'ticket_ids'          => [],
        'ticket_map'          => [
            'tic'  => 0,
            'copa' => 0
        ]
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? ($is_edit ? 'update' : 'create');

    $posted_csrf_token = $_POST['pgegestor_csrf_token'] ?? '';

    if (!pgegestor_validate_csrf_token($posted_csrf_token)) {
        $errors[] = 'Token de segurança inválido. Recarregue a página e tente novamente.';
    }

    if ($is_edit && $reservation_is_past && !$can_manage_past_reservation) {
        $errors[] = 'Seu perfil não possui permissão para alterar ou excluir reservas antigas.';
    }

    if ($is_edit && $form_action === 'delete') {
        if (!$can_delete_reservation) {
            $errors[] = 'Seu perfil não possui permissão para excluir esta reserva.';
        }

        if (empty($errors)) {
            $redirect_month = (new DateTime($reservation['begin']))->format('Y-m');
            $redirect_item  = (int)$reservation['reservationitems_id'];

            $delete_ok = $DB->delete(
                'glpi_reservations',
                [
                    'id' => $reservations_id
                ]
            );

            if ($delete_ok) {
                $ticket_solve_result = pgegestor_solve_generated_tickets_for_cancelled_reservation($reservation);

                unset($_SESSION[PGEGESTOR_CSRF_SESSION_KEY]);

                $message = 'Reserva excluída com sucesso.';

                if (!empty($ticket_solve_result['solved'])) {
                    $message .= ' Chamado(s) vinculado(s) solucionado(s): #'
                        . implode(', #', $ticket_solve_result['solved'])
                        . '.';
                }

                Session::addMessageAfterRedirect($message, true, INFO);

                foreach ($ticket_solve_result['errors'] as $ticket_error) {
                    Session::addMessageAfterRedirect($ticket_error, true, WARNING);
                }

		Html::redirect(
		    pgegestor_get_calendar_redirect_url(
		        $redirect_item,
		        $redirect_month,
		        $return_calendar
		    )
		);
            }

            $errors[] = 'Não foi possível excluir a reserva.';
        }
    } else {
        if ($is_edit && !$can_update_reservation) {
            $errors[] = 'Seu perfil não possui permissão para alterar esta reserva.';
        }

        if (!$is_edit && !$can_create_reservation) {
            $errors[] = 'Seu perfil não possui permissão para criar reservas.';
        }

        $form['reservationitems_id'] = isset($_POST['reservationitems_id'])
            ? (int)$_POST['reservationitems_id']
            : 0;

        $form['titulo'] = trim($_POST['titulo'] ?? '');
        $form['participantes'] = trim($_POST['participantes'] ?? '');
        $form['begin']  = $_POST['begin'] ?? '';
        $form['end']    = $_POST['end'] ?? '';

        $form['zoom']     = isset($_POST['zoom']);
        $form['apoio_ti'] = isset($_POST['apoio_ti']);
        $form['copa']     = isset($_POST['copa']);

        $previous_form = $is_edit
            ? pgegestor_build_reservation_form_from_reservation($reservation)
            : null;

        $copa_ticket_ids_to_solve = [];
        $copa_was_removed = false;
        $copa_was_added = false;

        if ($is_edit) {
            $comment_data = pgegestor_parse_comment($reservation['comment'] ?? '');
            $form['ticket_ids'] = $comment_data['ticket_ids'];
            $form['ticket_map'] = $comment_data['ticket_map'];

            $copa_was_removed = !empty($previous_form['copa']) && empty($form['copa']);
            $copa_was_added = empty($previous_form['copa']) && !empty($form['copa']);

            if ($copa_was_removed) {
                $copa_ticket_ids_to_solve = pgegestor_get_copa_ticket_ids_for_reservation($reservation);

                if (!empty($copa_ticket_ids_to_solve)) {
                    $form['ticket_ids'] = array_values(array_diff($form['ticket_ids'], $copa_ticket_ids_to_solve));

                    if (!empty($form['ticket_map']['copa']) && in_array((int)$form['ticket_map']['copa'], $copa_ticket_ids_to_solve, true)) {
                        $form['ticket_map']['copa'] = 0;
                    }
                }
            }
        }

        if ($form['reservationitems_id'] <= 0) {
            $errors[] = 'Selecione o item que será reservado.';
        }

        if ($form['titulo'] === '') {
            $errors[] = 'Informe o título da reunião.';
        }

        if ($form['participantes'] === '') {
            $errors[] = 'Informe o número de participantes.';
        } elseif (!ctype_digit($form['participantes']) || (int)$form['participantes'] <= 0) {
            $errors[] = 'O número de participantes deve conter apenas números e ser maior que zero.';
        }

        $begin_sql = pgegestor_sql_datetime_from_local($form['begin']);
        $end_sql   = pgegestor_sql_datetime_from_local($form['end']);

        if ($begin_sql === null) {
            $errors[] = 'Informe uma data e hora inicial válida.';
        }

        if ($end_sql === null) {
            $errors[] = 'Informe uma data e hora final válida.';
        }

        if ($begin_sql !== null && $end_sql !== null) {
            $begin_dt = new DateTime($begin_sql);
            $end_dt   = new DateTime($end_sql);

            if (!$is_edit && $begin_dt < $now_dt) {
                $errors[] = 'A data e hora inicial não pode ser anterior ao momento atual.';
            }

            if ($is_edit && $begin_dt < $now_dt && !$can_manage_past_reservation) {
                $errors[] = 'Seu perfil não possui permissão para salvar reservas antigas ou alterar uma reserva para data/hora anterior ao momento atual.';
            }

            if ($end_dt <= $begin_dt) {
                $errors[] = 'A data e hora final deve ser posterior à data e hora inicial.';
            }
        }

        if (!$is_edit) {
            $reservation_item = null;

            if ($form['reservationitems_id'] > 0) {
                $reservation_item = pgegestor_get_reservation_item($form['reservationitems_id']);

                if (!$reservation_item) {
                    $errors[] = 'Item de reserva não encontrado ou sem permissão de acesso.';
                }
            }
        }

        if ($reservation_item && $form['participantes'] !== '' && ctype_digit($form['participantes'])) {
            $participants = (int)$form['participantes'];
            $capacity = pgegestor_extract_capacity_from_reservation_item($reservation_item);

            if ($capacity <= 0) {
                $errors[] = 'Não foi possível identificar a capacidade do item. Informe um número no campo Comentários do item reservável, por exemplo: Capacidade: 20.';
            } elseif ($participants > $capacity) {
                $errors[] = 'O número de participantes informado (' . $participants . ') é maior que a capacidade cadastrada para este item (' . $capacity . ').';
            }
        }

        if (empty($errors) && $begin_sql !== null && $end_sql !== null) {
            $conflict = pgegestor_has_conflict(
                $form['reservationitems_id'],
                $begin_sql,
                $end_sql,
                $is_edit ? $reservations_id : 0
            );

            if ($conflict) {
                $errors[] = 'Já existe uma reserva para este item no período informado: '
                    . date('d/m/Y H:i', strtotime($conflict['begin']))
                    . ' até '
                    . date('d/m/Y H:i', strtotime($conflict['end']))
                    . ', realizada por '
                    . pgegestor_get_user_name((int)$conflict['users_id'])
                    . '.';
            }
        }

        if (empty($errors)) {
            $item_name = pgegestor_get_item_display_name($reservation_item);

            $comment_lines = [
                'Título da reunião: ' . $form['titulo'],
                'Número de participantes: ' . (int)$form['participantes'],
                '',
                'Solicitações adicionais (opcionais):',
                '- Link do Zoom: ' . ($form['zoom'] ? 'Sim' : 'Não'),
                '- Apoio técnico de TI: ' . ($form['apoio_ti'] ? 'Sim' : 'Não'),
                '- Serviços de copa (café/água): ' . ($form['copa'] ? 'Sim' : 'Não'),
                '',
                'Item reservado: ' . $item_name
            ];

            $comment_lines_base = $comment_lines;

            if ($is_edit && (!empty($form['ticket_ids']) || !empty($form['ticket_map']['tic']) || !empty($form['ticket_map']['copa']))) {
                if (!empty($form['ticket_map']['tic']) || !empty($form['ticket_map']['copa'])) {
                    $comment_lines = pgegestor_append_ticket_metadata_to_comment_lines(
                        $comment_lines,
                        $form['ticket_map']
                    );
                } else {
                    $comment_lines = pgegestor_append_ticket_ids_to_comment_lines(
                        $comment_lines,
                        $form['ticket_ids']
                    );
                }
            }

            if ($is_edit) {
                $save_ok = $DB->update(
                    'glpi_reservations',
                    [
                        'begin'   => $begin_sql,
                        'end'     => $end_sql,
                        'comment' => implode("\n", $comment_lines)
                    ],
                    [
                        'id' => $reservations_id
                    ]
                );
            } else {
                $save_ok = $DB->insert(
                    'glpi_reservations',
                    [
                        'reservationitems_id' => (int)$form['reservationitems_id'],
                        'users_id'            => (int)$_SESSION['glpiID'],
                        'begin'               => $begin_sql,
                        'end'                 => $end_sql,
                        'comment'             => implode("\n", $comment_lines)
                    ]
                );
            }

            if ($save_ok) {
                unset($_SESSION[PGEGESTOR_CSRF_SESSION_KEY]);

                if ($is_edit) {
                    $copa_solve_result = [
                        'solved' => [],
                        'errors' => []
                    ];

                    $copa_create_result = [
                        'ticket_id' => 0,
                        'errors'    => []
                    ];

                    if (!empty($copa_ticket_ids_to_solve)) {
                        $copa_solve_result = pgegestor_solve_ticket_ids_as_cancelled($copa_ticket_ids_to_solve);
                    }

                    if ($copa_was_added) {
                        $copa_create_result = pgegestor_create_copa_ticket_for_reservation(
                            $reservations_id,
                            $item_name,
                            $form,
                            $begin_sql,
                            $end_sql
                        );

                        if (!empty($copa_create_result['ticket_id'])) {
                            $form['ticket_map']['copa'] = (int)$copa_create_result['ticket_id'];
                            $form['ticket_ids'][] = (int)$copa_create_result['ticket_id'];
                            $form['ticket_ids'] = array_values(array_unique(array_filter(array_map('intval', $form['ticket_ids']))));

                            $comment_lines_with_new_ticket = pgegestor_append_ticket_metadata_to_comment_lines(
                                $comment_lines_base,
                                $form['ticket_map']
                            );

                            $DB->update(
                                'glpi_reservations',
                                [
                                    'comment' => implode("\n", $comment_lines_with_new_ticket)
                                ],
                                [
                                    'id' => $reservations_id
                                ]
                            );
                        }
                    }

                    $ticket_update_result = pgegestor_update_generated_tickets_for_reservation(
                        $reservation,
                        $item_name,
                        $form,
                        $begin_sql,
                        $end_sql,
                        $copa_ticket_ids_to_solve
                    );

                    $message = 'Reserva alterada com sucesso.';

                    if (!empty($ticket_update_result['updated'])) {
                        $message .= ' Chamado(s) vinculado(s) atualizado(s): #'
                            . implode(', #', $ticket_update_result['updated'])
                            . '.';
                    }

                    if (!empty($copa_create_result['ticket_id'])) {
                        $message .= ' Chamado de copa aberto: #'
                            . (int)$copa_create_result['ticket_id']
                            . '.';
                    }

                    if (!empty($copa_solve_result['solved'])) {
                        $message .= ' Chamado(s) de copa fechado(s): #'
                            . implode(', #', $copa_solve_result['solved'])
                            . '.';
                    }

                    Session::addMessageAfterRedirect($message, true, INFO);

                    foreach ($ticket_update_result['errors'] as $ticket_error) {
                        Session::addMessageAfterRedirect($ticket_error, true, WARNING);
                    }

                    foreach ($copa_create_result['errors'] as $ticket_error) {
                        Session::addMessageAfterRedirect($ticket_error, true, WARNING);
                    }

                    foreach ($copa_solve_result['errors'] as $ticket_error) {
                        Session::addMessageAfterRedirect($ticket_error, true, WARNING);
                    }
                } else {
                    $new_reservation_id = 0;

                    if (method_exists($DB, 'insertId')) {
                        $new_reservation_id = (int)$DB->insertId();
                    } elseif (method_exists($DB, 'lastInsertId')) {
                        $new_reservation_id = (int)$DB->lastInsertId();
                    }

                    $ticket_result = pgegestor_create_reservation_tickets(
                        $new_reservation_id,
                        $item_name,
                        $form,
                        $begin_sql,
                        $end_sql
                    );

                    $ticket_ids = $ticket_result['tickets'];
                    $ticket_map = $ticket_result['ticket_map'] ?? [];
                    $ticket_errors = $ticket_result['errors'];

                    if (!empty($ticket_ids)) {
                        $comment_lines_with_tickets = pgegestor_append_ticket_metadata_to_comment_lines(
                            $comment_lines,
                            $ticket_map
                        );

                        $DB->update(
                            'glpi_reservations',
                            [
                                'comment' => implode("\n", $comment_lines_with_tickets)
                            ],
                            [
                                'id' => $new_reservation_id
                            ]
                        );

                        Session::addMessageAfterRedirect(
                            'Reserva criada com sucesso. Chamado(s) aberto(s): #' . implode(', #', $ticket_ids) . '.',
                            true,
                            INFO
                        );
                    } else {
                        Session::addMessageAfterRedirect(
                            'Reserva criada com sucesso, mas nenhum chamado foi aberto automaticamente.',
                            true,
                            WARNING
                        );
                    }

                    foreach ($ticket_errors as $ticket_error) {
                        Session::addMessageAfterRedirect($ticket_error, true, WARNING);
                    }
                }

                $redirect_month = (new DateTime($begin_sql))->format('Y-m');

		Html::redirect(
		    pgegestor_get_calendar_redirect_url(
		        (int)$form['reservationitems_id'],
		        $redirect_month,
		        $return_calendar
		    )
		);
            }

            $errors[] = $is_edit
                ? 'Não foi possível alterar a reserva. Verifique os dados informados.'
                : 'Não foi possível criar a reserva. Verifique os dados informados.';
        }
    }
}

$selected_reservationitems_id = $form['reservationitems_id'] > 0
    ? (int)$form['reservationitems_id']
    : $get_reservationitems_id;

$current_item = null;

if ($selected_reservationitems_id > 0) {
    if ($is_edit && $reservation_item) {
        $current_item = $reservation_item;
    } else {
        $current_item = pgegestor_get_reservation_item($selected_reservationitems_id);
    }
}

$items = pgegestor_get_reservable_items();

$csrf_token = pgegestor_generate_csrf_token(!empty($errors) || $_SERVER['REQUEST_METHOD'] !== 'POST');
$glpi_csrf_token = Session::getNewCSRFToken(true);

$page_h1 = $is_edit ? 'Editar reserva' : 'Nova reserva';
$submit_label = $is_edit ? 'Alterar reserva' : 'Criar reserva';
$submit_icon = $is_edit ? 'ti ti-edit' : 'ti ti-device-floppy';

Html::header(
    $is_edit ? 'Reservas de Salas - Editar Reserva' : 'Reservas de Salas - Nova Reserva',
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginPgeservicosPortal'
);

$asset_version = defined('PLUGIN_PGESERVICOS_VERSION')
    ? PLUGIN_PGESERVICOS_VERSION
    : '1';

echo "<link rel='stylesheet' href='"
    . pgegestor_h($CFG_GLPI['root_doc'])
    . "/plugins/pgeservicos/css/pages/adicionar.css?v="
    . pgegestor_h($asset_version)
    . "'>";

pgeservicos_theme_print_vars();

echo "<div class='pgegestor-page'>";
echo "<div class='pgegestor-card'>";

echo "<h1>" . pgegestor_h($page_h1) . "</h1>";
echo "<p class='pgegestor-muted'>"
    . ($is_edit
        ? 'Atualize os dados da reserva selecionada.'
        : 'Preencha os dados abaixo para reservar a sala/equipamento.')
    . "</p>";

if ($is_edit && $reservation_is_past && !$can_manage_past_reservation) {
    echo "<div class='pgegestor-warning'>";
    echo "Esta reserva já ocorreu. Seu perfil não possui permissão para alterar ou excluir reservas antigas.";
    echo "</div>";
}

if (!$is_edit && !$can_create_reservation) {
    echo "<div class='pgegestor-warning'>";
    echo "Seu perfil não possui permissão para criar reservas.";
    echo "</div>";
}

if ($is_edit && !$can_update_reservation) {
    echo "<div class='pgegestor-warning'>";
    echo "Seu perfil não possui permissão para alterar esta reserva.";
    echo "</div>";
}

if ($is_edit && !$can_delete_reservation) {
    echo "<div class='pgegestor-warning'>";
    echo "Seu perfil não possui permissão para excluir esta reserva.";
    echo "</div>";
}

if (!empty($errors)) {
    echo "<div class='pgegestor-alert'>";
    echo "<strong>Verifique os campos abaixo:</strong><br>";

    foreach ($errors as $error) {
        echo "- " . pgegestor_h($error) . "<br>";
    }

    echo "</div>";
}

echo "<form method='post' action='" . pgegestor_h($CFG_GLPI['root_doc']) . "/plugins/pgeservicos/front/adicionar.php'>";

echo "<input type='hidden' name='_glpi_csrf_token' value='" . pgegestor_h($glpi_csrf_token) . "'>";
echo "<input type='hidden' name='pgegestor_csrf_token' value='" . pgegestor_h($csrf_token) . "'>";
echo "<input type='hidden' name='return_calendar' value='" . pgegestor_h($return_calendar) . "'>";

if ($is_edit) {
    echo "<input type='hidden' name='reservations_id' value='" . (int)$reservations_id . "'>";
}

echo "<div class='pgegestor-form-grid'>";

$selected_capacity = 0;

if ($current_item) {
    $selected_capacity = pgegestor_extract_capacity_from_reservation_item($current_item);
} else {
    foreach ($items as $item) {
        if ((int)$form['reservationitems_id'] === (int)$item['id']) {
            $selected_capacity = (int)$item['capacity'];
            break;
        }
    }
}

$capacity_message = $selected_capacity > 0
    ? 'Até ' . $selected_capacity . ' participantes'
    : '0 participantes';

echo "<div class='pgegestor-form-group full'>";
echo "<div class='pgegestor-item-capacity-row'>";
echo "<div class='pgegestor-item-field'>";
echo "<label for='reservationitems_id'>Item a reservar <span class='pgegestor-required'>*</span></label>";

if ($current_item) {
    echo "<input type='hidden' name='reservationitems_id' value='" . (int)$current_item['id'] . "'>";
    echo "<input type='text' class='form-control' value='" . pgegestor_h(pgegestor_get_item_display_name($current_item)) . "' disabled>";
} else {
    echo "<select class='form-control' name='reservationitems_id' id='reservationitems_id' required>";
    echo "<option value=''>Selecione...</option>";

    foreach ($items as $item) {
        $selected = ((int)$form['reservationitems_id'] === (int)$item['id'])
            ? " selected"
            : "";

        echo "<option value='" . (int)$item['id'] . "' data-capacity='" . (int)$item['capacity'] . "'" . $selected . ">"
            . pgegestor_h($item['label'])
            . "</option>";
    }

    echo "</select>";
}

echo "</div>";
echo "<div class='pgegestor-form-group pgegestor-capacity-field'>";
echo "<label for='pgegestor-capacity-text'>Capacidade</label>";
echo "<input type='text' class='form-control' id='pgegestor-capacity-text' value='" . pgegestor_h($capacity_message) . "' disabled>";
echo "</div>";
echo "</div>";
echo "</div>";

if ($is_edit) {
    echo "<div class='pgegestor-form-group full'>";
    echo "<label>Reservado por</label>";
    echo "<input type='text' class='form-control' value='" . pgegestor_h(pgegestor_get_user_name((int)$reservation['users_id'])) . "' disabled>";
    echo "</div>";

    $ticket_links_html = pgegestor_build_ticket_links_html(pgegestor_get_ticket_ids_for_display($form));

    if ($ticket_links_html !== '') {
        echo "<div class='pgegestor-form-group full'>";
        echo "<label>Chamado(s) vinculado(s)</label>";
        echo "<div class='pgegestor-ticket-links'>" . $ticket_links_html . "</div>";
        echo "</div>";
    }
}

echo "<div class='pgegestor-title-participants-row'>";

echo "<div class='pgegestor-form-group'>";
echo "<label for='titulo'>Título da reunião <span class='pgegestor-required'>*</span></label>";
echo "<input type='text' class='form-control' name='titulo' id='titulo' required maxlength='255' value='" . pgegestor_h($form['titulo']) . "'>";
echo "</div>";

echo "<div class='pgegestor-form-group'>";
echo "<label for='participantes'>Nº de participantes <span class='pgegestor-required'>*</span></label>";
echo "<input type='number' class='form-control' name='participantes' id='participantes' required min='1' step='1' inputmode='numeric' value='" . pgegestor_h($form['participantes']) . "'>";
echo "</div>";

echo "</div>";

echo "<div class='pgegestor-form-group'>";
echo "<label for='begin'>Data e hora inicial <span class='pgegestor-required'>*</span></label>";
echo "<input type='datetime-local' class='form-control' name='begin' id='begin' required value='" . pgegestor_h($form['begin']) . "'>";
echo "</div>";

echo "<div class='pgegestor-form-group'>";
echo "<label for='end'>Data e hora final <span class='pgegestor-required'>*</span></label>";
echo "<input type='datetime-local' class='form-control' name='end' id='end' required value='" . pgegestor_h($form['end']) . "'>";
echo "</div>";

echo "<div class='pgegestor-form-group full'>";
echo "<label>Solicitações adicionais (opcionais)</label>";
echo "<div class='pgegestor-checkboxes'>";

echo "<label class='pgegestor-checkbox-item'>";
echo "<input type='checkbox' name='zoom' value='1'" . ($form['zoom'] ? " checked" : "") . ">";
echo "<span>Solicitar link do Zoom</span>";
echo "</label>";

echo "<label class='pgegestor-checkbox-item'>";
echo "<input type='checkbox' name='apoio_ti' value='1'" . ($form['apoio_ti'] ? " checked" : "") . ">";
echo "<span>Solicitar apoio técnico de TI para acompanhamento do evento</span>";
echo "</label>";

echo "<label class='pgegestor-checkbox-item'>";
echo "<input type='checkbox' name='copa' value='1'" . ($form['copa'] ? " checked" : "") . ">";
echo "<span>Solicitar serviços de copa (café/água)</span>";
echo "</label>";

echo "</div>";
echo "</div>";

echo "</div>";

$past_locked = ($is_edit && $reservation_is_past && !$can_manage_past_reservation);

echo "<div class='pgegestor-actions'>";

if ($is_edit) {
    $delete_disabled = (!$can_delete_reservation || $past_locked);

    echo "<button type='submit'
                  name='form_action'
                  value='delete'
                  class='btn btn-danger pgegestor-actions-left'
                  data-pgegestor-confirm-delete='1'"
        . ($delete_disabled ? " disabled" : "")
        . ">";
    echo "<i class='ti ti-trash'></i> Excluir reserva";
    echo "</button>";
}

echo "<a class='btn btn-secondary' href='javascript:history.back();'>";
echo "<i class='ti ti-arrow-left'></i> Voltar";
echo "</a>";

$save_disabled = (($is_edit && (!$can_update_reservation || $past_locked)) || (!$is_edit && !$can_create_reservation));

echo "<button type='submit' name='form_action' value='" . ($is_edit ? 'update' : 'create') . "' class='btn btn-primary'"
    . ($save_disabled ? " disabled" : "")
    . ">";
echo "<i class='" . pgegestor_h($submit_icon) . "'></i> " . pgegestor_h($submit_label);
echo "</button>";

echo "</div>";

echo "</form>";

echo "</div>";
echo "</div>";

echo "<script src='"
    . pgegestor_h($CFG_GLPI['root_doc'])
    . "/plugins/pgeservicos/js/pages/adicionar.js?v="
    . pgegestor_h($asset_version)
    . "'></script>";

Html::footer();
