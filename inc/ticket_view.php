<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

if (!function_exists('pgeservicos_ticket_view_h')) {
    function pgeservicos_ticket_view_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('pgeservicos_ticket_view_clean_html')) {
    function pgeservicos_ticket_view_clean_html($content) {
        $content = trim((string)$content);

        if ($content === '') {
            return '<em>Sem conteúdo informado.</em>';
        }

        return Html::clean($content, false, 1);
    }
}

if (!function_exists('pgeservicos_ticket_view_date')) {
    function pgeservicos_ticket_view_date($date) {
        if (empty($date)) {
            return '-';
        }

        return Html::convDateTime($date);
    }
}

if (!function_exists('pgeservicos_ticket_view_dropdown_name')) {
    function pgeservicos_ticket_view_dropdown_name($table, $id) {
        $id = (int)$id;

        if ($id <= 0) {
            return '-';
        }

        $name = Dropdown::getDropdownName($table, $id);

        return $name !== '' ? $name : '-';
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

if (!function_exists('pgeservicos_ticket_view_service_level_rows')) {
    function pgeservicos_ticket_view_service_level_rows(Ticket $ticket) {
        $fields = $ticket->fields;

        return [
            ['field' => 'time_to_own', 'label' => 'Tempo para atendimento', 'value' => $fields['time_to_own'] ?? ''],
            ['field' => 'time_to_resolve', 'label' => 'Tempo para solução', 'value' => $fields['time_to_resolve'] ?? ''],
            [
                'field' => 'internal_time_to_own',
                'label' => 'Tempo interno para atendimento',
                'value' => $fields['internal_time_to_own'] ?? ''
            ],
            [
                'field' => 'internal_time_to_resolve',
                'label' => 'Tempo interno para solução',
                'value' => $fields['internal_time_to_resolve'] ?? ''
            ]
        ];
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
        if (empty($date)) {
            return false;
        }

        $tickets_id = (int)$tickets_id;
        $reference = $_SESSION['pgeservicos_meus_chamados_seen'][$tickets_id]
            ?? pgeservicos_get_recent_ticket_cutoff();

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
        $name = $item['name'] ?? $item['filename'] ?? 'Documento anexado';
        $filename = $item['filename'] ?? '';

        if ($filename !== '' && $filename !== $name) {
            return pgeservicos_ticket_view_h($name) . '<br><small>' . pgeservicos_ticket_view_h($filename) . '</small>';
        }

        return pgeservicos_ticket_view_h($name);
    }
}

if (!function_exists('pgeservicos_ticket_view_timeline')) {
    function pgeservicos_ticket_view_timeline(Ticket $ticket) {
        $tickets_id = (int)$ticket->fields['id'];
        $items = [];

        $items[] = [
            'kind'            => 'opening',
            'label'           => 'Abertura',
            'author'          => pgeservicos_ticket_view_user_name($ticket->fields['users_id_recipient'] ?? 0),
            'date'            => $ticket->fields['date'] ?? '',
            'content'         => pgeservicos_ticket_view_clean_html($ticket->fields['content'] ?? ''),
            'is_current_user' => (int)($ticket->fields['users_id_recipient'] ?? 0) === (int)Session::getLoginUserID(),
            'is_recent'       => false,
            'can_edit'        => false
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
            $content = $type_info['kind'] === 'document'
                ? pgeservicos_ticket_view_document_content($item)
                : pgeservicos_ticket_view_clean_html($item['content'] ?? '');

            if ($type_info['kind'] === 'validation') {
                $comments = trim((string)($item['comment_submission'] ?? $item['comment_validation'] ?? ''));

                if ($comments !== '') {
                    $content .= '<div class="pgeservicos-chamado-validation-comment">'
                        . pgeservicos_ticket_view_clean_html($comments)
                        . '</div>';
                }
            }

            $items[] = [
                'kind'            => $type_info['kind'],
                'label'           => $type_info['label'],
                'id'              => (int)($item['id'] ?? 0),
                'raw_content'     => (string)($item['content'] ?? ''),
                'solution_status' => $type_info['kind'] === 'solution' ? (int)($item['status'] ?? 0) : 0,
                'author'          => pgeservicos_ticket_view_user_name($users_id),
                'date'            => $date,
                'content'         => $content,
                'is_current_user' => $users_id > 0 && $users_id === (int)Session::getLoginUserID(),
                'is_recent'       => pgeservicos_ticket_view_is_recent_date($tickets_id, $date),
                'can_edit'        => !empty($item['can_edit'])
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
        $label = trim((string)$label);

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
