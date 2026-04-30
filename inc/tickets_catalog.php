<?php

if (!function_exists('pgeservicos_ticket_h')) {
    function pgeservicos_ticket_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('pgeservicos_get_accessible_entities')) {
    function pgeservicos_get_accessible_entities() {
        $entities = [];

        if (!empty($_SESSION['glpiactiveprofile']['entities'])) {
            foreach ($_SESSION['glpiactiveprofile']['entities'] as $profile_entity) {
                $entities_id = (int)($profile_entity['id'] ?? -1);

                if ($entities_id < 0) {
                    continue;
                }

                $entities[$entities_id] = $entities_id;

                if (!empty($profile_entity['is_recursive'])) {
                    foreach (getSonsOf('glpi_entities', $entities_id) as $son_id => $son_name) {
                        $entities[(int)$son_id] = (int)$son_id;
                    }
                }
            }
        }

        if (empty($entities) && !empty($_SESSION['glpiactiveentities'])) {
            foreach ($_SESSION['glpiactiveentities'] as $entities_id) {
                $entities[(int)$entities_id] = (int)$entities_id;
            }
        }

        ksort($entities);

        return array_values($entities);
    }
}

if (!function_exists('pgeservicos_get_ticket_last_view_at')) {
    function pgeservicos_get_ticket_last_view_at() {
        return $_SESSION['pgeservicos_meus_chamados_last_view'] ?? null;
    }
}

if (!function_exists('pgeservicos_touch_ticket_last_view')) {
    function pgeservicos_touch_ticket_last_view() {
        $_SESSION['pgeservicos_meus_chamados_last_view'] = date('Y-m-d H:i:s');
    }
}

if (!function_exists('pgeservicos_get_recent_ticket_cutoff')) {
    function pgeservicos_get_recent_ticket_cutoff() {
        return date('Y-m-d H:i:s', strtotime('-7 days'));
    }
}

if (!function_exists('pgeservicos_is_ticket_updated')) {
    function pgeservicos_is_ticket_updated($date_mod, $last_view_at = null, $tickets_id = 0) {
        if (empty($date_mod)) {
            return false;
        }

        $tickets_id = (int)$tickets_id;

        if (
            $tickets_id > 0
            && !empty($_SESSION['pgeservicos_meus_chamados_seen'][$tickets_id])
        ) {
            return strtotime((string)$date_mod)
                > strtotime((string)$_SESSION['pgeservicos_meus_chamados_seen'][$tickets_id]);
        }

        $reference = pgeservicos_get_recent_ticket_cutoff();

        return strtotime((string)$date_mod) > strtotime((string)$reference);
    }
}

if (!function_exists('pgeservicos_mark_ticket_seen')) {
    function pgeservicos_mark_ticket_seen($tickets_id, $date_mod) {
        $tickets_id = (int)$tickets_id;

        if ($tickets_id <= 0 || empty($date_mod)) {
            return;
        }

        if (!isset($_SESSION['pgeservicos_meus_chamados_seen'])) {
            $_SESSION['pgeservicos_meus_chamados_seen'] = [];
        }

        $_SESSION['pgeservicos_meus_chamados_seen'][$tickets_id] = (string)$date_mod;
    }
}

if (!function_exists('pgeservicos_ticket_involvement_label')) {
    function pgeservicos_ticket_involvement_label($involvement) {
        $labels = [
            'requerente' => 'Requerente',
            'observador' => 'Observador',
            'tecnico'    => 'Técnico',
            'grupo'      => 'Grupo'
        ];

        return $labels[$involvement] ?? $involvement;
    }
}

if (!function_exists('pgeservicos_ticket_actor_type_to_key')) {
    function pgeservicos_ticket_actor_type_to_key($type) {
        $type = (int)$type;

        if ($type === CommonITILActor::REQUESTER) {
            return 'requerente';
        }

        if ($type === CommonITILActor::OBSERVER) {
            return 'observador';
        }

        if ($type === CommonITILActor::ASSIGN) {
            return 'tecnico';
        }

        return '';
    }
}

if (!function_exists('pgeservicos_add_ticket_involvement')) {
    function pgeservicos_add_ticket_involvement(&$tickets, $tickets_id, $involvement) {
        $tickets_id = (int)$tickets_id;

        if ($tickets_id <= 0 || $involvement === '') {
            return;
        }

        if (!isset($tickets[$tickets_id])) {
            $tickets[$tickets_id] = [];
        }

        $tickets[$tickets_id][$involvement] = $involvement;
    }
}

if (!function_exists('pgeservicos_collect_user_ticket_involvements')) {
    function pgeservicos_collect_user_ticket_involvements($users_id) {
        global $DB;

        $tickets = [];
        $users_id = (int)$users_id;

        if ($users_id <= 0) {
            return $tickets;
        }

        foreach ($DB->request([
            'SELECT' => ['tickets_id', 'type'],
            'FROM'   => 'glpi_tickets_users',
            'WHERE'  => [
                'users_id' => $users_id,
                'type'     => [
                    CommonITILActor::REQUESTER,
                    CommonITILActor::OBSERVER,
                    CommonITILActor::ASSIGN
                ]
            ]
        ]) as $row) {
            pgeservicos_add_ticket_involvement(
                $tickets,
                (int)$row['tickets_id'],
                pgeservicos_ticket_actor_type_to_key($row['type'])
            );
        }

        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_tickets',
            'WHERE'  => [
                'users_id_recipient' => $users_id,
                'is_deleted'         => 0
            ]
        ]) as $row) {
            pgeservicos_add_ticket_involvement($tickets, (int)$row['id'], 'requerente');
        }

        $groups = $_SESSION['glpigroups'] ?? [];

        if (!empty($groups)) {
            foreach ($DB->request([
                'SELECT' => ['tickets_id'],
                'FROM'   => 'glpi_groups_tickets',
                'WHERE'  => [
                    'groups_id' => array_map('intval', $groups),
                    'type'      => [
                        CommonITILActor::REQUESTER,
                        CommonITILActor::OBSERVER,
                        CommonITILActor::ASSIGN
                    ]
                ]
            ]) as $row) {
                pgeservicos_add_ticket_involvement($tickets, (int)$row['tickets_id'], 'grupo');
            }
        }

        return $tickets;
    }
}

if (!function_exists('pgeservicos_normalize_ticket_filters')) {
    function pgeservicos_normalize_ticket_filters($input) {
        $status = isset($input['status']) ? (int)$input['status'] : 0;
        $entity = isset($input['entidade']) ? (int)$input['entidade'] : -1;
        $updated = !empty($input['atualizados']) ? 1 : 0;
        $involvement = isset($input['envolvimento'])
            ? preg_replace('/[^a-z_]/', '', (string)$input['envolvimento'])
            : '';

        if (!in_array($status, [1, 2, 3, 4, 5, 6], true)) {
            $status = 0;
        }

        if (!in_array($involvement, ['requerente', 'observador', 'tecnico', 'grupo'], true)) {
            $involvement = '';
        }

        return [
            'q'            => trim((string)($input['q'] ?? '')),
            'status'       => $status,
            'envolvimento' => $involvement,
            'entidade'     => $entity >= 0 ? $entity : -1,
            'atualizados'  => $updated
        ];
    }
}

if (!function_exists('pgeservicos_get_entity_display_names')) {
    function pgeservicos_get_entity_display_names($entities_ids) {
        global $DB;

        $entities_ids = array_values(array_unique(array_map('intval', $entities_ids)));
        $names = [];

        if (empty($entities_ids)) {
            return $names;
        }

        foreach ($DB->request([
            'SELECT' => ['id', 'name', 'completename'],
            'FROM'   => 'glpi_entities',
            'WHERE'  => ['id' => $entities_ids]
        ]) as $entity) {
            $id = (int)$entity['id'];
            $name = $entity['completename'] ?: $entity['name'];
            $names[$id] = $name ?: ('Entidade #' . $id);
        }

        foreach ($entities_ids as $id) {
            if (!isset($names[$id])) {
                $fallback = Dropdown::getDropdownName('glpi_entities', $id);
                $names[$id] = $fallback ?: ('Entidade #' . $id);
            }
        }

        return $names;
    }
}

if (!function_exists('pgeservicos_get_ticket_area_name')) {
    function pgeservicos_get_ticket_area_name($entities_id, $entity_names) {
        $entities_id = (int)$entities_id;
        $name = $entity_names[$entities_id] ?? ('Entidade #' . $entities_id);
        $normalized = mb_strtolower($name, 'UTF-8');

        if (
            $entities_id === 0
            || preg_match('/\bpge\s*-\s*es\b/u', $normalized)
            || strpos($normalized, 'portal pge') !== false
        ) {
            return 'Serviços de Informática';
        }

        if (
            strpos($normalized, 'inform') !== false
            || strpos($normalized, 'tecnologia') !== false
            || preg_match('/\bti\b/u', $normalized)
        ) {
            return 'Serviços de Informática';
        }

        if (
            strpos($normalized, 'administr') !== false
            || strpos($normalized, 'gead') !== false
        ) {
            return 'Serviços Administrativos';
        }

        return $name;
    }
}

if (!function_exists('pgeservicos_format_ticket_date')) {
    function pgeservicos_format_ticket_date($date) {
        if (empty($date)) {
            return '-';
        }

        return Html::convDateTime($date);
    }
}

if (!function_exists('pgeservicos_build_ticket_url')) {
    function pgeservicos_build_ticket_url($tickets_id, $root_doc = '') {
        $tickets_id = (int)$tickets_id;
        $custom_url = ($root_doc ?? '') . '/plugins/pgeservicos/front/chamado.php?tickets_id=' . $tickets_id;
        $custom_file = dirname(__DIR__) . '/front/chamado.php';

        if (is_readable($custom_file)) {
            return $custom_url;
        }

        $plugin_file = dirname(__DIR__) . '/front/abrir_chamado.php';

        if (is_readable($plugin_file)) {
            return ($root_doc ?? '') . '/plugins/pgeservicos/front/abrir_chamado.php?tickets_id=' . $tickets_id;
        }

        return ($root_doc ?? '') . '/front/ticket.form.php?id=' . $tickets_id;
    }
}

if (!function_exists('pgeservicos_ticket_matches_filters')) {
    function pgeservicos_ticket_matches_filters($ticket, $filters, $last_view_at) {
        if (!empty($filters['envolvimento'])) {
            $involvements = $ticket['involvements'] ?? [];

            if (!in_array($filters['envolvimento'], $involvements, true)) {
                return false;
            }
        }

        if (!empty($filters['atualizados']) && empty($ticket['is_updated'])) {
            return false;
        }

        return true;
    }
}

if (!function_exists('pgeservicos_get_user_tickets')) {
    function pgeservicos_get_user_tickets($filters = [], $options = []) {
        global $DB, $CFG_GLPI;

        $filters = pgeservicos_normalize_ticket_filters($filters);
        $users_id = (int)($options['users_id'] ?? Session::getLoginUserID());
        $last_view_at = $options['last_view_at'] ?? pgeservicos_get_ticket_last_view_at();
        $limit = (int)($options['limit'] ?? 200);
        $limit = $limit > 0 ? $limit : 200;
        $involvement_by_ticket = pgeservicos_collect_user_ticket_involvements($users_id);
        $ticket_ids = array_keys($involvement_by_ticket);
        $entity_options = [];

        if (empty($ticket_ids)) {
            return [
                'tickets'        => [],
                'entity_options' => [],
                'updated_count'  => 0,
                'total_count'    => 0,
                'filters'        => $filters
            ];
        }

        $accessible_entities = pgeservicos_get_accessible_entities();

        if (empty($accessible_entities)) {
            return [
                'tickets'        => [],
                'entity_options' => [],
                'updated_count'  => 0,
                'total_count'    => 0,
                'filters'        => $filters
            ];
        }

        $entity_option_ids = [];
        $entity_ticket = new Ticket();

        foreach ($DB->request([
            'SELECT' => ['id', 'entities_id'],
            'FROM'   => 'glpi_tickets',
            'WHERE'  => [
                'id'         => $ticket_ids,
                'is_deleted' => 0
            ] + getEntitiesRestrictCriteria(
                'glpi_tickets',
                'entities_id',
                $accessible_entities,
                false
            )
        ]) as $entity_row) {
            $option_ticket_id = (int)$entity_row['id'];

            if (
                $entity_ticket->getFromDB($option_ticket_id)
                && $entity_ticket->canViewItem()
            ) {
                $entity_option_ids[(int)$entity_row['entities_id']] = (int)$entity_row['entities_id'];
            }
        }

        $where = [
            'id'         => $ticket_ids,
            'is_deleted' => 0
        ] + getEntitiesRestrictCriteria(
            'glpi_tickets',
            'entities_id',
            $accessible_entities,
            false
        );

        if (!empty($filters['status'])) {
            $where['status'] = (int)$filters['status'];
        }

        if ($filters['entidade'] >= 0) {
            if (!in_array($filters['entidade'], $accessible_entities, true)) {
                return [
                    'tickets'        => [],
                    'entity_options' => [],
                    'updated_count'  => 0,
                    'total_count'    => 0,
                    'filters'        => $filters
                ];
            }

            $where['entities_id'] = (int)$filters['entidade'];
        }

        $query = [
            'SELECT' => [
                'id',
                'name',
                'status',
                'priority',
                'date',
                'date_mod',
                'entities_id',
                'users_id_recipient'
            ],
            'FROM'   => 'glpi_tickets',
            'WHERE'  => $where,
            'ORDER'  => ['date_mod DESC'],
            'LIMIT'  => $limit
        ];

        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $query['WHERE']['OR'] = [
                'name' => ['LIKE', '%' . $search . '%']
            ];

            if (ctype_digit($search)) {
                $query['WHERE']['OR']['id'] = (int)$search;
            }
        }

        $rows = [];
        $entity_ids = [];
        $ticket = new Ticket();

        foreach ($DB->request($query) as $row) {
            $tickets_id = (int)$row['id'];

            if (!$ticket->getFromDB($tickets_id) || !$ticket->canViewItem()) {
                continue;
            }

            $row['involvements'] = array_values($involvement_by_ticket[$tickets_id] ?? []);
            $row['is_updated'] = pgeservicos_is_ticket_updated($row['date_mod'], $last_view_at, $tickets_id);

            if (!pgeservicos_ticket_matches_filters($row, $filters, $last_view_at)) {
                continue;
            }

            $rows[] = $row;
            $entity_ids[(int)$row['entities_id']] = (int)$row['entities_id'];
        }

        $entity_names = pgeservicos_get_entity_display_names($entity_ids);
        $entity_option_names = pgeservicos_get_entity_display_names($entity_option_ids);
        $updated_count = 0;

        foreach ($entity_option_ids as $option_entity_id) {
            $entity_options[$option_entity_id] = pgeservicos_get_ticket_area_name(
                $option_entity_id,
                $entity_option_names
            );
        }

        foreach ($rows as &$row) {
            $entities_id = (int)$row['entities_id'];
            $row['area_name'] = pgeservicos_get_ticket_area_name($entities_id, $entity_names);
            $row['entity_name'] = $entity_names[$entities_id] ?? ('Entidade #' . $entities_id);
            $row['status_label'] = Ticket::getStatus((int)$row['status']);
            $row['priority_label'] = Ticket::getPriorityName((int)$row['priority']);
            $row['url'] = pgeservicos_build_ticket_url((int)$row['id'], $CFG_GLPI['root_doc'] ?? '');

            if (!empty($row['is_updated'])) {
                $updated_count++;
            }

        }

        unset($row);
        asort($entity_options);

        return [
            'tickets'        => $rows,
            'entity_options' => $entity_options,
            'updated_count'  => $updated_count,
            'total_count'    => count($rows),
            'filters'        => $filters
        ];
    }
}

if (!function_exists('pgeservicos_count_user_ticket_updates')) {
    function pgeservicos_count_user_ticket_updates() {
        $result = pgeservicos_get_user_tickets([], [
            'limit'        => 500,
            'last_view_at' => pgeservicos_get_ticket_last_view_at()
        ]);

        return (int)$result['updated_count'];
    }
}
