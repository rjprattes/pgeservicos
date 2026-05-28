<?php

require_once(__DIR__ . '/ticket_view_state.php');

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
    function pgeservicos_is_ticket_updated($date_mod, $last_view_at = null, $tickets_id = 0, $users_id = null) {
        if (empty($date_mod)) {
            return false;
        }

        $tickets_id = (int)$tickets_id;
        $users_id = $users_id === null ? (int)Session::getLoginUserID() : (int)$users_id;

        if ($tickets_id > 0 && $users_id > 0 && pgeservicos_ticket_views_table_exists(true)) {
            $states = pgeservicos_get_ticket_view_states([$tickets_id], $users_id);
            return pgeservicos_ticket_is_updated_for_view($date_mod, $states[$tickets_id] ?? null);
        }

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
    function pgeservicos_mark_ticket_seen($tickets_id, $date_mod = null) {
        $tickets_id = (int)$tickets_id;

        if ($tickets_id <= 0) {
            return false;
        }

        $marked = pgeservicos_mark_ticket_viewed($tickets_id);

        if (!isset($_SESSION['pgeservicos_meus_chamados_seen'])) {
            $_SESSION['pgeservicos_meus_chamados_seen'] = [];
        }

        $_SESSION['pgeservicos_meus_chamados_seen'][$tickets_id] = (string)($date_mod ?: pgeservicos_ticket_view_now());

        return $marked;
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

if (!function_exists('pgeservicos_current_profile_name')) {
    function pgeservicos_current_profile_name() {
        return trim((string)($_SESSION['glpiactiveprofile']['name'] ?? ''));
    }
}

if (!function_exists('pgeservicos_normalize_label')) {
    function pgeservicos_normalize_label($name) {
        $name = trim(mb_strtolower((string)$name, 'UTF-8'));
        $from = ['á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç'];
        $to = ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c'];
        $name = str_replace($from, $to, $name);

        return preg_replace('/\s+/', ' ', $name);
    }
}

if (!function_exists('pgeservicos_normalize_profile_name')) {
    function pgeservicos_normalize_profile_name($name) {
        return pgeservicos_normalize_label($name);
    }
}

if (!function_exists('pgeservicos_get_active_entity_id')) {
    function pgeservicos_get_active_entity_id() {
        if (isset($_SESSION['glpiactive_entity'])) {
            return (int)$_SESSION['glpiactive_entity'];
        }

        if (!empty($_SESSION['glpiactiveprofile']['entities'])) {
            $first = reset($_SESSION['glpiactiveprofile']['entities']);
            return (int)($first['id'] ?? -1);
        }

        return -1;
    }
}

if (!function_exists('pgeservicos_get_it_services_entity_id')) {
    function pgeservicos_get_it_services_entity_id() {
        global $DB;

        static $resolved = false;
        static $entity_id = null;

        if ($resolved) {
            return $entity_id;
        }

        $resolved = true;
        $exact_candidates = [];
        $fallback_candidates = [];

        foreach ($DB->request([
            'SELECT' => ['id', 'name', 'completename'],
            'FROM'   => 'glpi_entities',
            'ORDER'  => ['id ASC'],
        ]) as $entity) {
            $id = (int)($entity['id'] ?? -1);

            if ($id < 0) {
                continue;
            }

            $name = pgeservicos_normalize_label($entity['name'] ?? '');
            $completename = pgeservicos_normalize_label($entity['completename'] ?? '');
            $labels = [$name, $completename];

            if (in_array('portal pge', $labels, true) || in_array('servicos de informatica', $labels, true)) {
                $exact_candidates[$id] = $id;
                continue;
            }

            if (
                strpos($name, 'informatica') !== false
                || strpos($name, 'tecnologia da informacao') !== false
                || $name === 'ti'
            ) {
                $fallback_candidates[$id] = $id;
            }
        }

        if (isset($exact_candidates[0])) {
            $entity_id = 0;
            return $entity_id;
        }

        if (!empty($exact_candidates)) {
            $entity_id = reset($exact_candidates);
            return $entity_id;
        }

        if (!empty($fallback_candidates)) {
            $entity_id = reset($fallback_candidates);
            return $entity_id;
        }

        return null;
    }
}

if (!function_exists('pgeservicos_get_entity_display_name')) {
    function pgeservicos_get_entity_display_name($entities_id) {
        $entities_id = (int)$entities_id;
        $names = pgeservicos_get_entity_display_names([$entities_id]);

        return pgeservicos_get_ticket_area_name($entities_id, $names);
    }
}

if (!function_exists('pgeservicos_is_servicos_informatica_entity')) {
    function pgeservicos_is_servicos_informatica_entity($entities_id) {
        $it_services_entity = pgeservicos_get_it_services_entity_id();

        return $it_services_entity !== null && (int)$entities_id === (int)$it_services_entity;
    }
}

if (!function_exists('pgeservicos_get_ticket_list_context')) {
    function pgeservicos_get_ticket_list_context() {
        $profile_name = pgeservicos_current_profile_name();
        $normalized_profile = pgeservicos_normalize_profile_name($profile_name);
        $active_entity = pgeservicos_get_active_entity_id();
        $active_entity_name = $active_entity >= 0
            ? pgeservicos_get_entity_display_name($active_entity)
            : 'Entidade atual';
        $accessible_entities = pgeservicos_get_accessible_entities();
        $it_services_entity = pgeservicos_get_it_services_entity_id();
        $has_read_all = Session::haveRight('ticket', Ticket::READALL);

        if ($profile_name === 'Super-Admin' && $has_read_all) {
            return [
                'mode' => 'all_global',
                'title' => 'Chamados',
                'description' => 'Acompanhe aqui os chamados de todas as áreas.',
                'entity_filter' => null,
                'allowed_entities' => null,
                'recursive' => false,
                'actor_filter' => false,
                'profile_name' => $profile_name,
                'active_entity' => $active_entity,
            ];
        }

        if ($profile_name === 'Usuário' || $normalized_profile === 'usuario') {
            return [
                'mode' => 'my_global',
                'title' => 'Meus chamados',
                'description' => 'Acompanhe aqui os chamados em que você está envolvido.',
                'entity_filter' => null,
                'allowed_entities' => $accessible_entities,
                'recursive' => false,
                'actor_filter' => true,
                'profile_name' => $profile_name,
                'active_entity' => $active_entity,
            ];
        }

        if (($profile_name === 'Técnico' || $normalized_profile === 'tecnico') && $it_services_entity !== null) {
            return [
                'mode' => 'technician_it_entity',
                'title' => 'Chamados: Serviços de Informática',
                'description' => 'Acompanhe aqui os chamados da área de Serviços de Informática.',
                'entity_filter' => $it_services_entity,
                'allowed_entities' => [$it_services_entity],
                'recursive' => false,
                'actor_filter' => !$has_read_all,
                'profile_name' => $profile_name,
                'active_entity' => $active_entity,
                'scope_entity' => $it_services_entity,
            ];
        }

        return [
            'mode' => 'actor_entity_profile',
            'title' => 'Chamados: ' . $active_entity_name,
            'description' => 'Acompanhe aqui os chamados desta área em que você participa.',
            'entity_filter' => $active_entity >= 0 ? $active_entity : null,
            'allowed_entities' => $active_entity >= 0 ? [$active_entity] : $accessible_entities,
            'recursive' => false,
            'actor_filter' => true,
            'profile_name' => $profile_name,
            'active_entity' => $active_entity,
        ];
    }
}

if (!function_exists('pgeservicos_collect_user_ticket_involvements')) {
    function pgeservicos_collect_user_ticket_involvements($users_id, $respect_rights = true) {
        global $DB;

        $tickets = [];
        $users_id = (int)$users_id;

        if ($users_id <= 0) {
            return $tickets;
        }

        $can_read_all = !$respect_rights || Session::haveRight('ticket', Ticket::READALL);
        $can_read_my = $can_read_all || Session::haveRight('ticket', Ticket::READMY);
        $can_read_group = $can_read_all || Session::haveRight('ticket', Ticket::READGROUP);
        $can_read_assign = $can_read_all || Session::haveRight('ticket', Ticket::READASSIGN);
        $direct_types = [];

        if ($can_read_my) {
            $direct_types[] = CommonITILActor::REQUESTER;
            $direct_types[] = CommonITILActor::OBSERVER;
        }

        if ($can_read_assign) {
            $direct_types[] = CommonITILActor::ASSIGN;
        }

        $direct_types = array_values(array_unique($direct_types));

        if (!empty($direct_types)) {
            foreach ($DB->request([
                'SELECT' => ['tickets_id', 'type'],
                'FROM'   => 'glpi_tickets_users',
                'WHERE'  => [
                    'users_id' => $users_id,
                    'type'     => $direct_types
                ]
            ]) as $row) {
                pgeservicos_add_ticket_involvement(
                    $tickets,
                    (int)$row['tickets_id'],
                    pgeservicos_ticket_actor_type_to_key($row['type'])
                );
            }
        }

        if ($can_read_my) {
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
        }

        $groups = array_map('intval', $_SESSION['glpigroups'] ?? []);
        $group_types = [];

        if ($can_read_group) {
            $group_types[] = CommonITILActor::REQUESTER;
            $group_types[] = CommonITILActor::OBSERVER;
        }

        if ($can_read_assign) {
            $group_types[] = CommonITILActor::ASSIGN;
        }

        $group_types = array_values(array_unique($group_types));

        if (!empty($groups) && !empty($group_types)) {
            foreach ($DB->request([
                'SELECT' => ['tickets_id'],
                'FROM'   => 'glpi_groups_tickets',
                'WHERE'  => [
                    'groups_id' => $groups,
                    'type'      => $group_types
                ]
            ]) as $row) {
                pgeservicos_add_ticket_involvement($tickets, (int)$row['tickets_id'], 'grupo');
            }
        }

        return $tickets;
    }
}

if (!function_exists('pgeservicos_ticket_status_filter_options')) {
    function pgeservicos_ticket_status_filter_options() {
        $options = [
            'not_solved'   => 'Não solucionado',
            'not_closed'   => 'Não fechado',
            'processing'   => 'Processando',
            'solved_closed'=> 'Solucionado + Fechado',
            'all'          => 'Todos',
        ];

        foreach (Ticket::getAllStatusArray(true) as $status_id => $status_label) {
            if (!in_array((int)$status_id, [1, 2, 3, 4, 5, 6], true)) {
                continue;
            }

            $options[(string)(int)$status_id] = (string)$status_label;
        }

        return $options;
    }
}

if (!function_exists('pgeservicos_ticket_normalize_status_filter')) {
    function pgeservicos_ticket_normalize_status_filter($status) {
        $status = trim((string)$status);

        if ($status === '') {
            return 'not_solved';
        }

        return array_key_exists($status, pgeservicos_ticket_status_filter_options())
            ? $status
            : 'not_solved';
    }
}

if (!function_exists('pgeservicos_ticket_apply_status_filter')) {
    function pgeservicos_ticket_apply_status_filter(array &$where, $status_filter) {
        $status_filter = pgeservicos_ticket_normalize_status_filter($status_filter);

        if ($status_filter === 'all') {
            return;
        }

        $status_map = [
            'not_solved'    => [1, 2, 3, 4],
            'not_closed'    => [1, 2, 3, 4, 5],
            'processing'    => [2, 3],
            'solved_closed' => [5, 6],
        ];

        $where['status'] = $status_map[$status_filter] ?? [(int)$status_filter];
    }
}

if (!function_exists('pgeservicos_ticket_sort_options')) {
    function pgeservicos_ticket_sort_options() {
        return [
            'updated_desc' => 'Atualizados recentemente',
            'date_desc'    => 'Data de abertura: mais recentes',
            'date_asc'     => 'Data de abertura: mais antigos',
            'solution_asc' => 'Tempo para solução',
            'priority_desc'=> 'Prioridade',
            'name_asc'     => 'Título',
        ];
    }
}

if (!function_exists('pgeservicos_ticket_normalize_sort')) {
    function pgeservicos_ticket_normalize_sort($sort) {
        $sort = trim((string)$sort);

        return array_key_exists($sort, pgeservicos_ticket_sort_options())
            ? $sort
            : 'updated_desc';
    }
}

if (!function_exists('pgeservicos_ticket_order_criteria')) {
    function pgeservicos_ticket_order_criteria($sort) {
        $sort = pgeservicos_ticket_normalize_sort($sort);

        switch ($sort) {
            case 'date_desc':
                return ['date DESC', 'id DESC'];

            case 'date_asc':
                return ['date ASC', 'id ASC'];

            case 'solution_asc':
                return [
                    new QueryExpression('COALESCE(`time_to_resolve`, "9999-12-31 23:59:59") ASC'),
                    'id DESC',
                ];

            case 'priority_desc':
                return ['priority DESC', 'date_mod DESC', 'id DESC'];

            case 'name_asc':
                return ['name ASC', 'id DESC'];

            case 'updated_desc':
            default:
                return ['date_mod DESC', 'id DESC'];
        }
    }
}

if (!function_exists('pgeservicos_normalize_ticket_filters')) {
    function pgeservicos_normalize_ticket_filters($input) {
        $status = pgeservicos_ticket_normalize_status_filter($input['status'] ?? 'not_solved');
        $entity = isset($input['entidade']) ? (int)$input['entidade'] : -1;
        $page = isset($input['page']) ? (int)$input['page'] : 1;
        $per_page = isset($input['per_page']) ? (int)$input['per_page'] : 20;
        $sort = pgeservicos_ticket_normalize_sort($input['sort'] ?? 'updated_desc');
        $updated = !empty($input['atualizados']) ? 1 : 0;

        if (!in_array($per_page, [20, 50, 100, 250, 500], true)) {
            $per_page = 20;
        }

        if ($page < 1) {
            $page = 1;
        }

        return [
            'q'            => mb_substr(trim((string)($input['q'] ?? '')), 0, 120, 'UTF-8'),
            'status'       => $status,
            'entidade'     => $entity >= 0 ? $entity : -1,
            'atualizados'  => $updated,
            'page'         => $page,
            'per_page'     => $per_page,
            'sort'         => $sort,
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

        if (pgeservicos_is_servicos_informatica_entity($entities_id)) {
            return 'Serviços de Informática';
        }

        if (
            strpos($normalized, 'administr') !== false
            || strpos($normalized, 'gead') !== false
        ) {
            return 'Serviços Administrativos';
        }

        if (
            strpos($normalized, 'inform') !== false
            || strpos($normalized, 'tecnologia') !== false
            || preg_match('/\bti\b/u', $normalized)
        ) {
            return 'Serviços de Informática';
        }

        return $name;
    }
}

if (!function_exists('pgeservicos_format_ticket_date')) {
    function pgeservicos_format_ticket_date($date) {
        $date = trim((string)$date);

        if ($date === '') {
            return '-';
        }

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd-m-Y H:i:s',
            'd-m-Y H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
        ];

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $date);

            if ($parsed instanceof DateTimeImmutable) {
                $errors = DateTimeImmutable::getLastErrors();

                if (empty($errors['warning_count']) && empty($errors['error_count'])) {
                    return $parsed->format('d/m/Y H:i');
                }
            }
        }

        return $date;
    }
}

if (!function_exists('pgeservicos_ticket_solution_is_overdue')) {
    function pgeservicos_ticket_solution_is_overdue($time_to_resolve, $solvedate, $status = 0) {
        $deadline_ts = strtotime((string)$time_to_resolve);

        if ($deadline_ts === false) {
            return false;
        }

        $solvedate_ts = strtotime((string)$solvedate);

        if ($solvedate_ts !== false) {
            return $deadline_ts < $solvedate_ts;
        }

        if (in_array((int)$status, [5, 6], true)) {
            return false;
        }

        return $deadline_ts < time();
    }
}

if (!function_exists('pgeservicos_ticket_solution_progress')) {
    function pgeservicos_ticket_solution_progress($date_open, $time_to_resolve, $solvedate = null) {
        $start_ts = strtotime((string)$date_open);
        $deadline_ts = strtotime((string)$time_to_resolve);

        if ($start_ts === false || $deadline_ts === false || $deadline_ts <= $start_ts) {
            return null;
        }

        $reference_ts = strtotime((string)$solvedate);

        if ($reference_ts === false) {
            $reference_ts = time();
        }

        $progress = (($reference_ts - $start_ts) / ($deadline_ts - $start_ts)) * 100;

        return max(0, min(100, (int)round($progress)));
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
        if (!empty($filters['atualizados']) && empty($ticket['is_updated'])) {
            return false;
        }

        return true;
    }
}

if (!function_exists('pgeservicos_ticket_empty_result')) {
    function pgeservicos_ticket_empty_result(array $filters, array $context, array $entity_options = []) {
        return [
            'tickets'        => [],
            'entity_options' => $entity_options,
            'updated_count'  => 0,
            'total_count'    => 0,
            'filters'        => $filters,
            'context'        => $context,
            'pagination'     => [
                'page' => max(1, (int)($filters['page'] ?? 1)),
                'per_page' => (int)($filters['per_page'] ?? 20),
                'total_pages' => 1,
                'offset' => 0,
                'from' => 0,
                'to' => 0,
            ],
        ];
    }
}

if (!function_exists('pgeservicos_ticket_count')) {
    function pgeservicos_ticket_count(array $where) {
        global $DB;

        foreach ($DB->request([
            'COUNT' => 'cpt',
            'FROM'  => 'glpi_tickets',
            'WHERE' => $where,
        ]) as $row) {
            return (int)($row['cpt'] ?? 0);
        }

        return 0;
    }
}

if (!function_exists('pgeservicos_ticket_apply_search_filter')) {
    function pgeservicos_ticket_apply_search_filter(array &$where, $search) {
        $search = trim((string)$search);

        if ($search === '') {
            return;
        }

        $where['OR'] = [
            'name' => ['LIKE', '%' . $search . '%'],
        ];

        if (ctype_digit($search)) {
            $where['OR']['id'] = (int)$search;
        }
    }
}

if (!function_exists('pgeservicos_ticket_entity_options')) {
    function pgeservicos_ticket_entity_options(array $where) {
        global $DB;

        $entity_ids = [];

        foreach ($DB->request([
            'SELECT'  => ['entities_id'],
            'FROM'    => 'glpi_tickets',
            'WHERE'   => $where,
            'GROUPBY' => 'entities_id',
            'ORDER'   => ['entities_id ASC'],
        ]) as $row) {
            $entities_id = (int)($row['entities_id'] ?? -1);

            if ($entities_id >= 0) {
                $entity_ids[$entities_id] = $entities_id;
            }
        }

        $entity_names = pgeservicos_get_entity_display_names($entity_ids);
        $options = [];

        foreach ($entity_ids as $entities_id) {
            $options[$entities_id] = pgeservicos_get_ticket_area_name($entities_id, $entity_names);
        }

        asort($options);

        return $options;
    }
}

if (!function_exists('pgeservicos_ticket_catalog_user_label')) {
    function pgeservicos_ticket_catalog_user_label(array $user) {
        $label = formatUserName(
            (int)($user['id'] ?? 0),
            (string)($user['name'] ?? ''),
            (string)($user['realname'] ?? ''),
            (string)($user['firstname'] ?? ''),
            0
        );

        $label = trim(html_entity_decode((string)$label, ENT_QUOTES, 'UTF-8'));

        return $label !== '' ? $label : ('Usuário #' . (int)($user['id'] ?? 0));
    }
}

if (!function_exists('pgeservicos_ticket_catalog_safe_color')) {
    function pgeservicos_ticket_catalog_safe_color($color) {
        $color = trim((string)$color);

        if (preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $color)) {
            return $color;
        }

        return '';
    }
}

if (!function_exists('pgeservicos_ticket_catalog_join_labels')) {
    function pgeservicos_ticket_catalog_join_labels(array $items, $fallback = '-') {
        $labels = [];

        foreach ($items as $item) {
            $label = trim((string)($item['label'] ?? $item));

            if ($label !== '' && $label !== '-') {
                $labels[$label] = $label;
            }
        }

        return !empty($labels) ? implode(', ', array_values($labels)) : $fallback;
    }
}

if (!function_exists('pgeservicos_ticket_catalog_enrich_rows')) {
    function pgeservicos_ticket_catalog_enrich_rows(array &$rows) {
        global $DB;

        $ticket_ids = [];
        $user_ids = [];
        $group_ids = [];
        $location_ids = [];
        $user_actors = [];
        $group_actors = [];
        $users = [];
        $groups = [];
        $locations = [];
        $requester_user_ids_by_ticket = [];
        $vip_by_user = [];

        foreach ($rows as $row) {
            $tickets_id = (int)($row['id'] ?? 0);

            if ($tickets_id > 0) {
                $ticket_ids[$tickets_id] = $tickets_id;
            }

            $locations_id = (int)($row['locations_id'] ?? 0);

            if ($locations_id > 0) {
                $location_ids[$locations_id] = $locations_id;
            }
        }

        if (empty($ticket_ids)) {
            return;
        }

        foreach ($DB->request([
            'SELECT' => ['tickets_id', 'users_id', 'type'],
            'FROM'   => 'glpi_tickets_users',
            'WHERE'  => [
                'tickets_id' => array_values($ticket_ids),
                'type'       => [CommonITILActor::REQUESTER, CommonITILActor::ASSIGN],
            ],
        ]) as $actor) {
            $tickets_id = (int)($actor['tickets_id'] ?? 0);
            $users_id = (int)($actor['users_id'] ?? 0);
            $type = (int)($actor['type'] ?? 0);

            if ($tickets_id <= 0 || $users_id <= 0) {
                continue;
            }

            $user_ids[$users_id] = $users_id;
            $user_actors[$tickets_id][$type][$users_id] = $users_id;

            if ($type === CommonITILActor::REQUESTER) {
                $requester_user_ids_by_ticket[$tickets_id][$users_id] = $users_id;
            }
        }

        foreach ($DB->request([
            'SELECT' => ['tickets_id', 'groups_id', 'type'],
            'FROM'   => 'glpi_groups_tickets',
            'WHERE'  => [
                'tickets_id' => array_values($ticket_ids),
                'type'       => [CommonITILActor::REQUESTER, CommonITILActor::ASSIGN],
            ],
        ]) as $actor) {
            $tickets_id = (int)($actor['tickets_id'] ?? 0);
            $groups_id = (int)($actor['groups_id'] ?? 0);
            $type = (int)($actor['type'] ?? 0);

            if ($tickets_id <= 0 || $groups_id <= 0) {
                continue;
            }

            $group_ids[$groups_id] = $groups_id;
            $group_actors[$tickets_id][$type][$groups_id] = $groups_id;
        }

        if (!empty($user_ids)) {
            foreach ($DB->request([
                'SELECT' => ['id', 'name', 'realname', 'firstname'],
                'FROM'   => 'glpi_users',
                'WHERE'  => ['id' => array_values($user_ids)],
            ]) as $user) {
                $users[(int)$user['id']] = pgeservicos_ticket_catalog_user_label($user);
            }
        }

        if (!empty($group_ids)) {
            foreach ($DB->request([
                'SELECT' => ['id', 'name', 'completename'],
                'FROM'   => 'glpi_groups',
                'WHERE'  => ['id' => array_values($group_ids)],
            ]) as $group) {
                $groups_id = (int)$group['id'];
                $label = trim((string)($group['completename'] ?: $group['name']));
                $groups[$groups_id] = $label !== '' ? $label : ('Grupo #' . $groups_id);
            }
        }

        if (!empty($location_ids)) {
            foreach ($DB->request([
                'SELECT' => ['id', 'name', 'completename'],
                'FROM'   => 'glpi_locations',
                'WHERE'  => ['id' => array_values($location_ids)],
            ]) as $location) {
                $locations_id = (int)$location['id'];
                $label = trim((string)($location['completename'] ?: $location['name']));
                $locations[$locations_id] = $label !== '' ? $label : ('Localização #' . $locations_id);
            }
        }

        if (!empty($user_ids) && $DB->tableExists('glpi_plugin_vip_groups')) {
            foreach ($DB->request([
                'SELECT' => [
                    'glpi_groups_users.users_id',
                    'glpi_plugin_vip_groups.id',
                    'glpi_plugin_vip_groups.name',
                    'glpi_plugin_vip_groups.vip_color',
                ],
                'FROM' => 'glpi_groups_users',
                'LEFT JOIN' => [
                    'glpi_plugin_vip_groups' => [
                        'ON' => [
                            'glpi_plugin_vip_groups' => 'id',
                            'glpi_groups_users'      => 'groups_id',
                        ],
                    ],
                ],
                'WHERE' => [
                    'glpi_groups_users.users_id'   => array_values($user_ids),
                    'glpi_plugin_vip_groups.isvip' => 1,
                ],
            ]) as $vip) {
                $users_id = (int)($vip['users_id'] ?? 0);
                $vip_id = (int)($vip['id'] ?? 0);
                $label = trim((string)($vip['name'] ?? ''));

                if ($users_id <= 0 || $vip_id <= 0) {
                    continue;
                }

                $vip_by_user[$users_id][$vip_id] = [
                    'label' => $label !== '' ? $label : 'VIP',
                    'color' => pgeservicos_ticket_catalog_safe_color($vip['vip_color'] ?? ''),
                ];
            }
        }

        foreach ($rows as &$row) {
            $tickets_id = (int)($row['id'] ?? 0);
            $locations_id = (int)($row['locations_id'] ?? 0);
            $requesters = [];
            $assignees = [];
            $vip_tags = [];

            foreach (($user_actors[$tickets_id][CommonITILActor::REQUESTER] ?? []) as $users_id) {
                $requesters[] = ['label' => $users[$users_id] ?? ('Usuário #' . $users_id), 'kind' => 'user'];

                foreach (($vip_by_user[$users_id] ?? []) as $vip_id => $vip) {
                    $key = ($vip['label'] ?? 'VIP') . '|' . ($vip['color'] ?? '');
                    $vip_tags[$key] = $vip;
                }
            }

            foreach (($group_actors[$tickets_id][CommonITILActor::REQUESTER] ?? []) as $groups_id) {
                $requesters[] = ['label' => $groups[$groups_id] ?? ('Grupo #' . $groups_id), 'kind' => 'group'];
            }

            foreach (($user_actors[$tickets_id][CommonITILActor::ASSIGN] ?? []) as $users_id) {
                $assignees[] = ['label' => $users[$users_id] ?? ('Usuário #' . $users_id), 'kind' => 'user'];
            }

            foreach (($group_actors[$tickets_id][CommonITILActor::ASSIGN] ?? []) as $groups_id) {
                $assignees[] = ['label' => $groups[$groups_id] ?? ('Grupo #' . $groups_id), 'kind' => 'group'];
            }

            $row['requesters'] = $requesters;
            $row['requester_label'] = pgeservicos_ticket_catalog_join_labels($requesters, '-');
            $row['assignees'] = $assignees;
            $row['assignee_label'] = pgeservicos_ticket_catalog_join_labels($assignees, 'Sem atribuição');
            $row['vip_tags'] = array_values($vip_tags);
            $row['location_name'] = $locations_id > 0 ? ($locations[$locations_id] ?? ('Localização #' . $locations_id)) : '-';
            $row['time_to_resolve_label'] = !empty($row['time_to_resolve'])
                ? pgeservicos_format_ticket_date($row['time_to_resolve'])
                : 'Sem prazo';
            $row['time_to_resolve_overdue'] = !empty($row['time_to_resolve'])
                ? pgeservicos_ticket_solution_is_overdue(
                    $row['time_to_resolve'],
                    $row['solvedate'] ?? '',
                    $row['status'] ?? 0
                )
                : false;
            $row['time_to_resolve_progress'] = !empty($row['time_to_resolve'])
                ? pgeservicos_ticket_solution_progress(
                    $row['date'] ?? '',
                    $row['time_to_resolve'],
                    $row['solvedate'] ?? ''
                )
                : null;
            $row['date_label'] = pgeservicos_format_ticket_date($row['date'] ?? '');
        }

        unset($row);
    }
}

if (!function_exists('pgeservicos_ticket_scope_where')) {
    function pgeservicos_ticket_scope_where(array $context, array $actor_ticket_ids) {
        $where = ['is_deleted' => 0];
        $allowed_entities = $context['allowed_entities'];

        if (is_array($allowed_entities)) {
            $allowed_entities = array_values(array_unique(array_map('intval', $allowed_entities)));

            if (empty($allowed_entities)) {
                return null;
            }

            $where += getEntitiesRestrictCriteria('glpi_tickets', 'entities_id', $allowed_entities, false);
        }

        if (($context['entity_filter'] ?? null) !== null) {
            $where['entities_id'] = (int)$context['entity_filter'];
        }

        if (!empty($context['actor_filter'])) {
            if (empty($actor_ticket_ids)) {
                return null;
            }

            $where['id'] = array_values(array_unique(array_map('intval', $actor_ticket_ids)));
        }

        return $where;
    }
}

if (!function_exists('pgeservicos_get_user_tickets')) {
    function pgeservicos_get_user_tickets($filters = [], $options = []) {
        global $DB, $CFG_GLPI;

        $filters = pgeservicos_normalize_ticket_filters($filters);
        $context = pgeservicos_get_ticket_list_context();

        $users_id = (int)($options['users_id'] ?? Session::getLoginUserID());
        $last_view_at = $options['last_view_at'] ?? pgeservicos_get_ticket_last_view_at();
        $updated_expression = pgeservicos_ticket_updated_expression($users_id);
        $per_page = (int)$filters['per_page'];
        $involvement_by_ticket = pgeservicos_collect_user_ticket_involvements($users_id, true);
        $actor_ticket_ids = array_keys($involvement_by_ticket);
        $scope_where = pgeservicos_ticket_scope_where($context, $actor_ticket_ids);

        if ($scope_where === null) {
            return pgeservicos_ticket_empty_result($filters, $context);
        }

        $entity_option_where = $scope_where;

        pgeservicos_ticket_apply_status_filter($entity_option_where, $filters['status']);

        pgeservicos_ticket_apply_search_filter($entity_option_where, $filters['q']);
        $entity_options = pgeservicos_ticket_entity_options($entity_option_where);
        $where = $entity_option_where;

        if ($filters['entidade'] >= 0) {
            $selected_entity = (int)$filters['entidade'];

            if (($context['entity_filter'] ?? null) !== null && $selected_entity !== (int)$context['entity_filter']) {
                return pgeservicos_ticket_empty_result($filters, $context, $entity_options);
            }

            if (is_array($context['allowed_entities']) && !in_array($selected_entity, array_map('intval', $context['allowed_entities']), true)) {
                return pgeservicos_ticket_empty_result($filters, $context, $entity_options);
            }

            $where['entities_id'] = $selected_entity;
        }

        $base_where = $where;

        if (!empty($filters['atualizados'])) {
            if ($updated_expression instanceof QueryExpression) {
                $where[] = $updated_expression;
            } else {
                $where['date_mod'] = ['>', pgeservicos_get_recent_ticket_cutoff()];
            }
        }

        $total_count = pgeservicos_ticket_count($where);
        $updated_where = $base_where;

        if ($updated_expression instanceof QueryExpression) {
            $updated_where[] = $updated_expression;
        } else {
            $updated_where['date_mod'] = ['>', pgeservicos_get_recent_ticket_cutoff()];
        }

        $updated_count = pgeservicos_ticket_count($updated_where);
        $total_pages = max(1, (int)ceil($total_count / $per_page));
        $page = min(max(1, (int)$filters['page']), $total_pages);
        $filters['page'] = $page;
        $offset = ($page - 1) * $per_page;

        if ($total_count === 0) {
            return [
                'tickets'        => [],
                'entity_options' => $entity_options,
                'updated_count'  => 0,
                'total_count'    => 0,
                'filters'        => $filters,
                'context'        => $context,
                'pagination'     => [
                    'page' => 1,
                    'per_page' => $per_page,
                    'total_pages' => 1,
                    'offset' => 0,
                    'from' => 0,
                    'to' => 0,
                ],
            ];
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
                'users_id_recipient',
                'locations_id',
                'time_to_resolve',
                'solvedate'
            ],
            'FROM'   => 'glpi_tickets',
            'WHERE'  => $where,
            'ORDER'  => pgeservicos_ticket_order_criteria($filters['sort']),
            'START'  => $offset,
            'LIMIT'  => $per_page,
        ];

        $rows = [];
        $row_ticket_ids = [];
        $entity_ids = [];

        foreach ($DB->request($query) as $row) {
            $tickets_id = (int)$row['id'];
            $row['involvements'] = array_values($involvement_by_ticket[$tickets_id] ?? []);
            $rows[] = $row;
            $row_ticket_ids[$tickets_id] = $tickets_id;
            $entity_ids[(int)$row['entities_id']] = (int)$row['entities_id'];
        }

        $view_states = pgeservicos_get_ticket_view_states($row_ticket_ids, $users_id);
        $has_persistent_views = pgeservicos_ticket_views_table_exists(false);

        foreach ($rows as &$row) {
            $tickets_id = (int)$row['id'];
            $row['is_updated'] = $has_persistent_views
                ? pgeservicos_ticket_is_updated_for_view($row['date_mod'], $view_states[$tickets_id] ?? null)
                : pgeservicos_is_ticket_updated($row['date_mod'], $last_view_at, $tickets_id, $users_id);
        }

        unset($row);

        pgeservicos_ticket_catalog_enrich_rows($rows);

        $entity_names = pgeservicos_get_entity_display_names($entity_ids);

        foreach ($rows as &$row) {
            $entities_id = (int)$row['entities_id'];
            $row['area_name'] = pgeservicos_get_ticket_area_name($entities_id, $entity_names);
            $row['entity_name'] = $entity_names[$entities_id] ?? ('Entidade #' . $entities_id);
            $row['status_label'] = Ticket::getStatus((int)$row['status']);
            $row['priority_label'] = Ticket::getPriorityName((int)$row['priority']);
            $row['url'] = pgeservicos_build_ticket_url((int)$row['id'], $CFG_GLPI['root_doc'] ?? '');
        }

        unset($row);

        return [
            'tickets'        => $rows,
            'entity_options' => $entity_options,
            'updated_count'  => $updated_count,
            'total_count'    => $total_count,
            'filters'        => $filters,
            'context'        => $context,
            'pagination'     => [
                'page' => $page,
                'per_page' => $per_page,
                'total_pages' => $total_pages,
                'offset' => $offset,
                'from' => $offset + 1,
                'to' => min($offset + count($rows), $total_count),
            ],
        ];
    }
}


if (!function_exists('pgeservicos_collect_user_updated_ticket_views')) {
    function pgeservicos_collect_user_updated_ticket_views($users_id = null) {
        global $DB;

        $users_id = $users_id === null ? (int)Session::getLoginUserID() : (int)$users_id;

        if ($users_id <= 0) {
            return [];
        }

        $updated_expression = pgeservicos_ticket_updated_expression($users_id);

        if (!$updated_expression instanceof QueryExpression) {
            return [];
        }

        $context = pgeservicos_get_ticket_list_context();
        $involvement_by_ticket = pgeservicos_collect_user_ticket_involvements($users_id, true);
        $actor_ticket_ids = array_keys($involvement_by_ticket);
        $scope_where = pgeservicos_ticket_scope_where($context, $actor_ticket_ids);

        if ($scope_where === null) {
            return [];
        }

        $where = $scope_where;
        $where[] = $updated_expression;
        $ticket_views = [];

        foreach ($DB->request([
            'SELECT' => ['id', 'date_mod'],
            'FROM'   => 'glpi_tickets',
            'WHERE'  => $where,
            'ORDER'  => ['date_mod DESC', 'id DESC'],
        ]) as $row) {
            $tickets_id = (int)($row['id'] ?? 0);

            if ($tickets_id <= 0) {
                continue;
            }

            $ticket_views[$tickets_id] = (string)($row['date_mod'] ?? '');
        }

        return $ticket_views;
    }
}

if (!function_exists('pgeservicos_mark_user_ticket_updates_read')) {
    function pgeservicos_mark_user_ticket_updates_read($users_id = null) {
        $users_id = $users_id === null ? (int)Session::getLoginUserID() : (int)$users_id;

        if ($users_id <= 0) {
            return [
                'marked' => 0,
                'total'  => 0,
            ];
        }

        $ticket_views = pgeservicos_collect_user_updated_ticket_views($users_id);

        if (empty($ticket_views)) {
            return [
                'marked' => 0,
                'total'  => 0,
            ];
        }

        $marked = pgeservicos_mark_ticket_views_at($ticket_views, $users_id);

        if ($marked === false) {
            return false;
        }

        return [
            'marked' => (int)$marked,
            'total'  => count($ticket_views),
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
