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
$native_url = ($CFG_GLPI['root_doc'] ?? '')
    . '/plugins/pgeservicos/front/abrir_chamado.php?tickets_id='
    . $tickets_id;
$is_closed = (int)($fields['status'] ?? 0) === Ticket::CLOSED;
$can_update_ticket = $ticket->canUpdateItem();
$can_update_open_ticket = $can_update_ticket && !$is_closed;
$direct_actions = $is_closed ? [] : pgeservicos_ticket_view_action_definitions($ticket);
$can_manage_actors = pgeservicos_ticket_view_can_manage_actors($ticket) && !$is_closed;

if ($is_closed && $can_update_ticket) {
    $direct_actions['reopen'] = [
        'label' => 'Reabrir chamado',
        'description' => 'Registrar justificativa e reabrir este chamado.',
        'icon' => 'ti ti-refresh'
    ];
}

$can_trash_ticket = $ticket->can($tickets_id, DELETE);

if ($can_trash_ticket) {
    $direct_actions['trash'] = [
        'label' => 'Mandar para lixeira',
        'description' => 'Mover este chamado para a lixeira, sem excluir definitivamente.',
        'icon' => 'ti ti-trash'
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
$actor_search_url = $root_doc . '/plugins/pgeservicos/front/ajax_actor_search.php?v=' . (int)@filemtime(__DIR__ . '/ajax_actor_search.php');
$actor_update_url = $root_doc . '/plugins/pgeservicos/front/ajax_actor_update.php';
$field_search_url = $root_doc . '/plugins/pgeservicos/front/ajax_ticket_field_search.php';
$field_update_url = $root_doc . '/plugins/pgeservicos/front/ajax_ticket_field_update.php';
$sla_search_url = $root_doc . '/plugins/pgeservicos/front/ajax_sla_search.php';
$sla_update_url = $root_doc . '/plugins/pgeservicos/front/ajax_sla_update.php';
$action_target_search_url = $root_doc . '/plugins/pgeservicos/front/ajax_action_target_search.php';
$csrf_token = Session::getNewCSRFToken();
$sticky_vip_badges = '';
$detail_edit_fields = [
    'itilcategories_id' => [
        'label' => 'Categoria',
        'type' => 'relation',
        'table' => 'glpi_itilcategories',
        'value' => (int)($fields['itilcategories_id'] ?? 0)
    ],
    'requesttypes_id' => [
        'label' => 'Origem da requisição',
        'type' => 'relation',
        'table' => 'glpi_requesttypes',
        'value' => (int)($fields['requesttypes_id'] ?? 0)
    ],
    'type' => [
        'label' => 'Tipo',
        'type' => 'select',
        'value' => (int)($fields['type'] ?? 0),
        'options' => [
            Ticket::INCIDENT_TYPE => __('Incident'),
            Ticket::DEMAND_TYPE => __('Request')
        ]
    ],
    'status' => [
        'label' => 'Status',
        'type' => 'select',
        'value' => (int)($fields['status'] ?? 0),
        'options' => Ticket::getAllowedStatusArray((int)($fields['status'] ?? 0))
    ],
    'impact' => [
        'label' => 'Impacto',
        'type' => 'select',
        'value' => (int)($fields['impact'] ?? 0),
        'options' => array_combine(range(1, 5), array_map([Ticket::class, 'getImpactName'], range(1, 5)))
    ],
    'urgency' => [
        'label' => 'Urgência',
        'type' => 'select',
        'value' => (int)($fields['urgency'] ?? 0),
        'options' => array_combine(range(1, 5), array_map([Ticket::class, 'getUrgencyName'], range(1, 5)))
    ],
    'priority' => [
        'label' => 'Prioridade',
        'type' => 'select',
        'value' => (int)($fields['priority'] ?? 0),
        'options' => array_combine(range(1, 6), array_map([Ticket::class, 'getPriorityName'], range(1, 6)))
    ],
    'locations_id' => [
        'label' => 'Localização',
        'type' => 'relation',
        'table' => 'glpi_locations',
        'value' => (int)($fields['locations_id'] ?? 0)
    ]
];

if (!empty($vip_info)) {
    foreach (($vip_info['groups'] ?? [$vip_info]) as $vip_group) {
        $group_color = (string)($vip_group['color'] ?? '');
        $group_text_class = pgeservicos_ticket_view_color_text_class($group_color);
        $group_style = $group_color !== ''
            ? " style='--pgeservicos-vip-color: " . pgeservicos_ticket_view_h($group_color) . ";'"
            : '';

        $sticky_vip_badges .= "<span class='pgeservicos-chamado-badge is-vip "
            . pgeservicos_ticket_view_h($group_text_class)
            . "'{$group_style}>VIP: "
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
pgeservicos_theme_print_sidebar_sync_script($CFG_GLPI['root_doc'] ?? '', $asset_version);

echo "<div class='pgeservicos-container pgeservicos-chamado-page' data-actor-search-url='"
    . pgeservicos_ticket_view_h($actor_search_url)
    . "' data-csrf-token='"
    . pgeservicos_ticket_view_h($csrf_token)
    . "' data-action-url='"
    . pgeservicos_ticket_view_h($action_url)
    . "' data-actor-update-url='"
    . pgeservicos_ticket_view_h($actor_update_url)
    . "' data-field-search-url='"
    . pgeservicos_ticket_view_h($field_search_url)
    . "' data-field-update-url='"
    . pgeservicos_ticket_view_h($field_update_url)
    . "' data-sla-search-url='"
    . pgeservicos_ticket_view_h($sla_search_url)
    . "' data-sla-update-url='"
    . pgeservicos_ticket_view_h($sla_update_url)
    . "' data-action-target-search-url='"
    . pgeservicos_ticket_view_h($action_target_search_url)
    . "' data-tickets-id='"
    . (int)$tickets_id
    . "' data-ticket-closed='"
    . ($is_closed ? '1' : '0')
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
            <dt>Solucionado em</dt>
            <dd>" . pgeservicos_ticket_view_h(pgeservicos_ticket_view_date($fields['solvedate'] ?? '')) . "</dd>
        </div>
        <div>
            <dt>Fechado em</dt>
            <dd>" . pgeservicos_ticket_view_h(pgeservicos_ticket_view_date($fields['closedate'] ?? '')) . "</dd>
        </div>
    </dl>
</section>
";

if (!empty($vip_info)) {
    $vip_groups = $vip_info['groups'] ?? [$vip_info];
    $vip_group_count = count($vip_groups);
    $vip_bar_classes = ['pgeservicos-chamado-vip'];
    $vip_bar_style = '';

    if ($vip_group_count === 1) {
        $single_vip_color = (string)($vip_groups[0]['color'] ?? '');
        $vip_bar_classes[] = 'is-single-vip';
        $vip_bar_classes[] = pgeservicos_ticket_view_color_text_class($single_vip_color);
        $vip_bar_style = $single_vip_color !== ''
            ? " style='--pgeservicos-vip-color: " . pgeservicos_ticket_view_h($single_vip_color) . ";'"
            : '';
    }

    echo "
    <section class='" . pgeservicos_ticket_view_h(implode(' ', $vip_bar_classes)) . "'{$vip_bar_style}>
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

    if ($item['kind'] === 'solution') {
        if ((int)($item['solution_status'] ?? 0) === CommonITILValidation::ACCEPTED) {
            $item_classes[] = 'is-solution-approved';
        } elseif ((int)($item['solution_status'] ?? 0) === CommonITILValidation::REFUSED) {
            $item_classes[] = 'is-solution-refused';
        }
    }

    $timeline_can_edit = !empty($item['can_edit'])
        && in_array($item['kind'], ['opening', 'followup', 'task', 'solution'], true)
        && (int)$item['id'] > 0;
    $timeline_can_delete = !empty($item['can_delete'])
        && !empty($item['delete_itemtype'])
        && (int)($item['delete_id'] ?? 0) > 0;
    $delete_itemtype = (string)($item['delete_itemtype'] ?? '');
    $delete_id = (int)($item['delete_id'] ?? 0);
    $delete_attrs = $timeline_can_delete
        ? " data-pgeservicos-delete-itemtype='" . pgeservicos_ticket_view_h($delete_itemtype) . "' data-pgeservicos-delete-id='" . $delete_id . "'"
        : '';

    echo "
            <article class='" . pgeservicos_ticket_view_h(implode(' ', $item_classes)) . "' data-pgeservicos-timeline-item" . $delete_attrs . ">
                <div class='pgeservicos-chamado-avatar' aria-hidden='true'>";

    if (!empty($item['avatar_url'])) {
        echo "<img src='" . pgeservicos_ticket_view_h($item['avatar_url']) . "' alt=''>";
    } else {
        echo pgeservicos_ticket_view_h(pgeservicos_ticket_view_initials($item['author']));
    }

    echo "</div>
                <div class='pgeservicos-chamado-message-bubble'>
                    <header>
                        <div>
                            <strong>" . pgeservicos_ticket_view_h($item['author']) . "</strong>
                            <span>" . pgeservicos_ticket_view_h($item['label']) . "</span>
                        </div>
                        <div class='pgeservicos-chamado-message-tools'>
                            <time>" . pgeservicos_ticket_view_h(pgeservicos_ticket_view_date($item['date'])) . "</time>";

    if ($timeline_can_edit) {
        echo "
                            <button type='button' class='pgeservicos-chamado-edit-icon' data-pgeservicos-edit='" . pgeservicos_ticket_view_h($item['kind'] . '-' . (int)$item['id']) . "' title='Editar'>
                                <i class='ti ti-pencil' aria-hidden='true'></i>
                            </button>";
    }

    if ($timeline_can_delete && !$timeline_can_edit) {
        echo "
                            <button type='button' class='pgeservicos-chamado-delete-icon' data-pgeservicos-delete-trigger title='Excluir' aria-label='Excluir'>
                                <i class='ti ti-trash' aria-hidden='true'></i>
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
                    . "</div>"
                    . pgeservicos_ticket_view_render_attachments($item['attachments'] ?? []);

    if ($timeline_can_edit) {
        echo "
                    <form class='pgeservicos-chamado-inline-edit' data-pgeservicos-edit-form='" . pgeservicos_ticket_view_h($item['kind'] . '-' . (int)$item['id']) . "' method='post' enctype='multipart/form-data' action='" . pgeservicos_ticket_view_h($action_url) . "'>
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
                                <button type='button' class='pgeservicos-chamado-secondary' data-pgeservicos-cancel-edit>Cancelar</button>"
                                . ($timeline_can_delete ? "<button type='button' class='pgeservicos-chamado-danger' data-pgeservicos-delete-trigger>Excluir</button>" : '') .
                                "<button type='submit'>Salvar edição</button>
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
                        <form method='post' enctype='multipart/form-data' action='" . pgeservicos_ticket_view_h($action_url) . "'>
                            <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_ticket_view_h($csrf_token) . "'>
                            <input type='hidden' name='tickets_id' value='" . (int)$tickets_id . "'>
                            <input type='hidden' name='pgeservicos_action' value='solution_approval'>
                            <label>
                                Comentário
                                <textarea name='content' rows='3' placeholder='Informe um comentário, se necessário'></textarea>
                            </label>
                            <label>
                                Anexo
                                <input type='file' name='document'>
                            </label>
                            <div class='pgeservicos-chamado-solution-decision-actions'>
                                <button type='submit' name='approval' value='approve'>Aprovar solução</button>
                                <button type='submit' name='approval' value='reject' class='is-reject'>Reprovar</button>
                            </div>
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
            'trash' => 'trash_ticket'
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
            'trash' => 'trash_ticket'
        ][$action_key] ?? '';

        echo "
                <section class='pgeservicos-chamado-action-panel' data-pgeservicos-action-panel='" . pgeservicos_ticket_view_h($action_key) . "' hidden>
                    <header>
                        <strong>" . pgeservicos_ticket_view_h($action['label']) . "</strong>
                        <button type='button' data-pgeservicos-close-panel aria-label='Fechar'>×</button>
                    </header>";

        if ($action_key === 'reopen') {
            $textarea_label = 'Justificativa da reabertura';
            $submit_label = 'Reabrir chamado';

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
        } elseif ($action_key === 'trash') {
            echo "
                    <form method='post' action='" . pgeservicos_ticket_view_h($action_url) . "'>
                        <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_ticket_view_h($csrf_token) . "'>
                        <input type='hidden' name='tickets_id' value='" . (int)$tickets_id . "'>
                        <input type='hidden' name='pgeservicos_action' value='" . pgeservicos_ticket_view_h($post_action) . "'>
                        <p class='pgeservicos-chamado-action-note'>O chamado será movido para a lixeira e ficará armazenado até exclusão definitiva.</p>
                        <div class='pgeservicos-chamado-form-actions'>
                            <button type='button' class='pgeservicos-chamado-secondary' data-pgeservicos-close-panel>Cancelar</button>
                            <button type='submit'>Mandar para lixeira</button>
                        </div>
                    </form>";
        } elseif ($action_key === 'document') {
            $document_create_url = $root_doc . '/front/document.form.php';
            $document_max_upload = class_exists('Document') ? Document::getMaxUploadSize() : '';

            echo "
                    <form method='post' enctype='multipart/form-data' action='" . pgeservicos_ticket_view_h($action_url) . "'>
                        <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_ticket_view_h($csrf_token) . "'>
                        <input type='hidden' name='tickets_id' value='" . (int)$tickets_id . "'>
                        <input type='hidden' name='pgeservicos_action' value='" . pgeservicos_ticket_view_h($post_action) . "'>
                        <label>
                            Título
                            <span class='pgeservicos-document-title-row'>
                                <span class='pgeservicos-action-lookup' data-pgeservicos-action-lookup data-target-type='document'>
                                    <input type='hidden' name='documents_id' data-pgeservicos-action-lookup-value>
                                    <span class='pgeservicos-action-lookup-selection' data-pgeservicos-action-lookup-selection hidden></span>
                                    <input type='search' data-pgeservicos-action-lookup-search placeholder='Buscar documento' autocomplete='off'>
                                    <span class='pgeservicos-action-lookup-results' data-pgeservicos-action-lookup-results hidden></span>
                                </span>"
                                . (Document::canCreate()
                                    ? "<a class='pgeservicos-document-create-link' href='" . pgeservicos_ticket_view_h($document_create_url) . "' target='_blank' rel='noopener noreferrer' title='Adicionar novo documento no GLPI' aria-label='Adicionar novo documento no GLPI'>+</a>"
                                    : '') .
                            "</span>
                        </label>
                        <label>
                            Arquivo
                            <input type='file' name='document'>"
                            . ($document_max_upload !== '' ? "<span class='pgeservicos-chamado-form-help'>" . pgeservicos_ticket_view_h($document_max_upload) . " máx</span>" : '') .
                        "</label>
                        <p class='pgeservicos-chamado-action-note'>Selecione um documento existente ou envie um novo arquivo.</p>
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
                            Usuário validador
                            <span class='pgeservicos-action-lookup' data-pgeservicos-action-lookup data-target-type='user'>
                                <input type='hidden' name='users_id_validate' data-pgeservicos-action-lookup-value>
                                <span class='pgeservicos-action-lookup-selection' data-pgeservicos-action-lookup-selection hidden></span>
                                <input type='search' data-pgeservicos-action-lookup-search placeholder='Buscar usuário validador' autocomplete='off'>
                                <span class='pgeservicos-action-lookup-results' data-pgeservicos-action-lookup-results hidden></span>
                            </span>
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
                                <label>Duração<select name='actiontime'>
                                    <option value=''>Sem duração</option>
                                    <option value='300'>5 minutos</option>
                                    <option value='600'>10 minutos</option>
                                    <option value='900'>15 minutos</option>
                                    <option value='1800'>30 minutos</option>
                                    <option value='2700'>45 minutos</option>
                                    <option value='3600'>1 hora</option>
                                    <option value='5400'>1 hora e 30 minutos</option>
                                    <option value='7200'>2 horas</option>
                                    <option value='10800'>3 horas</option>
                                    <option value='14400'>4 horas</option>
                                    <option value='28800'>8 horas</option>
                                </select></label>
                                <label>Usuário atribuído<span class='pgeservicos-action-lookup' data-pgeservicos-action-lookup data-target-type='user'>
                                    <input type='hidden' name='users_id_tech' data-pgeservicos-action-lookup-value>
                                    <span class='pgeservicos-action-lookup-selection' data-pgeservicos-action-lookup-selection hidden></span>
                                    <input type='search' data-pgeservicos-action-lookup-search placeholder='Buscar usuário' autocomplete='off'>
                                    <span class='pgeservicos-action-lookup-results' data-pgeservicos-action-lookup-results hidden></span>
                                </span></label>
                                <label>Grupo atribuído<span class='pgeservicos-action-lookup' data-pgeservicos-action-lookup data-target-type='group'>
                                    <input type='hidden' name='groups_id_tech' data-pgeservicos-action-lookup-value>
                                    <span class='pgeservicos-action-lookup-selection' data-pgeservicos-action-lookup-selection hidden></span>
                                    <input type='search' data-pgeservicos-action-lookup-search placeholder='Buscar grupo' autocomplete='off'>
                                    <span class='pgeservicos-action-lookup-results' data-pgeservicos-action-lookup-results hidden></span>
                                </span></label>
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
        <section class='pgeservicos-chamado-panel pgeservicos-chamado-side-tabs' data-pgeservicos-side-tabs>
            <div class='pgeservicos-chamado-side-tablist' role='tablist' aria-label='Informações do chamado'>
                <button type='button' class='is-active' role='tab' aria-selected='true' data-pgeservicos-side-tab='actors'>Atores</button>
                <button type='button' role='tab' aria-selected='false' data-pgeservicos-side-tab='details'>Detalhes</button>
                <button type='button' role='tab' aria-selected='false' data-pgeservicos-side-tab='service'>Prazos</button>
            </div>
            <div class='pgeservicos-chamado-side-panel' data-pgeservicos-side-panel='details' hidden>
        <form class='pgeservicos-chamado-panel-details pgeservicos-side-inline-form' method='post' action='" . pgeservicos_ticket_view_h($action_url) . "'>
            <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_ticket_view_h($csrf_token) . "'>
            <input type='hidden' name='tickets_id' value='" . (int)$tickets_id . "'>
            <input type='hidden' name='pgeservicos_action' value='update_details'>
            <div class='pgeservicos-side-panel-header'>
                <h2>Detalhes</h2>
                " . ($can_update_open_ticket ? "<span class='pgeservicos-side-edit-hint'>Clique em um campo para alterar</span>" : '') . "
            </div>
            <dl class='pgeservicos-chamado-details'>
                <div class='pgeservicos-side-field pgeservicos-field-card pgeservicos-field-card--readonly is-readonly'>
                    <dt>Entidade</dt>
                    <dd>" . pgeservicos_ticket_view_h($entity_name) . "</dd>
                </div>";

foreach ($detail_edit_fields as $field_name => $field_config) {
    $read_value = '-';

    if ($field_config['type'] === 'select') {
        $read_value = (string)($field_config['options'][$field_config['value']] ?? '-');
    } elseif ($field_name === 'itilcategories_id') {
        $read_value = pgeservicos_ticket_view_dropdown_name('glpi_itilcategories', $field_config['value']);
    } elseif ($field_name === 'requesttypes_id') {
        $read_value = pgeservicos_ticket_view_dropdown_name('glpi_requesttypes', $field_config['value']);
    } elseif ($field_name === 'locations_id') {
        $read_value = pgeservicos_ticket_view_dropdown_name('glpi_locations', $field_config['value']);
    }

    echo "
                <div class='pgeservicos-side-field pgeservicos-field-card" . ($can_update_open_ticket ? " pgeservicos-field-card--editable is-editable" : " pgeservicos-field-card--readonly") . "' data-pgeservicos-field-row data-field='" . pgeservicos_ticket_view_h($field_name) . "' data-field-type='" . pgeservicos_ticket_view_h($field_config['type']) . "'>
                    <dt>" . pgeservicos_ticket_view_h($field_config['label']) . ($can_update_open_ticket ? "<span class='pgeservicos-field-card__edit-icon' aria-hidden='true'><i class='ti ti-pencil'></i></span>" : "") . "</dt>
                    <dd>
                        <button type='button' class='pgeservicos-side-read-value" . ($can_update_open_ticket ? "" : " is-readonly") . "' data-pgeservicos-field-open>" . pgeservicos_ticket_view_h($read_value) . "</button>
                        <span class='pgeservicos-side-edit-value'>";

    if ($field_config['type'] === 'select') {
        echo "<select name='" . pgeservicos_ticket_view_h($field_name) . "' data-pgeservicos-field-control data-current-label='" . pgeservicos_ticket_view_h($read_value) . "'>";

        foreach ($field_config['options'] as $value => $label) {
            $selected = (int)$field_config['value'] === (int)$value ? ' selected' : '';
            echo "<option value='" . (int)$value . "'{$selected}>" . pgeservicos_ticket_view_h($label) . "</option>";
        }

        echo "</select>";
    } else {
        echo "
                            <input type='hidden' name='" . pgeservicos_ticket_view_h($field_name) . "' value='" . (int)$field_config['value'] . "' data-pgeservicos-field-value>
                            <input type='search' value='' placeholder='Buscar " . pgeservicos_ticket_view_h(mb_strtolower($field_config['label'], 'UTF-8')) . "' data-pgeservicos-field-search data-current-label='" . pgeservicos_ticket_view_h($read_value) . "'>
                            <span class='pgeservicos-field-results' data-pgeservicos-field-results hidden></span>";
    }

    echo "
                        </span>
                    </dd>
                </div>";
}

echo "
            </dl>
            <div class='pgeservicos-field-feedback' data-pgeservicos-field-feedback hidden></div>
        </form>
            </div>
            <div class='pgeservicos-chamado-side-panel' data-pgeservicos-side-panel='service' hidden>
        <form class='pgeservicos-chamado-panel-service pgeservicos-side-inline-form' method='post' action='" . pgeservicos_ticket_view_h($action_url) . "'>
            <input type='hidden' name='_glpi_csrf_token' value='" . pgeservicos_ticket_view_h($csrf_token) . "'>
            <input type='hidden' name='tickets_id' value='" . (int)$tickets_id . "'>
            <input type='hidden' name='pgeservicos_action' value='update_details'>
            <div class='pgeservicos-side-panel-header'>
                <h2>Prazos</h2>
                " . ($can_update_open_ticket ? "<span class='pgeservicos-side-edit-hint'>Clique em um prazo para alterar</span>" : '') . "
            </div>
            <dl class='pgeservicos-chamado-details'>";

foreach ($service_levels as $service_level) {
    $date_value = '';
    $date_label = pgeservicos_ticket_view_date($service_level['value']);
    $agreement_id = (int)($service_level['agreement_id'] ?? 0);
    $agreement_name = (string)($service_level['agreement_name'] ?? '');
    $agreement_label = (string)($service_level['agreement_label'] ?? 'SLA');
    $has_agreement = $agreement_id > 0 && $agreement_name !== '';

    if (!empty($service_level['value'])) {
        $timestamp = strtotime((string)$service_level['value']);
        $date_value = $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
    }

    $agreement_chip = '';

    if ($has_agreement) {
        $agreement_chip = "<span class='pgeservicos-sla-chip' data-pgeservicos-sla-chip title='"
            . pgeservicos_ticket_view_h($agreement_name)
            . "' data-agreement-id='"
            . $agreement_id
            . "'><i class='ti ti-stopwatch'></i><span class='pgeservicos-sla-chip-label'>"
            . pgeservicos_ticket_view_h($agreement_name)
            . "</span>"
            . ($can_update_open_ticket ? "<button type='button' class='pgeservicos-sla-remove' data-pgeservicos-sla-remove aria-label='Remover " . pgeservicos_ticket_view_h($agreement_label) . "'>×</button>" : '')
            . "</span>";
    }

    $has_date = $date_value !== '' && $date_label !== '-';
    $deadline_read_html = pgeservicos_ticket_view_h($date_label);
    $deadline_read_class = '';

    if (!$has_date) {
        $deadline_read_class = ' is-empty';
        $deadline_read_html = "<span class='pgeservicos-deadline-empty-title'><i class='ti ti-calendar-time' aria-hidden='true'></i> Nenhum prazo definido</span>";

        if ($can_update_open_ticket) {
            $deadline_read_html .= "<span class='pgeservicos-deadline-empty-hint'>Clique para definir uma data manual</span>";
        }
    }

    echo "
                <div class='pgeservicos-side-field pgeservicos-field-card pgeservicos-deadline-card" . ($can_update_open_ticket ? " pgeservicos-field-card--editable is-editable" : " pgeservicos-field-card--readonly") . "' data-pgeservicos-field-row data-field='" . pgeservicos_ticket_view_h($service_level['field']) . "' data-field-type='datetime' data-sla-field='" . pgeservicos_ticket_view_h($service_level['field']) . "'>
                    <dt>" . pgeservicos_ticket_view_h($service_level['label']) . ($can_update_open_ticket ? "<span class='pgeservicos-field-card__edit-icon' aria-hidden='true'><i class='ti ti-calendar-time'></i></span>" : "") . "</dt>
                    <dd>
                        <span class='pgeservicos-deadline-date-row'>
                            <button type='button' class='pgeservicos-side-read-value" . $deadline_read_class . ($can_update_open_ticket ? "" : " is-readonly") . "' data-pgeservicos-field-open>" . $deadline_read_html . "</button>
                        </span>
                        <span class='pgeservicos-sla-row' data-pgeservicos-sla-row>
                            <span class='pgeservicos-sla-chip-wrap' data-pgeservicos-sla-chip-wrap>" . $agreement_chip . "</span>
                            " . ($can_update_open_ticket ? "<button type='button' class='pgeservicos-sla-link pgeservicos-sla-assign-btn' data-pgeservicos-sla-open title='Atribuir " . pgeservicos_ticket_view_h($agreement_label) . "'" . ($has_agreement ? " hidden" : "") . ">" . pgeservicos_ticket_view_h($agreement_label) . "</button>" : "") . "
                        </span>
                        " . ($can_update_open_ticket ? "<span class='pgeservicos-sla-picker' data-pgeservicos-sla-picker hidden>
                            <span class='pgeservicos-sla-warning'><i class='ti ti-alert-triangle'></i> A atribuição de uma SLA/OLA recalcula o prazo do chamado e pode ativar escalonamentos definidos na regra selecionada.</span>
                            <input type='search' data-pgeservicos-sla-search placeholder='Buscar " . pgeservicos_ticket_view_h($agreement_label) . "'>
                            <span class='pgeservicos-sla-results' data-pgeservicos-sla-results hidden></span>
                        </span>" : "") . "
                        <span class='pgeservicos-side-edit-value'>
                            <input type='datetime-local' name='" . pgeservicos_ticket_view_h($service_level['field']) . "' value='" . pgeservicos_ticket_view_h($date_value) . "' data-pgeservicos-field-control data-current-label='" . pgeservicos_ticket_view_h($date_label) . "'>
                        </span>
                    </dd>
                </div>";
}

echo "
            </dl>
            <div class='pgeservicos-field-feedback' data-pgeservicos-field-feedback hidden></div>
        </form>
            </div>
            <div class='pgeservicos-chamado-side-panel is-active' data-pgeservicos-side-panel='actors'>
        <div class='pgeservicos-chamado-panel-actors'>
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
                            <span class='" . pgeservicos_ticket_view_h(implode(' ', $actor_classes)) . "' data-link-id='" . (int)($actor['id'] ?? 0) . "' title='" . pgeservicos_ticket_view_h($actor['tooltip'] ?? '') . "'{$vip_style}>
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
        </div>
            </div>
        </section>
    </aside>
</div>
";

echo "</div>";

Html::footer();
