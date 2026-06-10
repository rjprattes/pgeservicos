<?php

include('../../../inc/includes.php');

require_once(__DIR__ . '/../inc/plugin_state.php');
pgeservicos_require_plugin_active();

Session::checkLoginUser();

global $CFG_GLPI;

require_once(__DIR__ . '/../inc/theme.php');
require_once(__DIR__ . '/../inc/satisfaction.php');

if (!function_exists('pgeservicos_surveys_h')) {
    function pgeservicos_surveys_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$can_manage = pgeservicos_satisfaction_user_can_create_surveys();
$ready = pgeservicos_satisfaction_plugin_ready();
http_response_code($can_manage && $ready ? 200 : 403);

Html::header(
    'PGE Serviços - Gerenciar pesquisas de satisfação',
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginPgeservicosPortal'
);

$asset_version = defined('PLUGIN_PGESERVICOS_VERSION') ? PLUGIN_PGESERVICOS_VERSION : '1';
$root_doc = $CFG_GLPI['root_doc'] ?? '';
$home_url = $root_doc . '/plugins/pgeservicos/front/index.php';
$results_url = $root_doc . '/plugins/pgeservicos/front/satisfacao_resultados.php';
$create_url = $root_doc . '/plugins/pgeservicos/front/satisfacao_survey_form.php';
$action_url = $root_doc . '/plugins/pgeservicos/front/satisfacao_surveys_action.php';

foreach ([
    '/plugins/pgeservicos/css/shared/pgeservicos.css',
    '/plugins/pgeservicos/css/pages/satisfacao_resultados.css',
] as $asset_path) {
    echo "<link rel='stylesheet' href='" . pgeservicos_surveys_h($root_doc . $asset_path) . "?v=" . pgeservicos_surveys_h($asset_version) . "'>";
}

pgeservicos_theme_print_vars();
pgeservicos_theme_print_sidebar_sync_script($root_doc, $asset_version);

echo "<div class='pgeservicos-container pgeservicos-satisfaction-results-page pgeservicos-satisfaction-surveys-page' style='" . pgeservicos_theme_style_attr() . "'" . pgeservicos_theme_topbar_context_attr() . ">";

echo "
<section class='pgeservicos-hero pgeservicos-satisfaction-results-hero'>
    <div>
        <div class='pgeservicos-header-actions'>
            <a class='pgeservicos-header-back-link' href='" . pgeservicos_surveys_h($results_url) . "'>&larr; Resultados de satisfação</a>
            <a class='pgeservicos-header-back-link' href='" . pgeservicos_surveys_h($home_url) . "'>&larr; Voltar para o Portal de Serviços</a>
        </div>
        <h1>Gerenciar pesquisas de satisfação</h1>
        <p>Visualize pesquisas criadas no Satisfaction e controle quais estão ativas.</p>
    </div>
</section>
";

if (!$can_manage || !$ready) {
    echo "<section class='pgeservicos-satisfaction-results-empty'>";
    echo "<i class='ti ti-lock' aria-hidden='true'></i>";
    echo "<h2>Gerenciamento indisponível</h2>";
    echo "<p>" . ($can_manage ? 'O plugin Satisfaction não está disponível.' : 'Apenas o perfil Super-Admin pode gerenciar pesquisas de satisfação.') . "</p>";
    echo "<a href='" . pgeservicos_surveys_h($results_url) . "'>Voltar para resultados</a>";
    echo "</section>";
    echo "</div>";
    Html::footer();
    exit;
}

$surveys = pgeservicos_satisfaction_get_surveys();
$csrf_token = Session::getNewCSRFToken();

echo "<div class='pgeservicos-satisfaction-results-toolbar'>";
echo "<a class='pgeservicos-satisfaction-results-create' href='" . pgeservicos_surveys_h($create_url) . "'><i class='ti ti-plus' aria-hidden='true'></i> Criar nova pesquisa</a>";
echo "</div>";

if (empty($surveys)) {
    echo "<section class='pgeservicos-satisfaction-results-empty'>";
    echo "<i class='ti ti-clipboard-off' aria-hidden='true'></i>";
    echo "<h2>Nenhuma pesquisa cadastrada</h2>";
    echo "<p>Crie uma nova pesquisa para começar a coletar avaliações por entidade.</p>";
    echo "<a href='" . pgeservicos_surveys_h($create_url) . "'>Criar nova pesquisa</a>";
    echo "</section>";
} else {
    echo "<section class='pgeservicos-satisfaction-surveys-list' aria-label='Pesquisas cadastradas'>";

    foreach ($surveys as $survey) {
        $survey_id = (int)($survey['id'] ?? 0);
        $answer_count = (int)($survey['answer_count'] ?? 0);
        $question_count = (int)($survey['question_count'] ?? 0);
        $active = !empty($survey['is_active']);
        $edit_url = $create_url . '?survey_id=' . $survey_id;
        $toggle_label = $active ? 'Inativar' : 'Ativar';
        $target_active = $active ? 0 : 1;

        echo "<article class='pgeservicos-satisfaction-survey-card'>";
        echo "<div class='pgeservicos-satisfaction-survey-card-heading'>";
        echo "<div><span>Pesquisa #" . $survey_id . "</span><h2>" . pgeservicos_surveys_h($survey['name'] ?? 'Pesquisa') . "</h2></div>";
        echo "<span class='pgeservicos-satisfaction-survey-status " . ($active ? 'is-active' : 'is-inactive') . "'>" . ($active ? 'Ativa' : 'Inativa') . "</span>";
        echo "</div>";

        echo "<div class='pgeservicos-satisfaction-survey-card-meta'>";
        echo "<span><em>Entidade</em><strong>" . pgeservicos_surveys_h($survey['entity_name'] ?? '-') . "</strong></span>";
        echo "<span><em>Recursiva</em><strong>" . (!empty($survey['is_recursive']) ? 'Sim' : 'Não') . "</strong></span>";
        echo "<span><em>Perguntas</em><strong>" . $question_count . "</strong></span>";
        echo "<span><em>Respostas</em><strong>" . $answer_count . "</strong></span>";
        echo "<span><em>Criada em</em><strong>" . pgeservicos_surveys_h(!empty($survey['date_creation']) ? Html::convDateTime($survey['date_creation']) : '-') . "</strong></span>";
        echo "<span><em>Atualizada em</em><strong>" . pgeservicos_surveys_h(!empty($survey['date_mod']) ? Html::convDateTime($survey['date_mod']) : '-') . "</strong></span>";
        echo "</div>";

        if (!empty($survey['comment'])) {
            echo "<p class='pgeservicos-satisfaction-survey-comment'>" . pgeservicos_surveys_h($survey['comment']) . "</p>";
        }

        echo "<details class='pgeservicos-satisfaction-survey-questions'>";
        echo "<summary>Visualizar perguntas</summary>";
        if (empty($survey['questions'])) {
            echo "<p>Nenhuma pergunta cadastrada.</p>";
        } else {
            echo "<ol>";
            foreach ($survey['questions'] as $question) {
                $type = (string)($question['type'] ?? '');
                $type_label = $type === PluginSatisfactionSurveyQuestion::NOTE
                    ? 'Nota até ' . max(1, min(10, (int)($question['number'] ?? 5)))
                    : ($type === PluginSatisfactionSurveyQuestion::YESNO ? 'Sim/Não' : 'Texto');
                echo "<li><strong>" . nl2br(pgeservicos_surveys_h(pgeservicos_satisfaction_question_label($question)), false) . "</strong><span>" . pgeservicos_surveys_h($type_label) . "</span></li>";
            }
            echo "</ol>";
        }
        echo "</details>";

        if ($answer_count > 0) {
            echo "<p class='pgeservicos-satisfaction-survey-warning'><i class='ti ti-alert-circle' aria-hidden='true'></i> Você não pode editar as perguntas quando houver respostas para essa pesquisa. Desative esta pesquisa e crie uma nova.</p>";
        }

        echo "<div class='pgeservicos-satisfaction-survey-card-actions'>";
        echo "<form method='post' action='" . pgeservicos_surveys_h($action_url) . "'>";
        echo "<input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_surveys_h($csrf_token) . "'>";
        echo "<input type='hidden' name='action' value='toggle_status'>";
        echo "<input type='hidden' name='survey_id' value='" . $survey_id . "'>";
        echo "<input type='hidden' name='is_active' value='" . $target_active . "'>";
        echo "<button type='submit'>" . pgeservicos_surveys_h($toggle_label) . "</button>";
        echo "</form>";
        if ($answer_count === 0) {
            echo "<a href='" . pgeservicos_surveys_h($edit_url) . "'>Editar</a>";
        } else {
            echo "<span class='is-disabled'>Editar</span>";
        }
        echo "</div>";
        echo "</article>";
    }

    echo "</section>";
}

echo "</div>";

Html::footer();
