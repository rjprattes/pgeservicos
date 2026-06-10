<?php

include('../../../inc/includes.php');

require_once(__DIR__ . '/../inc/plugin_state.php');
pgeservicos_require_plugin_active();

Session::checkLoginUser();

global $CFG_GLPI;

require_once(__DIR__ . '/../inc/theme.php');
require_once(__DIR__ . '/../inc/satisfaction.php');

if (!function_exists('pgeservicos_survey_form_h')) {
    function pgeservicos_survey_form_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$survey_id = isset($_GET['survey_id']) ? (int)$_GET['survey_id'] : 0;
$can_create = pgeservicos_satisfaction_user_can_create_surveys();
$ready = pgeservicos_satisfaction_plugin_ready();
$survey_context = $survey_id > 0 && $can_create && $ready
    ? pgeservicos_satisfaction_get_survey_for_management($survey_id)
    : null;
$editing = $survey_id > 0 && !empty($survey_context['ok']);
$survey = $editing ? $survey_context['survey'] : [];
$answer_count = $editing ? (int)($survey['answer_count'] ?? 0) : 0;
$can_edit_questions = !$editing || $answer_count === 0;
$status_ok = $can_create && $ready && ($survey_id <= 0 || $editing);
http_response_code($status_ok ? 200 : 403);

Html::header(
    $editing ? 'PGE Serviços - Editar pesquisa de satisfação' : 'PGE Serviços - Nova pesquisa de satisfação',
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginPgeservicosPortal'
);

$asset_version = defined('PLUGIN_PGESERVICOS_VERSION') ? PLUGIN_PGESERVICOS_VERSION : '1';
$root_doc = $CFG_GLPI['root_doc'] ?? '';
$home_url = $root_doc . '/plugins/pgeservicos/front/index.php';
$list_url = $root_doc . '/plugins/pgeservicos/front/satisfacao_resultados.php';
$manage_url = $root_doc . '/plugins/pgeservicos/front/satisfacao_surveys.php';
$action_url = $root_doc . '/plugins/pgeservicos/front/satisfacao_survey_action.php';

foreach ([
    '/plugins/pgeservicos/css/shared/pgeservicos.css',
    '/plugins/pgeservicos/css/pages/satisfacao_resultados.css',
] as $asset_path) {
    echo "<link rel='stylesheet' href='" . pgeservicos_survey_form_h($root_doc . $asset_path) . "?v=" . pgeservicos_survey_form_h($asset_version) . "'>";
}

echo "<script defer src='" . pgeservicos_survey_form_h($root_doc) . "/plugins/pgeservicos/js/satisfacao_resultados.js?v=" . pgeservicos_survey_form_h($asset_version) . "'></script>";

pgeservicos_theme_print_vars();
pgeservicos_theme_print_sidebar_sync_script($root_doc, $asset_version);

$page_title = $editing ? 'Editar pesquisa de satisfação' : 'Criar pesquisa de satisfação';
$page_description = $editing
    ? 'Atualize uma pesquisa sem respostas usando a estrutura nativa do GLPI/Satisfaction.'
    : 'Configure uma nova pesquisa usando a estrutura nativa do GLPI/Satisfaction.';

if ($editing && $answer_count > 0) {
    $page_description = 'Pesquisas com respostas não podem ter perguntas editadas.';
}

echo "<div class='pgeservicos-container pgeservicos-satisfaction-results-page' style='" . pgeservicos_theme_style_attr() . "'" . pgeservicos_theme_topbar_context_attr() . ">";

echo "
<section class='pgeservicos-hero pgeservicos-satisfaction-results-hero'>
    <div>
        <div class='pgeservicos-header-actions'>
            <a class='pgeservicos-header-back-link' href='" . pgeservicos_survey_form_h($manage_url) . "'>&larr; Gerenciar pesquisas</a>
            <a class='pgeservicos-header-back-link' href='" . pgeservicos_survey_form_h($list_url) . "'>&larr; Resultados de satisfação</a>
            <a class='pgeservicos-header-back-link' href='" . pgeservicos_survey_form_h($home_url) . "'>&larr; Voltar para o Portal de Serviços</a>
        </div>
        <h1>" . pgeservicos_survey_form_h($page_title) . "</h1>
        <p>" . pgeservicos_survey_form_h($page_description) . "</p>
    </div>
</section>
";

if (!$can_create || !$ready || ($survey_id > 0 && !$editing)) {
    echo "<section class='pgeservicos-satisfaction-results-empty'>";
    echo "<i class='ti ti-lock' aria-hidden='true'></i>";
    echo "<h2>Criação indisponível</h2>";
    if (!$can_create) {
        echo "<p>Apenas o perfil Super-Admin pode criar ou gerenciar pesquisas de satisfação.</p>";
    } elseif (!$ready) {
        echo "<p>O plugin Satisfaction não está disponível.</p>";
    } else {
        echo "<p>Pesquisa não encontrada.</p>";
    }
    echo "<a href='" . pgeservicos_survey_form_h($list_url) . "'>Voltar para resultados</a>";
    echo "</section>";
    echo "</div>";
    Html::footer();
    exit;
}

if (!$can_edit_questions) {
    echo "<section class='pgeservicos-satisfaction-results-empty'>";
    echo "<i class='ti ti-alert-circle' aria-hidden='true'></i>";
    echo "<h2>Pesquisa com respostas</h2>";
    echo "<p>Você não pode editar as perguntas quando houver respostas para essa pesquisa. Desative esta pesquisa e crie uma nova.</p>";
    echo "<a href='" . pgeservicos_survey_form_h($manage_url) . "'>Voltar para gerenciamento</a>";
    echo "<a href='" . pgeservicos_survey_form_h($root_doc . '/plugins/pgeservicos/front/satisfacao_survey_form.php') . "'>Criar nova pesquisa</a>";
    echo "</section>";
    echo "</div>";
    Html::footer();
    exit;
}

$entities = pgeservicos_satisfaction_entity_options(true);
$csrf_token = Session::getNewCSRFToken();
$selected_entity = $editing ? (int)($survey['entities_id'] ?? -1) : -1;
$survey_name = $editing ? (string)($survey['name'] ?? '') : '';
$survey_comment = $editing ? (string)($survey['comment'] ?? '') : '';
$is_active = !$editing || !empty($survey['is_active']);
$is_recursive = $editing && !empty($survey['is_recursive']);
$questions = $editing && !empty($survey['questions']) ? $survey['questions'] : [[
    'name'          => '',
    'type'          => PluginSatisfactionSurveyQuestion::NOTE,
    'comment'       => '',
    'number'        => 5,
    'default_value' => 1,
]];
$question_types = [
    PluginSatisfactionSurveyQuestion::NOTE => 'Nota',
    PluginSatisfactionSurveyQuestion::YESNO => 'Sim/Não',
    PluginSatisfactionSurveyQuestion::TEXTAREA => 'Texto',
];

echo "
<form class='pgeservicos-satisfaction-survey-form' method='post' action='" . pgeservicos_survey_form_h($action_url) . "' data-pgeservicos-survey-form>
    <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_survey_form_h($csrf_token) . "'>
    <input type='hidden' name='survey_id' value='" . (int)$survey_id . "'>

    <section class='pgeservicos-satisfaction-survey-section'>
        <h2>Dados da pesquisa</h2>
        <div class='pgeservicos-satisfaction-survey-grid'>
            <label>
                <span>Entidade/área</span>
                <select name='entities_id' required>
                    <option value=''>Selecione</option>
";
foreach ($entities as $entities_id => $entity_name) {
    $selected = (int)$entities_id === $selected_entity ? ' selected' : '';
    echo "<option value='" . (int)$entities_id . "'" . $selected . ">" . pgeservicos_survey_form_h($entity_name) . "</option>";
}
echo "
                </select>
            </label>
            <label>
                <span>Nome</span>
                <input type='text' name='name' maxlength='255' required placeholder='Pesquisa de Satisfação - Nome da área' value='" . pgeservicos_survey_form_h($survey_name) . "'>
            </label>
            <label class='pgeservicos-satisfaction-survey-check'>
                <input type='checkbox' name='is_active' value='1'" . ($is_active ? ' checked' : '') . ">
                <span>Pesquisa ativa</span>
            </label>
            <label class='pgeservicos-satisfaction-survey-check'>
                <input type='checkbox' name='is_recursive' value='1'" . ($is_recursive ? ' checked' : '') . ">
                <span>Aplicar a entidades filhas</span>
            </label>
        </div>
        <label class='pgeservicos-satisfaction-survey-full'>
            <span>Comentário</span>
            <textarea name='comment' rows='3' placeholder='Observações internas sobre a pesquisa'>" . pgeservicos_survey_form_h($survey_comment) . "</textarea>
        </label>
    </section>

    <section class='pgeservicos-satisfaction-survey-section'>
        <div class='pgeservicos-satisfaction-survey-section-heading'>
            <h2>Perguntas</h2>
            <button type='button' data-pgeservicos-add-question><i class='ti ti-plus' aria-hidden='true'></i> Adicionar pergunta</button>
        </div>
        <div class='pgeservicos-satisfaction-question-list' data-pgeservicos-question-list>
";

foreach ($questions as $question) {
    $type = (string)($question['type'] ?? PluginSatisfactionSurveyQuestion::NOTE);
    if (!isset($question_types[$type])) {
        $type = PluginSatisfactionSurveyQuestion::NOTE;
    }
    $question_name = (string)($question['name'] ?? ($question['label'] ?? ''));
    $question_comment = (string)($question['comment'] ?? '');
    $number = max(1, min(10, (int)($question['number'] ?? 5)));
    $default_value = max(1, min($number, (int)($question['default_value'] ?? 1)));

    echo "<article class='pgeservicos-satisfaction-question-builder' data-pgeservicos-question-row>";
    echo "<div class='pgeservicos-satisfaction-survey-grid'>";
    echo "<label class='pgeservicos-satisfaction-survey-full'><span>Pergunta</span><textarea name='question_name[]' rows='2' required placeholder='Ex.: Quantas estrelas você daria para o atendimento?'>" . pgeservicos_survey_form_h($question_name) . "</textarea></label>";
    echo "<label><span>Tipo</span><select name='question_type[]' data-pgeservicos-question-type>";
    foreach ($question_types as $option_type => $label) {
        $selected = $option_type === $type ? ' selected' : '';
        echo "<option value='" . pgeservicos_survey_form_h($option_type) . "'" . $selected . ">" . pgeservicos_survey_form_h($label) . "</option>";
    }
    echo "</select></label>";
    echo "<label data-pgeservicos-note-field><span>Nota máxima</span><input type='number' name='question_number[]' min='1' max='10' value='" . (int)$number . "'></label>";
    echo "<label data-pgeservicos-note-field><span>Valor padrão</span><input type='number' name='question_default_value[]' min='1' max='10' value='" . (int)$default_value . "'></label>";
    echo "<label class='pgeservicos-satisfaction-survey-full'><span>Comentário da pergunta</span><textarea name='question_comment[]' rows='2' placeholder='Texto de apoio opcional'>" . pgeservicos_survey_form_h($question_comment) . "</textarea></label>";
    echo "</div>";
    echo "<button type='button' class='pgeservicos-satisfaction-question-remove' data-pgeservicos-remove-question>Remover pergunta</button>";
    echo "</article>";
}

echo "
        </div>
    </section>

    <div class='pgeservicos-satisfaction-survey-actions'>
        <a href='" . pgeservicos_survey_form_h($manage_url) . "'>Cancelar</a>
        <button type='submit'><i class='ti ti-device-floppy' aria-hidden='true'></i> " . ($editing ? 'Salvar alterações' : 'Salvar pesquisa') . "</button>
    </div>
</form>
";

echo "</div>";

Html::footer();
