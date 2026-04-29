<?php

include('../../../inc/includes.php');

Session::checkLoginUser();

global $CFG_GLPI;

$profiles_id = isset($_POST['profiles_id']) ? (int)$_POST['profiles_id'] : 0;

$redirect_url = $CFG_GLPI['root_doc'] . '/front/profile.php';

if ($profiles_id > 0) {
    $redirect_url = $CFG_GLPI['root_doc'] . '/front/profile.form.php?id=' . $profiles_id;
}

if (!class_exists('PluginPgeservicosProfile')) {
    Session::addMessageAfterRedirect(
        'Classe de permissões do plugin não foi carregada.',
        true,
        ERROR
    );

    Html::redirect($redirect_url);
}

if (!PluginPgeservicosProfile::canManagePluginProfileRights()) {
    Session::addMessageAfterRedirect(
        'Você não possui permissão para alterar permissões de perfis.',
        true,
        ERROR
    );

    Html::redirect($redirect_url);
}

$posted_token = $_POST['pgeservicos_reserva_profile_csrf_token'] ?? '';

if (!PluginPgeservicosProfile::validateCSRFToken($posted_token)) {
    Session::addMessageAfterRedirect(
        'Token de segurança inválido. Recarregue a página e tente novamente.',
        true,
        ERROR
    );

    Html::redirect($redirect_url);
}

if ($profiles_id <= 0) {
    Session::addMessageAfterRedirect('Perfil inválido.', true, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/front/profile.php');
}

$profile = new Profile();

if (!$profile->getFromDB($profiles_id)) {
    Session::addMessageAfterRedirect('Perfil não encontrado.', true, ERROR);
    Html::redirect($CFG_GLPI['root_doc'] . '/front/profile.php');
}

$posted_rights = isset($_POST['rights']) && is_array($_POST['rights'])
    ? $_POST['rights']
    : [];

foreach (PluginPgeservicosProfile::getRightsList() as $right_name => $right_data) {
    $enabled = isset($posted_rights[$right_name]) && (int)$posted_rights[$right_name] === 1;

    PluginPgeservicosProfile::setRightValue(
        $profiles_id,
        $right_name,
        $enabled
    );
}

PluginPgeservicosProfile::clearCSRFToken();

Session::addMessageAfterRedirect(
    'Permissões de Reservas de Salas de Reunião atualizadas com sucesso.',
    true,
    INFO
);

Html::redirect(
    $CFG_GLPI['root_doc'] . '/front/profile.form.php?id=' . $profiles_id
);
