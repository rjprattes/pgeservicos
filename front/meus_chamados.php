<?php

include('../../../inc/includes.php');

Session::checkLoginUser();

global $CFG_GLPI;

require_once(__DIR__ . '/../inc/theme.php');
require_once(__DIR__ . '/../inc/tickets_catalog.php');

if (!function_exists('pgeservicos_h')) {
    function pgeservicos_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('pgeservicos_meus_chamados_page_url')) {
    function pgeservicos_meus_chamados_page_url($base_url, array $filters, $page) {
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

        if (!empty($filters['atualizados'])) {
            $params['atualizados'] = 1;
        }

        $per_page = (int)($filters['per_page'] ?? 20);

        if (in_array($per_page, [20, 50, 100, 250, 500], true) && $per_page !== 20) {
            $params['per_page'] = $per_page;
        }

        $page = max(1, (int)$page);

        if ($page > 1) {
            $params['page'] = $page;
        }

        return $base_url . (!empty($params) ? '?' . http_build_query($params) : '');
    }
}

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

Html::header(
    'PGE Serviços - ' . $page_title,
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginPgeservicosPortal'
);

$asset_version = defined('PLUGIN_PGESERVICOS_VERSION')
    ? PLUGIN_PGESERVICOS_VERSION
    : '1';
$home_url = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/pgeservicos/front/index.php';
$page_url = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/pgeservicos/front/meus_chamados.php';
$mark_updates_url = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/pgeservicos/front/ajax_mark_ticket_updates_read.php';
$csrf_token = Session::getNewCSRFToken();

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

echo "<div class='pgeservicos-container pgeservicos-meus-chamados-page' data-pgeservicos-mark-updates-url='"
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
    <div class='pgeservicos-meus-chamados-summary' aria-label='Resumo dos chamados'>
        <strong>" . pgeservicos_h($total_count) . "</strong>
        <span>" . ($total_count === 1 ? 'chamado encontrado' : 'chamados encontrados') . "</span>
        " . ($updated_count > 0
            ? "<em>" . pgeservicos_h($updated_count) . " " . ($updated_count === 1 ? 'atualização' : 'atualizações') . "</em>"
            : "<em>Sem novas atualizações</em>") . "
        " . ($updated_count > 0
            ? "<button type='button' class='pgeservicos-mark-updates-read' data-pgeservicos-mark-updates-read>
                <i class='ti ti-checks' aria-hidden='true'></i>
                <span>Marcar todas como visualizadas</span>
            </button>"
            : '') . "
    </div>
</section>
";

echo "
<form class='pgeservicos-meus-chamados-filters' method='get' action='" . pgeservicos_h($page_url) . "'>
    <input type='hidden' name='per_page' value='" . (int)($filters['per_page'] ?? 20) . "'>
    <div class='pgeservicos-filter-field pgeservicos-filter-search'>
        <label for='pgeservicos-ticket-q'>Buscar</label>
        <input
            type='search'
            id='pgeservicos-ticket-q'
            name='q'
            value='" . pgeservicos_h($filters['q']) . "'
            placeholder='Número ou título do chamado'
        >
    </div>

    <div class='pgeservicos-filter-field'>
        <label for='pgeservicos-ticket-status'>Status</label>
        <select id='pgeservicos-ticket-status' name='status'>";

foreach (pgeservicos_ticket_status_filter_options() as $status_value => $status_label) {
    $selected = (string)$filters['status'] === (string)$status_value ? " selected" : "";
    echo "<option value='" . pgeservicos_h($status_value) . "'{$selected}>"
        . pgeservicos_h($status_label)
        . "</option>";
}

echo "
        </select>
    </div>

    <div class='pgeservicos-filter-field'>
        <label for='pgeservicos-ticket-sort'>Ordenar por</label>
        <select id='pgeservicos-ticket-sort' name='sort'>";

foreach (pgeservicos_ticket_sort_options() as $sort_value => $sort_label) {
    $selected = (string)$filters['sort'] === (string)$sort_value ? " selected" : "";
    echo "<option value='" . pgeservicos_h($sort_value) . "'{$selected}>"
        . pgeservicos_h($sort_label)
        . "</option>";
}

echo "
        </select>
    </div>

    <div class='pgeservicos-filter-field'>
        <label for='pgeservicos-ticket-entidade'>Área</label>
        <select id='pgeservicos-ticket-entidade' name='entidade'>
            <option value=''>Todas</option>";

foreach ($entity_options as $entities_id => $entity_name) {
    $selected = (int)$filters['entidade'] === (int)$entities_id ? " selected" : "";
    echo "<option value='" . (int)$entities_id . "'{$selected}>"
        . pgeservicos_h($entity_name)
        . "</option>";
}

echo "
        </select>
    </div>

    <label class='pgeservicos-filter-check'>
        <input type='checkbox' name='atualizados' value='1' " . (!empty($filters['atualizados']) ? 'checked' : '') . ">
        <span>Somente atualizados</span>
    </label>

    <div class='pgeservicos-filter-actions'>
        <button type='submit'>
            <i class='ti ti-filter' aria-hidden='true'></i>
            Filtrar
        </button>
        <a href='" . pgeservicos_h($page_url) . "'>Limpar</a>
    </div>
</form>
";

if (empty($tickets)) {
    echo "
    <section class='pgeservicos-meus-chamados-empty'>
        <i class='ti ti-ticket-off' aria-hidden='true'></i>
        <h2>Nenhum chamado encontrado</h2>
        <p>
            Não localizamos chamados com os filtros atuais. Ajuste a busca ou limpe os filtros
            para consultar todos os chamados em que você está envolvido.
        </p>
    </section>
    ";
} else {
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
        $card_label = 'Abrir chamado #' . $ticket_id . ': ' . $ticket_name;

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
        </a>
        ";
    }

    echo "</section>";
}

if ($total_count > 0) {
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

    echo "
    <section class='pgeservicos-pagination' aria-label='Paginação dos chamados'>
        <p class='pgeservicos-pagination-summary'>Exibindo " . pgeservicos_h($from) . "–" . pgeservicos_h($to) . " de " . pgeservicos_h($total_count) . " " . ($total_count === 1 ? 'chamado' : 'chamados') . "</p>
        <form class='pgeservicos-pagination-size' method='get' action='" . pgeservicos_h($page_url) . "'>";

    if (($filters['q'] ?? '') !== '') {
        echo "<input type='hidden' name='q' value='" . pgeservicos_h($filters['q']) . "'>";
    }

    if (($filters['status'] ?? 'not_solved') !== 'not_solved') {
        echo "<input type='hidden' name='status' value='" . pgeservicos_h($filters['status']) . "'>";
    }

    if (($filters['sort'] ?? 'updated_desc') !== 'updated_desc') {
        echo "<input type='hidden' name='sort' value='" . pgeservicos_h($filters['sort']) . "'>";
    }

    if ((int)($filters['entidade'] ?? -1) >= 0) {
        echo "<input type='hidden' name='entidade' value='" . (int)$filters['entidade'] . "'>";
    }

    if (!empty($filters['atualizados'])) {
        echo "<input type='hidden' name='atualizados' value='1'>";
    }

    echo "
            <label for='pgeservicos-ticket-per-page'>Por página</label>
            <select id='pgeservicos-ticket-per-page' name='per_page' onchange='this.form.submit()'>";

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
}

echo "</div>";

Html::footer();
