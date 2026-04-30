<?php

include('../../../inc/includes.php');

Session::checkLoginUser();
Session::checkCSRF(['_glpi_csrf_token' => $_POST['_glpi_csrf_token'] ?? '']);

require_once(__DIR__ . '/../inc/tickets_catalog.php');
require_once(__DIR__ . '/../inc/ticket_view.php');
require_once(__DIR__ . '/../inc/ticket_actions.php');

$tickets_id = isset($_POST['tickets_id']) ? (int)$_POST['tickets_id'] : (int)($_GET['tickets_id'] ?? 0);
$ticket = pgeservicos_ticket_view_get_ticket($tickets_id);

if ($ticket === null) {
    Html::displayNotFoundError();
    exit;
}

if ($ticket === false) {
    Html::displayRightError();
    exit;
}

$action = $_POST['pgeservicos_action'] ?? '';

pgeservicos_ticket_action_handle($ticket, $action);
pgeservicos_ticket_action_redirect($tickets_id);
