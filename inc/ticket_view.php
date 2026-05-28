<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

if (!function_exists('pgeservicos_ticket_view_decode_text')) {
    function pgeservicos_ticket_view_decode_text($value) {
        $decoded = (string)$value;

        for ($i = 0; $i < 3; $i++) {
            $normalized = preg_replace('/&#0*62(?!\d);?/i', '>', $decoded);
            $normalized = preg_replace('/&#x0*3e(?![0-9a-f]);?/i', '>', $normalized);
            $next = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($next === $decoded) {
                break;
            }

            $decoded = $next;
        }

        return $decoded;
    }
}

if (!function_exists('pgeservicos_ticket_view_normalize_text')) {
    function pgeservicos_ticket_view_normalize_text($value) {
        $text = pgeservicos_ticket_view_decode_text($value);

        return str_replace(["\\r\\n", "\\n", "\\r"], ["\n", "\n", "\n"], $text);
    }
}

if (!function_exists('pgeservicos_ticket_view_h')) {
    function pgeservicos_ticket_view_h($value) {
        return htmlspecialchars(pgeservicos_ticket_view_decode_text($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('pgeservicos_ticket_view_clean_html')) {
    function pgeservicos_ticket_view_clean_html($content) {
        $content = trim(pgeservicos_ticket_view_normalize_text($content));

        if ($content === '') {
            return '<em>Sem conteúdo informado.</em>';
        }

        $clean = Html::clean($content, false, 1);

        if (str_contains($content, "\n") && !preg_match('/<(?:p|div|br|ul|ol|li|table|thead|tbody|tr|td|th|h[1-6]|blockquote|pre)\b/i', $clean)) {
            return nl2br($clean, false);
        }

        return $clean;
    }
}

if (!function_exists('pgeservicos_ticket_view_date')) {
    function pgeservicos_ticket_view_date($date) {
        $date = trim((string)$date);

        if ($date === '' || strtoupper($date) === 'NULL') {
            return '-';
        }

        $timestamp = strtotime(str_replace('T', ' ', $date));

        if (!$timestamp) {
            return '-';
        }

        return date('d/m/Y H:i', $timestamp);
    }
}

if (!function_exists('pgeservicos_ticket_view_relative_time')) {
    function pgeservicos_ticket_view_relative_time($date) {
        $timestamp = strtotime(str_replace('T', ' ', (string)$date));

        if (!$timestamp) {
            return '';
        }

        $diff = max(0, time() - $timestamp);

        if ($diff < 60) {
            return 'agora há pouco';
        }

        $minutes = (int)floor($diff / 60);

        if ($minutes < 60) {
            return $minutes === 1 ? '1 minuto atrás' : $minutes . ' minutos atrás';
        }

        $hours = (int)floor($minutes / 60);

        if ($hours < 24) {
            return $hours === 1 ? '1 hora atrás' : $hours . ' horas atrás';
        }

        $days = (int)floor($hours / 24);

        if ($days < 30) {
            return $days === 1 ? '1 dia atrás' : $days . ' dias atrás';
        }

        $months = (int)floor($days / 30);

        if ($months < 12) {
            return $months === 1 ? '1 mês atrás' : $months . ' meses atrás';
        }

        $years = (int)floor($months / 12);

        return $years === 1 ? '1 ano atrás' : $years . ' anos atrás';
    }
}

if (!function_exists('pgeservicos_ticket_view_last_update_summary')) {
    function pgeservicos_ticket_view_last_update_summary(Ticket $ticket, array $timeline) {
        $date = (string)($ticket->fields['date_mod'] ?? '');
        $users_id = (int)($ticket->fields['users_id_lastupdater'] ?? 0);

        foreach ($timeline as $item) {
            $item_date = (string)($item['date'] ?? '');

            if ($item_date !== '' && (!$date || strtotime($item_date) > strtotime($date))) {
                $date = $item_date;

                if (!empty($item['author_user_id'])) {
                    $users_id = (int)$item['author_user_id'];
                }
            }
        }

        $formatted = pgeservicos_ticket_view_date($date);
        $relative = pgeservicos_ticket_view_relative_time($date);
        $author = $users_id > 0 ? pgeservicos_ticket_view_user_name($users_id) : '';

        if ($formatted === '-' || $relative === '') {
            return [];
        }

        $text = 'Última atualização: ' . $relative;
        $title = 'Última atualização em ' . $formatted;

        if ($author !== '') {
            $text .= ' por ' . $author;
            $title .= ' por ' . $author;
        }

        return [
            'text' => $text,
            'title' => $title,
            'date' => $formatted,
            'relative' => $relative,
            'author' => $author,
        ];
    }
}

if (!function_exists('pgeservicos_ticket_view_dropdown_name')) {
    function pgeservicos_ticket_view_dropdown_name($table, $id) {
        $id = (int)$id;

        if ($id <= 0) {
            return '-';
        }

        $name = Dropdown::getDropdownName($table, $id);

        return $name !== '' ? pgeservicos_ticket_view_decode_text($name) : '-';
    }
}

if (!function_exists('pgeservicos_ticket_view_user_name')) {
    function pgeservicos_ticket_view_user_name($users_id) {
        $users_id = (int)$users_id;

        if ($users_id <= 0) {
            return __('System');
        }

        if (function_exists('getUserName')) {
            $name = getUserName($users_id, 0, true);

            if ($name !== '') {
                return $name;
            }
        }

        return pgeservicos_ticket_view_dropdown_name('glpi_users', $users_id);
    }
}

if (!function_exists('pgeservicos_ticket_view_initials')) {
    function pgeservicos_ticket_view_initials($name) {
        $name = trim(strip_tags((string)$name));

        if ($name === '') {
            return 'GL';
        }

        $parts = preg_split('/\s+/u', $name);
        $initials = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $initials .= mb_substr($part, 0, 1, 'UTF-8');

            if (mb_strlen($initials, 'UTF-8') >= 2) {
                break;
            }
        }

        return mb_strtoupper($initials ?: 'GL', 'UTF-8');
    }
}

if (!function_exists('pgeservicos_ticket_view_user_avatar_url')) {
    function pgeservicos_ticket_view_user_avatar_url($users_id) {
        $users_id = (int)$users_id;
        $user = new User();

        if ($users_id <= 0 || !$user->getFromDB($users_id)) {
            return '';
        }

        $picture = trim((string)($user->fields['picture'] ?? ''));

        if ($picture === '') {
            return '';
        }

        $picture_path = GLPI_PICTURE_DIR . '/' . $picture;

        if (!is_file($picture_path)) {
            return '';
        }

        return User::getThumbnailURLForPicture($picture);
    }
}

if (!function_exists('pgeservicos_ticket_view_type_label')) {
    function pgeservicos_ticket_view_type_label($type) {
        $type = (int)$type;

        if ($type === Ticket::INCIDENT_TYPE) {
            return __('Incident');
        }

        if ($type === Ticket::DEMAND_TYPE) {
            return __('Request');
        }

        return '-';
    }
}

if (!function_exists('pgeservicos_ticket_view_status_class')) {
    function pgeservicos_ticket_view_status_class($status) {
        $status = (int)$status;

        if (in_array($status, [Ticket::SOLVED, Ticket::CLOSED], true)) {
            return 'is-success';
        }

        if ($status === Ticket::WAITING) {
            return 'is-warning';
        }

        if ($status === Ticket::INCOMING) {
            return 'is-info';
        }

        return 'is-primary';
    }
}

if (!function_exists('pgeservicos_ticket_view_can_manage_actors')) {
    function pgeservicos_ticket_view_can_manage_actors(Ticket $ticket) {
        return Session::haveRight(Ticket::$rightname, UPDATE)
            && $ticket->can((int)$ticket->getID(), UPDATE);
    }
}

if (!function_exists('pgeservicos_ticket_view_safe_color')) {
    function pgeservicos_ticket_view_safe_color($color) {
        $color = trim((string)$color);

        if (preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $color)) {
            return $color;
        }

        return '';
    }
}

if (!function_exists('pgeservicos_ticket_view_color_text_class')) {
    function pgeservicos_ticket_view_color_text_class($color) {
        $color = pgeservicos_ticket_view_safe_color($color);

        if ($color === '') {
            return 'is-vip-text-light';
        }

        $hex = ltrim($color, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $luminance > 150 ? 'is-vip-text-dark' : 'is-vip-text-light';
    }
}

if (!function_exists('pgeservicos_ticket_view_entity_display_name')) {
    function pgeservicos_ticket_view_entity_display_name($entities_id) {
        $entities_id = (int)$entities_id;

        if (function_exists('pgeservicos_get_entity_display_names') && function_exists('pgeservicos_get_ticket_area_name')) {
            $entity_names = pgeservicos_get_entity_display_names([$entities_id]);
            return pgeservicos_get_ticket_area_name($entities_id, $entity_names);
        }

        $name = pgeservicos_ticket_view_dropdown_name('glpi_entities', $entities_id);

        return trim(mb_strtolower($name, 'UTF-8')) === 'portal pge'
            ? 'Serviços de Informática'
            : $name;
    }
}

if (!function_exists('pgeservicos_ticket_view_get_ticket')) {
    function pgeservicos_ticket_view_get_ticket($tickets_id) {
        $tickets_id = (int)$tickets_id;

        if ($tickets_id <= 0) {
            return null;
        }

        $ticket = new Ticket();

        if (!$ticket->getFromDB($tickets_id)) {
            return null;
        }

        $entities_id = (int)($ticket->fields['entities_id'] ?? -1);

        if ($entities_id >= 0) {
            Session::changeActiveEntities($entities_id, 0);
            $ticket->getFromDB($tickets_id);
        }

        if (!$ticket->canViewItem()) {
            return false;
        }

        return $ticket;
    }
}

if (!function_exists('pgeservicos_ticket_view_detail_rows')) {
    function pgeservicos_ticket_view_detail_rows(Ticket $ticket) {
        $fields = $ticket->fields;

        $rows = [
            [
                'label' => 'Entidade',
                'value' => pgeservicos_ticket_view_entity_display_name($fields['entities_id'] ?? 0)
            ],
            ['label' => 'Tipo', 'value' => pgeservicos_ticket_view_type_label($fields['type'] ?? 0)],
            [
                'label' => 'Categoria',
                'value' => pgeservicos_ticket_view_dropdown_name(
                    'glpi_itilcategories',
                    $fields['itilcategories_id'] ?? 0
                )
            ],
            [
                'label' => 'Origem da requisição',
                'value' => pgeservicos_ticket_view_dropdown_name(
                    'glpi_requesttypes',
                    $fields['requesttypes_id'] ?? 0
                )
            ],
            ['label' => 'Impacto', 'value' => Ticket::getImpactName((int)($fields['impact'] ?? 0))],
            ['label' => 'Urgência', 'value' => Ticket::getUrgencyName((int)($fields['urgency'] ?? 0))],
            ['label' => 'Prioridade', 'value' => Ticket::getPriorityName((int)($fields['priority'] ?? 0))],
            [
                'label' => 'Localização',
                'value' => pgeservicos_ticket_view_dropdown_name('glpi_locations', $fields['locations_id'] ?? 0)
            ]
        ];

        return array_values(array_filter($rows, static function ($row) {
            return trim((string)$row['value']) !== '' && $row['value'] !== '-';
        }));
    }
}

if (!function_exists('pgeservicos_ticket_view_service_level_config')) {
    function pgeservicos_ticket_view_service_level_config($field) {
        $configs = [
            'time_to_own' => [
                'field' => 'time_to_own',
                'label' => 'Tempo para atendimento',
                'agreement_label' => 'SLA',
                'agreement_type' => 'SLA',
                'agreement_table' => 'glpi_slas',
                'agreement_itemtype' => SLA::class,
                'agreement_field' => 'slas_id_tto',
                'slm_type' => SLM::TTO,
            ],
            'time_to_resolve' => [
                'field' => 'time_to_resolve',
                'label' => 'Tempo para solução',
                'agreement_label' => 'SLA',
                'agreement_type' => 'SLA',
                'agreement_table' => 'glpi_slas',
                'agreement_itemtype' => SLA::class,
                'agreement_field' => 'slas_id_ttr',
                'slm_type' => SLM::TTR,
            ],
            'internal_time_to_own' => [
                'field' => 'internal_time_to_own',
                'label' => 'Tempo interno para atendimento',
                'agreement_label' => 'OLA',
                'agreement_type' => 'OLA',
                'agreement_table' => 'glpi_olas',
                'agreement_itemtype' => OLA::class,
                'agreement_field' => 'olas_id_tto',
                'slm_type' => SLM::TTO,
            ],
            'internal_time_to_resolve' => [
                'field' => 'internal_time_to_resolve',
                'label' => 'Tempo interno para solução',
                'agreement_label' => 'OLA',
                'agreement_type' => 'OLA',
                'agreement_table' => 'glpi_olas',
                'agreement_itemtype' => OLA::class,
                'agreement_field' => 'olas_id_ttr',
                'slm_type' => SLM::TTR,
            ],
        ];

        return $configs[(string)$field] ?? null;
    }
}

if (!function_exists('pgeservicos_ticket_view_service_level_rows')) {
    function pgeservicos_ticket_view_service_level_rows(Ticket $ticket) {
        $fields = $ticket->fields;
        $rows = [];

        foreach (['time_to_own', 'time_to_resolve', 'internal_time_to_own', 'internal_time_to_resolve'] as $field) {
            $config = pgeservicos_ticket_view_service_level_config($field);

            if ($config === null) {
                continue;
            }

            $agreement_id = (int)($fields[$config['agreement_field']] ?? 0);
            $agreement_name = $agreement_id > 0
                ? pgeservicos_ticket_view_dropdown_name($config['agreement_table'], $agreement_id)
                : '';

            $rows[] = $config + [
                'value' => $fields[$field] ?? '',
                'agreement_id' => $agreement_id,
                'agreement_name' => $agreement_name !== '-' ? $agreement_name : ($agreement_id > 0 ? $config['agreement_label'] . ' #' . $agreement_id : ''),
            ];
        }

        return $rows;
    }
}

if (!function_exists('pgeservicos_ticket_view_actor_label')) {
    function pgeservicos_ticket_view_actor_label($type) {
        $labels = [
            CommonITILActor::REQUESTER => 'Requerentes',
            CommonITILActor::OBSERVER  => 'Observadores',
            CommonITILActor::ASSIGN    => 'Atribuídos'
        ];

        return $labels[(int)$type] ?? 'Atores';
    }
}

if (!function_exists('pgeservicos_ticket_view_actor_groups')) {
    function pgeservicos_ticket_view_actor_groups(Ticket $ticket) {
        $result = [];

        foreach ([CommonITILActor::REQUESTER, CommonITILActor::OBSERVER, CommonITILActor::ASSIGN] as $type) {
            $items = [];

            foreach ($ticket->getUsers($type) as $actor) {
                $users_id = (int)($actor['users_id'] ?? 0);
                $vip_info = (int)$type === CommonITILActor::REQUESTER
                    ? pgeservicos_ticket_view_actor_vip_info('user', $users_id)
                    : null;
                $items[] = [
                    'kind'  => 'user',
                    'id'    => (int)($actor['id'] ?? 0),
                    'actor_id' => $users_id,
                    'type'  => (int)$type,
                    'label' => pgeservicos_ticket_view_user_name($users_id),
                    'vip'   => $vip_info,
                    'tooltip' => pgeservicos_ticket_view_user_tooltip($users_id, $vip_info)
                ];
            }

            foreach ($ticket->getGroups($type) as $actor) {
                $groups_id = (int)($actor['groups_id'] ?? 0);
                $items[] = [
                    'kind'  => 'group',
                    'id'    => (int)($actor['id'] ?? 0),
                    'actor_id' => $groups_id,
                    'type'  => (int)$type,
                    'label' => pgeservicos_ticket_view_dropdown_name('glpi_groups', $groups_id),
                    'vip'   => null,
                    'tooltip' => pgeservicos_ticket_view_dropdown_name('glpi_groups', $groups_id)
                ];
            }

            foreach ($ticket->getSuppliers($type) as $actor) {
                $suppliers_id = (int)($actor['suppliers_id'] ?? 0);
                $items[] = [
                    'kind'  => 'supplier',
                    'id'    => (int)($actor['id'] ?? 0),
                    'actor_id' => $suppliers_id,
                    'type'  => (int)$type,
                    'label' => pgeservicos_ticket_view_dropdown_name('glpi_suppliers', $suppliers_id)
                ];
            }

            $result[] = [
                'label' => pgeservicos_ticket_view_actor_label($type),
                'items' => array_values(array_filter($items, static function ($item) {
                    return $item['label'] !== '-';
                }))
            ];
        }

        return $result;
    }
}

if (!function_exists('pgeservicos_ticket_view_is_recent_date')) {
    function pgeservicos_ticket_view_is_recent_date($tickets_id, $date) {
        static $references = [];

        if (empty($date)) {
            return false;
        }

        $tickets_id = (int)$tickets_id;

        if (!array_key_exists($tickets_id, $references)) {
            $states = pgeservicos_get_ticket_view_states([$tickets_id]);
            $references[$tickets_id] = $states[$tickets_id]
                ?? ($_SESSION['pgeservicos_meus_chamados_seen'][$tickets_id] ?? null);
        }

        $reference = $references[$tickets_id] ?: pgeservicos_get_recent_ticket_cutoff();

        return strtotime((string)$date) > strtotime((string)$reference);
    }
}

if (!function_exists('pgeservicos_ticket_view_timeline_label')) {
    function pgeservicos_ticket_view_timeline_label($type, $itiltype = '') {
        if ($type === ITILFollowup::class || $itiltype === 'Followup') {
            return ['kind' => 'followup', 'label' => 'Acompanhamento'];
        }

        if ($type === ITILSolution::class || $itiltype === 'Solution') {
            return ['kind' => 'solution', 'label' => 'Solução'];
        }

        if ($type === 'Document_Item') {
            return ['kind' => 'document', 'label' => 'Documento'];
        }

        if ($itiltype === 'Task' || preg_match('/Task$/', (string)$type)) {
            return ['kind' => 'task', 'label' => 'Tarefa'];
        }

        if ($itiltype === 'Validation' || preg_match('/Validation$/', (string)$type)) {
            return ['kind' => 'validation', 'label' => 'Validação'];
        }

        return ['kind' => 'event', 'label' => 'Atualização'];
    }
}

if (!function_exists('pgeservicos_ticket_view_document_content')) {
    function pgeservicos_ticket_view_document_content($item) {
        global $CFG_GLPI;

        $documents_id = (int)($item['id'] ?? 0);
        $filename = trim((string)($item['filename'] ?? ''));
        $filepath = trim((string)($item['filepath'] ?? ''));
        $name = trim((string)($item['name'] ?? ''));
        $label = $filename !== ''
            ? basename($filename)
            : ($filepath !== '' ? basename($filepath) : ($name !== '' ? $name : 'Documento anexado'));

        if ($documents_id <= 0) {
            return pgeservicos_ticket_view_h($label);
        }

        $size = '';
        if ($filepath !== '' && is_file(GLPI_DOC_DIR . '/' . $filepath)) {
            $size = pgeservicos_ticket_view_format_size(filesize(GLPI_DOC_DIR . '/' . $filepath));
        }

        return pgeservicos_ticket_view_render_attachments([[
            'id'       => $documents_id,
            'name'     => $label,
            'filename' => $filename,
            'mime'     => (string)($item['mime'] ?? ''),
            'size'     => $size,
            'kind'     => pgeservicos_ticket_view_attachment_kind($filename ?: $label, $item['mime'] ?? ''),
            'url'      => ($CFG_GLPI['root_doc'] ?? '') . '/front/document.send.php?docid=' . $documents_id
        ]]);
    }
}

if (!function_exists('pgeservicos_ticket_view_format_size')) {
    function pgeservicos_ticket_view_format_size($bytes) {
        $bytes = (int)$bytes;

        if ($bytes <= 0) {
            return '';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float)$bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string)(int)$size : number_format($size, 2, '.', '')) . ' ' . $units[$unit];
    }
}

if (!function_exists('pgeservicos_ticket_view_attachment_kind')) {
    function pgeservicos_ticket_view_attachment_kind($filename, $mime) {
        $extension = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
        $mime = strtolower((string)$mime);

        if ($extension === 'pdf' || $mime === 'application/pdf') {
            return 'pdf';
        }

        if (
            in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)
            || str_starts_with($mime, 'image/')
        ) {
            return 'image';
        }

        return 'file';
    }
}

if (!function_exists('pgeservicos_ticket_view_timeline_attachments')) {
    function pgeservicos_ticket_view_timeline_attachments($itemtype, $items_id) {
        global $DB, $CFG_GLPI;

        $itemtype = (string)$itemtype;
        $items_id = (int)$items_id;

        if ($itemtype === '' || $items_id <= 0 || !class_exists($itemtype)) {
            return [];
        }

        $linked_item = new $itemtype();

        if (!$linked_item instanceof CommonDBTM || !$linked_item->getFromDB($items_id)) {
            return [];
        }

        $attachments = [];

        foreach ($DB->request(Document_Item::getDocumentForItemRequest($linked_item, ['glpi_documents.name ASC'])) as $row) {
            $document = new Document();
            $documents_id = (int)($row['id'] ?? 0);

            if ($documents_id <= 0 || !$document->getFromDB($documents_id)) {
                continue;
            }

            if (!Document::canView() && !$document->canViewFile(['itemtype' => $itemtype, 'items_id' => $items_id])) {
                continue;
            }

            $document_items_id = (int)($row['assocID'] ?? 0);
            $filename = trim((string)($document->fields['filename'] ?? $row['filename'] ?? ''));
            $filepath = trim((string)($document->fields['filepath'] ?? $row['filepath'] ?? ''));
            $name = trim((string)($document->fields['name'] ?? $row['name'] ?? ''));
            $can_delete = false;

            if ($document_items_id > 0) {
                try {
                    $document_item = new Document_Item();
                    $can_delete = $document_item->getFromDB($document_items_id)
                        && (bool)$document_item->can($document_items_id, DELETE);
                } catch (Throwable $e) {
                    $can_delete = false;
                }
            }
            $label = $filename !== ''
                ? basename($filename)
                : ($filepath !== '' ? basename($filepath) : ($name !== '' ? $name : 'Documento anexado'));
            $size = '';

            if ($filepath !== '' && is_file(GLPI_DOC_DIR . '/' . $filepath)) {
                $size = pgeservicos_ticket_view_format_size(filesize(GLPI_DOC_DIR . '/' . $filepath));
            }

            $url = ($CFG_GLPI['root_doc'] ?? '')
                . '/front/document.send.php?docid=' . $documents_id
                . '&itemtype=' . rawurlencode($itemtype)
                . '&items_id=' . $items_id;

            $attachments[] = [
                'id'               => $documents_id,
                'document_item_id' => $document_items_id,
                'name'             => $label,
                'filename'         => $filename,
                'mime'             => (string)($document->fields['mime'] ?? $row['mime'] ?? ''),
                'size'             => $size,
                'kind'             => pgeservicos_ticket_view_attachment_kind($filename ?: $label, $document->fields['mime'] ?? $row['mime'] ?? ''),
                'url'              => $url,
                'can_delete'       => $can_delete
            ];
        }

        return $attachments;
    }
}

if (!function_exists('pgeservicos_ticket_view_render_edit_attachments')) {
    function pgeservicos_ticket_view_render_edit_attachments(array $attachments, $itemtype, $items_id) {
        $itemtype = (string)$itemtype;
        $items_id = (int)$items_id;

        if (empty($attachments) || $itemtype === '' || $items_id <= 0) {
            return '';
        }

        $html = '<div class="pgeservicos-edit-attachments" aria-label="Anexos vinculados">';

        foreach ($attachments as $attachment) {
            $documents_id = (int)($attachment['id'] ?? 0);
            $document_items_id = (int)($attachment['document_item_id'] ?? 0);
            $name = (string)($attachment['name'] ?? 'Documento anexado');
            $size = trim((string)($attachment['size'] ?? ''));
            $url = (string)($attachment['url'] ?? '');
            $kind = in_array($attachment['kind'] ?? '', ['pdf', 'image', 'file'], true) ? $attachment['kind'] : 'file';
            $icon = $kind === 'pdf' ? 'ti-file-type-pdf' : ($kind === 'image' ? 'ti-photo' : 'ti-paperclip');
            $meta = $size !== '' ? '<span class="pgeservicos-attachment-size">' . pgeservicos_ticket_view_h($size) . '</span>' : '';
            $can_delete = !empty($attachment['can_delete']) && $document_items_id > 0 && $documents_id > 0;

            $html .= '<div class="pgeservicos-edit-attachment" data-pgeservicos-edit-attachment'
                . ' data-document-item-id="' . $document_items_id . '"'
                . ' data-document-id="' . $documents_id . '"'
                . ' data-itemtype="' . pgeservicos_ticket_view_h($itemtype) . '"'
                . ' data-items-id="' . $items_id . '">';

            if ($kind === 'image') {
                $html .= '<button type="button" class="pgeservicos-edit-attachment__preview"'
                    . ' data-pgeservicos-lightbox-src="' . pgeservicos_ticket_view_h($url) . '"'
                    . ' data-pgeservicos-lightbox-title="' . pgeservicos_ticket_view_h($name) . '">'
                    . '<i class="ti ' . pgeservicos_ticket_view_h($icon) . '" aria-hidden="true"></i>';
            } else {
                $attrs = $kind === 'pdf' ? ' target="_blank" rel="noopener noreferrer"' : ' rel="noopener noreferrer" download';
                $html .= '<a class="pgeservicos-edit-attachment__preview" href="' . pgeservicos_ticket_view_h($url) . '"' . $attrs . '>'
                    . '<i class="ti ' . pgeservicos_ticket_view_h($icon) . '" aria-hidden="true"></i>';
            }

            $html .= '<span class="pgeservicos-attachment-info">'
                . '<span class="pgeservicos-attachment-name">' . pgeservicos_ticket_view_h($name) . '</span>'
                . $meta
                . '</span>';

            $html .= $kind === 'image' ? '</button>' : '</a>';

            if ($can_delete) {
                $html .= '<button type="button" class="pgeservicos-edit-attachment__delete" data-pgeservicos-attachment-delete-trigger aria-label="Remover anexo ' . pgeservicos_ticket_view_h($name) . '" title="Remover anexo">'
                    . '<i class="ti ti-trash" aria-hidden="true"></i>'
                    . '</button>';
            }

            $html .= '</div>';
        }

        return $html . '</div>';
    }
}

if (!function_exists('pgeservicos_ticket_view_render_attachments')) {
    function pgeservicos_ticket_view_render_attachments(array $attachments) {
        if (empty($attachments)) {
            return '';
        }

        $html = '<div class="pgeservicos-timeline-attachments" aria-label="Anexos">';

        foreach ($attachments as $attachment) {
            $kind = in_array($attachment['kind'] ?? '', ['pdf', 'image', 'file'], true) ? $attachment['kind'] : 'file';
            $name = (string)($attachment['name'] ?? 'Documento anexado');
            $size = trim((string)($attachment['size'] ?? ''));
            $url = (string)($attachment['url'] ?? '');
            $icon = $kind === 'pdf' ? 'ti-file-type-pdf' : 'ti-paperclip';
            $meta = $size !== '' ? '<span class="pgeservicos-attachment-size">' . pgeservicos_ticket_view_h($size) . '</span>' : '';

            if ($kind === 'image') {
                $html .= '<button type="button" class="pgeservicos-attachment pgeservicos-attachment--image"'
                    . ' data-pgeservicos-lightbox-src="' . pgeservicos_ticket_view_h($url) . '"'
                    . ' data-pgeservicos-lightbox-title="' . pgeservicos_ticket_view_h($name) . '"'
                    . ' title="' . pgeservicos_ticket_view_h($name) . '">'
                    . '<span class="pgeservicos-attachment-thumb">'
                    . '<img src="' . pgeservicos_ticket_view_h($url) . '" alt="' . pgeservicos_ticket_view_h($name) . '" loading="lazy">'
                    . '</span>'
                    . '<span class="pgeservicos-attachment-info">'
                    . '<span class="pgeservicos-attachment-name">' . pgeservicos_ticket_view_h($name) . '</span>'
                    . $meta
                    . '</span>'
                    . '</button>';
                continue;
            }

            $attrs = $kind === 'pdf'
                ? ' target="_blank" rel="noopener noreferrer"'
                : ' rel="noopener noreferrer" download';

            $html .= '<a class="pgeservicos-attachment pgeservicos-attachment--' . pgeservicos_ticket_view_h($kind) . '"'
                . ' href="' . pgeservicos_ticket_view_h($url) . '" title="' . pgeservicos_ticket_view_h($name) . '"' . $attrs . '>'
                . '<i class="ti ' . pgeservicos_ticket_view_h($icon) . '" aria-hidden="true"></i>'
                . '<span class="pgeservicos-attachment-info">'
                . '<span class="pgeservicos-attachment-name">' . pgeservicos_ticket_view_h($name) . '</span>'
                . $meta
                . '</span>'
                . '</a>';
        }

        return $html . '</div>';
    }
}

if (!function_exists('pgeservicos_ticket_view_task_state_label')) {
    function pgeservicos_ticket_view_task_state_label($state) {
        $state = (int)$state;

        if (class_exists('Planning')) {
            return trim(strip_tags((string)Planning::getState($state)));
        }

        return $state > 0 ? (string)$state : '';
    }
}

if (!function_exists('pgeservicos_ticket_view_task_metadata')) {
    function pgeservicos_ticket_view_task_metadata(array $item) {
        $badges = [];

        $users_id_tech = (int)($item['users_id_tech'] ?? 0);
        if ($users_id_tech > 0) {
            $badges[] = [
                'icon' => 'ti-user',
                'label' => pgeservicos_ticket_view_user_name($users_id_tech)
            ];
        }

        $groups_id_tech = (int)($item['groups_id_tech'] ?? 0);
        if ($groups_id_tech > 0) {
            $badges[] = [
                'icon' => 'ti-users',
                'label' => pgeservicos_ticket_view_dropdown_name('glpi_groups', $groups_id_tech)
            ];
        }

        $actiontime = (int)($item['actiontime'] ?? 0);
        if ($actiontime > 0) {
            $badges[] = [
                'icon' => 'ti-clock-hour-4',
                'label' => Html::timestampToString($actiontime, false)
            ];
        }

        $end = trim((string)($item['end'] ?? ''));
        if ($end !== '' && $end !== 'NULL') {
            $badges[] = [
                'icon' => 'ti-calendar-due',
                'label' => 'Conclusão até ' . pgeservicos_ticket_view_date($end)
            ];
        }

        $begin = trim((string)($item['begin'] ?? ''));
        if ($begin !== '' && $begin !== 'NULL') {
            $badges[] = [
                'icon' => 'ti-calendar-time',
                'label' => 'Início ' . pgeservicos_ticket_view_date($begin)
            ];
        }

        $state = pgeservicos_ticket_view_task_state_label($item['state'] ?? 0);
        if ($state !== '') {
            $badges[] = [
                'icon' => 'ti-list-check',
                'label' => $state
            ];
        }

        if (!empty($item['is_private'])) {
            $badges[] = [
                'icon' => 'ti-lock',
                'label' => 'Privado'
            ];
        }

        if (empty($badges)) {
            return '';
        }

        $html = '<div class="pgeservicos-timeline-task-meta" aria-label="Dados da tarefa">';

        foreach ($badges as $badge) {
            $html .= '<span title="' . pgeservicos_ticket_view_h($badge['label']) . '">'
                . '<i class="ti ' . pgeservicos_ticket_view_h($badge['icon']) . '" aria-hidden="true"></i>'
                . pgeservicos_ticket_view_h($badge['label'])
                . '</span>';
        }

        return $html . '</div>';
    }
}

if (!function_exists('pgeservicos_ticket_view_solution_metadata')) {
    function pgeservicos_ticket_view_solution_metadata(array $item) {
        $status = (int)($item['status'] ?? 0);

        if (!in_array($status, [CommonITILValidation::ACCEPTED, CommonITILValidation::REFUSED], true)) {
            return '';
        }

        $label = $status === CommonITILValidation::ACCEPTED ? 'Solução aprovada' : 'Solução reprovada';
        $date = pgeservicos_ticket_view_date($item['date_approval'] ?? '');
        $users_id = (int)($item['users_id_approval'] ?? 0);
        $author = $users_id > 0 ? pgeservicos_ticket_view_user_name($users_id) : '';
        $text = $label;

        if ($date !== '-') {
            $text .= ' em ' . $date;
        }

        if ($author !== '') {
            $text .= ' por ' . $author;
        }

        return '<div class="pgeservicos-timeline-solution-status">'
            . '<span><i class="ti ' . ($status === CommonITILValidation::ACCEPTED ? 'ti-circle-check' : 'ti-circle-x') . '" aria-hidden="true"></i>'
            . pgeservicos_ticket_view_h($text)
            . '</span></div>';
    }
}

if (!function_exists('pgeservicos_ticket_view_solution_decision')) {
    function pgeservicos_ticket_view_solution_decision($content) {
        $plain = trim(mb_strtolower(strip_tags(pgeservicos_ticket_view_decode_text($content)), 'UTF-8'));
        $plain = preg_replace('/\s+/u', ' ', (string)$plain);

        if (
            str_contains($plain, 'solução aprovada')
            || str_contains($plain, 'solucao aprovada')
            || str_contains($plain, 'solution approved')
        ) {
            return 'approved';
        }

        if (
            str_contains($plain, 'solução reprovada')
            || str_contains($plain, 'solucao reprovada')
            || str_contains($plain, 'solution refused')
            || str_contains($plain, 'solution rejected')
        ) {
            return 'refused';
        }

        return '';
    }
}

if (!function_exists('pgeservicos_ticket_view_timeline')) {
    function pgeservicos_ticket_view_timeline(Ticket $ticket) {
        $tickets_id = (int)$ticket->fields['id'];
        $ticket_is_closed = (int)($ticket->fields['status'] ?? 0) === Ticket::CLOSED;
        $items = [];

        $opening_users_id = (int)($ticket->fields['users_id_recipient'] ?? 0);
        $opening_can_edit = (int)($ticket->fields['status'] ?? 0) !== Ticket::CLOSED
            && $ticket->can((int)$ticket->getID(), UPDATE)
            && (!method_exists($ticket, 'canUpdateItem') || $ticket->canUpdateItem());

        $items[] = [
            'kind'            => 'opening',
            'label'           => 'Abertura',
            'id'              => $tickets_id,
            'ticket_name'     => pgeservicos_ticket_view_decode_text($ticket->fields['name'] ?? ''),
            'raw_content'     => pgeservicos_ticket_view_normalize_text($ticket->fields['content'] ?? ''),
            'solution_status' => 0,
            'author_user_id'  => $opening_users_id,
            'avatar_url'      => pgeservicos_ticket_view_user_avatar_url($opening_users_id),
            'author'          => pgeservicos_ticket_view_user_name($opening_users_id),
            'date'            => $ticket->fields['date'] ?? '',
            'content'         => pgeservicos_ticket_view_clean_html($ticket->fields['content'] ?? ''),
            'is_current_user' => $opening_users_id > 0 && $opening_users_id === (int)Session::getLoginUserID(),
            'is_recent'       => false,
            'can_edit'        => $opening_can_edit,
            'opening_author_can_edit' => $opening_can_edit,
            'can_delete'      => false,
            'delete_itemtype' => '',
            'delete_id'       => 0,
            'attachments'     => pgeservicos_ticket_view_timeline_attachments(Ticket::getType(), $tickets_id)
        ];

        foreach ($ticket->getTimelineItems([
            'with_logs'         => false,
            'with_documents'    => true,
            'with_validations'  => true,
            'check_view_rights' => true,
            'sort_by_date_desc' => false
        ]) as $timeline_item) {
            $item = $timeline_item['item'] ?? [];
            $type_info = pgeservicos_ticket_view_timeline_label(
                $timeline_item['type'] ?? '',
                $timeline_item['itiltype'] ?? ''
            );
            $date = $item['date_creation'] ?? $item['date'] ?? $item['date_mod'] ?? '';
            $users_id = (int)($item['users_id'] ?? 0);
            $raw_content = pgeservicos_ticket_view_normalize_text($item['content'] ?? '');
            $content = $type_info['kind'] === 'document'
                ? pgeservicos_ticket_view_document_content($item)
                : pgeservicos_ticket_view_clean_html($raw_content);

            if ($type_info['kind'] === 'followup') {
                $solution_decision = pgeservicos_ticket_view_solution_decision($raw_content);

                if ($solution_decision === 'approved') {
                    $type_info = ['kind' => 'solution-approved', 'label' => 'Solução aprovada'];
                } elseif ($solution_decision === 'refused') {
                    $type_info = ['kind' => 'solution-refused', 'label' => 'Solução reprovada'];
                }
            }

            if ($type_info['kind'] === 'validation') {
                $comments = trim((string)($item['comment_submission'] ?? $item['comment_validation'] ?? ''));

                if ($comments !== '') {
                    $content .= '<div class="pgeservicos-chamado-validation-comment">'
                        . pgeservicos_ticket_view_clean_html($comments)
                        . '</div>';
                }
            }

            if ($type_info['kind'] === 'task') {
                $content .= pgeservicos_ticket_view_task_metadata($item);
            }

            if ($type_info['kind'] === 'solution') {
                $solution_status = (int)($item['status'] ?? 0);

                if ($solution_status === CommonITILValidation::ACCEPTED) {
                    $type_info['label'] = 'Solução aprovada';
                } elseif ($solution_status === CommonITILValidation::REFUSED) {
                    $type_info['label'] = 'Solução reprovada';
                }

                $content .= pgeservicos_ticket_view_solution_metadata($item);
            }

            $itemtype = $timeline_item['type'] ?? '';
            $items_id = (int)($item['id'] ?? 0);
            $delete_itemtype = (string)$itemtype;
            $delete_id = $items_id;
            $can_delete = false;

            if ($type_info['kind'] === 'document') {
                $delete_itemtype = Document_Item::getType();
                $delete_id = (int)($item['documents_item_id'] ?? 0);
                $can_delete = false;

                if (!$ticket_is_closed && $delete_id > 0) {
                    try {
                        $document_item = new Document_Item();
                        $can_delete = $document_item->getFromDB($delete_id)
                            && (bool)$document_item->can($delete_id, DELETE);
                    } catch (Throwable $e) {
                        $can_delete = !empty($item['_can_delete']);
                    }
                }
            } elseif (
                in_array($itemtype, [ITILFollowup::getType(), ITILSolution::getType(), TicketTask::getType()], true)
                && $items_id > 0
            ) {
                try {
                    $timeline_object = $timeline_item['object'] ?? null;

                    if (!$timeline_object instanceof CommonDBTM && class_exists($itemtype)) {
                        $timeline_object = new $itemtype();
                        $timeline_object = $timeline_object->getFromDB($items_id) ? $timeline_object : null;
                    }

                    if ($timeline_object instanceof CommonDBTM && method_exists($timeline_object, 'setParentItem')) {
                        $timeline_object->setParentItem($ticket);
                    }

                    $can_delete = !$ticket_is_closed
                        && $timeline_object instanceof CommonDBTM
                        && (bool)$timeline_object->can($items_id, DELETE);
                } catch (Throwable $e) {
                    $can_delete = false;
                }
            }

            $items[] = [
                'kind'            => $type_info['kind'],
                'label'           => $type_info['label'],
                'id'              => $items_id,
                'raw_content'     => $raw_content,
                'solution_status' => $type_info['kind'] === 'solution' ? (int)($item['status'] ?? 0) : 0,
                'author_user_id'  => $users_id,
                'avatar_url'      => pgeservicos_ticket_view_user_avatar_url($users_id),
                'author'          => pgeservicos_ticket_view_user_name($users_id),
                'date'            => $date,
                'content'         => $content,
                'is_current_user' => $users_id > 0 && $users_id === (int)Session::getLoginUserID(),
                'is_recent'       => pgeservicos_ticket_view_is_recent_date($tickets_id, $date),
                'can_edit'        => !empty($item['can_edit']),
                'can_delete'      => $can_delete,
                'delete_itemtype' => $delete_itemtype,
                'delete_id'       => $delete_id,
                'attachments'     => $type_info['kind'] !== 'document'
                    ? pgeservicos_ticket_view_timeline_attachments($itemtype, $items_id)
                    : []
            ];
        }

        usort($items, static function ($a, $b) {
            return strtotime((string)$a['date']) <=> strtotime((string)$b['date']);
        });

        return $items;
    }
}

if (!function_exists('pgeservicos_ticket_view_vip_label')) {
    function pgeservicos_ticket_view_vip_label($vip_info) {
        if (empty($vip_info)) {
            return '';
        }

        $groups = $vip_info['groups'] ?? [$vip_info];
        $names = [];

        foreach ($groups as $group) {
            $name = trim((string)($group['name'] ?? ''));

            if ($name !== '') {
                $names[] = $name;
            }
        }

        return implode(', ', array_unique($names));
    }
}

if (!function_exists('pgeservicos_ticket_view_vip_from_group_id')) {
    function pgeservicos_ticket_view_vip_from_group_id($groups_id) {
        $groups_id = (int)$groups_id;

        if (
            $groups_id <= 0
            || !class_exists('PluginVipGroup')
        ) {
            return null;
        }

        $name = PluginVipGroup::getVipName($groups_id);
        $color = pgeservicos_ticket_view_safe_color(PluginVipGroup::getVipColor($groups_id));
        $icon = PluginVipGroup::getVipIcon($groups_id);

        return [
            'name'  => $name ?: 'VIP',
            'color' => $color,
            'icon'  => preg_replace('/[^a-z0-9\\-\\s]/i', '', (string)$icon)
        ];
    }
}

if (!function_exists('pgeservicos_ticket_view_vip_groups_for_user')) {
    function pgeservicos_ticket_view_vip_groups_for_user($users_id) {
        global $DB;

        $users_id = (int)$users_id;
        $vip_groups = [];

        if ($users_id <= 0 || !$DB->tableExists('glpi_plugin_vip_groups')) {
            return [];
        }

        foreach ($DB->request([
            'SELECT' => [
                'glpi_plugin_vip_groups.id',
                'glpi_plugin_vip_groups.name',
                'glpi_plugin_vip_groups.vip_color',
                'glpi_plugin_vip_groups.vip_icon'
            ],
            'FROM'   => 'glpi_groups_users',
            'LEFT JOIN' => [
                'glpi_plugin_vip_groups' => [
                    'ON' => [
                        'glpi_plugin_vip_groups' => 'id',
                        'glpi_groups_users'      => 'groups_id'
                    ]
                ]
            ],
            'WHERE'  => [
                'glpi_groups_users.users_id'   => $users_id,
                'glpi_plugin_vip_groups.isvip' => 1
            ]
        ]) as $row) {
            $vip_groups[(int)$row['id']] = [
                'name'  => $row['name'] ?: 'VIP',
                'color' => pgeservicos_ticket_view_safe_color($row['vip_color'] ?? ''),
                'icon'  => preg_replace('/[^a-z0-9\\-\\s]/i', '', (string)($row['vip_icon'] ?? ''))
            ];
        }

        return array_values($vip_groups);
    }
}

if (!function_exists('pgeservicos_ticket_view_actor_vip_info')) {
    function pgeservicos_ticket_view_actor_vip_info($kind, $actor_id) {
        $kind = (string)$kind;
        $actor_id = (int)$actor_id;
        $vip_groups = [];

        if ($kind === 'user') {
            $vip_groups = pgeservicos_ticket_view_vip_groups_for_user($actor_id);
        }

        if (empty($vip_groups)) {
            return null;
        }

        $first = reset($vip_groups);

        return [
            'name'   => $first['name'] ?? 'VIP',
            'color'  => $first['color'] ?? '',
            'icon'   => $first['icon'] ?? '',
            'groups' => array_values($vip_groups)
        ];
    }
}

if (!function_exists('pgeservicos_ticket_view_user_tooltip')) {
    function pgeservicos_ticket_view_user_tooltip($users_id, $vip_info = null) {
        $users_id = (int)$users_id;
        $user = new User();

        if ($users_id <= 0 || !$user->getFromDB($users_id)) {
            return '';
        }

        $lines = [];
        $name = pgeservicos_ticket_view_user_name($users_id);
        $email = UserEmail::getDefaultForUser($users_id);
        $phone = trim((string)($user->fields['phone'] ?? ''));
        $mobile = trim((string)($user->fields['mobile'] ?? ''));
        $location = pgeservicos_ticket_view_dropdown_name('glpi_locations', $user->fields['locations_id'] ?? 0);
        $vip_label = pgeservicos_ticket_view_vip_label($vip_info);

        if ($name !== '' && $name !== '-') {
            $lines[] = 'Nome: ' . $name;
        }

        if ($email !== '') {
            $lines[] = 'E-mail: ' . $email;
        }

        if ($phone !== '') {
            $lines[] = 'Telefone: ' . $phone;
        }

        if ($mobile !== '') {
            $lines[] = 'Celular: ' . $mobile;
        }

        if ($location !== '' && $location !== '-') {
            $lines[] = 'Localização: ' . $location;
        }

        if ($vip_label !== '') {
            $lines[] = 'VIP: ' . $vip_label;
        }

        return implode("\n", $lines);
    }
}

if (!function_exists('pgeservicos_ticket_view_actor_payload')) {
    function pgeservicos_ticket_view_actor_payload($kind, $actor_id, $label = '', $include_vip = true) {
        $kind = (string)$kind;
        $actor_id = (int)$actor_id;
        $label = trim(pgeservicos_ticket_view_decode_text((string)$label));

        if ($kind === 'user') {
            $vip = $include_vip ? pgeservicos_ticket_view_actor_vip_info('user', $actor_id) : null;
            $label = $label !== '' ? $label : pgeservicos_ticket_view_user_name($actor_id);

            return [
                'type' => 'user',
                'type_label' => 'Usuário',
                'label' => $label,
                'tooltip' => pgeservicos_ticket_view_user_tooltip($actor_id, $vip),
                'vip' => $vip
            ];
        }

        if ($kind === 'group') {
            $label = $label !== '' ? $label : pgeservicos_ticket_view_dropdown_name('glpi_groups', $actor_id);

            return [
                'type' => 'group',
                'type_label' => 'Grupo',
                'label' => $label,
                'tooltip' => $label !== '-' ? $label : '',
                'vip' => null
            ];
        }

        return [
            'type' => $kind,
            'type_label' => 'Ator',
            'label' => $label,
            'tooltip' => $label,
            'vip' => null
        ];
    }
}

if (!function_exists('pgeservicos_ticket_view_vip_info')) {
    function pgeservicos_ticket_view_vip_info(Ticket $ticket) {
        global $DB;

        $vip_groups = [];

        if ($DB->tableExists('glpi_plugin_vip_groups')) {
            $user_ids = [];

            foreach ($ticket->getUsers(CommonITILActor::REQUESTER) as $actor) {
                $users_id = (int)($actor['users_id'] ?? 0);

                if ($users_id > 0) {
                    $user_ids[$users_id] = $users_id;
                }
            }

            if (!empty($user_ids)) {
                foreach ($DB->request([
                    'SELECT' => [
                        'glpi_plugin_vip_groups.id',
                        'glpi_plugin_vip_groups.name',
                        'glpi_plugin_vip_groups.vip_color',
                        'glpi_plugin_vip_groups.vip_icon'
                    ],
                    'FROM'   => 'glpi_groups_users',
                    'LEFT JOIN' => [
                        'glpi_plugin_vip_groups' => [
                            'ON' => [
                                'glpi_plugin_vip_groups' => 'id',
                                'glpi_groups_users'      => 'groups_id'
                            ]
                        ]
                    ],
                    'WHERE'  => [
                        'glpi_groups_users.users_id'   => array_values($user_ids),
                        'glpi_plugin_vip_groups.isvip' => 1
                    ]
                ]) as $row) {
                    $vip_groups[(int)$row['id']] = [
                        'name'  => $row['name'] ?: 'VIP',
                        'color' => pgeservicos_ticket_view_safe_color($row['vip_color'] ?? ''),
                        'icon'  => preg_replace('/[^a-z0-9\\-\\s]/i', '', (string)($row['vip_icon'] ?? ''))
                    ];
                }
            }
        }

        if (empty($vip_groups)) {
            return null;
        }

        $first = reset($vip_groups);

        return [
            'name'   => $first['name'] ?? 'VIP',
            'color'  => $first['color'] ?? '',
            'icon'   => $first['icon'] ?? '',
            'groups' => array_values($vip_groups)
        ];
    }
}

if (!function_exists('pgeservicos_ticket_view_action_definitions')) {
    function pgeservicos_ticket_view_action_definitions(Ticket $ticket) {
        $definitions = [
            'answer' => [
                'label' => 'Responder',
                'description' => 'Adicionar um acompanhamento ao chamado.',
                'icon' => 'ti ti-message-reply'
            ],
            'task' => [
                'label' => 'Criar tarefa',
                'description' => 'Registrar uma tarefa vinculada ao chamado.',
                'icon' => 'ti ti-checkbox'
            ],
            'solution' => [
                'label' => 'Adicionar solução',
                'description' => 'Registrar uma solução e encaminhar o chamado conforme as regras do GLPI.',
                'icon' => 'ti ti-circle-check'
            ],
            'document' => [
                'label' => 'Adicionar documento',
                'description' => 'Anexar um arquivo ao chamado.',
                'icon' => 'ti ti-paperclip'
            ],
            'validation' => [
                'label' => 'Solicitar validação',
                'description' => 'Solicitar aprovação de um usuário.',
                'icon' => 'ti ti-user-check'
            ]
        ];
        $native_actions = $ticket->getTimelineItemtypes();
        $actions = [];

        foreach ($definitions as $key => $definition) {
            if (!isset($native_actions[$key]) || !empty($native_actions[$key]['hide_in_menu'])) {
                continue;
            }

            $actions[$key] = $definition;
        }

        return $actions;
    }
}

if (!function_exists('pgeservicos_ticket_view_actions')) {
    function pgeservicos_ticket_view_actions(Ticket $ticket, $native_url) {
        $actions = [];

        foreach ($ticket->getTimelineItemtypes() as $key => $action) {
            if (!empty($action['hide_in_menu'])) {
                continue;
            }

            $actions[] = [
                'key'   => preg_replace('/[^a-z0-9_-]/i', '', (string)$key),
                'label' => $action['label'] ?? $action['short_label'] ?? 'Ação',
                'icon'  => $action['icon'] ?? 'ti ti-plus',
                'url'   => $native_url
            ];
        }

        $actions[] = [
            'key'   => 'native',
            'label' => 'Abrir na tela nativa do GLPI',
            'icon'  => 'ti ti-external-link',
            'url'   => $native_url
        ];

        return $actions;
    }
}
