<?php

if (!defined('GLPI_KEEP_CSRF_TOKEN')) {
    define('GLPI_KEEP_CSRF_TOKEN', true);
}

include('../../../inc/includes.php');

Session::checkLoginUser();

global $DB;

require_once(__DIR__ . '/../inc/tickets_catalog.php');
require_once(__DIR__ . '/../inc/ticket_view.php');

header('Content-Type: application/json; charset=UTF-8');
$GLOBALS['pgeservicos_sla_update_ob_level'] = ob_get_level();
ob_start();

function pgeservicos_sla_update_response($payload, $status = 200) {
    $base_ob_level = $GLOBALS['pgeservicos_sla_update_ob_level'] ?? 0;

    while (ob_get_level() > $base_ob_level) {
        ob_end_clean();
    }

    if (isset($payload['ok']) && !isset($payload['success'])) {
        $payload['success'] = (bool)$payload['ok'];
    }

    if (isset($payload['success']) && !isset($payload['ok'])) {
        $payload['ok'] = (bool)$payload['success'];
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

function pgeservicos_sla_update_payload(Ticket $ticket, array $config) {
    $field = $config['field'];
    $agreement_field = $config['agreement_field'];
    $agreement_id = (int)($ticket->fields[$agreement_field] ?? 0);
    $agreement_name = $agreement_id > 0
        ? pgeservicos_ticket_view_dropdown_name($config['agreement_table'], $agreement_id)
        : '';

    return [
        'field' => $field,
        'date_value' => $ticket->fields[$field] ?? '',
        'date_label' => pgeservicos_ticket_view_date($ticket->fields[$field] ?? ''),
        'agreement_id' => $agreement_id,
        'agreement_name' => $agreement_name !== '-' ? $agreement_name : '',
        'agreement_label' => $config['agreement_label'],
    ];
}

if (GLPI_USE_CSRF_CHECK && !Session::validateCSRF(['_glpi_csrf_token' => $_POST['_glpi_csrf_token'] ?? ''])) {
    pgeservicos_sla_update_response([
        'ok' => false,
        'message' => 'Token de segurança inválido. Recarregue a página e tente novamente.'
    ], 403);
}

$tickets_id = (int)($_POST['tickets_id'] ?? 0);
$field = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['field'] ?? ''));
$action = preg_replace('/[^a-z_]/', '', (string)($_POST['action'] ?? ''));
$agreement_id = (int)($_POST['agreement_id'] ?? 0);
$config = pgeservicos_ticket_view_service_level_config($field);
$ticket = pgeservicos_ticket_view_get_ticket($tickets_id);

if ($config === null) {
    pgeservicos_sla_update_response(['ok' => false, 'message' => 'Prazo inválido.'], 400);
}

if ($ticket !== null && $ticket !== false && (int)($ticket->fields['status'] ?? 0) === Ticket::CLOSED) {
    pgeservicos_sla_update_response([
        'ok' => false,
        'message' => 'Chamado fechado: os prazos estão em modo somente leitura.'
    ], 403);
}

if ($ticket === null || $ticket === false || !$ticket->canUpdateItem()) {
    pgeservicos_sla_update_response([
        'ok' => false,
        'message' => 'Você não tem permissão para alterar os prazos deste chamado.'
    ], 403);
}

$table = $config['agreement_table'];

if (!$DB->tableExists($table)) {
    pgeservicos_sla_update_response(['ok' => false, 'message' => 'Tabela de SLA/OLA indisponível.'], 500);
}

try {
    if ($action === 'remove_sla') {
        if (!$ticket->deleteLevelAgreement($config['agreement_type'], $tickets_id, (int)$config['slm_type'], false)) {
            pgeservicos_sla_update_response(['ok' => false, 'message' => 'Não foi possível remover a SLA/OLA.'], 500);
        }
    } elseif ($action === 'assign_sla') {
        if ($agreement_id <= 0) {
            pgeservicos_sla_update_response(['ok' => false, 'message' => 'Selecione uma SLA/OLA válida.'], 400);
        }

        $where = [
            'id' => $agreement_id,
            'type' => (int)$config['slm_type']
        ];

        if ($DB->fieldExists($table, 'is_deleted')) {
            $where['is_deleted'] = 0;
        }

        if ($DB->fieldExists($table, 'entities_id')) {
            $ticket_entity = (int)($ticket->fields['entities_id'] ?? -1);
            $entities = [];

            if ($ticket_entity >= 0) {
                $entities[$ticket_entity] = $ticket_entity;

                foreach (getAncestorsOf('glpi_entities', $ticket_entity) as $ancestor_id) {
                    $entities[(int)$ancestor_id] = (int)$ancestor_id;
                }
            }

            if (function_exists('pgeservicos_get_accessible_entities')) {
                foreach (pgeservicos_get_accessible_entities() as $entities_id) {
                    $entities[(int)$entities_id] = (int)$entities_id;
                }
            }

            $where['entities_id'] = array_values(array_unique($entities ?: [$ticket_entity]));
        }

        $exists = false;
        foreach ($DB->request(['FROM' => $table, 'WHERE' => $where, 'LIMIT' => 1]) as $row) {
            $exists = true;
            break;
        }

        if (!$exists) {
            pgeservicos_sla_update_response(['ok' => false, 'message' => 'SLA/OLA indisponível para este chamado.'], 403);
        }

        $input = [
            'id' => $tickets_id,
            $config['agreement_field'] => $agreement_id
        ];

        if (!$ticket->update($input)) {
            pgeservicos_sla_update_response(['ok' => false, 'message' => 'Não foi possível atribuir a SLA/OLA.'], 500);
        }
    } else {
        pgeservicos_sla_update_response(['ok' => false, 'message' => 'Ação inválida.'], 400);
    }
} catch (Throwable $e) {
    pgeservicos_sla_update_response(['ok' => false, 'message' => 'Não foi possível atualizar a SLA/OLA.'], 500);
}

$updated_ticket = pgeservicos_ticket_view_get_ticket($tickets_id);

if (!$updated_ticket instanceof Ticket) {
    pgeservicos_sla_update_response(['ok' => false, 'message' => 'Não foi possível recarregar o chamado.'], 500);
}

pgeservicos_sla_update_response([
    'ok' => true,
    'message' => $action === 'remove_sla' ? 'SLA/OLA removida.' : 'SLA/OLA atribuída.',
] + pgeservicos_sla_update_payload($updated_ticket, $config));
