<?php

include('../../../inc/includes.php');

require_once(__DIR__ . '/../inc/plugin_state.php');
pgeservicos_require_plugin_active();

Session::checkLoginUser();

global $CFG_GLPI;

require_once(__DIR__ . '/../inc/theme.php');
require_once(__DIR__ . '/../inc/tickets_catalog.php');

if (!function_exists('pgeservicos_h')) {
    function pgeservicos_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('pgeservicos_meus_chamados_filter_params')) {
    function pgeservicos_meus_chamados_filter_params(array $filters, $page = null) {
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $params['q'] = (string)$filters['q'];
        }

        if (($filters['status'] ?? 'not_solved') !== 'not_solved') {
            $params['status'] = (string)$filters['status'];
        }

        if (($filters['sort'] ?? 'updated_desc') !== 'updated_desc') {
            $params['sort'] = (string)$filters['sort'];
        }

        if ((int)($filters['entidade'] ?? -1) >= 0) {
            $params['entidade'] = (int)$filters['entidade'];
        }

        if (!empty($filters['atualizados']) && ($filters['status'] ?? '') !== 'updated_only') {
            $params['atualizados'] = 1;
        }

        if (!empty($filters['awaiting_approval']) && ($filters['status'] ?? '') !== 'awaiting_approval') {
            $params['awaiting_approval'] = 1;
        }

        if (!empty($filters['satisfaction'])) {
            $params['satisfaction'] = 1;
        }

        foreach (['opened', 'solved', 'closed'] as $date_filter) {
            if (empty($filters[$date_filter . '_enabled'])) {
                continue;
            }

            $has_range = (($filters[$date_filter . '_from'] ?? '') !== '')
                || (($filters[$date_filter . '_to'] ?? '') !== '');

            if (!$has_range) {
                continue;
            }

            $params[$date_filter . '_enabled'] = 1;

            if (($filters[$date_filter . '_from'] ?? '') !== '') {
                $params[$date_filter . '_from'] = (string)$filters[$date_filter . '_from'];
            }

            if (($filters[$date_filter . '_to'] ?? '') !== '') {
                $params[$date_filter . '_to'] = (string)$filters[$date_filter . '_to'];
            }
        }

        $per_page = (int)($filters['per_page'] ?? 20);

        if (in_array($per_page, [20, 50, 100, 250, 500], true) && $per_page !== 20) {
            $params['per_page'] = $per_page;
        }

        if ($page !== null) {
            $page = max(1, (int)$page);

            if ($page > 1) {
                $params['page'] = $page;
            }
        }

        return $params;
    }
}

if (!function_exists('pgeservicos_meus_chamados_page_url')) {
    function pgeservicos_meus_chamados_page_url($base_url, array $filters, $page = null) {
        $params = pgeservicos_meus_chamados_filter_params($filters, $page);

        return $base_url . (!empty($params) ? '?' . http_build_query($params) : '');
    }
}

if (!function_exists('pgeservicos_meus_chamados_print_hidden_filters')) {
    function pgeservicos_meus_chamados_print_hidden_filters(array $filters, array $exclude = []) {
        $exclude = array_flip($exclude);

        foreach (pgeservicos_meus_chamados_filter_params($filters, null) as $name => $value) {
            if (isset($exclude[$name])) {
                continue;
            }

            echo "<input type='hidden' name='" . pgeservicos_h($name) . "' value='" . pgeservicos_h($value) . "'>";
        }
    }
}

if (!function_exists('pgeservicos_meus_chamados_has_date_filters')) {
    function pgeservicos_meus_chamados_has_date_filters(array $filters) {
        foreach (['opened', 'solved', 'closed'] as $prefix) {
            if (
                !empty($filters[$prefix . '_enabled'])
                && (($filters[$prefix . '_from'] ?? '') !== '' || ($filters[$prefix . '_to'] ?? '') !== '')
            ) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('pgeservicos_meus_chamados_format_filter_date')) {
    function pgeservicos_meus_chamados_format_filter_date($date) {
        $date = trim((string)$date);

        if ($date === '') {
            return '';
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        if (!$parsed instanceof DateTimeImmutable) {
            return $date;
        }

        $errors = DateTimeImmutable::getLastErrors();

        if ($errors !== false && (!empty($errors['warning_count']) || !empty($errors['error_count']))) {
            return $date;
        }

        return $parsed->format('d/m/Y');
    }
}

if (!function_exists('pgeservicos_meus_chamados_date_range_label')) {
    function pgeservicos_meus_chamados_date_range_label(array $filters, $prefix) {
        $from = pgeservicos_meus_chamados_format_filter_date($filters[$prefix . '_from'] ?? '');
        $to = pgeservicos_meus_chamados_format_filter_date($filters[$prefix . '_to'] ?? '');

        if ($from !== '' && $to !== '') {
            return $from . ' a ' . $to;
        }

        if ($from !== '') {
            return 'a partir de ' . $from;
        }

        if ($to !== '') {
            return 'até ' . $to;
        }

        return '';
    }
}

if (!function_exists('pgeservicos_meus_chamados_date_filter')) {
    function pgeservicos_meus_chamados_date_filter(array $filters, $prefix, $label) {
        $enabled = !empty($filters[$prefix . '_enabled']);
        $from = (string)($filters[$prefix . '_from'] ?? '');
        $to = (string)($filters[$prefix . '_to'] ?? '');
        $checked = $enabled ? ' checked' : '';
        $enabled_class = $enabled ? ' is-enabled' : '';
        $id = 'pgeservicos-ticket-' . $prefix;

        return "
        <div class='pgeservicos-date-filter{$enabled_class}' data-pgeservicos-date-filter='" . pgeservicos_h($prefix) . "'>
            <label class='pgeservicos-date-filter-toggle' for='" . pgeservicos_h($id) . "-enabled'>
                <input type='checkbox' id='" . pgeservicos_h($id) . "-enabled' name='" . pgeservicos_h($prefix) . "_enabled' value='1'{$checked}>
                <span>" . pgeservicos_h($label) . "</span>
            </label>
            <div class='pgeservicos-date-filter-fields'>
                <label for='" . pgeservicos_h($id) . "-from'>
                    <span>Início</span>
                    <input type='date' id='" . pgeservicos_h($id) . "-from' name='" . pgeservicos_h($prefix) . "_from' value='" . pgeservicos_h($from) . "'>
                </label>
                <label for='" . pgeservicos_h($id) . "-to'>
                    <span>Fim</span>
                    <input type='date' id='" . pgeservicos_h($id) . "-to' name='" . pgeservicos_h($prefix) . "_to' value='" . pgeservicos_h($to) . "'>
                </label>
            </div>
        </div>
        ";
    }
}

if (!function_exists('pgeservicos_meus_chamados_summary_html')) {
    function pgeservicos_meus_chamados_summary_html($total_count, $updated_count, $satisfaction_mode) {
        return "
        <strong>" . pgeservicos_h($total_count) . "</strong>
        <span>" . ((int)$total_count === 1 ? 'chamado encontrado' : 'chamados encontrados') . "</span>
        " . ($satisfaction_mode
            ? "<em>Aguardando avaliação</em>"
            : ((int)$updated_count > 0
                ? "<em>" . pgeservicos_h($updated_count) . " " . ((int)$updated_count === 1 ? 'atualização' : 'atualizações') . "</em>"
                : "<em>Sem novas atualizações</em>")) . "
        " . (!$satisfaction_mode && (int)$updated_count > 0
            ? "<button type='button' class='pgeservicos-mark-updates-read' data-pgeservicos-mark-updates-read>
                <i class='ti ti-checks' aria-hidden='true'></i>
                <span>Marcar todas como visualizadas</span>
            </button>"
            : '');
    }
}

if (!function_exists('pgeservicos_meus_chamados_active_filters_html')) {
    function pgeservicos_meus_chamados_active_filters_html(array $filters, array $entity_options = []) {
        $chips = [];
        $status = (string)($filters['status'] ?? 'not_solved');

        if ($status !== 'not_solved') {
            $status_options = pgeservicos_ticket_status_filter_options();
            $chips[] = [
                'key' => 'status',
                'label' => 'Status',
                'value' => $status_options[$status] ?? $status,
            ];
        }

        if (($filters['q'] ?? '') !== '') {
            $chips[] = [
                'key' => 'q',
                'label' => 'Busca',
                'value' => '“' . (string)$filters['q'] . '”',
            ];
        }

        $entity = (int)($filters['entidade'] ?? -1);
        if ($entity >= 0) {
            $chips[] = [
                'key' => 'entidade',
                'label' => 'Área',
                'value' => $entity_options[$entity] ?? pgeservicos_get_entity_display_name($entity),
            ];
        }

        $sort = (string)($filters['sort'] ?? 'updated_desc');
        if ($sort !== 'updated_desc') {
            $sort_options = pgeservicos_ticket_sort_options();
            $chips[] = [
                'key' => 'sort',
                'label' => 'Ordenação',
                'value' => $sort_options[$sort] ?? $sort,
            ];
        }

        $date_labels = [
            'opened' => 'Data de abertura',
            'solved' => 'Data de solução',
            'closed' => 'Data de fechamento',
        ];

        foreach ($date_labels as $prefix => $label) {
            if (empty($filters[$prefix . '_enabled'])) {
                continue;
            }

            $value = pgeservicos_meus_chamados_date_range_label($filters, $prefix);

            if ($value === '') {
                continue;
            }

            $chips[] = [
                'key' => $prefix,
                'label' => $label,
                'value' => $value,
            ];
        }

        if (empty($chips)) {
            return '';
        }

        $html = "<div class='pgeservicos-active-filters-list' aria-label='Filtros ativos'>";

        foreach ($chips as $chip) {
            $html .= "
            <span class='pgeservicos-active-filter-chip'>
                <span><strong>" . pgeservicos_h($chip['label']) . ":</strong> " . pgeservicos_h($chip['value']) . "</span>
                <button type='button' data-pgeservicos-clear-filter='" . pgeservicos_h($chip['key']) . "' aria-label='Remover filtro " . pgeservicos_h($chip['label']) . "'>×</button>
            </span>";
        }

        return $html . "</div>";
    }
}

if (!function_exists('pgeservicos_meus_chamados_filters_html')) {
    function pgeservicos_meus_chamados_filters_html($page_url, array $filters, array $entity_options) {
        $advanced_open = pgeservicos_meus_chamados_has_date_filters($filters);
        $advanced_hidden = $advanced_open ? '' : ' hidden';
        $advanced_expanded = $advanced_open ? 'true' : 'false';
        $active_filters = pgeservicos_meus_chamados_active_filters_html($filters, $entity_options);

        ob_start();
        echo "
<form class='pgeservicos-meus-chamados-filters' method='get' action='" . pgeservicos_h($page_url) . "' data-pgeservicos-auto-filters>
    <input type='hidden' name='per_page' value='" . (int)($filters['per_page'] ?? 20) . "'>
    <div class='pgeservicos-filter-main'>
        <div class='pgeservicos-filter-field pgeservicos-filter-search'>
            <label for='pgeservicos-ticket-q'>Buscar</label>
            <input
                type='search'
                id='pgeservicos-ticket-q'
                name='q'
                value='" . pgeservicos_h($filters['q']) . "'
                placeholder='Número, título, pessoa, localização ou acompanhamento'
                autocomplete='off'
                data-pgeservicos-search
            >
        </div>

        <div class='pgeservicos-filter-field'>
            <label for='pgeservicos-ticket-status'>Status</label>
            <select id='pgeservicos-ticket-status' name='status' data-pgeservicos-auto-submit>";

        foreach (pgeservicos_ticket_status_filter_options() as $status_value => $status_label) {
            $selected = (string)$filters['status'] === (string)$status_value ? ' selected' : '';
            echo "<option value='" . pgeservicos_h($status_value) . "'{$selected}>"
                . pgeservicos_h($status_label)
                . "</option>";
        }

        echo "
            </select>
        </div>

        <div class='pgeservicos-filter-field'>
            <label for='pgeservicos-ticket-entidade'>Área</label>
            <select id='pgeservicos-ticket-entidade' name='entidade' data-pgeservicos-auto-submit>
                <option value=''" . ((int)($filters['entidade'] ?? -1) < 0 ? ' selected' : '') . ">Todas</option>";

        foreach ($entity_options as $entities_id => $entity_name) {
            $selected = (int)$filters['entidade'] === (int)$entities_id ? ' selected' : '';
            echo "<option value='" . (int)$entities_id . "'{$selected}>"
                . pgeservicos_h($entity_name)
                . "</option>";
        }

        echo "
            </select>
        </div>

        <div class='pgeservicos-filter-field'>
            <label for='pgeservicos-ticket-sort'>Ordenar por</label>
            <select id='pgeservicos-ticket-sort' name='sort' data-pgeservicos-auto-submit>";

        foreach (pgeservicos_ticket_sort_options() as $sort_value => $sort_label) {
            $selected = (string)$filters['sort'] === (string)$sort_value ? ' selected' : '';
            echo "<option value='" . pgeservicos_h($sort_value) . "'{$selected}>"
                . pgeservicos_h($sort_label)
                . "</option>";
        }

        echo "
            </select>
        </div>
    </div>

    <div class='pgeservicos-filter-toolbar'>
        <button type='button' class='pgeservicos-advanced-toggle' aria-expanded='" . pgeservicos_h($advanced_expanded) . "' aria-controls='pgeservicos-advanced-filters' data-pgeservicos-advanced-toggle>
            <i class='ti ti-adjustments-horizontal' aria-hidden='true'></i>
            <span>Filtros avançados</span>
        </button>
        <a href='" . pgeservicos_h($page_url) . "' data-pgeservicos-clear-filters>Limpar</a>
    </div>

    <div class='pgeservicos-active-filters' data-pgeservicos-active-filters>" . $active_filters . "</div>

    <div id='pgeservicos-advanced-filters' class='pgeservicos-advanced-filters' data-pgeservicos-advanced-panel{$advanced_hidden}>
        <div class='pgeservicos-date-filters' aria-label='Filtros de data'>
            " . pgeservicos_meus_chamados_date_filter($filters, 'opened', 'Data de abertura') . "
            " . pgeservicos_meus_chamados_date_filter($filters, 'solved', 'Data de solução') . "
            " . pgeservicos_meus_chamados_date_filter($filters, 'closed', 'Data de fechamento') . "
        </div>
    </div>
</form>";

        return ob_get_clean();
    }
}

if (!function_exists('pgeservicos_meus_chamados_list_html')) {
    function pgeservicos_meus_chamados_list_html(array $tickets, array $filters, $satisfaction_mode) {
        if (empty($tickets)) {
            if ($satisfaction_mode) {
                $empty_title = 'Nenhum chamado aguardando avaliação';
                $empty_description = 'Não encontramos pesquisas de satisfação pendentes para o seu usuário.';
            } elseif (!empty($filters['awaiting_approval'])) {
                $empty_title = 'Nenhum chamado aguardando sua aprovação';
                $empty_description = 'Não localizamos chamados solucionados aguardando sua aprovação nos filtros atuais.';
            } else {
                $empty_title = 'Nenhum chamado encontrado';
                $empty_description = 'Não localizamos chamados com os filtros atuais. Ajuste a busca ou limpe os filtros para consultar todos os chamados em que você está envolvido.';
            }

            return "
            <section class='pgeservicos-meus-chamados-empty'>
                <i class='ti ti-ticket-off' aria-hidden='true'></i>
                <h2>" . pgeservicos_h($empty_title) . "</h2>
                <p>" . pgeservicos_h($empty_description) . "</p>
            </section>";
        }

        ob_start();
        echo "<section class='pgeservicos-ticket-list' aria-label='Lista de chamados'>";

        foreach ($tickets as $ticket) {
            $ticket_id = (int)$ticket['id'];
            $status_class = 'pgeservicos-status-' . (int)$ticket['status'];
            $updated_class = !empty($ticket['is_updated'])
                ? ' pgeservicos-ticket-card-updated'
                : '';
            $ticket_name = (string)($ticket['name'] ?? '');
            $ticket_url = (string)($ticket['url'] ?? '#');
            $requester_label = (string)($ticket['requester_label'] ?? '-');
            $location_name = (string)($ticket['location_name'] ?? '-');
            $assignee_label = (string)($ticket['assignee_label'] ?? 'Sem atribuição');
            $time_to_resolve = (string)($ticket['time_to_resolve_label'] ?? 'Sem prazo');
            $time_to_resolve_overdue = !empty($ticket['time_to_resolve_overdue']);
            $time_to_resolve_progress = $ticket['time_to_resolve_progress'] ?? null;
            $has_solution_progress = $time_to_resolve_progress !== null && !$time_to_resolve_overdue;
            $solution_overdue_class = $time_to_resolve_overdue
                ? ' pgeservicos-ticket-meta-pill--overdue'
                : '';
            $solution_progress_class = $has_solution_progress
                ? ' pgeservicos-ticket-meta-pill--progress'
                : '';
            $solution_progress_style = $has_solution_progress
                ? " style='--pgeservicos-ticket-solution-progress: " . max(0, min(100, (int)$time_to_resolve_progress)) . "%;'"
                : '';
            $time_to_resolve_title = 'Tempo para solução: ' . $time_to_resolve
                . ($time_to_resolve_progress !== null ? ' - Progresso: ' . (int)$time_to_resolve_progress . '%' : '')
                . ($time_to_resolve_overdue ? ' (vencido)' : '');
            $date_label = (string)($ticket['date_label'] ?? pgeservicos_format_ticket_date($ticket['date'] ?? ''));
            $card_label = ($satisfaction_mode ? 'Responder pesquisa do chamado #' : 'Abrir chamado #') . $ticket_id . ': ' . $ticket_name;

            echo "
            <a class='pgeservicos-ticket-card{$updated_class}' href='" . pgeservicos_h($ticket_url) . "' aria-label='" . pgeservicos_h($card_label) . "'>
                <span class='pgeservicos-ticket-card-accent' aria-hidden='true'></span>
                <div class='pgeservicos-ticket-card-main'>
                    <div class='pgeservicos-ticket-title-row'>
                        <h2 title='" . pgeservicos_h($ticket_name) . "'>
                            <span>#{$ticket_id}</span>
                            <strong>" . pgeservicos_h($ticket_name) . "</strong>
                        </h2>
                        " . (!empty($ticket['is_updated'])
                            ? "<span class='pgeservicos-ticket-update-dot'><i class='ti ti-bell-ringing' aria-hidden='true'></i>Atualizado</span>"
                            : '') . "
                    </div>

                    <div class='pgeservicos-ticket-badges'>
                        <span class='pgeservicos-ticket-badge pgeservicos-ticket-area' title='" . pgeservicos_h($ticket['entity_name'] ?? $ticket['area_name']) . "'>"
                            . pgeservicos_h($ticket['area_name'])
                        . "</span>
                        <span class='pgeservicos-ticket-badge {$status_class}'>"
                            . pgeservicos_h($ticket['status_label'])
                        . "</span>";

            foreach (($ticket['vip_tags'] ?? []) as $vip_tag) {
                $vip_label = trim((string)($vip_tag['label'] ?? 'VIP'));
                $vip_color = trim((string)($vip_tag['color'] ?? ''));
                $vip_style = $vip_color !== ''
                    ? " style='--pgeservicos-ticket-vip-color: " . pgeservicos_h($vip_color) . ";'"
                    : '';
                echo "<span class='pgeservicos-ticket-badge pgeservicos-ticket-vip' title='VIP: " . pgeservicos_h($vip_label) . "'{$vip_style}>VIP: "
                    . pgeservicos_h($vip_label)
                    . "</span>";
            }

            if (!empty($ticket['is_awaiting_approval'])) {
                echo "<span class='pgeservicos-ticket-badge pgeservicos-ticket-badge--awaiting-approval'>Aguardando aprovação</span>";
            }

            if (!empty($ticket['is_satisfaction_pending'])) {
                echo "<span class='pgeservicos-ticket-badge pgeservicos-ticket-badge--satisfaction'>Aguardando avaliação</span>";
            }

            echo "
                    </div>

                    <div class='pgeservicos-ticket-meta-grid'>
                        <span class='pgeservicos-ticket-meta-pill pgeservicos-ticket-meta-pill--requester' title='Requerente: " . pgeservicos_h($requester_label) . "'>
                            <i class='ti ti-user' aria-hidden='true'></i>
                            <em class='pgeservicos-ticket-meta-label'>Requerente</em>
                            <strong class='pgeservicos-ticket-meta-value'>" . pgeservicos_h($requester_label) . "</strong>
                        </span>
                        <span class='pgeservicos-ticket-meta-pill pgeservicos-ticket-meta-pill--location' title='Localização: " . pgeservicos_h($location_name) . "'>
                            <i class='ti ti-map-pin' aria-hidden='true'></i>
                            <em class='pgeservicos-ticket-meta-label'>Localização</em>
                            <strong class='pgeservicos-ticket-meta-value'>" . pgeservicos_h($location_name) . "</strong>
                        </span>
                        <span class='pgeservicos-ticket-meta-pill pgeservicos-ticket-meta-pill--assigned' title='Atribuídos: " . pgeservicos_h($assignee_label) . "'>
                            <i class='ti ti-users' aria-hidden='true'></i>
                            <em class='pgeservicos-ticket-meta-label'>Atribuídos</em>
                            <strong class='pgeservicos-ticket-meta-value'>" . pgeservicos_h($assignee_label) . "</strong>
                        </span>
                        <span class='pgeservicos-ticket-meta-pill pgeservicos-ticket-meta-pill--solution" . pgeservicos_h($solution_overdue_class . $solution_progress_class) . "' title='" . pgeservicos_h($time_to_resolve_title) . "'" . $solution_progress_style . ">
                            <i class='ti ti-hourglass' aria-hidden='true'></i>
                            <em class='pgeservicos-ticket-meta-label'>Tempo solução</em>
                            <strong class='pgeservicos-ticket-meta-value'>" . pgeservicos_h($time_to_resolve) . "</strong>
                        </span>
                        <span class='pgeservicos-ticket-meta-pill pgeservicos-ticket-meta-pill--opened' title='Aberto em: " . pgeservicos_h($date_label) . "'>
                            <i class='ti ti-calendar' aria-hidden='true'></i>
                            <em class='pgeservicos-ticket-meta-label'>Aberto</em>
                            <strong class='pgeservicos-ticket-meta-value'>" . pgeservicos_h($date_label) . "</strong>
                        </span>
                    </div>
                </div>
            </a>";
        }

        echo "</section>";
        return ob_get_clean();
    }
}

if (!function_exists('pgeservicos_meus_chamados_pagination_html')) {
    function pgeservicos_meus_chamados_pagination_html($page_url, array $filters, array $pagination, $total_count) {
        if ((int)$total_count <= 0) {
            return '';
        }

        $current_page = max(1, (int)($pagination['page'] ?? 1));
        $total_pages = max(1, (int)($pagination['total_pages'] ?? 1));
        $from = (int)($pagination['from'] ?? 0);
        $to = (int)($pagination['to'] ?? 0);
        $per_page = (int)($pagination['per_page'] ?? $filters['per_page'] ?? 20);
        $per_page_options = [20, 50, 100, 250, 500];

        if (!in_array($per_page, $per_page_options, true)) {
            $per_page = 20;
        }

        $page_numbers = [];
        $page_numbers[1] = 1;
        for ($page_index = max(1, $current_page - 2); $page_index <= min($total_pages, $current_page + 2); $page_index++) {
            $page_numbers[$page_index] = $page_index;
        }
        $page_numbers[$total_pages] = $total_pages;
        ksort($page_numbers);

        ob_start();
        echo "
        <section class='pgeservicos-pagination' aria-label='Paginação dos chamados'>
            <p class='pgeservicos-pagination-summary'>Exibindo " . pgeservicos_h($from) . "–" . pgeservicos_h($to) . " de " . pgeservicos_h($total_count) . " " . ((int)$total_count === 1 ? 'chamado' : 'chamados') . "</p>
            <form class='pgeservicos-pagination-size' method='get' action='" . pgeservicos_h($page_url) . "' data-pgeservicos-pagination-size>";

        pgeservicos_meus_chamados_print_hidden_filters($filters, ['per_page', 'page']);

        echo "
                <label for='pgeservicos-ticket-per-page'>Por página</label>
                <select id='pgeservicos-ticket-per-page' name='per_page' data-pgeservicos-per-page>";

        foreach ($per_page_options as $per_page_option) {
            $selected = $per_page === $per_page_option ? ' selected' : '';
            echo "<option value='" . (int)$per_page_option . "'{$selected}>" . (int)$per_page_option . "</option>";
        }

        echo "
                </select>
            </form>
            <nav aria-label='Navegar entre páginas'>";

        if ($current_page > 1) {
            echo "<a href='" . pgeservicos_h(pgeservicos_meus_chamados_page_url($page_url, $filters, $current_page - 1)) . "'>&larr; Anterior</a>";
        } else {
            echo "<span class='is-disabled' aria-disabled='true'>&larr; Anterior</span>";
        }

        $previous_page = 0;
        foreach ($page_numbers as $page_number) {
            if ($previous_page > 0 && $page_number > $previous_page + 1) {
                echo "<span class='pgeservicos-pagination-ellipsis' aria-hidden='true'>…</span>";
            }

            if ($page_number === $current_page) {
                echo "<span class='is-current' aria-current='page'>" . pgeservicos_h($page_number) . "</span>";
            } else {
                echo "<a href='" . pgeservicos_h(pgeservicos_meus_chamados_page_url($page_url, $filters, $page_number)) . "'>" . pgeservicos_h($page_number) . "</a>";
            }

            $previous_page = $page_number;
        }

        if ($current_page < $total_pages) {
            echo "<a href='" . pgeservicos_h(pgeservicos_meus_chamados_page_url($page_url, $filters, $current_page + 1)) . "'>Próxima &rarr;</a>";
        } else {
            echo "<span class='is-disabled' aria-disabled='true'>Próxima &rarr;</span>";
        }

        echo "
            </nav>
        </section>";

        return ob_get_clean();
    }
}

$asset_version = defined('PLUGIN_PGESERVICOS_VERSION')
    ? PLUGIN_PGESERVICOS_VERSION
    : '1';
$home_url = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/pgeservicos/front/index.php';
$page_url = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/pgeservicos/front/meus_chamados.php';
$mark_updates_url = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/pgeservicos/front/ajax_mark_ticket_updates_read.php';
$csrf_token = Session::getNewCSRFToken();

$last_view_at = pgeservicos_get_ticket_last_view_at();
$tickets_result = pgeservicos_get_user_tickets($_GET, [
    'last_view_at' => $last_view_at
]);
$filters = $tickets_result['filters'];
$tickets = $tickets_result['tickets'];
$entity_options = $tickets_result['entity_options'];
$updated_count = (int)$tickets_result['updated_count'];
$total_count = (int)$tickets_result['total_count'];
$list_context = $tickets_result['context'] ?? pgeservicos_get_ticket_list_context();
$pagination = $tickets_result['pagination'] ?? [
    'page' => 1,
    'per_page' => 20,
    'total_pages' => 1,
    'from' => 0,
    'to' => 0,
];
$page_title = (string)($list_context['title'] ?? 'Meus chamados');
$page_description = (string)($list_context['description'] ?? 'Acompanhe seus chamados e atualizações.');
$satisfaction_mode = !empty($filters['satisfaction']);
$summary_html = pgeservicos_meus_chamados_summary_html($total_count, $updated_count, $satisfaction_mode);
$list_html = pgeservicos_meus_chamados_list_html($tickets, $filters, $satisfaction_mode);
$pagination_html = pgeservicos_meus_chamados_pagination_html($page_url, $filters, $pagination, $total_count);
$active_filters_html = !$satisfaction_mode
    ? pgeservicos_meus_chamados_active_filters_html($filters, $entity_options)
    : '';
$normalized_url = pgeservicos_meus_chamados_page_url($page_url, $filters, $filters['page'] ?? 1);
$is_partial = !empty($_GET['partial'])
    || (isset($_SERVER['HTTP_X_PGESERVICOS_PARTIAL']) && $_SERVER['HTTP_X_PGESERVICOS_PARTIAL'] === '1');

if ($is_partial) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'ok' => true,
        'summary_html' => $summary_html,
        'list_html' => $list_html,
        'pagination_html' => $pagination_html,
        'active_filters_html' => $active_filters_html,
        'url' => $normalized_url,
        'total' => $total_count,
        'page' => (int)($pagination['page'] ?? 1),
        'per_page' => (int)($pagination['per_page'] ?? $filters['per_page'] ?? 20),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

Html::header(
    'PGE Serviços - ' . $page_title,
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginPgeservicosPortal'
);

echo "<link rel='stylesheet' href='"
    . pgeservicos_h($CFG_GLPI['root_doc'])
    . "/plugins/pgeservicos/css/shared/pgeservicos.css?v="
    . pgeservicos_h($asset_version)
    . "'>";

echo "<link rel='stylesheet' href='"
    . pgeservicos_h($CFG_GLPI['root_doc'])
    . "/plugins/pgeservicos/css/pages/meus_chamados.css?v="
    . pgeservicos_h($asset_version)
    . "'>";

echo "<script defer src='"
    . pgeservicos_h($CFG_GLPI['root_doc'])
    . "/plugins/pgeservicos/js/meus_chamados.js?v="
    . pgeservicos_h($asset_version)
    . "'></script>";

pgeservicos_theme_print_vars();
pgeservicos_theme_print_sidebar_sync_script($CFG_GLPI['root_doc'] ?? '', $asset_version);

echo "<div class='pgeservicos-container pgeservicos-meus-chamados-page"
    . ($satisfaction_mode ? " pgeservicos-meus-chamados-page--satisfaction" : "")
    . "' style='"
    . pgeservicos_theme_style_attr()
    . "'"
    . pgeservicos_theme_topbar_context_attr()
    . " data-pgeservicos-mark-updates-url='"
    . pgeservicos_h($mark_updates_url)
    . "' data-pgeservicos-csrf-token='"
    . pgeservicos_h($csrf_token)
    . "'>";

echo "
<section class='pgeservicos-hero pgeservicos-meus-chamados-hero'>
    <div>
        <div class='pgeservicos-header-actions'>
            <a class='pgeservicos-header-back-link' href='" . pgeservicos_h($home_url) . "'>
                &larr; Voltar para o Portal de Serviços
            </a>
        </div>
        <h1>" . pgeservicos_h($page_title) . "</h1>
        <p>" . pgeservicos_h($page_description) . "</p>
    </div>
    <div class='pgeservicos-meus-chamados-summary' aria-label='Resumo dos chamados' data-pgeservicos-summary>"
        . $summary_html .
    "</div>
</section>";

if (!$satisfaction_mode) {
    echo pgeservicos_meus_chamados_filters_html($page_url, $filters, $entity_options);
}

echo "
<div class='pgeservicos-results-shell' data-pgeservicos-results-shell>
    <div data-pgeservicos-results>" . $list_html . "</div>
    <div data-pgeservicos-pagination-region>" . $pagination_html . "</div>
</div>";

echo "</div>";

Html::footer();
