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

$last_view_at = pgeservicos_get_ticket_last_view_at();
$tickets_result = pgeservicos_get_user_tickets($_GET, [
    'last_view_at' => $last_view_at
]);
$filters = $tickets_result['filters'];
$tickets = $tickets_result['tickets'];
$entity_options = $tickets_result['entity_options'];
$updated_count = (int)$tickets_result['updated_count'];
$total_count = (int)$tickets_result['total_count'];

Html::header(
    'PGE Serviços - Meus chamados',
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginPgeservicosPortal'
);

$asset_version = defined('PLUGIN_PGESERVICOS_VERSION')
    ? PLUGIN_PGESERVICOS_VERSION
    : '1';
$home_url = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/pgeservicos/front/index.php';
$page_url = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/pgeservicos/front/meus_chamados.php';

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

pgeservicos_theme_print_vars();

echo "<div class='pgeservicos-container pgeservicos-meus-chamados-page'>";

echo "
<a class='pgeservicos-back-link pgeservicos-meus-chamados-back' href='" . pgeservicos_h($home_url) . "'>
    &larr; Voltar para o Portal de Serviços
</a>

<section class='pgeservicos-hero pgeservicos-meus-chamados-hero'>
    <div>
        <h1>Meus chamados</h1>
        <p>
            Acompanhe em um só lugar os chamados em que você aparece como requerente,
            observador, técnico ou participante por grupo, reunindo as entidades às quais
            você possui acesso.
        </p>
    </div>
    <div class='pgeservicos-meus-chamados-summary' aria-label='Resumo dos chamados'>
        <strong>" . pgeservicos_h($total_count) . "</strong>
        <span>" . ($total_count === 1 ? 'chamado encontrado' : 'chamados encontrados') . "</span>
        " . ($updated_count > 0
            ? "<em>" . pgeservicos_h($updated_count) . " " . ($updated_count === 1 ? 'atualização' : 'atualizações') . "</em>"
            : "<em>Sem novas atualizações</em>") . "
    </div>
</section>
";

echo "
<form class='pgeservicos-meus-chamados-filters' method='get' action='" . pgeservicos_h($page_url) . "'>
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
        <select id='pgeservicos-ticket-status' name='status'>
            <option value=''>Todos</option>";

foreach (Ticket::getAllStatusArray(true) as $status_id => $status_label) {
    if (!in_array((int)$status_id, [1, 2, 3, 4, 5, 6], true)) {
        continue;
    }

    $selected = (int)$filters['status'] === (int)$status_id ? " selected" : "";
    echo "<option value='" . (int)$status_id . "'{$selected}>"
        . pgeservicos_h($status_label)
        . "</option>";
}

echo "
        </select>
    </div>

    <div class='pgeservicos-filter-field'>
        <label for='pgeservicos-ticket-envolvimento'>Envolvimento</label>
        <select id='pgeservicos-ticket-envolvimento' name='envolvimento'>
            <option value=''>Todos</option>";

foreach (['requerente', 'observador', 'tecnico', 'grupo'] as $involvement) {
    $selected = $filters['envolvimento'] === $involvement ? " selected" : "";
    echo "<option value='" . pgeservicos_h($involvement) . "'{$selected}>"
        . pgeservicos_h(pgeservicos_ticket_involvement_label($involvement))
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

        echo "
        <article class='pgeservicos-ticket-card{$updated_class}'>
            <div class='pgeservicos-ticket-card-main'>
                <div class='pgeservicos-ticket-title-row'>
                    <h2>
                        <span>#{$ticket_id}</span>
                        " . pgeservicos_h($ticket['name']) . "
                    </h2>
                    " . (!empty($ticket['is_updated'])
                        ? "<span class='pgeservicos-ticket-update-dot'>Atualizado</span>"
                        : '') . "
                </div>

                <div class='pgeservicos-ticket-badges'>
                    <span class='pgeservicos-ticket-badge pgeservicos-ticket-area'>"
                        . pgeservicos_h($ticket['area_name'])
                    . "</span>
                    <span class='pgeservicos-ticket-badge {$status_class}'>"
                        . pgeservicos_h($ticket['status_label'])
                    . "</span>";

        foreach ($ticket['involvements'] as $involvement) {
            echo "<span class='pgeservicos-ticket-badge pgeservicos-ticket-involvement'>"
                . pgeservicos_h(pgeservicos_ticket_involvement_label($involvement))
                . "</span>";
        }

        echo "
                </div>

                <dl class='pgeservicos-ticket-meta'>
                    <div>
                        <dt>Aberto em</dt>
                        <dd>" . pgeservicos_h(pgeservicos_format_ticket_date($ticket['date'])) . "</dd>
                    </div>
                    <div>
                        <dt>Última atualização</dt>
                        <dd>" . pgeservicos_h(pgeservicos_format_ticket_date($ticket['date_mod'])) . "</dd>
                    </div>
                    <div>
                        <dt>Prioridade</dt>
                        <dd>" . pgeservicos_h($ticket['priority_label']) . "</dd>
                    </div>
                </dl>

                <div class='pgeservicos-ticket-card-action'>
                    <a href='" . pgeservicos_h($ticket['url']) . "'>
                        Abrir chamado
                        <i class='ti ti-arrow-right' aria-hidden='true'></i>
                    </a>
                </div>
            </div>
        </article>
        ";
    }

    echo "</section>";
}

echo "</div>";

Html::footer();
