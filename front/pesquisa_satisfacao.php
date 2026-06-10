<?php

include('../../../inc/includes.php');

require_once(__DIR__ . '/../inc/plugin_state.php');
pgeservicos_require_plugin_active();

Session::checkLoginUser();

global $CFG_GLPI;

require_once(__DIR__ . '/../inc/theme.php');
require_once(__DIR__ . '/../inc/ticket_view.php');
require_once(__DIR__ . '/../inc/satisfaction.php');

if (!function_exists('pgeservicos_satisfaction_h')) {
    function pgeservicos_satisfaction_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('pgeservicos_satisfaction_question_html')) {
    function pgeservicos_satisfaction_question_html($value) {
        return nl2br(pgeservicos_satisfaction_h($value), false);
    }
}

if (!function_exists('pgeservicos_satisfaction_render_note')) {
    function pgeservicos_satisfaction_render_note(array $question) {
        $question_id = (int)$question['id'];
        $max = max(1, min(10, (int)($question['number'] ?? 5)));
        $value = max(1, min($max, (int)($question['value'] ?? 1)));

        echo "<div class='pgeservicos-satisfaction-rating' role='radiogroup' aria-label='Nota da pergunta'>";
        for ($score = 1; $score <= $max; $score++) {
            $input_id = 'pgeservicos-satisfaction-q' . $question_id . '-' . $score;
            $checked = $score === $value ? ' checked' : '';
            echo "<input type='radio' id='" . pgeservicos_satisfaction_h($input_id) . "' name='answer[" . $question_id . "]' value='" . $score . "'" . $checked . ">";
            echo "<label for='" . pgeservicos_satisfaction_h($input_id) . "' title='Nota " . $score . "'>";
            echo "<strong>" . $score . "</strong>";
            echo "<span aria-hidden='true'>" . str_repeat('&#9733;', $score) . "</span>";
            echo "</label>";
        }
        echo "</div>";
    }
}

if (!function_exists('pgeservicos_satisfaction_render_yesno')) {
    function pgeservicos_satisfaction_render_yesno(array $question) {
        $question_id = (int)$question['id'];
        $value = (int)($question['value'] ?? 0) === 1 ? 1 : 0;
        echo "<div class='pgeservicos-satisfaction-choice' role='radiogroup' aria-label='Resposta sim ou não'>";
        foreach ([1 => 'Sim', 0 => 'Não'] as $option_value => $label) {
            $input_id = 'pgeservicos-satisfaction-q' . $question_id . '-' . $option_value;
            $checked = $option_value === $value ? ' checked' : '';
            echo "<input type='radio' id='" . pgeservicos_satisfaction_h($input_id) . "' name='answer[" . $question_id . "]' value='" . $option_value . "'" . $checked . ">";
            echo "<label for='" . pgeservicos_satisfaction_h($input_id) . "'>" . pgeservicos_satisfaction_h($label) . "</label>";
        }
        echo "</div>";
    }
}

if (!function_exists('pgeservicos_satisfaction_render_question')) {
    function pgeservicos_satisfaction_render_question(array $question) {
        $type = (string)($question['type'] ?? '');
        $question_id = (int)($question['id'] ?? 0);
        $label = (string)($question['label'] ?? $question['name'] ?? 'Pergunta');
        $comment = trim((string)($question['comment'] ?? ''));

        echo "<article class='pgeservicos-satisfaction-question'>";
        echo "<div class='pgeservicos-satisfaction-question-text'>";
        echo "<h2>" . pgeservicos_satisfaction_question_html($label) . "</h2>";
        if ($comment !== '') {
            echo "<p>" . pgeservicos_satisfaction_question_html($comment) . "</p>";
        }
        echo "</div>";

        echo "<div class='pgeservicos-satisfaction-question-field'>";
        switch ($type) {
            case PluginSatisfactionSurveyQuestion::NOTE:
                pgeservicos_satisfaction_render_note($question);
                break;

            case PluginSatisfactionSurveyQuestion::YESNO:
                pgeservicos_satisfaction_render_yesno($question);
                break;

            case PluginSatisfactionSurveyQuestion::TEXTAREA:
                echo "<textarea name='answer[" . $question_id . "]' rows='5' placeholder='Escreva sua resposta'>" . pgeservicos_satisfaction_h($question['value'] ?? '') . "</textarea>";
                break;

            default:
                echo "<input type='text' name='answer[" . $question_id . "]' value='" . pgeservicos_satisfaction_h($question['value'] ?? '') . "'>";
                break;
        }
        echo "</div>";
        echo "</article>";
    }
}

$tickets_id = isset($_GET['tickets_id']) ? (int)$_GET['tickets_id'] : 0;
$context = pgeservicos_satisfaction_get_pending_context($tickets_id);
$status_code = !empty($context['ok']) ? 200 : (int)($context['status'] ?? 404);
http_response_code($status_code);

$page_title = 'Pesquisa de satisfação';
Html::header(
    'PGE Serviços - ' . $page_title,
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginPgeservicosPortal'
);

$asset_version = defined('PLUGIN_PGESERVICOS_VERSION') ? PLUGIN_PGESERVICOS_VERSION : '1';
$root_doc = $CFG_GLPI['root_doc'] ?? '';
$home_url = $root_doc . '/plugins/pgeservicos/front/index.php';
$list_url = $root_doc . '/plugins/pgeservicos/front/meus_chamados.php?satisfaction=1';
$action_url = $root_doc . '/plugins/pgeservicos/front/pesquisa_satisfacao_action.php';

foreach ([
    '/plugins/pgeservicos/css/shared/pgeservicos.css',
    '/plugins/pgeservicos/css/pages/pesquisa_satisfacao.css',
] as $asset_path) {
    echo "<link rel='stylesheet' href='" . pgeservicos_satisfaction_h($root_doc . $asset_path) . "?v=" . pgeservicos_satisfaction_h($asset_version) . "'>";
}

echo "<script defer src='" . pgeservicos_satisfaction_h($root_doc) . "/plugins/pgeservicos/js/pesquisa_satisfacao.js?v=" . pgeservicos_satisfaction_h($asset_version) . "'></script>";

pgeservicos_theme_print_vars();
pgeservicos_theme_print_sidebar_sync_script($root_doc, $asset_version);

echo "<div class='pgeservicos-container pgeservicos-satisfaction-page' style='" . pgeservicos_theme_style_attr() . "'" . pgeservicos_theme_topbar_context_attr() . ">";

echo "
<section class='pgeservicos-hero pgeservicos-satisfaction-hero'>
    <div>
        <div class='pgeservicos-header-actions'>
            <a class='pgeservicos-header-back-link' href='" . pgeservicos_satisfaction_h($list_url) . "'>&larr; Chamados aguardando avaliação</a>
            <a class='pgeservicos-header-back-link' href='" . pgeservicos_satisfaction_h($home_url) . "'>&larr; Voltar para o Portal de Serviços</a>
        </div>
        <h1>Pesquisa de satisfação</h1>
        <p>" . (!empty($context['ok'])
            ? "Avalie o atendimento referente ao chamado #" . (int)$context['ticket']->fields['id'] . "."
            : "Não foi possível abrir esta pesquisa de satisfação.") . "</p>
    </div>
</section>
";

if (empty($context['ok'])) {
    echo "<section class='pgeservicos-satisfaction-error'>";
    echo "<i class='ti ti-clipboard-off' aria-hidden='true'></i>";
    echo "<h2>Pesquisa indisponível</h2>";
    echo "<p>" . pgeservicos_satisfaction_h($context['message'] ?? 'Esta pesquisa não está disponível para o seu usuário.') . "</p>";
    echo "<a href='" . pgeservicos_satisfaction_h($list_url) . "'>Voltar para chamados aguardando avaliação</a>";
    echo "</section>";
    echo "</div>";
    Html::footer();
    exit;
}

/** @var Ticket $ticket */
$ticket = $context['ticket'];
$questions = $context['questions'];
$has_note_question = false;

foreach ($questions as $question) {
    if ((string)($question['type'] ?? '') === PluginSatisfactionSurveyQuestion::NOTE) {
        $has_note_question = true;
        break;
    }
}

$csrf_token = Session::getNewCSRFToken();
$ticket_id = (int)$ticket->fields['id'];
$ticket_name = (string)($ticket->fields['name'] ?? 'Chamado');
$status_label = Ticket::getStatus((int)($ticket->fields['status'] ?? 0));
$entity_name = Dropdown::getDropdownName('glpi_entities', (int)($ticket->fields['entities_id'] ?? 0));
$date_label = pgeservicos_ticket_view_date($ticket->fields['date'] ?? '');

if ($entity_name === '') {
    $entity_name = 'Entidade #' . (int)($ticket->fields['entities_id'] ?? 0);
}

echo "
<section class='pgeservicos-satisfaction-ticket-card' aria-label='Resumo do chamado'>
    <span class='pgeservicos-satisfaction-ticket-icon'><i class='ti ti-ticket' aria-hidden='true'></i></span>
    <div>
        <span>Chamado #" . $ticket_id . "</span>
        <h2>" . pgeservicos_satisfaction_h($ticket_name) . "</h2>
        <div class='pgeservicos-satisfaction-ticket-meta'>
            <em>" . pgeservicos_satisfaction_h($status_label) . "</em>
            <em>" . pgeservicos_satisfaction_h($entity_name) . "</em>
            <em>Aberto em " . pgeservicos_satisfaction_h($date_label) . "</em>
        </div>
    </div>
</section>
";

echo "
<form class='pgeservicos-satisfaction-form' method='post' action='" . pgeservicos_satisfaction_h($action_url) . "'>
    <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_satisfaction_h($csrf_token) . "'>
    <input type='hidden' name='tickets_id' value='" . $ticket_id . "'>
";

if (empty($questions)) {
    echo "<section class='pgeservicos-satisfaction-question'>";
    echo "<div class='pgeservicos-satisfaction-question-text'><h2>Satisfação com a resolução do chamado</h2><p>A pesquisa configurada não possui perguntas extras. Informe uma nota geral para concluir.</p></div>";
    echo "<div class='pgeservicos-satisfaction-question-field'>";
    echo "<div class='pgeservicos-satisfaction-rating' role='radiogroup' aria-label='Nota geral'>";
    for ($score = 1; $score <= 5; $score++) {
        $input_id = 'pgeservicos-satisfaction-fallback-' . $score;
        $checked = $score === 3 ? ' checked' : '';
        echo "<input type='radio' id='" . pgeservicos_satisfaction_h($input_id) . "' name='satisfaction' value='" . $score . "'" . $checked . ">";
        echo "<label for='" . pgeservicos_satisfaction_h($input_id) . "'><strong>" . $score . "</strong><span aria-hidden='true'>" . str_repeat('&#9733;', $score) . "</span></label>";
    }
    echo "</div></div></section>";
} else {
    foreach ($questions as $question) {
        pgeservicos_satisfaction_render_question($question);
    }

    if (!$has_note_question) {
        echo "<section class='pgeservicos-satisfaction-question'>";
        echo "<div class='pgeservicos-satisfaction-question-text'><h2>Nota geral</h2><p>Informe a nota geral que será registrada na pesquisa de satisfação do GLPI.</p></div>";
        echo "<div class='pgeservicos-satisfaction-question-field'>";
        echo "<div class='pgeservicos-satisfaction-rating' role='radiogroup' aria-label='Nota geral'>";
        for ($score = 0; $score <= 5; $score++) {
            $input_id = 'pgeservicos-satisfaction-overall-' . $score;
            $checked = $score === 3 ? ' checked' : '';
            echo "<input type='radio' id='" . pgeservicos_satisfaction_h($input_id) . "' name='satisfaction' value='" . $score . "'" . $checked . ">";
            echo "<label for='" . pgeservicos_satisfaction_h($input_id) . "'><strong>" . $score . "</strong><span aria-hidden='true'>" . str_repeat('&#9733;', $score) . "</span></label>";
        }
        echo "</div></div></section>";
    }
}

echo "
    <section class='pgeservicos-satisfaction-question pgeservicos-satisfaction-comment'>
        <div class='pgeservicos-satisfaction-question-text'>
            <h2>Comentário geral</h2>
            <p>Campo opcional usado no registro nativo da pesquisa de satisfação do GLPI.</p>
        </div>
        <div class='pgeservicos-satisfaction-question-field'>
            <textarea name='comment' rows='5' placeholder='Conte como foi sua experiência com o atendimento'></textarea>
        </div>
    </section>

    <div class='pgeservicos-satisfaction-actions'>
        <a href='" . pgeservicos_satisfaction_h($list_url) . "'>Cancelar</a>
        <button type='submit' name='update' value='1'>
            <i class='ti ti-send' aria-hidden='true'></i>
            Enviar pesquisa
        </button>
    </div>
</form>
";

echo "</div>";

Html::footer();
