<?php

include('../../../inc/includes.php');

Session::checkLoginUser();

global $CFG_GLPI;

$tickets_id = isset($_GET['tickets_id']) ? (int)$_GET['tickets_id'] : 0;

if ($tickets_id <= 0) {
    Html::displayErrorAndDie('Chamado não informado.');
}

$ticket = new Ticket();

if (!$ticket->getFromDB($tickets_id)) {
    Html::displayErrorAndDie('Chamado não encontrado.');
}

$entities_id = isset($ticket->fields['entities_id'])
    ? (int)$ticket->fields['entities_id']
    : -1;

if ($entities_id < 0) {
    Html::displayErrorAndDie('Não foi possível identificar a entidade do chamado.');
}

if (!Session::changeActiveEntities($entities_id, 0)) {
    Html::displayErrorAndDie(
        'Não foi possível alterar para a entidade do chamado. Verifique se o usuário possui acesso à entidade correspondente.'
    );
}

Html::redirect($CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=' . $tickets_id);
exit;
