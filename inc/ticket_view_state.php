<?php

if (!function_exists('pgeservicos_ticket_views_table')) {
    function pgeservicos_ticket_views_table() {
        return 'glpi_plugin_pgeservicos_ticket_views';
    }
}


if (!function_exists('pgeservicos_ensure_ticket_views_timestamp_columns')) {
    function pgeservicos_ensure_ticket_views_timestamp_columns($table) {
        global $DB;

        $needs_alter = false;

        foreach ($DB->request("SHOW COLUMNS FROM `{$table}`") as $column) {
            $field = (string)($column['Field'] ?? '');
            $type = strtolower((string)($column['Type'] ?? ''));

            if (
                in_array($field, ['last_viewed_at', 'created_at', 'updated_at'], true)
                && strpos($type, 'datetime') === 0
            ) {
                $needs_alter = true;
                break;
            }
        }

        if (!$needs_alter) {
            return true;
        }

        return (bool)$DB->query("ALTER TABLE `{$table}`
            MODIFY `last_viewed_at` timestamp NULL DEFAULT NULL,
            MODIFY `created_at` timestamp NULL DEFAULT NULL,
            MODIFY `updated_at` timestamp NULL DEFAULT NULL");
    }
}

if (!function_exists('pgeservicos_ensure_ticket_views_table')) {
    function pgeservicos_ensure_ticket_views_table() {
        global $DB;

        $table = pgeservicos_ticket_views_table();

        if ($DB->tableExists($table)) {
            return pgeservicos_ensure_ticket_views_timestamp_columns($table);
        }

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `tickets_id` int unsigned NOT NULL,
            `users_id` int unsigned NOT NULL,
            `last_viewed_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_ticket_user` (`tickets_id`, `users_id`),
            KEY `idx_users_id` (`users_id`),
            KEY `idx_tickets_id` (`tickets_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$DB->query($sql)) {
            return false;
        }

        return $DB->tableExists($table, false)
            && pgeservicos_ensure_ticket_views_timestamp_columns($table);
    }
}

if (!function_exists('pgeservicos_ticket_views_table_exists')) {
    function pgeservicos_ticket_views_table_exists($try_create = true) {
        global $DB;

        $table = pgeservicos_ticket_views_table();

        if ($DB->tableExists($table)) {
            return true;
        }

        return $try_create ? pgeservicos_ensure_ticket_views_table() : false;
    }
}

if (!function_exists('pgeservicos_ticket_view_now')) {
    function pgeservicos_ticket_view_now() {
        return $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
    }
}

if (!function_exists('pgeservicos_mark_ticket_viewed')) {
    function pgeservicos_mark_ticket_viewed($tickets_id, $users_id = null) {
        global $DB;

        $tickets_id = (int)$tickets_id;
        $users_id = $users_id === null ? (int)Session::getLoginUserID() : (int)$users_id;

        if ($tickets_id <= 0 || $users_id <= 0) {
            return false;
        }

        $ticket = new Ticket();

        if (!$ticket->getFromDB($tickets_id) || !$ticket->canViewItem()) {
            return false;
        }

        if (!pgeservicos_ticket_views_table_exists(true)) {
            return false;
        }

        $table = pgeservicos_ticket_views_table();
        $now = pgeservicos_ticket_view_now();
        $existing = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => $table,
            'WHERE'  => [
                'tickets_id' => $tickets_id,
                'users_id'   => $users_id,
            ],
            'LIMIT' => 1,
        ])->current();

        if ($existing && isset($existing['id'])) {
            return (bool)$DB->update(
                $table,
                [
                    'last_viewed_at' => $now,
                    'updated_at'     => $now,
                ],
                ['id' => (int)$existing['id']]
            );
        }

        return (bool)$DB->insert(
            $table,
            [
                'tickets_id'      => $tickets_id,
                'users_id'        => $users_id,
                'last_viewed_at'  => $now,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]
        );
    }
}


if (!function_exists('pgeservicos_mark_ticket_views_at')) {
    function pgeservicos_mark_ticket_views_at(array $ticket_views, $users_id = null) {
        global $DB;

        $users_id = $users_id === null ? (int)Session::getLoginUserID() : (int)$users_id;
        $ticket_views = array_filter($ticket_views, static function ($viewed_at, $tickets_id) {
            return (int)$tickets_id > 0;
        }, ARRAY_FILTER_USE_BOTH);

        if ($users_id <= 0 || empty($ticket_views) || !pgeservicos_ticket_views_table_exists(true)) {
            return 0;
        }

        $table = DBmysql::quoteName(pgeservicos_ticket_views_table());
        $now = pgeservicos_ticket_view_now();
        $marked = 0;

        foreach (array_chunk($ticket_views, 300, true) as $chunk) {
            $values = [];

            foreach ($chunk as $tickets_id => $viewed_at) {
                $tickets_id = (int)$tickets_id;
                $viewed_at = trim((string)$viewed_at);

                if ($tickets_id <= 0) {
                    continue;
                }

                if ($viewed_at === '' || strtotime($viewed_at) === false) {
                    $viewed_at = $now;
                }

                $values[] = '(' . $tickets_id
                    . ', ' . $users_id
                    . ', ' . DBmysql::quoteValue($viewed_at)
                    . ', ' . DBmysql::quoteValue($now)
                    . ', ' . DBmysql::quoteValue($now)
                    . ')';
            }

            if (empty($values)) {
                continue;
            }

            $sql = "INSERT INTO {$table} (`tickets_id`, `users_id`, `last_viewed_at`, `created_at`, `updated_at`) VALUES "
                . implode(', ', $values)
                . " ON DUPLICATE KEY UPDATE "
                . "`last_viewed_at` = GREATEST(COALESCE(`last_viewed_at`, '1000-01-01 00:00:00'), VALUES(`last_viewed_at`)), "
                . "`updated_at` = VALUES(`updated_at`)";

            if (!$DB->query($sql)) {
                return false;
            }

            $marked += count($values);
        }

        return $marked;
    }
}

if (!function_exists('pgeservicos_get_ticket_view_states')) {
    function pgeservicos_get_ticket_view_states(array $ticket_ids, $users_id = null) {
        global $DB;

        $users_id = $users_id === null ? (int)Session::getLoginUserID() : (int)$users_id;
        $ticket_ids = array_values(array_unique(array_filter(array_map('intval', $ticket_ids))));
        $states = [];

        if ($users_id <= 0 || empty($ticket_ids) || !pgeservicos_ticket_views_table_exists(true)) {
            return $states;
        }

        foreach ($DB->request([
            'SELECT' => ['tickets_id', 'last_viewed_at'],
            'FROM'   => pgeservicos_ticket_views_table(),
            'WHERE'  => [
                'tickets_id' => $ticket_ids,
                'users_id'   => $users_id,
            ],
        ]) as $row) {
            $states[(int)$row['tickets_id']] = (string)($row['last_viewed_at'] ?? '');
        }

        return $states;
    }
}

if (!function_exists('pgeservicos_ticket_is_updated_for_view')) {
    function pgeservicos_ticket_is_updated_for_view($date_mod, $last_viewed_at) {
        if (empty($date_mod)) {
            return false;
        }

        if (empty($last_viewed_at)) {
            return true;
        }

        $date_mod_ts = strtotime((string)$date_mod);
        $viewed_ts = strtotime((string)$last_viewed_at);

        if ($date_mod_ts === false || $viewed_ts === false) {
            return false;
        }

        return $date_mod_ts > $viewed_ts;
    }
}

if (!function_exists('pgeservicos_ticket_updated_expression')) {
    function pgeservicos_ticket_updated_expression($users_id = null) {
        $users_id = $users_id === null ? (int)Session::getLoginUserID() : (int)$users_id;

        if ($users_id <= 0 || !pgeservicos_ticket_views_table_exists(true)) {
            return null;
        }

        $views_table = DBmysql::quoteName(pgeservicos_ticket_views_table());
        $tickets_id = DBmysql::quoteName('glpi_tickets.id');
        $tickets_date_mod = DBmysql::quoteName('glpi_tickets.date_mod');

        return new QueryExpression(
            "(NOT EXISTS ("
            . "SELECT 1 FROM {$views_table} tv "
            . "WHERE tv.`tickets_id` = {$tickets_id} "
            . "AND tv.`users_id` = {$users_id}"
            . ") OR EXISTS ("
            . "SELECT 1 FROM {$views_table} tv "
            . "WHERE tv.`tickets_id` = {$tickets_id} "
            . "AND tv.`users_id` = {$users_id} "
            . "AND {$tickets_date_mod} > tv.`last_viewed_at`"
            . "))"
        );
    }
}
