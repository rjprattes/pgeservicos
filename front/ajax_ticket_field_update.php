<?php

if (!defined('GLPI_KEEP_CSRF_TOKEN')) {
    define('GLPI_KEEP_CSRF_TOKEN', true);
}

include('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkCSRF(['_glpi_csrf_token' => $_POST['_glpi_csrf_token'] ?? '']);

require_once(__DIR__ . '/../inc/tickets_catalog.php');
require_once(__DIR__ . '/../inc/ticket_view.php');

header('Content-Type: application/json; charset=UTF-8');

function pgeservicos_ticket_field_update_response($payload) {
    echo json_encode($payload);
    exit;
}

function pgeservicos_ticket_field_update_label($field, $value) {
    $value = (int)$value;

    if ($field === 'type') {
        return pgeservicos_ticket_view_type_label($value);
    }

    if ($field === 'status') {
        return Ticket::getStatus($value);
    }

    if ($field === 'impact') {
        return Ticket::getImpactName($value);
    }

    if ($field === 'urgency') {
        return Ticket::getUrgencyName($value);
    }

    if ($field === 'priority') {
        return Ticket::getPriorityName($value);
    }

    if ($field === 'itilcategories_id') {
        return pgeservicos_ticket_view_dropdown_name('glpi_itilcategories', $value);
    }

    if ($field === 'requesttypes_id') {
        return pgeservicos_ticket_view_dropdown_name('glpi_requesttypes', $value);
    }

    if ($field === 'locations_id') {
        return pgeservicos_ticket_view_dropdown_name('glpi_locations', $value);
    }

    return $value > 0 ? (string)$value : '-';
}

$tickets_id = (int)($_POST['tickets_id'] ?? 0);
$field = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['field'] ?? ''));
$raw_value = trim((string)($_POST['value'] ?? ''));
$ticket = pgeservicos_ticket_view_get_ticket($tickets_id);

if ($ticket !== null && $ticket !== false && (int)($ticket->fields['status'] ?? 0) === Ticket::CLOSED) {
    pgeservicos_ticket_field_update_response([
        'ok' => false,
        
        'message' => 'Chamado fechado: este campo está em modo somente leitura.'
    ]);
}

if ($ticket === null || $ticket === false || !$ticket->canUpdateItem()) {
    pgeservicos_ticket_field_update_response([
        'ok' => false,
        'message' => 'Você não tem permissão para alterar este chamado.'
    ]);
}

$allowed_selects = [
    'type',
    'status',
    'impact',
    'urgency',
    'priority',
    'itilcategories_id',
    'requesttypes_id',
    'locations_id'
];
$allowed_dates = [
    'time_to_own',
    'time_to_resolve',
    'internal_time_to_own',
    'internal_time_to_resolve'
];

if (!in_array($field, $allowed_selects, true) && !in_array($field, $allowed_dates, true)) {
    pgeservicos_ticket_field_update_response([
        'ok' => false,
        'message' => 'Campo inválido.'
    ]);
}

if (in_array($field, $allowed_dates, true)) {
    $config = pgeservicos_ticket_view_service_level_config($field);
    $agreement_field = $config['agreement_field'] ?? '';

    if ($agreement_field !== '' && (int)($ticket->fields[$agreement_field] ?? 0) > 0) {
        pgeservicos_ticket_field_update_response([
            'ok' => false,
            'message' => 'Remova a SLA/OLA antes de alterar manualmente este prazo.'
        ]);
    }

    $value = '';

    if ($raw_value !== '') {
        $timestamp = strtotime(str_replace('T', ' ', $raw_value));

        if (!$timestamp) {
            pgeservicos_ticket_field_update_response([
                'ok' => false,
                'message' => 'Data inválida.'
            ]);
        }

        $value = date('Y-m-d H:i:s', $timestamp);
    }

    $label = pgeservicos_ticket_view_date($value);
} else {
    $value = (int)$raw_value;

    if ($field === 'status') {
        $current_status = (int)($ticket->fields['status'] ?? 0);
        $allowed_status = Ticket::getAllowedStatusArray($current_status);

        if (!isset($allowed_status[$value])) {
            pgeservicos_ticket_field_update_response([
                'ok' => false,
                'message' => 'Status não permitido para este chamado.'
            ]);
        }
    }

    $label = pgeservicos_ticket_field_update_label($field, $value);
}

$input = [
    'id' => $tickets_id,
    $field => $value
];

$ticket->check($tickets_id, UPDATE, $input);

if (!$ticket->update($input)) {
    pgeservicos_ticket_field_update_response([
        'ok' => false,
        'message' => 'Não foi possível salvar a alteração.'
    ]);
}

pgeservicos_mark_ticket_seen($tickets_id);

pgeservicos_ticket_field_update_response([
    'ok' => true,
    'message' => 'Salvo.',
    'field' => $field,
    'value' => $value,
    'label' => $label !== '' ? $label : '-'
]);
