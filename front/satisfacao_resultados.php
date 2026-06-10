<?php

include('../../../inc/includes.php');

require_once(__DIR__ . '/../inc/plugin_state.php');
pgeservicos_require_plugin_active();

Session::checkLoginUser();

global $CFG_GLPI;

require_once(__DIR__ . '/../inc/theme.php');
require_once(__DIR__ . '/../inc/satisfaction.php');

if (!function_exists('pgeservicos_satisfaction_page_h')) {
    function pgeservicos_satisfaction_page_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('pgeservicos_satisfaction_page_url')) {
    function pgeservicos_satisfaction_page_url(array $params = []) {
        global $CFG_GLPI;

        $base = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/pgeservicos/front/satisfacao_resultados.php';
        $current = $_GET;
        unset($current['page']);
        $query = array_merge($current, $params);

        foreach ($query as $key => $value) {
            if ($value === '' || $value === null || $value === -1 || $value === '-1') {
                unset($query[$key]);
            }
        }

        return $base . (!empty($query) ? '?' . http_build_query($query) : '');
    }
}

if (!function_exists('pgeservicos_satisfaction_hidden_filter_inputs')) {
    function pgeservicos_satisfaction_hidden_filter_inputs(array $filters, array $skip = []) {
        $fields = ['q', 'entity', 'date_from', 'date_to', 'rating', 'survey_id', 'sort', 'per_page'];
        foreach ($fields as $field) {
            if (in_array($field, $skip, true)) {
                continue;
            }

            $value = $filters[$field] ?? null;
            if ($value === null || $value === '' || $value === -1 || $value === '-1' || ($field === 'survey_id' && (int)$value === 0)) {
                continue;
            }

            echo "<input type='hidden' name='" . pgeservicos_satisfaction_page_h($field) . "' value='" . pgeservicos_satisfaction_page_h($value) . "'>";
        }
    }
}

$access = pgeservicos_satisfaction_require_results_access();
$filters = pgeservicos_satisfaction_normalize_result_filters($_GET);
$results = !empty($access['ok'])
    ? pgeservicos_get_satisfaction_results($filters)
    : ['ok' => false, 'message' => $access['message'] ?? 'Acesso negado.', 'filters' => $filters, 'items' => [], 'total' => 0, 'pages' => 1, 'summary' => ['average' => null, 'entities' => 0]];
$filters = $results['filters'];
$is_super_admin = pgeservicos_satisfaction_current_profile_is_super_admin();
$can_create = pgeservicos_satisfaction_user_can_create_surveys();
$entity_options = $is_super_admin ? pgeservicos_satisfaction_entity_options(false) : [];
$survey_options = !empty($access['ok']) ? pgeservicos_satisfaction_survey_options() : [];

http_response_code(!empty($access['ok']) ? 200 : (int)($access['status'] ?? 403));

Html::header(
    'PGE Serviços - Resultados de satisfação',
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginPgeservicosPortal'
);

$asset_version = defined('PLUGIN_PGESERVICOS_VERSION') ? PLUGIN_PGESERVICOS_VERSION : '1';
$root_doc = $CFG_GLPI['root_doc'] ?? '';
$home_url = $root_doc . '/plugins/pgeservicos/front/index.php';
$create_url = $root_doc . '/plugins/pgeservicos/front/satisfacao_survey_form.php';
$manage_url = $root_doc . '/plugins/pgeservicos/front/satisfacao_surveys.php';
$page_url = $root_doc . '/plugins/pgeservicos/front/satisfacao_resultados.php';

foreach ([
    '/plugins/pgeservicos/css/shared/pgeservicos.css',
    '/plugins/pgeservicos/css/pages/satisfacao_resultados.css',
] as $asset_path) {
    echo "<link rel='stylesheet' href='" . pgeservicos_satisfaction_page_h($root_doc . $asset_path) . "?v=" . pgeservicos_satisfaction_page_h($asset_version) . "'>";
}

echo "<script defer src='" . pgeservicos_satisfaction_page_h($root_doc) . "/plugins/pgeservicos/js/satisfacao_resultados.js?v=" . pgeservicos_satisfaction_page_h($asset_version) . "'></script>";

pgeservicos_theme_print_vars();
pgeservicos_theme_print_sidebar_sync_script($root_doc, $asset_version);

echo "<div class='pgeservicos-container pgeservicos-satisfaction-results-page' style='" . pgeservicos_theme_style_attr() . "'" . pgeservicos_theme_topbar_context_attr() . ">";

echo "
<section class='pgeservicos-hero pgeservicos-satisfaction-results-hero'>
    <div>
        <div class='pgeservicos-header-actions'>
            <a class='pgeservicos-header-back-link' href='" . pgeservicos_satisfaction_page_h($home_url) . "'>&larr; Voltar para o Portal de Serviços</a>
        </div>
        <h1>Resultados de satisfação</h1>
        <p>Acompanhe as avaliações enviadas pelos usuários sobre os atendimentos.</p>
    </div>
    <div class='pgeservicos-satisfaction-results-summary'>
        <span>" . (int)($results['total'] ?? 0) . "</span>
        <small>avaliações encontradas</small>
    </div>
</section>
";

if (empty($access['ok'])) {
    echo "<section class='pgeservicos-satisfaction-results-empty'>";
    echo "<i class='ti ti-lock' aria-hidden='true'></i>";
    echo "<h2>Acesso indisponível</h2>";
    echo "<p>" . pgeservicos_satisfaction_page_h($access['message'] ?? 'Você não possui permissão para visualizar esta área.') . "</p>";
    echo "</section>";
    echo "</div>";
    Html::footer();
    exit;
}

$average = $results['summary']['average'];
$average_label = $average === null ? 'Sem média' : pgeservicos_satisfaction_rating_label($average);

echo "
<section class='pgeservicos-satisfaction-results-metrics' aria-label='Resumo das avaliações'>
    <article>
        <span>" . (int)$results['total'] . "</span>
        <small>Total respondido</small>
    </article>
    <article>
        <span>" . pgeservicos_satisfaction_page_h($average_label) . "</span>
        <small>Média geral</small>
    </article>
    <article>
        <span>" . (int)($results['summary']['entities'] ?? 0) . "</span>
        <small>Entidades avaliadas</small>
    </article>
</section>
";

if ($can_create) {
    echo "<div class='pgeservicos-satisfaction-results-toolbar'>";
    echo "<a class='pgeservicos-satisfaction-results-create' href='" . pgeservicos_satisfaction_page_h($create_url) . "'><i class='ti ti-plus' aria-hidden='true'></i> Criar nova pesquisa</a>";
    echo "<a class='pgeservicos-satisfaction-results-manage' href='" . pgeservicos_satisfaction_page_h($manage_url) . "'><i class='ti ti-settings' aria-hidden='true'></i> Gerenciar pesquisas</a>";
    echo "</div>";
}

echo "
<form class='pgeservicos-satisfaction-results-filters' method='get' action='" . pgeservicos_satisfaction_page_h($page_url) . "'>
    <input type='hidden' name='per_page' value='" . (int)$filters['per_page'] . "'>
    <label>
        <span>Buscar</span>
        <input type='search' name='q' value='" . pgeservicos_satisfaction_page_h($filters['q']) . "' placeholder='Número ou título do chamado'>
    </label>
";

if ($is_super_admin) {
    echo "<label><span>Entidade</span><select name='entity'>";
    echo "<option value='-1'>Todas</option>";
    foreach ($entity_options as $entities_id => $entity_name) {
        $selected = (int)$filters['entity'] === (int)$entities_id ? ' selected' : '';
        echo "<option value='" . (int)$entities_id . "'" . $selected . ">" . pgeservicos_satisfaction_page_h($entity_name) . "</option>";
    }
    echo "</select></label>";
}

echo "
    <label>
        <span>Período inicial</span>
        <input type='date' name='date_from' value='" . pgeservicos_satisfaction_page_h($filters['date_from']) . "'>
    </label>
    <label>
        <span>Período final</span>
        <input type='date' name='date_to' value='" . pgeservicos_satisfaction_page_h($filters['date_to']) . "'>
    </label>
    <label>
        <span>Nota média</span>
        <select name='rating'>
            <option value='-1'>Todas</option>
";
for ($rating = 10; $rating >= 1; $rating--) {
    $selected = (int)$filters['rating'] === $rating ? ' selected' : '';
    echo "<option value='" . $rating . "'" . $selected . ">" . $rating . "</option>";
}
echo "
        </select>
    </label>
    <label>
        <span>Pesquisa</span>
        <select name='survey_id'>
            <option value='0'>Todas</option>
";
foreach ($survey_options as $survey_id => $survey_name) {
    $selected = (int)$filters['survey_id'] === (int)$survey_id ? ' selected' : '';
    echo "<option value='" . (int)$survey_id . "'" . $selected . ">" . pgeservicos_satisfaction_page_h($survey_name) . "</option>";
}
echo "
        </select>
    </label>
    <label>
        <span>Ordenar por</span>
        <select name='sort'>
";
$sort_options = [
    'recent'      => 'Respondidas recentemente',
    'low_rating'  => 'Menor nota média',
    'high_rating' => 'Maior nota média',
    'ticket'      => 'Número do chamado',
    'entity'      => 'Entidade',
];
foreach ($sort_options as $sort_key => $sort_label) {
    $selected = (string)$filters['sort'] === $sort_key ? ' selected' : '';
    echo "<option value='" . pgeservicos_satisfaction_page_h($sort_key) . "'" . $selected . ">" . pgeservicos_satisfaction_page_h($sort_label) . "</option>";
}
echo "
        </select>
    </label>
    <div class='pgeservicos-satisfaction-results-filter-actions'>
        <button type='submit'><i class='ti ti-filter' aria-hidden='true'></i> Filtrar</button>
        <a href='" . pgeservicos_satisfaction_page_h($page_url) . "'>Limpar</a>
    </div>
</form>
";

if (empty($results['items'])) {
    echo "<section class='pgeservicos-satisfaction-results-empty'>";
    echo "<i class='ti ti-clipboard-off' aria-hidden='true'></i>";
    echo "<h2>Nenhuma avaliação encontrada</h2>";
    echo "<p>Não encontramos respostas de pesquisa de satisfação para os filtros atuais.</p>";
    echo "</section>";
} else {
    echo "<section class='pgeservicos-satisfaction-results-list' aria-label='Avaliações respondidas'>";

    foreach ($results['items'] as $item) {
        $ticket_id = (int)$item['tickets_id'];
        $detail_url = $root_doc . '/plugins/pgeservicos/front/satisfacao_resultado_detalhe.php?satisfaction_id=' . (int)$item['satisfaction_id'];
        $comment = trim((string)($item['comment'] ?? ''));
        $requesters = !empty($item['requesters']) ? implode(', ', $item['requesters']) : 'Sem requerente';
        $assignees = !empty($item['assignees']) ? implode(', ', $item['assignees']) : 'Sem atribuição';
        $survey_name = trim((string)($item['survey_name'] ?? '')) ?: 'Pesquisa nativa';
        $location_name = trim((string)($item['location_name'] ?? '')) ?: '-';
        $rating_label = (string)($item['rating_average_label'] ?? 'Sem nota');

        echo "<a class='pgeservicos-satisfaction-result-card' href='" . pgeservicos_satisfaction_page_h($detail_url) . "' aria-label='Ver detalhes da avaliação do chamado #" . $ticket_id . "'>";
        echo "<span class='pgeservicos-satisfaction-result-accent' aria-hidden='true'></span>";
        echo "<div class='pgeservicos-satisfaction-result-main'>";
        echo "<div class='pgeservicos-satisfaction-result-title-row'>";
        echo "<h2 title='" . pgeservicos_satisfaction_page_h($item['ticket_name'] ?? 'Chamado') . "'><span>#" . $ticket_id . "</span> <strong>" . pgeservicos_satisfaction_page_h($item['ticket_name'] ?? 'Chamado') . "</strong></h2>";
        echo "<span class='pgeservicos-satisfaction-result-detail'>Ver detalhes</span>";
        echo "</div>";
        echo "<div class='pgeservicos-satisfaction-result-badges'>";
        echo "<span>" . pgeservicos_satisfaction_page_h($item['entity_name'] ?? 'Entidade') . "</span>";
        echo "<span>" . pgeservicos_satisfaction_page_h($survey_name) . "</span>";
        echo "</div>";
        echo "<div class='pgeservicos-satisfaction-result-meta-grid'>";
        echo "<span class='pgeservicos-satisfaction-result-meta-pill pgeservicos-satisfaction-result-meta-pill--requester' title='Requerente: " . pgeservicos_satisfaction_page_h($requesters) . "'><i class='ti ti-user' aria-hidden='true'></i><em>Requerente</em><strong>" . pgeservicos_satisfaction_page_h($requesters) . "</strong></span>";
        echo "<span class='pgeservicos-satisfaction-result-meta-pill pgeservicos-satisfaction-result-meta-pill--assigned' title='Atribuição: " . pgeservicos_satisfaction_page_h($assignees) . "'><i class='ti ti-users' aria-hidden='true'></i><em>Atribuição</em><strong>" . pgeservicos_satisfaction_page_h($assignees) . "</strong></span>";
        echo "<span class='pgeservicos-satisfaction-result-meta-pill pgeservicos-satisfaction-result-meta-pill--location' title='Localização: " . pgeservicos_satisfaction_page_h($location_name) . "'><i class='ti ti-map-pin' aria-hidden='true'></i><em>Localização</em><strong>" . pgeservicos_satisfaction_page_h($location_name) . "</strong></span>";
        echo "<span class='pgeservicos-satisfaction-result-meta-pill pgeservicos-satisfaction-result-meta-pill--answered' title='Respondida em: " . pgeservicos_satisfaction_page_h(Html::convDateTime($item['date_answered'])) . "'><i class='ti ti-calendar-check' aria-hidden='true'></i><em>Respondida em</em><strong>" . pgeservicos_satisfaction_page_h(Html::convDateTime($item['date_answered'])) . "</strong></span>";
        echo "<span class='pgeservicos-satisfaction-result-meta-pill pgeservicos-satisfaction-result-meta-pill--rating' title='Nota média: " . pgeservicos_satisfaction_page_h($rating_label) . "'><i class='ti ti-star' aria-hidden='true'></i><em>Nota média</em><strong>" . pgeservicos_satisfaction_page_h($rating_label) . "</strong></span>";
        echo "</div>";
        if ($comment !== '') {
            echo "<p>" . pgeservicos_satisfaction_page_h(mb_substr($comment, 0, 180, 'UTF-8')) . (mb_strlen($comment, 'UTF-8') > 180 ? '...' : '') . "</p>";
        }
        echo "</div>";
        echo "</a>";
    }

    echo "</section>";

    $page = (int)$filters['page'];
    $pages = (int)$results['pages'];
    $from = $results['total'] > 0 ? (int)$filters['start'] + 1 : 0;
    $to = min((int)$results['total'], (int)$filters['start'] + (int)$filters['per_page']);
    $per_page_options = [10, 20, 50, 100];

    echo "<section class='pgeservicos-satisfaction-results-pagination' aria-label='Paginação de avaliações'>";
    echo "<p class='pgeservicos-satisfaction-results-pagination-summary'>Exibindo " . pgeservicos_satisfaction_page_h($from) . "–" . pgeservicos_satisfaction_page_h($to) . " de " . (int)$results['total'] . " avaliações</p>";
    echo "<form class='pgeservicos-satisfaction-results-pagination-size' method='get' action='" . pgeservicos_satisfaction_page_h($page_url) . "'>";
    pgeservicos_satisfaction_hidden_filter_inputs($filters, ['per_page']);
    echo "<label for='pgeservicos-satisfaction-per-page'>Por página</label>";
    echo "<select id='pgeservicos-satisfaction-per-page' name='per_page' onchange='this.form.submit()'>";
    foreach ($per_page_options as $per_page_option) {
        $selected = (int)$filters['per_page'] === $per_page_option ? ' selected' : '';
        echo "<option value='" . (int)$per_page_option . "'" . $selected . ">" . (int)$per_page_option . "</option>";
    }
    echo "</select></form>";
    echo "<nav aria-label='Navegar entre páginas'>";
    echo $page > 1
        ? "<a href='" . pgeservicos_satisfaction_page_h(pgeservicos_satisfaction_page_url(['page' => $page - 1])) . "'>&larr; Anterior</a>"
        : "<span class='is-disabled' aria-disabled='true'>&larr; Anterior</span>";
    echo "<span class='is-current' aria-current='page'>" . $page . "/" . $pages . "</span>";
    echo $page < $pages
        ? "<a href='" . pgeservicos_satisfaction_page_h(pgeservicos_satisfaction_page_url(['page' => $page + 1])) . "'>Próxima &rarr;</a>"
        : "<span class='is-disabled' aria-disabled='true'>Próxima &rarr;</span>";
    echo "</nav>";
    echo "</section>";
}

echo "</div>";

Html::footer();
