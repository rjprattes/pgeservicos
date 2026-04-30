<?php

include('../../../inc/includes.php');

Session::checkLoginUser();

global $CFG_GLPI;

require_once(__DIR__ . '/../inc/theme.php');
require_once(__DIR__ . '/../inc/tickets_catalog.php');
require_once(__DIR__ . '/../inc/ticket_view.php');

$tickets_id = isset($_GET['tickets_id']) ? (int)$_GET['tickets_id'] : 0;
$ticket = pgeservicos_ticket_view_get_ticket($tickets_id);

if ($ticket === null) {
    Html::displayNotFoundError();
    exit;
}

if ($ticket === false) {
    Html::displayRightError();
    exit;
}

$fields = $ticket->fields;
$ticket_name = trim((string)($fields['name'] ?? ''));
$ticket_title = $ticket_name !== '' ? $ticket_name : ('Chamado #' . $tickets_id);
$status_label = Ticket::getStatus((int)($fields['status'] ?? 0));
$status_class = pgeservicos_ticket_view_status_class($fields['status'] ?? 0);
$priority_label = Ticket::getPriorityName((int)($fields['priority'] ?? 0));
$type_label = pgeservicos_ticket_view_type_label($fields['type'] ?? 0);
$entity_names = pgeservicos_get_entity_display_names([(int)($fields['entities_id'] ?? 0)]);
$area_name = pgeservicos_get_ticket_area_name((int)($fields['entities_id'] ?? 0), $entity_names);
$entity_name = $area_name;
$was_updated = pgeservicos_is_ticket_updated($fields['date_mod'] ?? '', null, $tickets_id);
$timeline = pgeservicos_ticket_view_timeline($ticket);
$details = pgeservicos_ticket_view_detail_rows($ticket);
$service_levels = pgeservicos_ticket_view_service_level_rows($ticket);
$actors = pgeservicos_ticket_view_actor_groups($ticket);
$vip_info = pgeservicos_ticket_view_vip_info($ticket);
$vip_label = pgeservicos_ticket_view_vip_label($vip_info);
$native_url = ($CFG_GLPI['root_doc'] ?? '')
    . '/plugins/pgeservicos/front/abrir_chamado.php?tickets_id='
    . $tickets_id;
$direct_actions = pgeservicos_ticket_view_action_definitions($ticket);
$can_update_ticket = $ticket->canUpdateItem();
$can_manage_actors = pgeservicos_ticket_view_can_manage_actors($ticket);

if ((int)$fields['status'] === Ticket::CLOSED && $can_update_ticket) {
    $direct_actions['reopen'] = [
        'label' => 'Reabrir chamado',
        'description' => 'Registrar justificativa e reabrir este chamado.',
        'icon' => 'ti ti-refresh'
    ];
}

$can_cancel_ticket = $can_update_ticket
    && (int)$fields['status'] !== Ticket::CLOSED
    && (
        Ticket::isAllowedStatus((int)$fields['status'], Ticket::CLOSED)
        || Ticket::isAllowedStatus((int)$fields['status'], Ticket::SOLVED)
    );

if ($can_cancel_ticket) {
    $direct_actions['cancel'] = [
        'label' => 'Cancelar chamado',
        'description' => 'Registrar justificativa e encerrar este chamado.',
        'icon' => 'ti ti-circle-x'
    ];
}

pgeservicos_mark_ticket_seen($tickets_id, $fields['date_mod'] ?? date('Y-m-d H:i:s'));

Html::header(
    'PGE Serviços - Chamado #' . $tickets_id,
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginPgeservicosPortal'
);

$asset_version = defined('PLUGIN_PGESERVICOS_VERSION')
    ? PLUGIN_PGESERVICOS_VERSION
    : '1';
$root_doc = $CFG_GLPI['root_doc'] ?? '';
$home_url = $root_doc . '/plugins/pgeservicos/front/index.php';
$tickets_url = $root_doc . '/plugins/pgeservicos/front/meus_chamados.php';
$action_url = $root_doc . '/plugins/pgeservicos/front/chamado_action.php';
$actor_search_url = $root_doc . '/plugins/pgeservicos/front/ajax_actor_search.php';
$actor_update_url = $root_doc . '/plugins/pgeservicos/front/ajax_actor_update.php';
$csrf_token = Session::getNewCSRFToken();
$vip_text_class = pgeservicos_ticket_view_color_text_class($vip_info['color'] ?? '');
$vip_badge_style = !empty($vip_info['color'])
    ? " style='--pgeservicos-vip-color: " . pgeservicos_ticket_view_h($vip_info['color']) . ";'"
    : '';
$sticky_vip_badges = '';

if (!empty($vip_info)) {
    foreach (($vip_info['groups'] ?? [$vip_info]) as $vip_group) {
        $group_color = (string)($vip_group['color'] ?? '');
        $group_text_class = pgeservicos_ticket_view_color_text_class($group_color);
        $group_style = $group_color !== ''
            ? " style='--pgeservicos-vip-color: " . pgeservicos_ticket_view_h($group_color) . ";'"
            : '';

        $sticky_vip_badges .= "<span class='pgeservicos-chamado-badge is-vip "
            . pgeservicos_ticket_view_h($group_text_class)
            . "'{$group_style}>"
            . pgeservicos_ticket_view_h($vip_group['name'] ?? 'VIP')
            . "</span>";
    }
}

echo "<link rel='stylesheet' href='"
    . pgeservicos_ticket_view_h($root_doc)
    . "/plugins/pgeservicos/css/shared/pgeservicos.css?v="
    . pgeservicos_ticket_view_h($asset_version)
    . "'>";

echo "<link rel='stylesheet' href='"
    . pgeservicos_ticket_view_h($root_doc)
    . "/plugins/pgeservicos/css/pages/chamado.css?v="
    . pgeservicos_ticket_view_h($asset_version)
    . "'>";

echo "<script defer src='"
    . pgeservicos_ticket_view_h($root_doc)
    . "/plugins/pgeservicos/js/chamado.js?v="
    . pgeservicos_ticket_view_h($asset_version)
    . "'></script>";

pgeservicos_theme_print_vars();

echo "<div class='pgeservicos-container pgeservicos-chamado-page' data-actor-search-url='"
    . pgeservicos_ticket_view_h($actor_search_url)
    . "' data-csrf-token='"
    . pgeservicos_ticket_view_h($csrf_token)
    . "' data-action-url='"
    . pgeservicos_ticket_view_h($action_url)
    . "' data-actor-update-url='"
    . pgeservicos_ticket_view_h($actor_update_url)
    . "' data-tickets-id='"
    . (int)$tickets_id
    . "'>";

echo "
<header class='pgeservicos-chamado-sticky' aria-label='Resumo fixo do chamado'>
    <div class='pgeservicos-chamado-sticky-links'>
        <a href='" . pgeservicos_ticket_view_h($tickets_url) . "'>&larr; Meus chamados</a>
        <a href='" . pgeservicos_ticket_view_h($home_url) . "'>&larr; Portal</a>
    </div>
    <div class='pgeservicos-chamado-sticky-title'>
        <span>#" . (int)$tickets_id . "</span>
        <strong>" . pgeservicos_ticket_view_h($ticket_title) . "</strong>
    </div>
    <div class='pgeservicos-chamado-sticky-badges'>
        <span class='pgeservicos-chamado-badge {$status_class}'>" . pgeservicos_ticket_view_h($status_label) . "</span>
        " . ($was_updated ? "<span class='pgeservicos-chamado-badge is-update'>Atualizado</span>" : '') . "
        {$sticky_vip_badges}
    </div>
</header>

<section class='pgeservicos-hero pgeservicos-chamado-hero'>
    <div class='pgeservicos-chamado-hero-main'>
        <nav class='pgeservicos-chamado-topnav' aria-label='Navegação do chamado'>
            <a class='pgeservicos-back-link' href='" . pgeservicos_ticket_view_h($tickets_url) . "'>
                &larr; Meus chamados
            </a>
            <a class='pgeservicos-back-link pgeservicos-back-link-secondary' href='" . pgeservicos_ticket_view_h($home_url) . "'>
                &larr; Portal de Serviços
            </a>
        </nav>
        <span class='pgeservicos-chamado-kicker'>Chamado #" . (int)$tickets_id . "</span>
        <h1>" . pgeservicos_ticket_view_h($ticket_title) . "</h1>
        <div class='pgeservicos-chamado-badges' aria-label='Resumo do chamado'>
            <span class='pgeservicos-chamado-badge {$status_class}'>" . pgeservicos_ticket_view_h($status_label) . "</span>
            " . ($was_updated
                ? "<span class='pgeservicos-chamado-badge is-update'>Atualizado recentemente</span>"
                : '') . "
        </div>
    </div>
    <dl class='pgeservicos-chamado-hero-meta'>
        <div>
            <dt>Aberto em</dt>
            <dd>" . pgeservicos_ticket_view_h(pgeservicos_ticket_view_date($fields['date'] ?? '')) . "</dd>
        </div>
        <div>
            <dt>Última atualização</dt>
            <dd>" . pgeservicos_ticket_view_h(pgeservicos_ticket_view_date($fields['date_mod'] ?? '')) . "</dd>
        </div>
        <div>
            <dt>Entidade</dt>
            <dd>" . pgeservicos_ticket_view_h($entity_name) . "</dd>
        </div>
    </dl>
</section>
";

if (!empty($vip_info)) {
    $vip_groups = $vip_info['groups'] ?? [$vip_info];

    echo "
    <section class='pgeservicos-chamado-vip'>
        <i class='fas fa-exclamation-triangle' aria-hidden='true'></i>
        <div>
            <strong>Atendimento VIP</strong>
        </div>
        <div class='pgeservicos-chamado-vip-groups'>";

    foreach ($vip_groups as $vip_group) {
        $group_color = (string)($vip_group['color'] ?? '');
        $group_text_class = pgeservicos_ticket_view_color_text_class($group_color);
        $group_style = $group_color !== ''
            ? " style='--pgeservicos-vip-color: " . pgeservicos_ticket_view_h($group_color) . ";'"
            : '';

        echo "<span class='" . pgeservicos_ticket_view_h($group_text_class) . "'{$group_style}>"
            . pgeservicos_ticket_view_h($vip_group['name'] ?? 'VIP')
            . "</span>";
    }

    echo "
        </div>
    </section>
    ";
}

echo "
<div class='pgeservicos-chamado-layout'>
    <main class='pgeservicos-chamado-timeline-card' aria-label='Linha do tempo do chamado'>
        <div class='pgeservicos-chamado-section-title'>
            <div>
                <h2>Linha do tempo</h2>
            </div>
        </div>
        <div class='pgeservicos-chamado-timeline'>";

foreach ($timeline as $item) {
    $item_classes = [
        'pgeservicos-chamado-message',
        'is-' . $item['kind']
    ];

    if (!empty($item['is_current_user'])) {
        $item_classes[] = 'is-mine';
    }

    if (!empty($item['is_recent'])) {
        $item_classes[] = 'is-recent';
    }

    echo "
            <article class='" . pgeservicos_ticket_view_h(implode(' ', $item_classes)) . "'>
                <div class='pgeservicos-chamado-avatar' aria-hidden='true'>"
                    . pgeservicos_ticket_view_h(pgeservicos_ticket_view_initials($item['author']))
                . "</div>
                <div class='pgeservicos-chamado-message-bubble'>
                    <header>
                        <div>
                            <strong>" . pgeservicos_ticket_view_h($item['author']) . "</strong>
                            <span>" . pgeservicos_ticket_view_h($item['label']) . "</span>
                        </div>
                        <div class='pgeservicos-chamado-message-tools'>
                            <time>" . pgeservicos_ticket_view_h(pgeservicos_ticket_view_date($item['date'])) . "</time>";

    if (
        !empty($item['can_edit'])
        && in_array($item['kind'], ['followup', 'task', 'solution'], true)
        && (int)$item['id'] > 0
    ) {
        echo "
                            <button type='button' class='pgeservicos-chamado-edit-icon' data-pgeservicos-edit='" . (int)$item['id'] . "' title='Editar'>
                                <i class='ti ti-pencil' aria-hidden='true'></i>
                            </button>";
    }

    echo "
                        </div>
                    </header>
                    " . (!empty($item['is_recent'])
                        ? "<span class='pgeservicos-chamado-new-badge'>Novo</span>"
                        : '') . "
                    <div class='pgeservicos-chamado-message-content'>"
                        . $item['content']
                    . "</div>";

    if (
        !empty($item['can_edit'])
        && in_array($item['kind'], ['followup', 'task', 'solution'], true)
        && (int)$item['id'] > 0
    ) {
        echo "
                    <form class='pgeservicos-chamado-inline-edit' data-pgeservicos-edit-form='" . (int)$item['id'] . "' method='post' enctype='multipart/form-data' action='" . pgeservicos_ticket_view_h($action_url) . "'>
                            <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_ticket_view_h($csrf_token) . "'>
                            <input type='hidden' name='tickets_id' value='" . (int)$tickets_id . "'>
                            <input type='hidden' name='pgeservicos_action' value='update_timeline'>
                            <input type='hidden' name='timeline_type' value='" . pgeservicos_ticket_view_h($item['kind']) . "'>
                            <input type='hidden' name='timeline_id' value='" . (int)$item['id'] . "'>
                            <input type='hidden' name='content' class='pgeservicos-rich-input' value='" . pgeservicos_ticket_view_h($item['raw_content']) . "'>
                            <div class='pgeservicos-rich-toolbar' aria-label='Formatação'>
                                <button type='button' data-pgeservicos-command='bold'><strong>B</strong></button>
                                <button type='button' data-pgeservicos-command='italic'><em>I</em></button>
                                <button type='button' data-pgeservicos-command='insertUnorderedList'><i class='ti ti-list' aria-hidden='true'></i></button>
                                <button type='button' data-pgeservicos-command='createLink'><i class='ti ti-link' aria-hidden='true'></i></button>
                                <button type='button' data-pgeservicos-command='undo'><i class='ti ti-arrow-back-up' aria-hidden='true'></i></button>
                                <button type='button' data-pgeservicos-command='redo'><i class='ti ti-arrow-forward-up' aria-hidden='true'></i></button>
                            </div>
                            <div class='pgeservicos-rich-editor' contenteditable='true'>"
                                . pgeservicos_ticket_view_clean_html($item['raw_content'])
                            . "</div>
                            <input type='file' name='document'>
                            <div class='pgeservicos-chamado-form-actions'>
                                <button type='button' class='pgeservicos-chamado-secondary' data-pgeservicos-cancel-edit>Cancelar</button>
                                <button type='submit'>Salvar edição</button>
                            </div>
                    </form>";
    }

    if (
        $item['kind'] === 'solution'
        && $ticket->canApprove()
        && in_array((int)$item['solution_status'], [0, CommonITILValidation::WAITING], true)
    ) {
        echo "
                    <div class='pgeservicos-chamado-solution-decision'>
                        <form method='post' action='" . pgeservicos_ticket_view_h($action_url) . "'>
                            <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_ticket_view_h($csrf_token) . "'>
                            <input type='hidden' name='tickets_id' value='" . (int)$tickets_id . "'>
                            <input type='hidden' name='pgeservicos_action' value='solution_approval'>
                            <input type='hidden' name='approval' value='approve'>
                            <input type='hidden' name='content' value=''>
                            <button type='submit'>Aprovar solução</button>
                        </form>
                        <form method='post' action='" . pgeservicos_ticket_view_h($action_url) . "'>
                            <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_ticket_view_h($csrf_token) . "'>
                            <input type='hidden' name='tickets_id' value='" . (int)$tickets_id . "'>
                            <input type='hidden' name='pgeservicos_action' value='solution_approval'>
                            <input type='hidden' name='approval' value='reject'>
                            <input type='text' name='content' placeholder='Motivo da reprovação' required>
                            <button type='submit'>Reprovar</button>
                        </form>
                    </div>";
    }

    echo "
                </div>
            </article>";
}

echo "
        </div>
        <section class='pgeservicos-chamado-actions pgeservicos-chamado-chat-actions' aria-label='Ações do chamado'>
            <div class='pgeservicos-chamado-section-title'>
                <div>
                    <h2>Ações</h2>
                    <p>Escolha uma ação para responder, anexar arquivos ou atualizar o andamento.</p>
                </div>
                <a class='pgeservicos-chamado-native-link' href='" . pgeservicos_ticket_view_h($native_url) . "'>
                    Abrir no GLPI
                </a>
            </div>
            <div class='pgeservicos-chamado-action-tabs'>";

if (empty($direct_actions)) {
    // No direct actions available; keep only the native GLPI fallback link.
} else {
    foreach ($direct_actions as $action_key => $action) {
        $post_action = [
            'answer' => 'add_followup',
            'task' => 'add_task',
            'solution' => 'add_solution',
            'document' => 'add_document',
            'validation' => 'add_validation',
            'reopen' => 'reopen_ticket',
            'cancel' => 'cancel_ticket'
        ][$action_key] ?? '';

        echo "
                <button type='button' class='pgeservicos-chamado-action-tab' data-pgeservicos-action-tab='" . pgeservicos_ticket_view_h($action_key) . "'>
                        <i class='" . pgeservicos_ticket_view_h($action['icon']) . "' aria-hidden='true'></i>
                        <span>" . pgeservicos_ticket_view_h($action['label']) . "</span>
                </button>";
    }

    echo "
            </div>
            <div class='pgeservicos-chamado-action-panels'>";

    foreach ($direct_actions as $action_key => $action) {
        $post_action = [
            'answer' => 'add_followup',
            'task' => 'add_task',
            'solution' => 'add_solution',
            'document' => 'add_document',
            'validation' => 'add_validation',
            'reopen' => 'reopen_ticket',
            'cancel' => 'cancel_ticket'
        ][$action_key] ?? '';

        echo "
                <section class='pgeservicos-chamado-action-panel' data-pgeservicos-action-panel='" . pgeservicos_ticket_view_h($action_key) . "' hidden>
                    <header>
                        <strong>" . pgeservicos_ticket_view_h($action['label']) . "</strong>
                        <button type='button' data-pgeservicos-close-panel aria-label='Fechar'>×</button>
                    </header>";

        if (in_array($action_key, ['reopen', 'cancel'], true)) {
            $textarea_label = $action_key === 'reopen'
                ? 'Justificativa da reabertura'
                : 'Justificativa do cancelamento';
            $submit_label = $action_key === 'reopen'
                ? 'Reabrir chamado'
                : 'Cancelar chamado';

            echo "
                    <form method='post' action='" . pgeservicos_ticket_view_h($action_url) . "'>
                        <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_ticket_view_h($csrf_token) . "'>
                        <input type='hidden' name='tickets_id' value='" . (int)$tickets_id . "'>
                        <input type='hidden' name='pgeservicos_action' value='" . pgeservicos_ticket_view_h($post_action) . "'>
                        <label>
                            " . pgeservicos_ticket_view_h($textarea_label) . "
                            <textarea name='content' rows='4' required></textarea>
                        </label>
                        <div class='pgeservicos-chamado-form-actions'>
                            <button type='button' class='pgeservicos-chamado-secondary' data-pgeservicos-close-panel>Cancelar</button>
                            <button type='submit'>" . pgeservicos_ticket_view_h($submit_label) . "</button>
                        </div>
                    </form>";
        } elseif ($action_key === 'document') {
            echo "
                    <form method='post' enctype='multipart/form-data' action='" . pgeservicos_ticket_view_h($action_url) . "'>
                        <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_ticket_view_h($csrf_token) . "'>
                        <input type='hidden' name='tickets_id' value='" . (int)$tickets_id . "'>
                        <input type='hidden' name='pgeservicos_action' value='" . pgeservicos_ticket_view_h($post_action) . "'>
                        <label>
                            Nome do documento
                            <input type='text' name='document_name' placeholder='Opcional'>
                        </label>
                        <label>
                            Arquivo
                            <input type='file' name='document' required>
                        </label>
                        <div class='pgeservicos-chamado-form-actions'>
                            <button type='submit'>Anexar documento</button>
                        </div>
                    </form>";
        } elseif ($action_key === 'validation') {
            echo "
                    <form method='post' enctype='multipart/form-data' action='" . pgeservicos_ticket_view_h($action_url) . "'>
                        <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_ticket_view_h($csrf_token) . "'>
                        <input type='hidden' name='tickets_id' value='" . (int)$tickets_id . "'>
                        <input type='hidden' name='pgeservicos_action' value='" . pgeservicos_ticket_view_h($post_action) . "'>
                        <label>
                            ID do usuário validador
                            <input type='number' min='1' name='users_id_validate' required>
                        </label>
                        <label>
                            Comentário da solicitação
                            <textarea name='comment_submission' rows='4'></textarea>
                        </label>
                        <label>
                            Anexo
                            <input type='file' name='document'>
                        </label>
                        <div class='pgeservicos-chamado-form-actions'>
                            <button type='submit'>Solicitar validação</button>
                        </div>
                    </form>";
        } else {
            $button_label = [
                'answer' => 'Adicionar acompanhamento',
                'task' => 'Criar tarefa',
                'solution' => 'Adicionar solução'
            ][$action_key] ?? 'Enviar';

            echo "
                    <form method='post' enctype='multipart/form-data' action='" . pgeservicos_ticket_view_h($action_url) . "'>
                        <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_ticket_view_h($csrf_token) . "'>
                        <input type='hidden' name='tickets_id' value='" . (int)$tickets_id . "'>
                        <input type='hidden' name='pgeservicos_action' value='" . pgeservicos_ticket_view_h($post_action) . "'>
                        <label>
                            Descrição
                            <input type='hidden' name='content' class='pgeservicos-rich-input'>
                            <div class='pgeservicos-rich-toolbar' aria-label='Formatação'>
                                <button type='button' data-pgeservicos-command='bold'><strong>B</strong></button>
                                <button type='button' data-pgeservicos-command='italic'><em>I</em></button>
                                <button type='button' data-pgeservicos-command='insertUnorderedList'><i class='ti ti-list' aria-hidden='true'></i></button>
                                <button type='button' data-pgeservicos-command='createLink'><i class='ti ti-link' aria-hidden='true'></i></button>
                                <button type='button' data-pgeservicos-command='undo'><i class='ti ti-arrow-back-up' aria-hidden='true'></i></button>
                                <button type='button' data-pgeservicos-command='redo'><i class='ti ti-arrow-forward-up' aria-hidden='true'></i></button>
                            </div>
                            <div class='pgeservicos-rich-editor' contenteditable='true'></div>
                        </label>
                        " . ($action_key !== 'solution'
                            ? "<label class='pgeservicos-chamado-check'><input type='checkbox' name='is_private' value='1'> Privado</label>"
                            : '') . "
                        " . (in_array($action_key, ['answer', 'task'], true)
                            ? "<label class='pgeservicos-chamado-check'><input type='checkbox' name='mark_pending' value='1'> Marcar chamado como pendente</label>"
                            : '') . "
                        " . ($action_key === 'task'
                            ? "<div class='pgeservicos-chamado-form-grid'>
                                <label>Conclusão até<input type='datetime-local' name='end'></label>
                                <label>ID do usuário atribuído<input type='number' min='1' name='users_id_tech'></label>
                                <label>ID do grupo atribuído<input type='number' min='1' name='groups_id_tech'></label>
                            </div>"
                            : '') . "
                        " . ($action_key === 'solution'
                            ? "<a class='pgeservicos-chamado-native-link' href='" . pgeservicos_ticket_view_h($root_doc . '/front/knowbaseitem.php') . "' target='_blank' rel='noopener'>Abrir base de conhecimento</a>"
                            : '') . "
                        <label>
                            Anexo
                            <input type='file' name='document'>
                        </label>
                        <div class='pgeservicos-chamado-form-actions'>
                            <button type='submit'>" . pgeservicos_ticket_view_h($button_label) . "</button>
                        </div>
                    </form>";
        }

        echo "
                </section>";
    }
}

echo "
            </div>
        </section>
    </main>

    <aside class='pgeservicos-chamado-sidebar' aria-label='Detalhes do chamado'>
        <section class='pgeservicos-chamado-panel pgeservicos-chamado-panel-details'>
            <h2>Detalhes</h2>
            <dl class='pgeservicos-chamado-details'>";

foreach ($details as $detail) {
    echo "
                <div>
                    <dt>" . pgeservicos_ticket_view_h($detail['label']) . "</dt>
                    <dd>" . pgeservicos_ticket_view_h($detail['value']) . "</dd>
                </div>";
}

echo "
            </dl>
";

if ($can_update_ticket) {
    echo "
            <details class='pgeservicos-chamado-side-edit'>
                <summary>Editar detalhes</summary>
                <form method='post' action='" . pgeservicos_ticket_view_h($action_url) . "'>
                    <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_ticket_view_h($csrf_token) . "'>
                    <input type='hidden' name='tickets_id' value='" . (int)$tickets_id . "'>
                    <input type='hidden' name='pgeservicos_action' value='update_details'>
                    <label>Usuários e grupos
                        <select name='type'>";

    foreach ([Ticket::INCIDENT_TYPE => __('Incident'), Ticket::DEMAND_TYPE => __('Request')] as $value => $label) {
        $selected = (int)$fields['type'] === (int)$value ? ' selected' : '';
        echo "<option value='" . (int)$value . "'{$selected}>" . pgeservicos_ticket_view_h($label) . "</option>";
    }

    echo "
                        </select>
                    </label>
                    <label>Status
                        <select name='status'>";

    foreach (Ticket::getAllowedStatusArray((int)$fields['status']) as $value => $label) {
        $selected = (int)$fields['status'] === (int)$value ? ' selected' : '';
        echo "<option value='" . (int)$value . "'{$selected}>" . pgeservicos_ticket_view_h($label) . "</option>";
    }

    echo "
                        </select>
                    </label>";

    foreach (
        [
            'impact' => ['label' => 'Impacto', 'method' => 'getImpactName', 'max' => 5],
            'urgency' => ['label' => 'Urgência', 'method' => 'getUrgencyName', 'max' => 5],
            'priority' => ['label' => 'Prioridade', 'method' => 'getPriorityName', 'max' => 6]
        ] as $field_name => $config
    ) {
        echo "<label>" . pgeservicos_ticket_view_h($config['label']) . "<select name='" . pgeservicos_ticket_view_h($field_name) . "'>";

        for ($value = 1; $value <= (int)$config['max']; $value++) {
            $selected = (int)$fields[$field_name] === $value ? ' selected' : '';
            $method = $config['method'];
            $label = Ticket::$method($value);
            echo "<option value='{$value}'{$selected}>" . pgeservicos_ticket_view_h($label) . "</option>";
        }

        echo "</select></label>";
    }

    echo "
                    <div class='pgeservicos-chamado-form-actions'>
                        <button type='submit'>Salvar detalhes</button>
                    </div>
                </form>
            </details>";
}

echo "
        </section>

        <section class='pgeservicos-chamado-panel pgeservicos-chamado-panel-service'>
            <h2>Níveis de serviço</h2>
            <dl class='pgeservicos-chamado-details'>";

foreach ($service_levels as $service_level) {
    echo "
                <div>
                    <dt>" . pgeservicos_ticket_view_h($service_level['label']) . "</dt>
                    <dd>" . pgeservicos_ticket_view_h(pgeservicos_ticket_view_date($service_level['value'])) . "</dd>
                </div>";
}

echo "
            </dl>";

if ($can_update_ticket) {
    echo "
            <details class='pgeservicos-chamado-side-edit'>
                <summary>Editar níveis de serviço</summary>
                <form method='post' action='" . pgeservicos_ticket_view_h($action_url) . "'>
                    <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_ticket_view_h($csrf_token) . "'>
                    <input type='hidden' name='tickets_id' value='" . (int)$tickets_id . "'>
                    <input type='hidden' name='pgeservicos_action' value='update_details'>";

    foreach ($service_levels as $service_level) {
        $date_value = '';

        if (!empty($service_level['value'])) {
            $timestamp = strtotime((string)$service_level['value']);
            $date_value = $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
        }

        echo "
                    <label>" . pgeservicos_ticket_view_h($service_level['label']) . "
                        <input type='datetime-local' name='" . pgeservicos_ticket_view_h($service_level['field']) . "' value='" . pgeservicos_ticket_view_h($date_value) . "'>
                    </label>";
    }

    echo "
                    <div class='pgeservicos-chamado-form-actions'>
                        <button type='submit'>Salvar níveis</button>
                    </div>
                </form>
            </details>";
}

echo "
        </section>

        <section class='pgeservicos-chamado-panel pgeservicos-chamado-panel-actors'>
            <h2>Atores</h2>
            <div class='pgeservicos-chamado-actors'>";

$actor_roles = ['requester', 'observer', 'assign'];
$current_user_value = 'user:' . (int)Session::getLoginUserID();

foreach ($actors as $actor_index => $group) {
    $actor_role = $actor_roles[$actor_index] ?? '';

    echo "
                <div class='pgeservicos-chamado-actor-group'>
                    <h3>" . pgeservicos_ticket_view_h($group['label']) . "</h3>
                    <div class='pgeservicos-actor-field-row'>
                        <div class='pgeservicos-actor-picker pgeservicos-actor-picker-inline' data-pgeservicos-actor-picker data-actor-role='" . pgeservicos_ticket_view_h($actor_role) . "'>
                            <div class='pgeservicos-actor-chip-scroll' data-pgeservicos-actor-chip-scroll>";

    if (empty($group['items'])) {
        echo "<span class='pgeservicos-actor-empty'>Nenhum registro.</span>";
    } else {
        foreach ($group['items'] as $actor) {
            $kind_label = [
                'user' => 'Usuário',
                'group' => 'Grupo',
                'supplier' => 'Fornecedor'
            ][$actor['kind']] ?? 'Ator';
            $vip = $actor['vip'] ?? null;
            $vip_label = pgeservicos_ticket_view_vip_label($vip);
            $vip_color = (string)($vip['color'] ?? '');
            $vip_text_class = pgeservicos_ticket_view_color_text_class($vip_color);
            $vip_style = $actor['kind'] === 'user' && $vip_color !== ''
                ? " style='--pgeservicos-vip-color: " . pgeservicos_ticket_view_h($vip_color) . ";'"
                : '';
            $actor_classes = [
                'pgeservicos-actor-tag',
                'pgeservicos-actor-tag-existing',
                'is-' . preg_replace('/[^a-z]/', '', (string)$actor['kind'])
            ];

            if ($actor['kind'] === 'user' && $vip_label !== '') {
                $actor_classes[] = 'is-vip';
                $actor_classes[] = $vip_text_class;
            }

            echo "
                            <span class='" . pgeservicos_ticket_view_h(implode(' ', $actor_classes)) . "' data-link-id='" . (int)($actor['id'] ?? 0) . "' data-tooltip='" . pgeservicos_ticket_view_h($actor['tooltip'] ?? '') . "'{$vip_style}>
                                <span class='pgeservicos-chamado-actor-kind'>"
                                    . pgeservicos_ticket_view_h($kind_label)
                                . "</span>
                                <span class='pgeservicos-actor-name'>"
                                    . pgeservicos_ticket_view_h($actor['label'])
                                . "</span>";

            if (
                $can_manage_actors
                && in_array($actor['kind'], ['user', 'group'], true)
                && (int)($actor['id'] ?? 0) > 0
            ) {
                echo "
                                <button type='button'
                                    data-pgeservicos-remove-actor
                                    data-actor-kind='" . pgeservicos_ticket_view_h($actor['kind']) . "'
                                    data-link-id='" . (int)$actor['id'] . "'
                                    aria-label='Remover " . pgeservicos_ticket_view_h($actor['label']) . "'>×</button>";
            }

            echo "
                            </span>";
        }
    }

    if ($can_manage_actors && $actor_role !== '') {
        echo "
                            </div>                    
                            <input type='search' data-pgeservicos-actor-search placeholder='Buscar usuário ou grupo'>
                            <span class='pgeservicos-actor-results' data-pgeservicos-actor-results hidden></span>";
    } else {
        echo "
                            </div>";
    }

    echo "
                        </div>";

    if ($can_manage_actors && $actor_role !== '') {
        echo "
                        <button type='button'
                            class='pgeservicos-actor-me'
                            data-pgeservicos-actor-me
                            data-actor-role='" . pgeservicos_ticket_view_h($actor_role) . "'
                            data-actor-value='" . pgeservicos_ticket_view_h($current_user_value) . "'
                            title='Associar a mim mesmo'
                            aria-label='Associar a mim mesmo'>
                            <i class='ti ti-user-plus' aria-hidden='true'></i>
                        </button>";
    }

    echo "
                    </div>
                </div>";
}

echo "
            </div>
        </section>
    </aside>
</div>
";

echo "</div>";

Html::footer();
