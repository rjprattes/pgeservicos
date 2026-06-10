<?php

include('../../../inc/includes.php');

require_once(__DIR__ . '/../inc/plugin_state.php');
pgeservicos_require_plugin_active();

Session::checkLoginUser();

global $CFG_GLPI;

require_once(__DIR__ . '/../inc/theme.php');
require_once(__DIR__ . '/../inc/satisfaction.php');

if (!function_exists('pgeservicos_satisfaction_detail_h')) {
    function pgeservicos_satisfaction_detail_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('pgeservicos_satisfaction_detail_stars')) {
    function pgeservicos_satisfaction_detail_stars($rating) {
        $rating = max(0, min(5, (int)$rating));
        return str_repeat('&#9733;', $rating) . str_repeat('&#9734;', 5 - $rating);
    }
}

$satisfaction_id = isset($_GET['satisfaction_id']) ? (int)$_GET['satisfaction_id'] : 0;
$tickets_id = isset($_GET['tickets_id']) ? (int)$_GET['tickets_id'] : 0;
$detail = pgeservicos_get_satisfaction_result_detail($satisfaction_id, $tickets_id);
$status_code = !empty($detail['ok']) ? 200 : (int)($detail['status'] ?? 404);
http_response_code($status_code);

Html::header(
    'PGE Serviços - Detalhe da avaliação',
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginPgeservicosPortal'
);

$asset_version = defined('PLUGIN_PGESERVICOS_VERSION') ? PLUGIN_PGESERVICOS_VERSION : '1';
$root_doc = $CFG_GLPI['root_doc'] ?? '';
$home_url = $root_doc . '/plugins/pgeservicos/front/index.php';
$list_url = $root_doc . '/plugins/pgeservicos/front/satisfacao_resultados.php';

foreach ([
    '/plugins/pgeservicos/css/shared/pgeservicos.css',
    '/plugins/pgeservicos/css/pages/satisfacao_resultados.css',
] as $asset_path) {
    echo "<link rel='stylesheet' href='" . pgeservicos_satisfaction_detail_h($root_doc . $asset_path) . "?v=" . pgeservicos_satisfaction_detail_h($asset_version) . "'>";
}

pgeservicos_theme_print_vars();
pgeservicos_theme_print_sidebar_sync_script($root_doc, $asset_version);

echo "<div class='pgeservicos-container pgeservicos-satisfaction-results-page' style='" . pgeservicos_theme_style_attr() . "'" . pgeservicos_theme_topbar_context_attr() . ">";

echo "
<section class='pgeservicos-hero pgeservicos-satisfaction-results-hero'>
    <div>
        <div class='pgeservicos-header-actions'>
            <a class='pgeservicos-header-back-link' href='" . pgeservicos_satisfaction_detail_h($list_url) . "'>&larr; Resultados de satisfação</a>
            <a class='pgeservicos-header-back-link' href='" . pgeservicos_satisfaction_detail_h($home_url) . "'>&larr; Voltar para o Portal de Serviços</a>
        </div>
        <h1>Detalhe da avaliação</h1>
        <p>Veja a nota, comentário e respostas enviadas pelo usuário.</p>
    </div>
</section>
";

if (empty($detail['ok'])) {
    echo "<section class='pgeservicos-satisfaction-results-empty'>";
    echo "<i class='ti ti-lock' aria-hidden='true'></i>";
    echo "<h2>Avaliação indisponível</h2>";
    echo "<p>" . pgeservicos_satisfaction_detail_h($detail['message'] ?? 'Você não pode visualizar esta avaliação.') . "</p>";
    echo "<a href='" . pgeservicos_satisfaction_detail_h($list_url) . "'>Voltar para resultados</a>";
    echo "</section>";
    echo "</div>";
    Html::footer();
    exit;
}

$item = $detail['item'];
$ticket_id = (int)$item['tickets_id'];
$ticket_url = $root_doc . '/plugins/pgeservicos/front/chamado.php?tickets_id=' . $ticket_id;
$rating = (int)$item['satisfaction'];
$requesters = !empty($item['requesters']) ? implode(', ', $item['requesters']) : 'Sem requerente';
$assignees = !empty($item['assignees']) ? implode(', ', $item['assignees']) : 'Sem atribuição';
$survey_name = trim((string)($item['survey_name'] ?? '')) ?: 'Pesquisa nativa';
$comment = trim((string)($item['comment'] ?? ''));

echo "
<section class='pgeservicos-satisfaction-detail-card'>
    <div class='pgeservicos-satisfaction-detail-heading'>
        <div>
            <span>Chamado #" . $ticket_id . "</span>
            <h2>" . pgeservicos_satisfaction_detail_h($item['ticket_name'] ?? 'Chamado') . "</h2>
        </div>
        <a href='" . pgeservicos_satisfaction_detail_h($ticket_url) . "'>Abrir chamado</a>
    </div>
    <div class='pgeservicos-satisfaction-detail-grid'>
        <div><strong>Entidade</strong><span>" . pgeservicos_satisfaction_detail_h($item['entity_name'] ?? 'Entidade') . "</span></div>
        <div><strong>Pesquisa</strong><span>" . pgeservicos_satisfaction_detail_h($survey_name) . "</span></div>
        <div><strong>Respondida em</strong><span>" . pgeservicos_satisfaction_detail_h(Html::convDateTime($item['date_answered'])) . "</span></div>
        <div><strong>Fechamento</strong><span>" . pgeservicos_satisfaction_detail_h(!empty($item['closedate']) ? Html::convDateTime($item['closedate']) : 'Sem data') . "</span></div>
        <div><strong>Requerente</strong><span>" . pgeservicos_satisfaction_detail_h($requesters) . "</span></div>
        <div><strong>Atribuição</strong><span>" . pgeservicos_satisfaction_detail_h($assignees) . "</span></div>
    </div>
</section>

<section class='pgeservicos-satisfaction-detail-rating'>
    <div>
        <span>Nota geral</span>
        <strong>" . $rating . "/5</strong>
        <em aria-hidden='true'>" . pgeservicos_satisfaction_detail_stars($rating) . "</em>
    </div>
    <article>
        <h2>Comentário geral</h2>
        <p>" . ($comment !== '' ? nl2br(pgeservicos_satisfaction_detail_h($comment), false) : 'Nenhum comentário geral informado.') . "</p>
    </article>
</section>
";

echo "<section class='pgeservicos-satisfaction-detail-answers'>";
echo "<h2>Respostas da pesquisa</h2>";

if (empty($item['answer_details'])) {
    echo "<p class='pgeservicos-satisfaction-detail-muted'>Nenhuma pergunta extra foi registrada para esta avaliação.</p>";
} else {
    foreach ($item['answer_details'] as $answer) {
        echo "<article>";
        echo "<strong>" . nl2br(pgeservicos_satisfaction_detail_h($answer['label'] ?? 'Pergunta'), false) . "</strong>";
        echo "<p>" . nl2br(pgeservicos_satisfaction_detail_h($answer['display'] ?? ''), false) . "</p>";
        echo "</article>";
    }
}

echo "</section>";
echo "</div>";

Html::footer();
