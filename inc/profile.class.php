<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginPgeservicosProfile extends CommonDBTM
{
    public static $rightname = 'profile';

    const RIGHT_VALUE = READ;
    const CSRF_SESSION_KEY = 'pgeservicos_reserva_profile_csrf_token';

    public static function getTypeName($nb = 0)
    {
        return 'Reservas de Salas de Reunião';
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getID() > 0) {
            return self::createTabEntry('Reservas de Salas');
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getID() > 0) {
            self::showForProfile((int)$item->getID());
        }

        return true;
    }

    public static function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function getCSRFToken($force = false)
    {
        if (
            $force
            || empty($_SESSION[self::CSRF_SESSION_KEY])
            || !is_string($_SESSION[self::CSRF_SESSION_KEY])
        ) {
            $_SESSION[self::CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CSRF_SESSION_KEY];
    }

    public static function validateCSRFToken($token)
    {
        if (
            empty($token)
            || empty($_SESSION[self::CSRF_SESSION_KEY])
            || !is_string($_SESSION[self::CSRF_SESSION_KEY])
        ) {
            return false;
        }

        return hash_equals($_SESSION[self::CSRF_SESSION_KEY], (string)$token);
    }

    public static function clearCSRFToken()
    {
        unset($_SESSION[self::CSRF_SESSION_KEY]);
    }

    public static function canManagePluginProfileRights()
    {
        return Session::haveRight('profile', UPDATE)
            || Session::haveRight('config', UPDATE);
    }

    public static function getRightsList()
    {
        return [
            'plugin_pgeservicos_reserva_view' => [
                'label' => 'Visualizar reservas',
                'description' => 'Permite acessar a tela inicial e visualizar calendários.'
            ],
            'plugin_pgeservicos_reserva_view_full_calendar' => [
                'label' => 'Visualizar calendário completo',
                'description' => 'Permite visualizar o calendário consolidado de todos os itens.'
            ],
            'plugin_pgeservicos_reserva_create' => [
                'label' => 'Criar reservas',
                'description' => 'Permite criar novas reservas.'
            ],
            'plugin_pgeservicos_reserva_edit_own' => [
                'label' => 'Editar próprias reservas',
                'description' => 'Permite editar reservas futuras criadas pelo próprio usuário.'
            ],
            'plugin_pgeservicos_reserva_delete_own' => [
                'label' => 'Excluir próprias reservas',
                'description' => 'Permite excluir reservas futuras criadas pelo próprio usuário.'
            ],
            'plugin_pgeservicos_reserva_edit_all' => [
                'label' => 'Editar qualquer reserva',
                'description' => 'Permite editar reservas futuras criadas por qualquer usuário.'
            ],
            'plugin_pgeservicos_reserva_delete_all' => [
                'label' => 'Excluir qualquer reserva',
                'description' => 'Permite excluir reservas futuras criadas por qualquer usuário.'
            ],
            'plugin_pgeservicos_reserva_manage_past' => [
                'label' => 'Alterar/excluir reservas antigas',
                'description' => 'Permite alterar ou excluir reservas cuja data/hora inicial já passou.'
            ]
        ];
    }

    public static function getRightValue($profiles_id, $right_name)
    {
        global $DB;

        $profiles_id = (int)$profiles_id;

        if ($profiles_id <= 0) {
            return 0;
        }

        $iterator = $DB->request([
            'SELECT' => ['rights'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => [
                'profiles_id' => $profiles_id,
                'name'        => $right_name
            ],
            'LIMIT' => 1
        ]);

        $row = $iterator->current();

        if ($row && isset($row['rights'])) {
            return (int)$row['rights'];
        }

        return 0;
    }

    public static function profileHasRight($profiles_id, $right_name)
    {
        return self::getRightValue((int)$profiles_id, $right_name) > 0;
    }

    public static function setRightValue($profiles_id, $right_name, $enabled)
    {
        global $DB;

        $profiles_id = (int)$profiles_id;
        $right_name = (string)$right_name;
        $rights = $enabled ? self::RIGHT_VALUE : 0;

        $existing = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_profilerights',
            'WHERE'  => [
                'profiles_id' => $profiles_id,
                'name'        => $right_name
            ],
            'LIMIT' => 1
        ])->current();

        if ($existing && isset($existing['id'])) {
            return $DB->update(
                'glpi_profilerights',
                [
                    'rights' => $rights
                ],
                [
                    'id' => (int)$existing['id']
                ]
            );
        }

        return $DB->insert(
            'glpi_profilerights',
            [
                'profiles_id' => $profiles_id,
                'name'        => $right_name,
                'rights'      => $rights
            ]
        );
    }

    public static function showForProfile($profiles_id)
    {
        global $CFG_GLPI;

        $profiles_id = (int)$profiles_id;

        $profile = new Profile();

        if (!$profile->getFromDB($profiles_id)) {
            echo "<div class='alert alert-danger'>Perfil não encontrado.</div>";
            return;
        }

        $can_edit = self::canManagePluginProfileRights();

        $asset_version = defined('PLUGIN_PGESERVICOS_VERSION')
            ? PLUGIN_PGESERVICOS_VERSION
            : '1';

        echo "<link rel='stylesheet' href='"
            . self::h($CFG_GLPI['root_doc'])
            . "/plugins/pgeservicos/css/pages/profile.css?v="
            . self::h($asset_version)
            . "'>";

        echo "<div class='pgegestor-profile-box'>";
        echo "<h2>Permissões - Reservas de Salas de Reunião</h2>";
        echo "<p class='pgegestor-profile-muted'>";
        echo "Configure quais ações este perfil poderá executar no plugin de reservas.";
        echo "</p>";

        if (!$can_edit) {
            echo "<div class='alert alert-warning'>";
            echo "Seu usuário não possui permissão para alterar perfis. As permissões abaixo estão somente para consulta.";
            echo "</div>";
        }

        echo "<form method='post' action='" . self::h($CFG_GLPI['root_doc']) . "/plugins/pgeservicos/front/profile_rights.php'>";
        echo "<input type='hidden' name='profiles_id' value='" . (int)$profiles_id . "'>";
        echo "<input type='hidden' name='_glpi_csrf_token' value='" . self::h(Session::getNewCSRFToken()) . "'>";
        echo "<input type='hidden' name='pgeservicos_reserva_profile_csrf_token' value='" . self::h(self::getCSRFToken()) . "'>";

        echo "
            <table class='pgegestor-rights-table'>
                <thead>
                    <tr>
                        <th>Permissão</th>
                        <th class='pgegestor-check-cell'>Permitir</th>
                    </tr>
                </thead>
                <tbody>
        ";

        foreach (self::getRightsList() as $right_name => $right_data) {
            $checked = self::profileHasRight($profiles_id, $right_name) ? " checked" : "";
            $disabled = !$can_edit ? " disabled" : "";

            echo "
                <tr>
                    <td>
                        <div class='pgegestor-right-label'>" . self::h($right_data['label']) . "</div>
                        <div class='pgegestor-right-description'>" . self::h($right_data['description']) . "</div>
                    </td>
                    <td class='pgegestor-check-cell'>
                        <input type='checkbox'
                               name='rights[" . self::h($right_name) . "]'
                               value='1'
                               " . $checked . "
                               " . $disabled . ">
                    </td>
                </tr>
            ";
        }

        echo "
                </tbody>
            </table>
        ";

        if ($can_edit) {
            echo "
                <div class='pgegestor-profile-actions'>
                    <button type='submit' class='btn btn-primary'>
                        <i class='ti ti-device-floppy'></i>
                        Salvar permissões
                    </button>
                </div>
            ";
        }

        echo "</form>";
        echo "</div>";
    }

    public static function getCurrentProfileId()
    {
        global $DB;

        if (isset($_SESSION['glpiactiveprofile']) && is_array($_SESSION['glpiactiveprofile'])) {
            if (isset($_SESSION['glpiactiveprofile']['id']) && (int)$_SESSION['glpiactiveprofile']['id'] > 0) {
                return (int)$_SESSION['glpiactiveprofile']['id'];
            }

            if (isset($_SESSION['glpiactiveprofile']['profiles_id']) && (int)$_SESSION['glpiactiveprofile']['profiles_id'] > 0) {
                return (int)$_SESSION['glpiactiveprofile']['profiles_id'];
            }

            if (!empty($_SESSION['glpiactiveprofile']['name'])) {
                $active_profile_name = (string)$_SESSION['glpiactiveprofile']['name'];

                if (isset($_SESSION['glpiprofiles']) && is_array($_SESSION['glpiprofiles'])) {
                    foreach ($_SESSION['glpiprofiles'] as $profile_id => $profile_data) {
                        if (is_array($profile_data) && isset($profile_data['name'])) {
                            if ((string)$profile_data['name'] === $active_profile_name) {
                                if (is_numeric($profile_id) && (int)$profile_id > 0) {
                                    return (int)$profile_id;
                                }

                                if (isset($profile_data['id']) && (int)$profile_data['id'] > 0) {
                                    return (int)$profile_data['id'];
                                }

                                if (isset($profile_data['profiles_id']) && (int)$profile_data['profiles_id'] > 0) {
                                    return (int)$profile_data['profiles_id'];
                                }
                            }
                        }
                    }
                }

                try {
                    $iterator = $DB->request([
                        'SELECT' => ['id'],
                        'FROM'   => 'glpi_profiles',
                        'WHERE'  => [
                            'name' => $active_profile_name
                        ],
                        'LIMIT' => 1
                    ]);

                    $row = $iterator->current();

                    if ($row && isset($row['id']) && (int)$row['id'] > 0) {
                        return (int)$row['id'];
                    }
                } catch (Exception $e) {
                    return 0;
                }
            }
        }

        if (
            isset($_SESSION['glpiprofiles'])
            && is_array($_SESSION['glpiprofiles'])
            && count($_SESSION['glpiprofiles']) === 1
        ) {
            foreach ($_SESSION['glpiprofiles'] as $profile_id => $profile_data) {
                if (is_numeric($profile_id) && (int)$profile_id > 0) {
                    return (int)$profile_id;
                }

                if (is_array($profile_data)) {
                    if (isset($profile_data['id']) && (int)$profile_data['id'] > 0) {
                        return (int)$profile_data['id'];
                    }

                    if (isset($profile_data['profiles_id']) && (int)$profile_data['profiles_id'] > 0) {
                        return (int)$profile_data['profiles_id'];
                    }
                }
            }
        }

        return 0;
    }

    public static function currentProfileHasRight($right_name)
    {
        $profiles_id = self::getCurrentProfileId();

        if ($profiles_id <= 0) {
            return false;
        }

        return self::profileHasRight($profiles_id, $right_name);
    }

    public static function canViewReservations()
    {
        return self::currentProfileHasRight('plugin_pgeservicos_reserva_view');
    }

    public static function canViewFullCalendar()
    {
        return self::currentProfileHasRight('plugin_pgeservicos_reserva_view_full_calendar');
    }

    public static function canCreateReservation()
    {
        return self::currentProfileHasRight('plugin_pgeservicos_reserva_create');
    }

    public static function canEditOwnReservation()
    {
        return self::currentProfileHasRight('plugin_pgeservicos_reserva_edit_own');
    }

    public static function canDeleteOwnReservation()
    {
        return self::currentProfileHasRight('plugin_pgeservicos_reserva_delete_own');
    }

    public static function canEditAllReservations()
    {
        return self::currentProfileHasRight('plugin_pgeservicos_reserva_edit_all');
    }

    public static function canDeleteAllReservations()
    {
        return self::currentProfileHasRight('plugin_pgeservicos_reserva_delete_all');
    }

    public static function canManagePastReservations()
    {
        return self::currentProfileHasRight('plugin_pgeservicos_reserva_manage_past');
    }

    public static function canEditReservation($reservation_users_id)
    {
        $reservation_users_id = (int)$reservation_users_id;
        $current_users_id = isset($_SESSION['glpiID']) ? (int)$_SESSION['glpiID'] : 0;

        if (self::canEditAllReservations()) {
            return true;
        }

        if (
            self::canEditOwnReservation()
            && $current_users_id > 0
            && $reservation_users_id === $current_users_id
        ) {
            return true;
        }

        return false;
    }

    public static function canDeleteReservation($reservation_users_id)
    {
        $reservation_users_id = (int)$reservation_users_id;
        $current_users_id = isset($_SESSION['glpiID']) ? (int)$_SESSION['glpiID'] : 0;

        if (self::canDeleteAllReservations()) {
            return true;
        }

        if (
            self::canDeleteOwnReservation()
            && $current_users_id > 0
            && $reservation_users_id === $current_users_id
        ) {
            return true;
        }

        return false;
    }
}
