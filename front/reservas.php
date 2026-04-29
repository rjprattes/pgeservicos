<?php

include('../../../inc/includes.php');

Session::checkLoginUser();

global $DB, $CFG_GLPI;

require_once(__DIR__ . '/../inc/theme.php');

function pgegestor_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pgegestor_is_helpdesk_interface() {
    return isset($_SESSION['glpiactiveprofile']['interface'])
        && $_SESSION['glpiactiveprofile']['interface'] === 'helpdesk';
}

function pgegestor_can_view_reservations() {
    if (class_exists('PluginPgeservicosProfile') && method_exists('PluginPgeservicosProfile', 'canViewReservations')) {
        return PluginPgeservicosProfile::canViewReservations();
    }

    if (class_exists('PluginPgegestorProfile') && method_exists('PluginPgegestorProfile', 'canViewReservations')) {
        return PluginPgegestorProfile::canViewReservations();
    }

    return true;
}

function pgegestor_can_view_full_calendar() {
    if (class_exists('PluginPgeservicosProfile') && method_exists('PluginPgeservicosProfile', 'canViewFullCalendar')) {
        return PluginPgeservicosProfile::canViewFullCalendar();
    }

    if (class_exists('PluginPgegestorProfile') && method_exists('PluginPgegestorProfile', 'canViewFullCalendar')) {
        return PluginPgegestorProfile::canViewFullCalendar();
    }

    return true;
}

function pgegestor_strip_item_prefix($name) {
    $name = (string)$name;
    $name = preg_replace('/^\s*Dispositivo\s*-\s*/iu', '', $name);

    return trim($name);
}

function pgegestor_get_reservable_item_info($itemtype, $items_id) {
    $info = [
        'type_label' => $itemtype,
        'name'       => $itemtype . ' #' . (int)$items_id,
        'comment'    => ''
    ];

    if (!class_exists($itemtype)) {
        return $info;
    }

    $item = getItemForItemtype($itemtype);

    if (!$item || !$item->getFromDB((int)$items_id)) {
        return $info;
    }

    if (method_exists($itemtype, 'getTypeName')) {
        $info['type_label'] = call_user_func([$itemtype, 'getTypeName'], 1);
    }

    if (!empty($item->fields['name'])) {
        $info['name'] = $item->fields['name'];
    } elseif (method_exists($item, 'getName')) {
        $info['name'] = $item->getName();
    }

    if (isset($item->fields['comment'])) {
        $info['comment'] = $item->fields['comment'];
    }

    return $info;
}

function pgegestor_normalize_text_for_number($value) {
    $value = (string)$value;
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);
    $value = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);

    return trim($value);
}

function pgegestor_extract_first_integer($value) {
    $value = pgegestor_normalize_text_for_number($value);

    if (preg_match('/\d+/', $value, $matches)) {
        return (int)$matches[0];
    }

    return 0;
}

function pgegestor_extract_capacity($reservationitem_comment, $original_item_comment) {
    $comments = [
        $reservationitem_comment,
        $original_item_comment
    ];

    foreach ($comments as $comment) {
        $capacity = pgegestor_extract_first_integer($comment);

        if ($capacity > 0) {
            return $capacity;
        }
    }

    return 0;
}

function pgegestor_get_reservable_rooms() {
    global $DB;

    $iterator = $DB->request([
        'SELECT' => [
            'id',
            'itemtype',
            'items_id',
            'entities_id',
            'comment'
        ],
        'FROM'  => 'glpi_reservationitems',
        'WHERE' => [
            'is_active' => 1
        ] + getEntitiesRestrictCriteria(
            'glpi_reservationitems',
            'entities_id',
            '',
            true
        )
    ]);

    $rooms = [];

    foreach ($iterator as $row) {
        $item_info = pgegestor_get_reservable_item_info(
            $row['itemtype'],
            (int)$row['items_id']
        );

        $room_name = pgegestor_strip_item_prefix($item_info['name']);

        $capacity = pgegestor_extract_capacity(
            $row['comment'] ?? '',
            $item_info['comment'] ?? ''
        );

        $rooms[] = [
            'reservationitems_id' => (int)$row['id'],
            'name'                => $room_name,
            'capacity'            => $capacity,
            'entities_id'         => (int)$row['entities_id'],
            'entity_name'         => Dropdown::getDropdownName(
                'glpi_entities',
                (int)$row['entities_id']
            )
        ];
    }

    usort($rooms, function ($a, $b) {
        return strnatcasecmp($a['name'], $b['name']);
    });

    return $rooms;
}

if (!pgegestor_can_view_reservations()) {
    Html::displayErrorAndDie('Você não tem permissão para acessar as reservas.');
}

$page_title = 'Reserva de Salas de Reunião';

if (pgegestor_is_helpdesk_interface()) {
    Html::header(
        $page_title,
        $_SERVER['PHP_SELF'],
        'plugins',
        'pgeservicos'
    );
} else {
    Html::header(
        $page_title,
        $_SERVER['PHP_SELF'],
        'tools',
        'PluginPgeservicosPortal'
    );
}

$asset_version = defined('PLUGIN_PGESERVICOS_VERSION')
    ? PLUGIN_PGESERVICOS_VERSION
    : '1';

echo "<link rel='stylesheet' href='"
    . pgegestor_h($CFG_GLPI['root_doc'])
    . "/plugins/pgeservicos/css/shared/pgeservicos.css?v="
    . pgegestor_h($asset_version)
    . "'>";

echo "<link rel='stylesheet' href='"
    . pgegestor_h($CFG_GLPI['root_doc'])
    . "/plugins/pgeservicos/css/pages/reservas.css?v="
    . pgegestor_h($asset_version)
    . "'>";

pgeservicos_theme_print_vars();

$rooms = pgegestor_get_reservable_rooms();

$portal_link = $CFG_GLPI['root_doc'] . '/plugins/pgeservicos/front/index.php';
$full_calendar_link = $CFG_GLPI['root_doc'] . '/plugins/pgeservicos/front/calendario.php';

echo "<div class='pgeservicos-container pgeservicos-reservas-page'>";

echo "
<section class='pgeservicos-hero'>
    <h1>Reserva de Salas de Reunião</h1>
    <p>
        Consulte a disponibilidade das salas, visualize os calendários de reserva e selecione o espaço mais adequado
        para sua reunião, evento ou atividade institucional.
    </p>
</section>
";

echo "
<a class='pgeservicos-back-link' href='" . pgegestor_h($portal_link) . "'>&larr; Voltar para o Portal de Serviços</a>
";

echo "
<div class='pgeservicos-reservas-header'>
    <div>
        <h2 class='pgeservicos-section-title'>Salas disponíveis</h2>
        <p class='pgeservicos-subtitle'>
            Selecione uma sala para visualizar o calendário de reservas.
        </p>
    </div>
";

if (pgegestor_can_view_full_calendar()) {
    echo "
    <a class='pgeservicos-reservas-main-btn' href='" . pgegestor_h($full_calendar_link) . "'>
        <i class='ti ti-calendar-event'></i>
        Ver calendário completo
    </a>
    ";
}

echo "</div>";

if (empty($rooms)) {
    echo "
    <div class='pgeservicos-info-box'>
        <h3>Nenhuma sala encontrada</h3>
        <p>Nenhuma sala reservável foi localizada para a entidade atual.</p>
    </div>
    ";
} else {
    echo "<div class='pgeservicos-grid pgeservicos-area-orange'>";

    foreach ($rooms as $room) {
        $calendar_link = $CFG_GLPI['root_doc']
            . '/plugins/pgeservicos/front/calendario.php?reservationitems_id='
            . (int)$room['reservationitems_id'];

        $capacity_text = $room['capacity'] > 0
            ? $room['capacity'] . ' participantes'
            : 'Capacidade não informada';

        echo "
        <div class='pgeservicos-card pgeservicos-reserva-card'>
            <div class='pgeservicos-card-icon'>
                <i class='ti ti-door'></i>
            </div>

            <h3>" . pgegestor_h($room['name']) . "</h3>

            <p>
                <strong>Capacidade:</strong> " . pgegestor_h($capacity_text) . "
            </p>

            <a href='" . pgegestor_h($calendar_link) . "'>
                <i class='ti ti-calendar-event'></i>
                Ver calendário
            </a>
        </div>
        ";
    }

    echo "</div>";
}

echo "</div>";

Html::footer();
