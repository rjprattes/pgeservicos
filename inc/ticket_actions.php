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
        $content = (string)($_POST[$field] ?? '');

        if (function_exists('pgeservicos_ticket_view_normalize_text')) {
            return trim(pgeservicos_ticket_view_normalize_text($content));
        }

        return trim(str_replace(["\\r\\n", "\\n", "\\r"], ["\n", "\n", "\n"], $content));
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

if (!function_exists('pgeservicos_ticket_action_upload_limit_bytes')) {
    function pgeservicos_ticket_action_upload_limit_bytes() {
        global $CFG_GLPI;

        $document_limit = (int)($CFG_GLPI['document_max_size'] ?? 0) * 1024 * 1024;
        $php_limit = class_exists('Toolbox') && method_exists('Toolbox', 'getPhpUploadSizeLimit')
            ? (int)Toolbox::getPhpUploadSizeLimit()
            : 0;
        $limits = array_filter([$document_limit, $php_limit], static function ($value) {
            return (int)$value > 0;
        });

        return !empty($limits) ? min($limits) : 0;
    }
}

if (!function_exists('pgeservicos_ticket_action_format_size')) {
    function pgeservicos_ticket_action_format_size($bytes) {
        $bytes = (int)$bytes;

        if (class_exists('Toolbox')) {
            return Toolbox::getSize($bytes);
        }

        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }
}

if (!function_exists('pgeservicos_ticket_action_upload_too_large_message')) {
    function pgeservicos_ticket_action_upload_too_large_message($filename = '', $size = 0) {
        $limit = pgeservicos_ticket_action_upload_limit_bytes();
        $message = 'Arquivo muito grande.';

        if ($limit > 0) {
            $message .= ' Limite máximo: ' . pgeservicos_ticket_action_format_size($limit) . '.';
        }

        if ((int)$size > 0) {
            $message .= ' Arquivo enviado: ' . pgeservicos_ticket_action_format_size($size) . '.';
        }

        if (trim((string)$filename) !== '') {
            $message .= ' Arquivo: ' . trim((string)$filename) . '.';
        }

        $message .= ' Escolha um arquivo menor antes de enviar.';

        return $message;
    }
}

if (!function_exists('pgeservicos_ticket_action_validate_upload')) {
    function pgeservicos_ticket_action_validate_upload($field = 'document') {
        if (empty($_FILES[$field])) {
            return true;
        }

        $file = $_FILES[$field];
        $names = is_array($file['name'] ?? null) ? $file['name'] : [$file['name'] ?? ''];
        $sizes = is_array($file['size'] ?? null) ? $file['size'] : [$file['size'] ?? 0];
        $errors = is_array($file['error'] ?? null) ? $file['error'] : [$file['error'] ?? UPLOAD_ERR_NO_FILE];
        $limit = pgeservicos_ticket_action_upload_limit_bytes();

        foreach ($errors as $index => $error) {
            $error = (int)$error;
            $name = (string)($names[$index] ?? '');
            $size = (int)($sizes[$index] ?? 0);

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($limit > 0 && $size > $limit)) {
                Session::addMessageAfterRedirect(pgeservicos_ticket_action_upload_too_large_message($name, $size), false, ERROR);
                return false;
            }

            if ($error !== UPLOAD_ERR_OK) {
                Session::addMessageAfterRedirect('Arquivo inválido ou acima do tamanho permitido.', false, ERROR);
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('pgeservicos_ticket_action_attach_upload')) {
    function pgeservicos_ticket_action_attach_upload(Ticket $ticket, $itemtype, $items_id, $field = 'document') {
        if (!pgeservicos_ticket_action_has_upload($field)) {
            return true;
        }

        $itemtype = (string)$itemtype;
        $items_id = (int)$items_id;

        if ($itemtype === '' || !class_exists($itemtype) || $items_id <= 0) {
            Session::addMessageAfterRedirect('Tipo de item inválido para anexar arquivo.', false, ERROR);
            return false;
        }

        if (!pgeservicos_ticket_action_validate_upload($field)) {
            return false;
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

        if (!pgeservicos_ticket_action_validate_upload('document')) {
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

        if (!pgeservicos_ticket_action_validate_upload('document')) {
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

        if (!empty($_POST['users_id_tech'])) {
            $users_id_tech = (int)$_POST['users_id_tech'];
            $user = new User();

            if ($users_id_tech <= 0 || !$user->getFromDB($users_id_tech) || (int)($user->fields['is_deleted'] ?? 0) === 1 || (int)($user->fields['is_active'] ?? 1) === 0) {
                Session::addMessageAfterRedirect('Usuário atribuído inválido.', false, ERROR);
                return false;
            }

            $input['users_id_tech'] = $users_id_tech;
        }

        if (!empty($_POST['groups_id_tech'])) {
            $groups_id_tech = (int)$_POST['groups_id_tech'];
            $group = new Group();

            if (
                $groups_id_tech <= 0
                || !$group->getFromDB($groups_id_tech)
                || (int)($group->fields['is_deleted'] ?? 0) === 1
                || (int)($group->fields['is_task'] ?? 0) !== 1
            ) {
                Session::addMessageAfterRedirect('Grupo atribuído inválido.', false, ERROR);
                return false;
            }

            $input['groups_id_tech'] = $groups_id_tech;
        }

        if (!empty($_POST['end'])) {
            $end = str_replace('T', ' ', trim((string)$_POST['end']));

            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $end) || strtotime($end) === false) {
                Session::addMessageAfterRedirect('Data de conclusão inválida.', false, ERROR);
                return false;
            }

            $input['end'] = $end . ':00';
        }

        if (isset($_POST['actiontime']) && $_POST['actiontime'] !== '') {
            $actiontime = (int)$_POST['actiontime'];
            $allowed_actiontimes = [0, 300, 600, 900, 1800, 2700, 3600, 5400, 7200, 10800, 14400, 28800];

            if (!in_array($actiontime, $allowed_actiontimes, true)) {
                Session::addMessageAfterRedirect('Duração inválida para a tarefa.', false, ERROR);
                return false;
            }

            if ($actiontime > 0) {
                $input['actiontime'] = $actiontime;

                if (!empty($input['end'])) {
                    $input['begin'] = date('Y-m-d H:i:s', strtotime($input['end']) - $actiontime);
                }
            }
        }

        $task->check(-1, CREATE, $input);

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

        if (!pgeservicos_ticket_action_validate_upload('document')) {
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

        $user = new User();

        if ($users_id_validate <= 0 || !$user->getFromDB($users_id_validate) || (int)($user->fields['is_deleted'] ?? 0) === 1 || (int)($user->fields['is_active'] ?? 1) === 0) {
            Session::addMessageAfterRedirect('Selecione um usuário validador válido.', false, ERROR);
            return false;
        }

        if (!pgeservicos_ticket_action_validate_upload('document')) {
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

if (!function_exists('pgeservicos_ticket_action_link_existing_document')) {
    function pgeservicos_ticket_action_link_existing_document(Ticket $ticket, $documents_id) {
        global $DB;

        $documents_id = (int)$documents_id;

        if ($documents_id <= 0) {
            Session::addMessageAfterRedirect('Documento inválido.', false, ERROR);
            return false;
        }

        if (!$ticket->canAddItem('Document')) {
            Session::addMessageAfterRedirect('Você não tem permissão para vincular documentos a este chamado.', false, ERROR);
            return false;
        }

        $document = new Document();

        if (!$document->getFromDB($documents_id) || (int)($document->fields['is_deleted'] ?? 0) === 1) {
            Session::addMessageAfterRedirect('Documento inválido.', false, ERROR);
            return false;
        }

        if (!$document->can($documents_id, READ)) {
            Session::addMessageAfterRedirect('Você não tem permissão para vincular este documento.', false, ERROR);
            return false;
        }

        $already_linked = $DB->request([
            'COUNT' => 'cpt',
            'FROM'  => 'glpi_documents_items',
            'WHERE' => [
                'documents_id' => $documents_id,
                'itemtype'     => Ticket::getType(),
                'items_id'     => (int)$ticket->getID()
            ]
        ])->current();

        if ((int)($already_linked['cpt'] ?? 0) > 0) {
            Session::addMessageAfterRedirect('Este documento já está vinculado ao chamado.', false, ERROR);
            return false;
        }

        $document_item = new Document_Item();
        $input = [
            'documents_id' => $documents_id,
            'itemtype'     => Ticket::getType(),
            'items_id'     => (int)$ticket->getID(),
            'users_id'     => (int)Session::getLoginUserID()
        ];

        $document_item->check(-1, CREATE, $input);

        if ($document_item->add($input)) {
            Session::addMessageAfterRedirect('Documento vinculado ao chamado com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('Não foi possível anexar o documento ao chamado.', false, ERROR);
        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_add_document')) {
    function pgeservicos_ticket_action_add_document(Ticket $ticket) {
        $documents_id = (int)($_POST['documents_id'] ?? 0);
        $has_upload = pgeservicos_ticket_action_has_upload('document');

        if ($documents_id <= 0 && !$has_upload) {
            Session::addMessageAfterRedirect('Nenhum documento ou arquivo foi informado.', false, ERROR);
            return false;
        }

        if (!pgeservicos_ticket_action_validate_upload('document')) {
            return false;
        }

        if ($documents_id > 0 && $has_upload) {
            Session::addMessageAfterRedirect('Selecione um documento existente ou envie um novo arquivo, não ambos.', false, ERROR);
            return false;
        }

        if ($documents_id > 0) {
            return pgeservicos_ticket_action_link_existing_document($ticket, $documents_id);
        }

        if (!$ticket->canAddItem('Document')) {
            Session::addMessageAfterRedirect('Você não tem permissão para anexar documentos a este chamado.', false, ERROR);
            return false;
        }

        $input = [
            'name'         => (string)($_FILES['document']['name'] ?? ('Documento chamado ' . $ticket->getID())),
            'entities_id'  => (int)$ticket->fields['entities_id'],
            'is_recursive' => 0,
            'itemtype'     => Ticket::getType(),
            'items_id'     => (int)$ticket->getID()
        ];

        $document = new Document();
        $document->check(-1, CREATE, $input);

        if (!Document::uploadDocument($input, $_FILES['document'])) {
            Session::addMessageAfterRedirect('Arquivo inválido ou acima do tamanho permitido.', false, ERROR);
            return false;
        }

        if ($document->add($input)) {
            Session::addMessageAfterRedirect('Documento anexado com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('Não foi possível anexar o documento.', false, ERROR);
        return false;
    }
}


if (!function_exists('pgeservicos_ticket_action_timeline_item_belongs')) {
    function pgeservicos_ticket_action_timeline_item_belongs(Ticket $ticket, CommonDBTM $item, $itemtype) {
        $tickets_id = (int)$ticket->getID();

        if ($itemtype === Document_Item::getType()) {
            return (string)($item->fields['itemtype'] ?? '') === Ticket::getType()
                && (int)($item->fields['items_id'] ?? 0) === $tickets_id;
        }

        if (in_array($itemtype, [ITILFollowup::getType(), ITILSolution::getType()], true)) {
            return (string)($item->fields['itemtype'] ?? '') === Ticket::getType()
                && (int)($item->fields['items_id'] ?? 0) === $tickets_id;
        }

        if ($itemtype === TicketTask::getType()) {
            return (int)($item->fields['tickets_id'] ?? 0) === $tickets_id;
        }

        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_delete_timeline_item')) {
    function pgeservicos_ticket_action_delete_timeline_item(Ticket $ticket) {
        $itemtype = (string)($_POST['itemtype'] ?? '');
        $items_id = (int)($_POST['items_id'] ?? 0);
        $allowed = [
            ITILFollowup::getType(),
            ITILSolution::getType(),
            TicketTask::getType(),
            Document_Item::getType()
        ];

        if ($items_id <= 0 || !in_array($itemtype, $allowed, true) || !class_exists($itemtype)) {
            Session::addMessageAfterRedirect('Item inválido.', false, ERROR);
            return false;
        }

        $item = new $itemtype();

        if (!$item instanceof CommonDBTM || !$item->getFromDB($items_id)) {
            Session::addMessageAfterRedirect('Item inválido.', false, ERROR);
            return false;
        }

        if (method_exists($item, 'setParentItem')) {
            $item->setParentItem($ticket);
        }

        if (!pgeservicos_ticket_action_timeline_item_belongs($ticket, $item, $itemtype)) {
            Session::addMessageAfterRedirect('O item não pertence a este chamado.', false, ERROR);
            return false;
        }

        if (!$item->can($items_id, DELETE)) {
            Session::addMessageAfterRedirect('Você não tem permissão para excluir este item.', false, ERROR);
            return false;
        }

        if ($item->delete(['id' => $items_id])) {
            Session::addMessageAfterRedirect($itemtype === Document_Item::getType() ? 'Documento desvinculado com sucesso.' : 'Item excluído com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('Este item não pode ser excluído.', false, ERROR);
        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_delete_attachment')) {
    function pgeservicos_ticket_action_delete_attachment(Ticket $ticket) {
        $itemtype = (string)($_POST['itemtype'] ?? '');
        $items_id = (int)($_POST['items_id'] ?? 0);
        $documents_id = (int)($_POST['documents_id'] ?? 0);
        $document_items_id = (int)($_POST['document_items_id'] ?? 0);
        $allowed = [
            ITILFollowup::getType(),
            ITILSolution::getType(),
            TicketTask::getType()
        ];

        if ($itemtype === '' || !in_array($itemtype, $allowed, true) || !class_exists($itemtype) || $items_id <= 0 || $documents_id <= 0 || $document_items_id <= 0) {
            Session::addMessageAfterRedirect('Anexo inválido.', false, ERROR);
            return false;
        }

        $item = new $itemtype();

        if (!$item instanceof CommonDBTM || !$item->getFromDB($items_id)) {
            Session::addMessageAfterRedirect('Item da linha do tempo inválido.', false, ERROR);
            return false;
        }

        if (method_exists($item, 'setParentItem')) {
            $item->setParentItem($ticket);
        }

        if (!pgeservicos_ticket_action_timeline_item_belongs($ticket, $item, $itemtype)) {
            Session::addMessageAfterRedirect('O anexo não pertence a este chamado.', false, ERROR);
            return false;
        }

        $document_item = new Document_Item();

        if (!$document_item->getFromDB($document_items_id)) {
            Session::addMessageAfterRedirect('Vínculo do anexo inválido.', false, ERROR);
            return false;
        }

        if (
            (int)($document_item->fields['documents_id'] ?? 0) !== $documents_id
            || (string)($document_item->fields['itemtype'] ?? '') !== $itemtype
            || (int)($document_item->fields['items_id'] ?? 0) !== $items_id
        ) {
            Session::addMessageAfterRedirect('O anexo não pertence a este item.', false, ERROR);
            return false;
        }

        if (!$document_item->can($document_items_id, DELETE)) {
            Session::addMessageAfterRedirect('Você não tem permissão para remover este anexo.', false, ERROR);
            return false;
        }

        if ($document_item->delete(['id' => $document_items_id])) {
            Session::addMessageAfterRedirect('Anexo removido com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('Não foi possível remover o anexo.', false, ERROR);
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

        if (!pgeservicos_ticket_action_validate_upload('document')) {
            return false;
        }

        $type_labels = [
            'opening'  => 'mensagem de abertura',
            'followup' => 'acompanhamento',
            'task'     => 'tarefa',
            'solution' => 'solução'
        ];

        if ($timeline_type === 'opening') {
            $item = $ticket;

            if ($timeline_id !== (int)$ticket->getID()) {
                Session::addMessageAfterRedirect('Abertura inválida para este chamado.', false, ERROR);
                return false;
            }
        } elseif ($timeline_type === 'followup') {
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

        $label = $type_labels[$timeline_type] ?? 'item';

        if (!$item->can($timeline_id, UPDATE)) {
            Session::addMessageAfterRedirect('Você não tem permissão para editar esta ' . $label . '.', false, ERROR);
            return false;
        }

        $current_content = trim((string)($item->fields['content'] ?? ''));
        $has_upload = pgeservicos_ticket_action_has_upload('document');
        $opening_name = '';
        $opening_author_id = 0;
        $opening_author_changed = false;

        if ($timeline_type === 'opening') {
            $opening_name = trim(pgeservicos_ticket_view_decode_text(strip_tags((string)($_POST['opening_name'] ?? ''))));
            $current_opening_name = trim(pgeservicos_ticket_view_decode_text((string)($item->fields['name'] ?? '')));
            $current_opening_author_id = (int)($item->fields['users_id_recipient'] ?? 0);
            $opening_author_raw = trim((string)($_POST['opening_users_id_recipient'] ?? ''));
            $opening_author_id = $opening_author_raw === '' ? $current_opening_author_id : (int)$opening_author_raw;
            $opening_author_changed = $opening_author_id !== $current_opening_author_id;

            if ($opening_name === '') {
                Session::addMessageAfterRedirect('Informe o título do chamado.', false, ERROR);
                return false;
            }

            if ($opening_author_changed) {
                if ($opening_author_id <= 0 || !pgeservicos_ticket_action_actor_is_accessible('user', $opening_author_id, $ticket)) {
                    Session::addMessageAfterRedirect('Usuário informado em Por é inválido ou inacessível para este chamado.', false, ERROR);
                    return false;
                }
            }
        }

        $has_opening_change = $timeline_type === 'opening'
            && ($opening_name !== $current_opening_name || $opening_author_changed);

        if ($current_content === $content && !$has_upload && !$has_opening_change) {
            Session::addMessageAfterRedirect('Nenhuma alteração detectada.');
            return true;
        }

        $input = [
            'id'      => $timeline_id,
            'content' => $content,
            '_update' => 1
        ];

        if ($timeline_type === 'opening') {
            $input['name'] = $opening_name;
            $input['users_id_recipient'] = $opening_author_id;
        }

        if ($item->update($input)) {
            $attachment_itemtype = [
                'opening'  => Ticket::getType(),
                'followup' => ITILFollowup::getType(),
                'task'     => TicketTask::getType(),
                'solution' => ITILSolution::getType(),
            ][$timeline_type] ?? '';

            if (!pgeservicos_ticket_action_attach_upload($ticket, $attachment_itemtype, $timeline_id)) {
                Session::addMessageAfterRedirect('A mensagem foi atualizada, mas não foi possível anexar o arquivo enviado.', false, ERROR);
                return false;
            }

            Session::addMessageAfterRedirect('Mensagem atualizada com sucesso.');
            return true;
        }

        Session::addMessageAfterRedirect('O GLPI recusou a atualização da ' . $label . '. Verifique se o conteúdo é válido e se seu perfil pode alterar esse item.', false, ERROR);
        return false;
    }
}

if (!function_exists('pgeservicos_ticket_action_solution_decision_content')) {
    function pgeservicos_ticket_action_solution_decision_content($approval, $comment) {
        $title = $approval === 'approve' ? 'Solução aprovada' : 'Solução reprovada';
        $comment = trim((string)$comment);
        $html = '<p><strong>' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></p>';

        if ($comment !== '') {
            $html .= '<p>' . nl2br(htmlspecialchars($comment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
        }

        return $html;
    }
}

if (!function_exists('pgeservicos_ticket_action_solution_approval')) {
    function pgeservicos_ticket_action_solution_approval(Ticket $ticket) {
        $approval = (string)($_POST['approval'] ?? '');
        $comment = pgeservicos_ticket_action_content();

        if (!$ticket->canApprove()) {
            Session::addMessageAfterRedirect('Você não tem permissão para aprovar ou reprovar a solução.', false, ERROR);
            return false;
        }

        if (!pgeservicos_ticket_action_validate_upload('document')) {
            return false;
        }

        if ($approval === 'reject' && $comment === '') {
            Session::addMessageAfterRedirect('Informe o motivo da reprovação da solução.', false, ERROR);
            return false;
        }

        $followup = new ITILFollowup();
        $input = [
            'itemtype'   => Ticket::getType(),
            'items_id'   => (int)$ticket->getID(),
            'content'    => pgeservicos_ticket_action_solution_decision_content($approval, $comment),
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
        $followups_id = $followup->add($input);

        if ($followups_id) {
            pgeservicos_ticket_action_attach_upload($ticket, ITILFollowup::getType(), $followups_id);
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

if (!function_exists('pgeservicos_ticket_action_trash_ticket')) {
    function pgeservicos_ticket_action_trash_ticket(Ticket $ticket) {
        $tickets_id = (int)$ticket->getID();

        if (!$ticket->can($tickets_id, DELETE)) {
            Session::addMessageAfterRedirect('Você não tem permissão para mandar este chamado para a lixeira.', false, ERROR);
            return false;
        }

        if ($ticket->isField('is_deleted') && (int)($ticket->fields['is_deleted'] ?? 0) === 1) {
            Session::addMessageAfterRedirect('Este chamado já está na lixeira.', false, WARNING);
            return 'tickets_list';
        }

        if (!$ticket->maybeDeleted()) {
            Session::addMessageAfterRedirect('Este chamado não pode ser enviado para a lixeira.', false, ERROR);
            return false;
        }

        if ($ticket->delete(['id' => $tickets_id])) {
            Session::addMessageAfterRedirect('Chamado enviado para a lixeira.');
            return 'tickets_list';
        }

        Session::addMessageAfterRedirect('Não foi possível mandar este chamado para a lixeira.', false, ERROR);
        return false;
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

        foreach (['itilcategories_id', 'requesttypes_id', 'locations_id'] as $field) {
            if (array_key_exists($field, $_POST)) {
                $input[$field] = (int)$_POST[$field];
            }
        }

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

        if ((int)($ticket->fields['status'] ?? 0) === Ticket::CLOSED && !in_array($action, ['reopen_ticket', 'trash_ticket'], true)) {
            Session::addMessageAfterRedirect('Chamado fechado: esta ação não está disponível.', false, ERROR);
            return false;
        }

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
            case 'delete_timeline_item':
                return pgeservicos_ticket_action_delete_timeline_item($ticket);
            case 'delete_attachment':
                return pgeservicos_ticket_action_delete_attachment($ticket);
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
            case 'trash_ticket':
                return pgeservicos_ticket_action_trash_ticket($ticket);
        }

        Session::addMessageAfterRedirect('Ação inválida.', false, ERROR);
        return false;
    }
}
