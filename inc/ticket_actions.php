<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

if (!function_exists('pgeservicos_ticket_action_redirect')) {
    function pgeservicos_ticket_action_redirect($tickets_id) {
        global $CFG_GLPI;

        Html::redirect(
            ($CFG_GLPI['root_doc'] ?? '')
            . '/plugins/pgeservicos/front/chamado.php?tickets_id='
            . (int)$tickets_id
        );
    }
}

if (!function_exists('pgeservicos_ticket_action_content')) {
    function pgeservicos_ticket_action_content($field = 'content') {
        return trim((string)($_POST[$field] ?? ''));
    }
}

if (!function_exists('pgeservicos_ticket_action_private_flag')) {
    function pgeservicos_ticket_action_private_flag() {
        return !empty($_POST['is_private']) ? 1 : 0;
    }
}

if (!function_exists('pgeservicos_ticket_action_assert_content')) {
    function pgeservicos_ticket_action_assert_content($content) {
        if ($content === '') {
            Session::addMessageAfterRedirect('Informe uma descrição antes de enviar.', false, ERROR);
            return false;
        }

        return true;
    }
}

if (!function_exists('pgeservicos_ticket_action_has_upload')) {
    function pgeservicos_ticket_action_has_upload($field = 'document') {
        return !empty($_FILES[$field])
            && (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }
}

if (!function_exists('pgeservicos_ticket_action_attach_upload')) {
    function pgeservicos_ticket_action_attach_upload(Ticket $ticket, $itemtype, $items_id, $field = 'document') {
        if (!pgeservicos_ticket_action_has_upload($field)) {
            return true;
        }

        $input = [
            'name'         => (string)($_FILES[$field]['name'] ?? ('Documento chamado ' . $ticket->getID())),
            'entities_id'  => (int)$ticket->fields['entities_id'],
            'is_recursive' => 0,
            'itemtype'     => $itemtype,
            'items_id'     => (int)$items_id
        ];

        $document = new Document();
        $document->check(-1, CREATE, $input);

        if (!Document::uploadDocument($input, $_FILES[$field])) {
            return false;
        }

        if (!$document->add($input)) {
            Session::addMessageAfterRedirect('Não foi possível anexar o arquivo enviado.', false, ERROR);
            return false;
        }

        return true;
    }
}

if (!function_exists('pgeservicos_ticket_action_mark_pending')) {
    function pgeservicos_ticket_action_mark_pending(Ticket $ticket) {
        if (empty($_POST['mark_pending'])) {
            return true;
        }

        $ticket->check((int)$ticket->getID(), UPDATE);

        if ($ticket->update([
            'id'     => (int)$ticket->getID(),
            'status' => Ticket::WAITING
        ])) {
            return true;
        }

        Session::addMessageAfterRedirect('A interação foi salva, mas não foi possível marcar o chamado como pendente.', false, ERROR);
        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_add_followup')) {
    function pgeservicos_ticket_action_add_followup(Ticket $ticket) {
        $content = pgeservicos_ticket_action_content();

        if (!pgeservicos_ticket_action_assert_content($content)) {
            return false;
        }

        $followup = new ITILFollowup();
        $input = [
            'itemtype'   => Ticket::getType(),
            'items_id'   => (int)$ticket->getID(),
            'content'    => $content,
            'is_private' => pgeservicos_ticket_action_private_flag(),
            'add'        => 1
        ];

        $followup->check(-1, CREATE, $input);

        $followups_id = $followup->add($input);

        if ($followups_id) {
            pgeservicos_ticket_action_attach_upload($ticket, ITILFollowup::getType(), $followups_id);
            pgeservicos_ticket_action_mark_pending($ticket);
            Session::addMessageAfterRedirect('Acompanhamento adicionado com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('Não foi possível adicionar o acompanhamento.', false, ERROR);
        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_add_task')) {
    function pgeservicos_ticket_action_add_task(Ticket $ticket) {
        $content = pgeservicos_ticket_action_content();

        if (!pgeservicos_ticket_action_assert_content($content)) {
            return false;
        }

        $task = new TicketTask();
        $input = [
            'tickets_id'  => (int)$ticket->getID(),
            'content'     => $content,
            'is_private'  => pgeservicos_ticket_action_private_flag(),
            'state'       => Planning::TODO,
            'users_id'    => Session::getLoginUserID(),
            'add'         => 1
        ];

        $task->check(-1, CREATE, $input);

        if (!empty($_POST['users_id_tech'])) {
            $input['users_id_tech'] = (int)$_POST['users_id_tech'];
        }

        if (!empty($_POST['groups_id_tech'])) {
            $input['groups_id_tech'] = (int)$_POST['groups_id_tech'];
        }

        if (!empty($_POST['end'])) {
            $input['end'] = str_replace('T', ' ', (string)$_POST['end']) . ':00';
        }

        $tasks_id = $task->add($input);

        if ($tasks_id) {
            pgeservicos_ticket_action_attach_upload($ticket, TicketTask::getType(), $tasks_id);
            pgeservicos_ticket_action_mark_pending($ticket);
            Session::addMessageAfterRedirect('Tarefa criada com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('Não foi possível criar a tarefa.', false, ERROR);
        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_add_solution')) {
    function pgeservicos_ticket_action_add_solution(Ticket $ticket) {
        $content = pgeservicos_ticket_action_content();

        if (!pgeservicos_ticket_action_assert_content($content)) {
            return false;
        }

        if (!$ticket->canSolve()) {
            Session::addMessageAfterRedirect('Você não tem permissão para solucionar este chamado.', false, ERROR);
            return false;
        }

        $solution = new ITILSolution();
        $input = [
            'itemtype' => Ticket::getType(),
            'items_id' => (int)$ticket->getID(),
            'content'  => $content,
            'add'      => 1
        ];

        $solution->check(-1, CREATE, $input);

        $solutions_id = $solution->add($input);

        if ($solutions_id) {
            pgeservicos_ticket_action_attach_upload($ticket, ITILSolution::getType(), $solutions_id);
            Session::addMessageAfterRedirect('Solução adicionada com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('Não foi possível adicionar a solução.', false, ERROR);
        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_add_validation')) {
    function pgeservicos_ticket_action_add_validation(Ticket $ticket) {
        $users_id_validate = (int)($_POST['users_id_validate'] ?? 0);

        if ($users_id_validate <= 0) {
            Session::addMessageAfterRedirect('Informe o ID do usuário validador.', false, ERROR);
            return false;
        }

        $validation = new TicketValidation();
        $input = [
            'tickets_id'          => (int)$ticket->getID(),
            'users_id'           => Session::getLoginUserID(),
            'users_id_validate'  => $users_id_validate,
            'comment_submission' => pgeservicos_ticket_action_content('comment_submission'),
            'add'                => 1
        ];

        $validation->check(-1, CREATE, $input);

        $validations_id = $validation->add($input);

        if ($validations_id) {
            pgeservicos_ticket_action_attach_upload($ticket, TicketValidation::getType(), $validations_id);
            Session::addMessageAfterRedirect('Solicitação de validação enviada com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('Não foi possível solicitar a validação.', false, ERROR);
        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_add_document')) {
    function pgeservicos_ticket_action_add_document(Ticket $ticket) {
        if (!pgeservicos_ticket_action_has_upload('document')) {
            Session::addMessageAfterRedirect('Selecione um arquivo para anexar.', false, ERROR);
            return false;
        }

        $input = [
            'name'         => trim((string)($_POST['document_name'] ?? '')),
            'entities_id'  => (int)$ticket->fields['entities_id'],
            'is_recursive' => 0,
            'itemtype'     => Ticket::getType(),
            'items_id'     => (int)$ticket->getID()
        ];

        if ($input['name'] === '') {
            $input['name'] = (string)($_FILES['document']['name'] ?? ('Documento chamado ' . $ticket->getID()));
        }

        $document = new Document();
        $document->check(-1, CREATE, $input);

        if (Document::uploadDocument($input, $_FILES['document']) && $document->add($input)) {
            Session::addMessageAfterRedirect('Documento anexado com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('Não foi possível anexar o documento.', false, ERROR);
        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_update_timeline')) {
    function pgeservicos_ticket_action_update_timeline(Ticket $ticket) {
        $timeline_type = preg_replace('/[^a-z_]/', '', (string)($_POST['timeline_type'] ?? ''));
        $timeline_id = (int)($_POST['timeline_id'] ?? 0);
        $content = pgeservicos_ticket_action_content();

        if ($timeline_id <= 0 || !pgeservicos_ticket_action_assert_content($content)) {
            return false;
        }

        if ($timeline_type === 'followup') {
            $item = new ITILFollowup();

            if (
                !$item->getFromDB($timeline_id)
                || $item->fields['itemtype'] !== Ticket::getType()
                || (int)$item->fields['items_id'] !== (int)$ticket->getID()
            ) {
                Session::addMessageAfterRedirect('Acompanhamento inválido para este chamado.', false, ERROR);
                return false;
            }
        } elseif ($timeline_type === 'task') {
            $item = new TicketTask();

            if (!$item->getFromDB($timeline_id) || (int)$item->fields['tickets_id'] !== (int)$ticket->getID()) {
                Session::addMessageAfterRedirect('Tarefa inválida para este chamado.', false, ERROR);
                return false;
            }
        } elseif ($timeline_type === 'solution') {
            $item = new ITILSolution();

            if (
                !$item->getFromDB($timeline_id)
                || $item->fields['itemtype'] !== Ticket::getType()
                || (int)$item->fields['items_id'] !== (int)$ticket->getID()
            ) {
                Session::addMessageAfterRedirect('Solução inválida para este chamado.', false, ERROR);
                return false;
            }
        } else {
            Session::addMessageAfterRedirect('Tipo de atualização inválido.', false, ERROR);
            return false;
        }

        $item->check($timeline_id, UPDATE);

        if ($item->update([
            'id'      => $timeline_id,
            'content' => $content
        ])) {
            pgeservicos_ticket_action_attach_upload($ticket, $item->getType(), $timeline_id);
            Session::addMessageAfterRedirect('Item atualizado com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('Não foi possível atualizar o item.', false, ERROR);
        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_solution_approval')) {
    function pgeservicos_ticket_action_solution_approval(Ticket $ticket) {
        $approval = (string)($_POST['approval'] ?? '');
        $content = pgeservicos_ticket_action_content();

        if (!$ticket->canApprove()) {
            Session::addMessageAfterRedirect('Você não tem permissão para aprovar ou reprovar a solução.', false, ERROR);
            return false;
        }

        if ($approval === 'reject' && $content === '') {
            Session::addMessageAfterRedirect('Informe o motivo da reprovação da solução.', false, ERROR);
            return false;
        }

        $followup = new ITILFollowup();
        $input = [
            'itemtype'   => Ticket::getType(),
            'items_id'   => (int)$ticket->getID(),
            'content'    => $content,
            'is_private' => 0,
            'add'        => 1
        ];

        if ($approval === 'approve') {
            $input['add_close'] = 1;
        } elseif ($approval === 'reject') {
            $input['add_reopen'] = 1;
        } else {
            Session::addMessageAfterRedirect('Ação de solução inválida.', false, ERROR);
            return false;
        }

        $followup->check(-1, CREATE, $input);

        if ($followup->add($input)) {
            Session::addMessageAfterRedirect($approval === 'approve' ? 'Solução aprovada com sucesso.' : 'Solução reprovada com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('Não foi possível registrar a decisão sobre a solução.', false, ERROR);
        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_reopen_ticket')) {
    function pgeservicos_ticket_action_reopen_ticket(Ticket $ticket) {
        $content = pgeservicos_ticket_action_content();

        if ((int)$ticket->fields['status'] !== Ticket::CLOSED) {
            Session::addMessageAfterRedirect('Este chamado não está fechado.', false, ERROR);
            return false;
        }

        if (!pgeservicos_ticket_action_assert_content($content)) {
            return false;
        }

        $ticket->check((int)$ticket->getID(), UPDATE);

        $followup = new ITILFollowup();
        $input = [
            'itemtype'   => Ticket::getType(),
            'items_id'   => (int)$ticket->getID(),
            'content'    => $content,
            'is_private' => 0,
            'add_reopen' => 1,
            '_add'       => 1
        ];

        $followup->check(-1, CREATE, $input);

        if (!$followup->add($input)) {
            Session::addMessageAfterRedirect('Não foi possível registrar a justificativa de reabertura.', false, ERROR);
            return false;
        }

        $ticket->getFromDB((int)$ticket->getID());

        if ((int)$ticket->fields['status'] === Ticket::CLOSED) {
            $ticket->update([
                'id'     => (int)$ticket->getID(),
                'status' => Ticket::INCOMING
            ]);
        }

        Session::addMessageAfterRedirect('Chamado reaberto com sucesso.');
        return true;
    }
}

if (!function_exists('pgeservicos_ticket_action_cancel_ticket')) {
    function pgeservicos_ticket_action_cancel_ticket(Ticket $ticket) {
        $content = pgeservicos_ticket_action_content();

        if ((int)$ticket->fields['status'] === Ticket::CLOSED) {
            Session::addMessageAfterRedirect('Este chamado já está fechado.', false, ERROR);
            return false;
        }

        $target_status = 0;

        if (Ticket::isAllowedStatus((int)$ticket->fields['status'], Ticket::CLOSED)) {
            $target_status = Ticket::CLOSED;
        } elseif (Ticket::isAllowedStatus((int)$ticket->fields['status'], Ticket::SOLVED)) {
            $target_status = Ticket::SOLVED;
        }

        if ($target_status <= 0) {
            Session::addMessageAfterRedirect('Seu perfil não permite cancelar este chamado a partir do status atual.', false, ERROR);
            return false;
        }

        if (!pgeservicos_ticket_action_assert_content($content)) {
            return false;
        }

        $ticket->check((int)$ticket->getID(), UPDATE);

        $followup = new ITILFollowup();
        $input = [
            'itemtype'   => Ticket::getType(),
            'items_id'   => (int)$ticket->getID(),
            'content'    => $content,
            'is_private' => 0,
            'add'        => 1
        ];

        $followup->check(-1, CREATE, $input);

        if (!$followup->add($input)) {
            Session::addMessageAfterRedirect('Não foi possível registrar a justificativa do cancelamento.', false, ERROR);
            return false;
        }

        if (!$ticket->update([
            'id'     => (int)$ticket->getID(),
            'status' => $target_status
        ])) {
            Session::addMessageAfterRedirect('A justificativa foi registrada, mas não foi possível cancelar o chamado.', false, ERROR);
            return false;
        }

        Session::addMessageAfterRedirect('Chamado cancelado com sucesso.');
        return true;
    }
}

if (!function_exists('pgeservicos_ticket_action_update_details')) {
    function pgeservicos_ticket_action_update_details(Ticket $ticket) {
        $ticket->check((int)$ticket->getID(), UPDATE);

        $input = [
            'id'       => (int)$ticket->getID(),
            'type'     => (int)($_POST['type'] ?? $ticket->fields['type']),
            'status'   => (int)($_POST['status'] ?? $ticket->fields['status']),
            'impact'   => (int)($_POST['impact'] ?? $ticket->fields['impact']),
            'urgency'  => (int)($_POST['urgency'] ?? $ticket->fields['urgency']),
            'priority' => (int)($_POST['priority'] ?? $ticket->fields['priority'])
        ];

        foreach (['time_to_own', 'time_to_resolve', 'internal_time_to_own', 'internal_time_to_resolve'] as $field) {
            if (array_key_exists($field, $_POST)) {
                $value = trim((string)$_POST[$field]);
                $input[$field] = $value !== '' ? str_replace('T', ' ', $value) . ':00' : null;
            }
        }

        if ($ticket->update($input)) {
            Session::addMessageAfterRedirect('Detalhes atualizados com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('Não foi possível atualizar os detalhes.', false, ERROR);
        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_actor_key')) {
    function pgeservicos_ticket_action_actor_key($role) {
        $map = [
            'requester' => 'requester',
            'observer'  => 'observer',
            'assign'    => 'assign'
        ];

        return $map[$role] ?? '';
    }
}

if (!function_exists('pgeservicos_ticket_action_actor_is_accessible')) {
    function pgeservicos_ticket_action_actor_is_accessible($kind, $actor_id, Ticket $ticket = null) {
        global $DB;

        $kind = (string)$kind;
        $actor_id = (int)$actor_id;

        if ($actor_id <= 0 || !function_exists('pgeservicos_get_accessible_entities')) {
            return false;
        }

        $accessible_entities = pgeservicos_get_accessible_entities();

        if (empty($accessible_entities)) {
            return false;
        }

        if ($kind === 'user') {
            foreach ($DB->request([
                'COUNT' => 'cpt',
                'FROM'  => 'glpi_users',
                'INNER JOIN' => [
                    'glpi_profiles_users' => [
                        'ON' => [
                            'glpi_profiles_users' => 'users_id',
                            'glpi_users'          => 'id'
                        ]
                    ]
                ],
                'WHERE' => [
                    'glpi_users.id' => $actor_id,
                    'glpi_users.is_deleted' => 0,
                    'glpi_users.is_active' => 1,
                    'glpi_profiles_users.entities_id' => $accessible_entities
                ]
            ]) as $row) {
                return (int)($row['cpt'] ?? 0) > 0;
            }

            return false;
        }

        if ($kind === 'group') {
            $group_entities = $accessible_entities;

            if ($ticket !== null) {
                $ticket_entity = (int)($ticket->fields['entities_id'] ?? -1);

                if ($ticket_entity >= 0) {
                    foreach (getAncestorsOf('glpi_entities', $ticket_entity) as $ancestor_id) {
                        $group_entities[(int)$ancestor_id] = (int)$ancestor_id;
                    }
                }
            }

            foreach ($DB->request([
                'SELECT' => ['id', 'entities_id', 'is_recursive'],
                'FROM'  => 'glpi_groups',
                'WHERE' => [
                    'id' => $actor_id,
                    'is_deleted' => 0,
                    'entities_id' => array_values(array_unique($group_entities))
                ]
            ]) as $row) {
                $group_entity = (int)($row['entities_id'] ?? -1);

                return in_array($group_entity, $accessible_entities, true) || !empty($row['is_recursive']);
            }

            return false;
        }

        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_add_actor')) {
    function pgeservicos_ticket_action_add_actor(Ticket $ticket) {
        $ticket->check((int)$ticket->getID(), UPDATE);

        if (function_exists('pgeservicos_ticket_view_can_manage_actors') && !pgeservicos_ticket_view_can_manage_actors($ticket)) {
            Session::addMessageAfterRedirect('Você não tem permissão para alterar os atores deste chamado.', false, ERROR);
            return false;
        }

        $role = pgeservicos_ticket_action_actor_key((string)($_POST['actor_role'] ?? ''));
        $actors = $_POST['actor_values'] ?? [];

        if (!is_array($actors)) {
            $actors = [$actors];
        }

        if ($role === '' || empty($actors)) {
            Session::addMessageAfterRedirect('Informe o papel e ao menos um usuário ou grupo.', false, ERROR);
            return false;
        }

        $types = [
            'requester' => CommonITILActor::REQUESTER,
            'observer'  => CommonITILActor::OBSERVER,
            'assign'    => CommonITILActor::ASSIGN
        ];
        $added = 0;

        foreach ($actors as $actor_value) {
            if (!preg_match('/^(user|group):(\\d+)$/', (string)$actor_value, $matches)) {
                continue;
            }

            $kind = $matches[1];
            $actor_id = (int)$matches[2];

            if ($actor_id <= 0) {
                continue;
            }

            if (!pgeservicos_ticket_action_actor_is_accessible($kind, $actor_id, $ticket)) {
                continue;
            }

            $already = false;

            if ($kind === 'user') {
                foreach ($ticket->getUsers($types[$role]) as $actor) {
                    if ((int)($actor['users_id'] ?? 0) === $actor_id) {
                        $already = true;
                        break;
                    }
                }
            } else {
                foreach ($ticket->getGroups($types[$role]) as $actor) {
                    if ((int)($actor['groups_id'] ?? 0) === $actor_id) {
                        $already = true;
                        break;
                    }
                }
            }

            if ($already) {
                continue;
            }

            $input = Toolbox::addslashes_deep($ticket->fields);
            $input['id'] = (int)$ticket->getID();
            $input['_itil_' . $role] = [
                '_type' => $kind,
                'use_notification' => 1
            ];

            if ($kind === 'user') {
                $input['_itil_' . $role]['users_id'] = $actor_id;
            } else {
                $input['_itil_' . $role]['groups_id'] = $actor_id;
            }

            if ($ticket->update($input)) {
                $ticket->getFromDB((int)$ticket->getID());
                $added++;
            }
        }

        if ($added > 0) {
            Session::addMessageAfterRedirect('Ator adicionado com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('Nenhum ator novo foi adicionado.', false, WARNING);
        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_remove_actor')) {
    function pgeservicos_ticket_action_remove_actor(Ticket $ticket) {
        $ticket->check((int)$ticket->getID(), UPDATE);

        if (function_exists('pgeservicos_ticket_view_can_manage_actors') && !pgeservicos_ticket_view_can_manage_actors($ticket)) {
            Session::addMessageAfterRedirect('Você não tem permissão para alterar os atores deste chamado.', false, ERROR);
            return false;
        }

        $kind = (string)($_POST['actor_kind'] ?? '');
        $link_id = (int)($_POST['link_id'] ?? 0);

        if ($link_id <= 0 || !in_array($kind, ['user', 'group'], true)) {
            Session::addMessageAfterRedirect('Ator inválido.', false, ERROR);
            return false;
        }

        $link = $kind === 'user' ? new Ticket_User() : new Group_Ticket();

        if (!$link->getFromDB($link_id) || (int)$link->fields['tickets_id'] !== (int)$ticket->getID()) {
            Session::addMessageAfterRedirect('Ator não pertence a este chamado.', false, ERROR);
            return false;
        }

        $link->check($link_id, DELETE);

        if ($link->delete(['id' => $link_id])) {
            Session::addMessageAfterRedirect('Ator removido com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('Não foi possível remover o ator.', false, ERROR);
        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_handle')) {
    function pgeservicos_ticket_action_handle(Ticket $ticket, $action) {
        $action = preg_replace('/[^a-z_]/', '', (string)$action);

        switch ($action) {
            case 'add_followup':
                return pgeservicos_ticket_action_add_followup($ticket);
            case 'add_task':
                return pgeservicos_ticket_action_add_task($ticket);
            case 'add_solution':
                return pgeservicos_ticket_action_add_solution($ticket);
            case 'add_validation':
                return pgeservicos_ticket_action_add_validation($ticket);
            case 'add_document':
                return pgeservicos_ticket_action_add_document($ticket);
            case 'update_timeline':
                return pgeservicos_ticket_action_update_timeline($ticket);
            case 'update_details':
                return pgeservicos_ticket_action_update_details($ticket);
            case 'add_actor':
                return pgeservicos_ticket_action_add_actor($ticket);
            case 'remove_actor':
                return pgeservicos_ticket_action_remove_actor($ticket);
            case 'solution_approval':
                return pgeservicos_ticket_action_solution_approval($ticket);
            case 'reopen_ticket':
                return pgeservicos_ticket_action_reopen_ticket($ticket);
            case 'cancel_ticket':
                return pgeservicos_ticket_action_cancel_ticket($ticket);
        }

        Session::addMessageAfterRedirect('Ação inválida.', false, ERROR);
        return false;
    }
}
