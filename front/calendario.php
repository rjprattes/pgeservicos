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

    return true;
}

function pgegestor_can_view_full_calendar() {
    if (class_exists('PluginPgeservicosProfile') && method_exists('PluginPgeservicosProfile', 'canViewFullCalendar')) {
        return PluginPgeservicosProfile::canViewFullCalendar();
    }

    return true;
}

function pgegestor_can_create_reservation() {
    if (class_exists('PluginPgeservicosProfile') && method_exists('PluginPgeservicosProfile', 'canCreateReservation')) {
        return PluginPgeservicosProfile::canCreateReservation();
    }

    return true;
}

function pgegestor_can_edit_reservation($users_id) {
    if (class_exists('PluginPgeservicosProfile') && method_exists('PluginPgeservicosProfile', 'canEditReservation')) {
        return PluginPgeservicosProfile::canEditReservation((int)$users_id);
    }

    return true;
}

function pgegestor_can_manage_past_reservations() {
    if (class_exists('PluginPgeservicosProfile') && method_exists('PluginPgeservicosProfile', 'canManagePastReservations')) {
        return PluginPgeservicosProfile::canManagePastReservations();
    }

    return false;
}

function pgegestor_get_month_name_pt($month_number) {
    $months = [
        1  => 'Janeiro',
        2  => 'Fevereiro',
        3  => 'Março',
        4  => 'Abril',
        5  => 'Maio',
        6  => 'Junho',
        7  => 'Julho',
        8  => 'Agosto',
        9  => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro'
    ];

    return $months[(int)$month_number] ?? '';
}

function pgegestor_get_weekday_headers() {
    return ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
}

function pgegestor_get_user_name($users_id) {
    $users_id = (int)$users_id;

    if ($users_id <= 0) {
        return '-';
    }

    if (function_exists('getUserName')) {
        return getUserName($users_id);
    }

    $user = new User();

    if ($user->getFromDB($users_id)) {
        return $user->getFriendlyName();
    }

    return 'Usuário #' . $users_id;
}

function pgegestor_strip_item_prefix($name) {
    $name = (string)$name;
    $name = preg_replace('/^\s*Dispositivo\s*-\s*/iu', '', $name);

    return trim($name);
}

function pgegestor_get_item_label($reservationitem_row) {
    $itemtype = $reservationitem_row['itemtype'] ?? '';
    $items_id = (int)($reservationitem_row['items_id'] ?? 0);

    if (!$itemtype || $items_id <= 0 || !class_exists($itemtype)) {
        return 'Item #' . $items_id;
    }

    $item = getItemForItemtype($itemtype);

    if (!$item || !$item->getFromDB($items_id)) {
        return 'Item #' . $items_id;
    }

    $name = $item->fields['name'] ?? (method_exists($item, 'getName') ? $item->getName() : ('Item #' . $items_id));

    return pgegestor_strip_item_prefix($name);
}

function pgegestor_get_reservation_item($reservationitems_id) {
    global $DB;

    $reservationitems_id = (int)$reservationitems_id;

    if ($reservationitems_id <= 0) {
        return null;
    }

    $iterator = $DB->request([
        'SELECT' => [
            'id',
            'itemtype',
            'items_id',
            'entities_id',
            'comment'
        ],
        'FROM' => 'glpi_reservationitems',
        'WHERE' => [
            'id'        => $reservationitems_id,
            'is_active' => 1
        ] + getEntitiesRestrictCriteria(
            'glpi_reservationitems',
            'entities_id',
            '',
            true
        ),
        'LIMIT' => 1
    ]);

    $row = $iterator->current();

    return $row ?: null;
}

function pgegestor_parse_comment($comment) {
    $data = [
        'titulo'        => '',
        'participantes' => '',
        'zoom'          => false,
        'apoio_ti'      => false,
        'copa'          => false
    ];

    $lines = preg_split('/\r\n|\r|\n/', (string)$comment);

    foreach ($lines as $line) {
        $line = trim($line);

        if (stripos($line, 'Título da reunião:') === 0) {
            $data['titulo'] = trim(substr($line, strlen('Título da reunião:')));
        }

        if (stripos($line, 'Número de participantes:') === 0) {
            $data['participantes'] = trim(substr($line, strlen('Número de participantes:')));
        }

        if (stripos($line, '- Link do Zoom:') === 0) {
            $data['zoom'] = stripos($line, 'Sim') !== false;
        }

        if (stripos($line, '- Apoio técnico de TI:') === 0) {
            $data['apoio_ti'] = stripos($line, 'Sim') !== false;
        }

        if (
            stripos($line, '- Serviços de copa:') === 0
            || stripos($line, '- Serviços de copa (café/água):') === 0
        ) {
            $data['copa'] = stripos($line, 'Sim') !== false;
        }
    }

    return $data;
}

function pgegestor_get_reservations_for_period($start_sql, $end_sql, $reservationitems_id = 0) {
    global $DB;

    $where = [
        'glpi_reservations.begin' => ['<', $end_sql],
        'glpi_reservations.end'   => ['>', $start_sql]
    ];

    if ((int)$reservationitems_id > 0) {
        $where['glpi_reservations.reservationitems_id'] = (int)$reservationitems_id;
    }

    $iterator = $DB->request([
        'SELECT' => [
            'glpi_reservations.id AS reservations_id',
            'glpi_reservations.reservationitems_id',
            'glpi_reservations.users_id',
            'glpi_reservations.begin',
            'glpi_reservations.end',
            'glpi_reservations.comment',
            'glpi_reservationitems.itemtype',
            'glpi_reservationitems.items_id',
            'glpi_reservationitems.entities_id'
        ],
        'FROM' => 'glpi_reservations',
        'INNER JOIN' => [
            'glpi_reservationitems' => [
                'ON' => [
                    'glpi_reservations'     => 'reservationitems_id',
                    'glpi_reservationitems' => 'id'
                ]
            ]
        ],
        'WHERE' => $where + getEntitiesRestrictCriteria(
            'glpi_reservationitems',
            'entities_id',
            '',
            true
        ),
        'ORDER' => [
            'glpi_reservations.begin ASC'
        ]
    ]);

    $reservations_by_day = [];

    foreach ($iterator as $row) {
        $day_key = date('Y-m-d', strtotime($row['begin']));
        $parsed = pgegestor_parse_comment($row['comment'] ?? '');
        $item_name = pgegestor_get_item_label($row);

        $reservations_by_day[$day_key][] = [
            'id'                 => (int)$row['reservations_id'],
            'reservationitem_id' => (int)$row['reservationitems_id'],
            'users_id'           => (int)$row['users_id'],
            'begin'              => $row['begin'],
            'end'                => $row['end'],
            'titulo'             => $parsed['titulo'] !== '' ? $parsed['titulo'] : '(Sem título)',
            'participantes'      => $parsed['participantes'] !== '' ? $parsed['participantes'] : '-',
            'zoom'               => $parsed['zoom'],
            'apoio_ti'           => $parsed['apoio_ti'],
            'copa'               => $parsed['copa'],
            'usuario'            => pgegestor_get_user_name((int)$row['users_id']),
            'item_nome'          => $item_name
        ];
    }

    return $reservations_by_day;
}

if (!pgegestor_can_view_reservations()) {
    Html::displayErrorAndDie('Você não tem permissão para acessar as reservas.');
}

$reservationitems_id = isset($_GET['reservationitems_id']) ? (int)$_GET['reservationitems_id'] : 0;
$is_full_calendar = $reservationitems_id <= 0;

if ($is_full_calendar && !pgegestor_can_view_full_calendar()) {
    Html::displayErrorAndDie('Você não tem permissão para visualizar o calendário completo.');
}

$month = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$current_month = DateTime::createFromFormat('Y-m-d H:i:s', $month . '-01 00:00:00');

if (!$current_month) {
    $current_month = new DateTime(date('Y-m-01 00:00:00'));
}

$selected_item = null;

if ($reservationitems_id > 0) {
    $selected_item = pgegestor_get_reservation_item($reservationitems_id);

    if (!$selected_item) {
        Html::displayErrorAndDie('Item reservável não encontrado.');
    }
}

$calendar_start = clone $current_month;
$weekday_number = (int)$calendar_start->format('w');
$calendar_start->modify('-' . $weekday_number . ' days');
$calendar_start->setTime(0, 0, 0);

$calendar_end = clone $current_month;
$calendar_end->modify('last day of this month');
$weekday_number_end = (int)$calendar_end->format('w'); // 0 = Domingo, 6 = Sábado
$calendar_end->modify('+' . (6 - $weekday_number_end) . ' days');
$calendar_end->setTime(23, 59, 59);

$prev_month = (clone $current_month)->modify('-1 month')->format('Y-m');
$next_month = (clone $current_month)->modify('+1 month')->format('Y-m');
$today_month = date('Y-m');

$reservations_by_day = pgegestor_get_reservations_for_period(
    $calendar_start->format('Y-m-d H:i:s'),
    $calendar_end->format('Y-m-d H:i:s'),
    $reservationitems_id
);

$page_title = 'Reservas de Salas - Calendário';

if (pgegestor_is_helpdesk_interface()) {
    Html::header(
        $page_title,
        $_SERVER['PHP_SELF'],
        'plugins',
        'pgegestor'
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
    . "/plugins/pgeservicos/css/pages/calendario.css?v="
    . pgegestor_h($asset_version)
    . "'>";

pgeservicos_theme_print_vars();

$month_title = pgegestor_get_month_name_pt((int)$current_month->format('m')) . ' de ' . $current_month->format('Y');
$today_date = date('Y-m-d');
$now_timestamp = time();
$can_manage_past_reservations = pgegestor_can_manage_past_reservations();
$weekday_headers = pgegestor_get_weekday_headers();

$base_params = [];

if ($reservationitems_id > 0) {
    $base_params['reservationitems_id'] = $reservationitems_id;
}

$prev_link = $CFG_GLPI['root_doc'] . '/plugins/pgeservicos/front/calendario.php?' . http_build_query($base_params + ['month' => $prev_month]);
$today_link = $CFG_GLPI['root_doc'] . '/plugins/pgeservicos/front/calendario.php?' . http_build_query($base_params + ['month' => $today_month]);
$next_link = $CFG_GLPI['root_doc'] . '/plugins/pgeservicos/front/calendario.php?' . http_build_query($base_params + ['month' => $next_month]);
$back_link = $CFG_GLPI['root_doc'] . '/plugins/pgeservicos/front/reservas.php';
$full_calendar_link = $CFG_GLPI['root_doc']
    . '/plugins/pgeservicos/front/calendario.php?'
    . http_build_query(['month' => $current_month->format('Y-m')]);

echo "<div class='pgegestor-page'>";

echo "<div class='pgegestor-toolbar'>";
echo "<a class='pgegestor-back-btn' href='" . pgegestor_h($back_link) . "'>&larr; Voltar</a>";
echo "</div>";

echo "<div class='pgegestor-calendar-header'>";
echo "<div>";
echo "<h1 class='pgegestor-calendar-title'>" . pgegestor_h($month_title) . "</h1>";

if ($selected_item) {
    echo "<p class='pgegestor-calendar-subtitle'>Calendário de reservas - " . pgegestor_h(pgegestor_get_item_label($selected_item)) . "</p>";
} else {
    echo "<p class='pgegestor-calendar-subtitle'>Calendário completo com todos os itens reserváveis.</p>";
}

echo "</div>";

echo "<div class='pgegestor-calendar-actions'>";

if ($selected_item && pgegestor_can_view_full_calendar()) {
    echo "<a class='pgegestor-full-calendar-btn' href='" . pgegestor_h($full_calendar_link) . "'>";
    echo "<i class='ti ti-calendar-event'></i> Ver calendário completo";
    echo "</a>";
}

echo "<div class='pgegestor-nav'>";
echo "<a href='" . pgegestor_h($prev_link) . "'>&lsaquo; Mês anterior</a>";
echo "<a href='" . pgegestor_h($today_link) . "'>Hoje</a>";
echo "<a href='" . pgegestor_h($next_link) . "'>Próximo mês &rsaquo;</a>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "<div class='pgegestor-calendar-wrap'>";
echo "<div class='pgegestor-calendar-grid'>";

foreach ($weekday_headers as $weekday) {
    echo "<div class='pgegestor-weekday'>" . pgegestor_h($weekday) . "</div>";
}

$cursor = clone $calendar_start;
$month_ref = $current_month->format('m');

while ($cursor <= $calendar_end) {
    for ($col = 1; $col <= 7; $col++) {
        $cell_date = $cursor->format('Y-m-d');
        $cell_month = $cursor->format('m');
        $day_reservations = $reservations_by_day[$cell_date] ?? [];

        $is_other_month = ($cell_month !== $month_ref);
        $is_today = ($cell_date === $today_date);
        $is_unavailable = ($cell_date < $today_date);

        $day_classes = ['pgegestor-day'];

        if ($is_other_month) {
            $day_classes[] = 'is-other-month';
        }

        if ($is_today) {
            $day_classes[] = 'is-today';
        }

        if ($is_unavailable) {
            $day_classes[] = 'is-unavailable';
        }

        echo "<div class='" . implode(' ', $day_classes) . "'>";

        echo "<div class='pgegestor-day-top'>";
        echo "<div class='pgegestor-day-number-wrap'>";
        echo "<div class='pgegestor-day-number'>" . $cursor->format('d') . "</div>";

        if ($is_today) {
            echo "<span class='pgegestor-today-badge'>Hoje</span>";
        }

        echo "</div>";

	echo "<div class='pgegestor-day-action'>";

	if ($is_unavailable) {
	    echo "<span class='pgegestor-unavailable-label'>Indisponível</span>";
	} else {
		$new_params = [
		    'date'            => $cell_date,
		    'return_calendar' => $is_full_calendar ? 'full' : 'item'
		];

		if ($reservationitems_id > 0) {
		    $new_params['reservationitems_id'] = $reservationitems_id;
		}

		$new_link = $CFG_GLPI['root_doc']
		    . '/plugins/pgeservicos/front/adicionar.php?'
		    . http_build_query($new_params);

		echo "<a class='pgegestor-new-btn' href='" . pgegestor_h($new_link) . "'>+ Nova</a>";
	}

	echo "</div>";
	echo "</div>";

        if (!empty($day_reservations)) {
            echo "<div class='pgegestor-reservations'>";

            foreach ($day_reservations as $reservation) {
                $begin_time = date('H:i', strtotime($reservation['begin']));
                $end_time = date('H:i', strtotime($reservation['end']));
		$edit_params = [
		    'reservations_id'   => (int)$reservation['id'],
		    'return_calendar'   => $is_full_calendar ? 'full' : 'item'
		];

		$edit_params = [
		    'reservations_id'   => (int)$reservation['id'],
		    'return_calendar'   => $is_full_calendar ? 'full' : 'item'
		];

		$edit_link = $CFG_GLPI['root_doc']
		    . '/plugins/pgeservicos/front/adicionar.php?'
		    . http_build_query($edit_params);

                $reservation_is_past = strtotime($reservation['begin']) < $now_timestamp;

                $can_edit_basic = pgegestor_can_edit_reservation((int)$reservation['users_id']);
                $can_edit_past_rule = !$reservation_is_past || $can_manage_past_reservations;

                $can_edit = $can_edit_basic && $can_edit_past_rule;

                $card_classes = ['pgegestor-reservation-card'];

                if (!$can_edit) {
                    $card_classes[] = 'is-locked';
                }

                $card_class_attr = implode(' ', $card_classes);

                $tag_open = $can_edit
                    ? "<a class='" . pgegestor_h($card_class_attr) . "' href='" . pgegestor_h($edit_link) . "'>"
                    : "<div class='" . pgegestor_h($card_class_attr) . "'>";

                $tag_close = $can_edit ? "</a>" : "</div>";

                echo $tag_open;

                echo "<div class='pgegestor-reservation-time'>" . pgegestor_h($begin_time . ' - ' . $end_time) . "</div>";
                echo "<div class='pgegestor-reservation-title'>" . pgegestor_h($reservation['titulo']) . "</div>";
                echo "<div class='pgegestor-reservation-user'>Reservado por: " . pgegestor_h($reservation['usuario']) . "</div>";

                echo "<div class='pgegestor-tooltip-content'>";
                echo "<div class='pgegestor-tooltip-title'>" . pgegestor_h($reservation['titulo']) . "</div>";

                echo "<div class='pgegestor-tooltip-row'><span class='pgegestor-tooltip-label'>Local do evento:</span> " . pgegestor_h($reservation['item_nome']) . "</div>";
                echo "<div class='pgegestor-tooltip-row'><span class='pgegestor-tooltip-label'>Início:</span> " . pgegestor_h(date('d/m/Y H:i', strtotime($reservation['begin']))) . "</div>";
                echo "<div class='pgegestor-tooltip-row'><span class='pgegestor-tooltip-label'>Fim:</span> " . pgegestor_h(date('d/m/Y H:i', strtotime($reservation['end']))) . "</div>";
                echo "<div class='pgegestor-tooltip-row'><span class='pgegestor-tooltip-label'>Reservado por:</span> " . pgegestor_h($reservation['usuario']) . "</div>";
                echo "<div class='pgegestor-tooltip-row'><span class='pgegestor-tooltip-label'>Nº de participantes:</span> " . pgegestor_h($reservation['participantes']) . "</div>";

                echo "<div class='pgegestor-tooltip-section'>";
                echo "<div class='pgegestor-tooltip-label'>Solicitações adicionais:</div>";
                echo "<ul class='pgegestor-tooltip-list'>";
                echo "<li>Link do Zoom: " . ($reservation['zoom'] ? 'Sim' : 'Não') . "</li>";
                echo "<li>Apoio técnico de TI: " . ($reservation['apoio_ti'] ? 'Sim' : 'Não') . "</li>";
                echo "<li>Serviços de copa (café/água): " . ($reservation['copa'] ? 'Sim' : 'Não') . "</li>";
                echo "</ul>";
                echo "</div>";

                if ($can_edit) {
                    echo "<div class='pgegestor-edit-note'>";
                    echo "Clique para editar esta reserva.";
                    echo "</div>";
                } elseif ($reservation_is_past && !$can_manage_past_reservations) {
                    echo "<div class='pgegestor-locked-note'>";
                    echo "Esta reserva já ocorreu e não pode mais ser editada ou excluída.";
                    echo "</div>";
                } elseif (!$can_edit_basic) {
                    echo "<div class='pgegestor-locked-note'>";
                    echo "Seu perfil não permite editar esta reserva.";
                    echo "</div>";
                }

                echo "</div>";

                echo $tag_close;
            }

            echo "</div>";
        } else {
            echo "<div class='pgegestor-empty'>Sem reservas</div>";
        }

        echo "</div>";

        $cursor->modify('+1 day');
    }
}

echo "</div>";
echo "</div>";
echo "</div>";

echo "<div id='pgegestor-global-tooltip'></div>";

echo "<script src='"
    . pgegestor_h($CFG_GLPI['root_doc'])
    . "/plugins/pgeservicos/js/pages/calendario.js?v="
    . pgegestor_h($asset_version)
    . "'></script>";

Html::footer();
