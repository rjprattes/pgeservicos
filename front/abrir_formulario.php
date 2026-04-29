<?php

include('../../../inc/includes.php');

Session::checkLoginUser();

global $CFG_GLPI;

function pgeservicos_redirect_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$form_id   = isset($_GET['form_id']) ? (int)$_GET['form_id'] : 0;
$entity_id = isset($_GET['entity_id']) ? (int)$_GET['entity_id'] : -1;

if ($form_id <= 0) {
    Html::displayErrorAndDie('Formulário não informado.');
}

if ($entity_id >= 0) {
    $changed = Session::changeActiveEntities($entity_id, 0);

    if (!$changed) {
        Html::displayErrorAndDie(
            'Não foi possível alterar para a entidade do formulário. Verifique se o usuário possui acesso à entidade correspondente.'
        );
    }
}

$form_url = $CFG_GLPI['root_doc'] . '/plugins/formcreator/front/formdisplay.php?id=' . $form_id;

Html::redirect($form_url);
exit;
