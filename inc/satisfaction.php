<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

if (!function_exists('pgeservicos_satisfaction_plugin_ready')) {
    function pgeservicos_satisfaction_plugin_ready() {
        global $DB;

        if (!class_exists('Plugin') || !method_exists('Plugin', 'isPluginActive')) {
            return false;
        }

        try {
            if (!Plugin::isPluginActive('satisfaction')) {
                return false;
            }
        } catch (Throwable $e) {
            return false;
        }

        $plugin_dir = method_exists('Plugin', 'getPhpDir')
            ? Plugin::getPhpDir('satisfaction')
            : GLPI_ROOT . '/marketplace/satisfaction';
        $class_files = [
            'inc/survey.class.php',
            'inc/surveyquestion.class.php',
            'inc/surveyanswer.class.php',
            'inc/surveytranslation.class.php',
        ];

        foreach ($class_files as $class_file) {
            $path = $plugin_dir . '/' . $class_file;

            if (is_readable($path)) {
                require_once($path);
            }
        }

        return $DB->tableExists('glpi_ticketsatisfactions')
            && $DB->tableExists('glpi_plugin_satisfaction_surveys')
            && $DB->tableExists('glpi_plugin_satisfaction_surveyquestions')
            && $DB->tableExists('glpi_plugin_satisfaction_surveyanswers')
            && class_exists('TicketSatisfaction')
            && class_exists('PluginSatisfactionSurvey')
            && class_exists('PluginSatisfactionSurveyQuestion')
            && class_exists('PluginSatisfactionSurveyAnswer');
    }
}


if (!function_exists('pgeservicos_satisfaction_active_survey_entities')) {
    function pgeservicos_satisfaction_active_survey_entities() {
        global $DB;

        static $entities = null;

        if ($entities !== null) {
            return $entities;
        }

        $entities = [];

        if (!pgeservicos_satisfaction_plugin_ready()) {
            return $entities;
        }

        foreach ($DB->request([
            'SELECT' => ['id', 'entities_id', 'is_recursive'],
            'FROM'   => 'glpi_plugin_satisfaction_surveys',
            'WHERE'  => ['is_active' => 1],
        ]) as $survey) {
            $entities_id = (int)($survey['entities_id'] ?? -1);

            if ($entities_id < 0) {
                continue;
            }

            $entities[$entities_id] = $entities_id;

            if (!empty($survey['is_recursive'])) {
                foreach (getSonsOf('glpi_entities', $entities_id) as $son_id => $son_name) {
                    $entities[(int)$son_id] = (int)$son_id;
                }
            }
        }

        ksort($entities);

        return array_values($entities);
    }
}

if (!function_exists('pgeservicos_satisfaction_load_ticket_satisfaction')) {
    function pgeservicos_satisfaction_load_ticket_satisfaction($tickets_id) {
        $tickets_id = (int)$tickets_id;

        if ($tickets_id <= 0 || !pgeservicos_satisfaction_plugin_ready()) {
            return false;
        }

        $satisfaction = new TicketSatisfaction();

        return $satisfaction->getFromDB($tickets_id) ? $satisfaction : false;
    }
}

if (!function_exists('pgeservicos_ticket_satisfaction_pending_condition')) {
    function pgeservicos_ticket_satisfaction_pending_condition($users_id = null) {
        global $DB;

        $users_id = $users_id === null ? (int)Session::getLoginUserID() : (int)$users_id;

        if ($users_id <= 0 || !pgeservicos_satisfaction_plugin_ready()) {
            return null;
        }

        $tickets_id = DBmysql::quoteName('glpi_tickets.id');
        $tickets_status = DBmysql::quoteName('glpi_tickets.status');
        $tickets_entity = DBmysql::quoteName('glpi_tickets.entities_id');
        $closed_status = (int)Ticket::CLOSED;
        $survey_entities = pgeservicos_satisfaction_active_survey_entities();
        $internal_survey_type = 1;
        $requester_type = (int)CommonITILActor::REQUESTER;
        $answer_conditions = [];

        if (empty($survey_entities)) {
            return null;
        }

        $answer_conditions[] = "EXISTS ("
            . "SELECT 1 FROM " . DBmysql::quoteName('glpi_tickets_users') . " tu "
            . "WHERE tu.`tickets_id` = {$tickets_id} "
            . "AND tu.`users_id` = {$users_id} "
            . "AND tu.`type` = {$requester_type}"
            . ")";

        if (Session::haveRight('ticket', Ticket::SURVEY)) {
            $answer_conditions[] = DBmysql::quoteName('glpi_tickets.users_id_recipient') . " = {$users_id}";
        }

        $groups = array_values(array_unique(array_filter(array_map('intval', $_SESSION['glpigroups'] ?? []))));

        if (!empty($groups)) {
            $answer_conditions[] = "EXISTS ("
                . "SELECT 1 FROM " . DBmysql::quoteName('glpi_groups_tickets') . " gt "
                . "WHERE gt.`tickets_id` = {$tickets_id} "
                . "AND gt.`groups_id` IN (" . implode(',', $groups) . ") "
                . "AND gt.`type` = {$requester_type}"
                . ")";
        }

        $pending_survey = "EXISTS ("
            . "SELECT 1 FROM " . DBmysql::quoteName('glpi_ticketsatisfactions') . " ts "
            . "WHERE ts.`tickets_id` = {$tickets_id} "
            . "AND ts.`type` = {$internal_survey_type} "
            . "AND ts.`date_answered` IS NULL "
            . "AND EXISTS ("
            . "SELECT 1 FROM " . DBmysql::quoteName('glpi_entities') . " ent "
            . "WHERE ent.`id` = {$tickets_entity} "
            . "AND (ent.`inquest_duration` = 0 "
            . "OR DATEDIFF(ADDDATE(ts.`date_begin`, INTERVAL ent.`inquest_duration` DAY), CURDATE()) > 0)"
            . ")"
            . ")";

        return "{$tickets_status} = {$closed_status} "
            . "AND {$tickets_entity} IN (" . implode(',', array_map('intval', $survey_entities)) . ") "
            . "AND {$pending_survey} "
            . "AND (" . implode(' OR ', $answer_conditions) . ")";
    }
}

if (!function_exists('pgeservicos_ticket_satisfaction_pending_expression')) {
    function pgeservicos_ticket_satisfaction_pending_expression($users_id = null) {
        $condition = pgeservicos_ticket_satisfaction_pending_condition($users_id);

        return $condition !== null ? new QueryExpression($condition) : null;
    }
}

if (!function_exists('pgeservicos_collect_satisfaction_pending_ticket_ids')) {
    function pgeservicos_collect_satisfaction_pending_ticket_ids(array $ticket_ids, $users_id = null) {
        global $DB;

        $ticket_ids = array_values(array_unique(array_filter(array_map('intval', $ticket_ids))));

        if (empty($ticket_ids)) {
            return [];
        }

        $condition = pgeservicos_ticket_satisfaction_pending_condition($users_id);

        if ($condition === null) {
            return [];
        }

        $pending_ticket_ids = [];

        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_tickets',
            'WHERE'  => [
                'id' => $ticket_ids,
                new QueryExpression($condition),
            ],
        ]) as $row) {
            $tickets_id = (int)($row['id'] ?? 0);

            if ($tickets_id > 0) {
                $pending_ticket_ids[$tickets_id] = $tickets_id;
            }
        }

        return $pending_ticket_ids;
    }
}

if (!function_exists('pgeservicos_satisfaction_default_question_value')) {
    function pgeservicos_satisfaction_default_question_value(array $question) {
        if ((string)($question['type'] ?? '') === PluginSatisfactionSurveyQuestion::TEXTAREA) {
            return '';
        }

        if ((string)($question['type'] ?? '') === PluginSatisfactionSurveyQuestion::NOTE) {
            $max = max(1, (int)($question['number'] ?? 5));
            $value = (int)($question['default_value'] ?? 1);

            return max(1, min($max, $value));
        }

        return 0;
    }
}

if (!function_exists('pgeservicos_satisfaction_question_label')) {
    function pgeservicos_satisfaction_question_label(array $question) {
        $name = (string)($question['name'] ?? '');

        if (
            class_exists('PluginSatisfactionSurveyTranslation')
            && PluginSatisfactionSurveyTranslation::hasTranslation(
                (int)($question['plugin_satisfaction_surveys_id'] ?? 0),
                (int)($question['id'] ?? 0)
            )
        ) {
            $translated = PluginSatisfactionSurveyTranslation::getTranslation(
                (int)($question['plugin_satisfaction_surveys_id'] ?? 0),
                (int)($question['id'] ?? 0)
            );

            if (trim((string)$translated) !== '') {
                $name = (string)$translated;
            }
        }

        return $name;
    }
}

if (!function_exists('pgeservicos_satisfaction_load_questions')) {
    function pgeservicos_satisfaction_load_questions($survey_id, array $existing_answers = []) {
        $survey_id = (int)$survey_id;
        $questions = [];

        if ($survey_id <= 0 || !pgeservicos_satisfaction_plugin_ready()) {
            return $questions;
        }

        $question_obj = new PluginSatisfactionSurveyQuestion();
        $raw_questions = $question_obj->find([
            PluginSatisfactionSurveyQuestion::$items_id => $survey_id,
        ], 'id');

        foreach ($raw_questions as $question) {
            $question_id = (int)($question['id'] ?? 0);

            if ($question_id <= 0) {
                continue;
            }

            $question['label'] = pgeservicos_satisfaction_question_label($question);
            $question['value'] = array_key_exists($question_id, $existing_answers)
                ? $existing_answers[$question_id]
                : pgeservicos_satisfaction_default_question_value($question);
            $questions[] = $question;
        }

        return $questions;
    }
}

if (!function_exists('pgeservicos_satisfaction_get_pending_context')) {
    function pgeservicos_satisfaction_get_pending_context($tickets_id, $users_id = null) {
        require_once(__DIR__ . '/ticket_view.php');

        $tickets_id = (int)$tickets_id;
        $ticket = pgeservicos_ticket_view_get_ticket($tickets_id);

        if ($ticket === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'Chamado não encontrado.'];
        }

        if ($ticket === false) {
            return ['ok' => false, 'status' => 403, 'message' => 'Você não tem permissão para acessar este chamado.'];
        }

        if (!pgeservicos_satisfaction_plugin_ready()) {
            return ['ok' => false, 'status' => 503, 'message' => 'Pesquisa de satisfação indisponível.'];
        }

        $satisfaction = pgeservicos_satisfaction_load_ticket_satisfaction($tickets_id);

        if (!$satisfaction instanceof TicketSatisfaction) {
            return ['ok' => false, 'status' => 404, 'message' => 'Este chamado não possui pesquisa de satisfação pendente.'];
        }

        if ((int)($satisfaction->fields['type'] ?? 0) !== 1) {
            return ['ok' => false, 'status' => 400, 'message' => 'Esta pesquisa de satisfação é externa e deve ser respondida pelo fluxo nativo.'];
        }

        if (!empty($satisfaction->fields['date_answered'])) {
            return ['ok' => false, 'status' => 409, 'message' => 'Esta pesquisa de satisfação já foi respondida.'];
        }

        if (!$satisfaction->canUpdateItem()) {
            return ['ok' => false, 'status' => 403, 'message' => 'Você não pode responder esta pesquisa de satisfação.'];
        }

        $users_id = $users_id === null ? (int)Session::getLoginUserID() : (int)$users_id;
        $pending_ids = pgeservicos_collect_satisfaction_pending_ticket_ids([$tickets_id], $users_id);

        if (!isset($pending_ids[$tickets_id])) {
            if (!empty($satisfaction->fields['date_answered'])) {
                return ['ok' => false, 'status' => 409, 'message' => 'Esta pesquisa de satisfação já foi respondida.'];
            }

            return ['ok' => false, 'status' => 400, 'message' => 'O prazo para resposta desta pesquisa expirou ou a pesquisa não está mais disponível para a entidade do chamado.'];
        }

        $answer_obj = new PluginSatisfactionSurveyAnswer();
        $existing_answers = [];
        $survey_id = null;

        if ($answer_obj->getFromDBByCrit(['ticketsatisfactions_id' => (int)$satisfaction->fields['id']])) {
            $survey_id = (int)($answer_obj->fields['plugin_satisfaction_surveys_id'] ?? 0);
            if (!empty($answer_obj->fields['answer'])) {
                $dbu = new DbUtils();
                $imported = $dbu->importArrayFromDB($answer_obj->fields['answer']);
                $existing_answers = is_array($imported) ? $imported : [];
            }
        }

        if ($survey_id <= 0) {
            $survey_id = (int)PluginSatisfactionSurvey::getObjectForEntity((int)$ticket->fields['entities_id']);
        }

        if ($survey_id <= 0) {
            return ['ok' => false, 'status' => 404, 'message' => 'Nenhuma pesquisa de satisfação ativa foi encontrada para a entidade do chamado.'];
        }

        $survey = new PluginSatisfactionSurvey();

        if (!$survey->getFromDB($survey_id) || empty($survey->fields['is_active'])) {
            return ['ok' => false, 'status' => 404, 'message' => 'A pesquisa de satisfação vinculada não está ativa.'];
        }

        $questions = pgeservicos_satisfaction_load_questions($survey_id, $existing_answers);

        return [
            'ok'                  => true,
            'ticket'              => $ticket,
            'satisfaction'        => $satisfaction,
            'survey'              => $survey,
            'survey_id'           => $survey_id,
            'questions'           => $questions,
            'existing_answers'    => $existing_answers,
        ];
    }
}


if (!function_exists('pgeservicos_satisfaction_get_context')) {
    function pgeservicos_satisfaction_get_context($tickets_id) {
        return pgeservicos_satisfaction_get_pending_context($tickets_id);
    }
}

if (!function_exists('pgeservicos_satisfaction_sanitize_answers')) {
    function pgeservicos_satisfaction_sanitize_answers(array $questions, array $raw_answers) {
        $answers = [];

        foreach ($questions as $question) {
            $question_id = (int)($question['id'] ?? 0);
            $type = (string)($question['type'] ?? '');
            $raw_value = $raw_answers[$question_id] ?? pgeservicos_satisfaction_default_question_value($question);

            if ($question_id <= 0) {
                continue;
            }

            switch ($type) {
                case PluginSatisfactionSurveyQuestion::TEXTAREA:
                    $answers[$question_id] = Html::cleanPostForTextArea((string)$raw_value);
                    break;

                case PluginSatisfactionSurveyQuestion::YESNO:
                    $answers[$question_id] = (int)$raw_value === 1 ? 1 : 0;
                    break;

                case PluginSatisfactionSurveyQuestion::NOTE:
                    $max = max(1, min(10, (int)($question['number'] ?? 5)));
                    $value = (int)$raw_value;
                    $answers[$question_id] = max(1, min($max, $value));
                    break;

                default:
                    $answers[$question_id] = trim((string)$raw_value);
                    break;
            }
        }

        return $answers;
    }
}

if (!function_exists('pgeservicos_satisfaction_overall_score')) {
    function pgeservicos_satisfaction_overall_score(array $questions, array $answers, $fallback = 3) {
        foreach ($questions as $question) {
            if ((string)($question['type'] ?? '') !== PluginSatisfactionSurveyQuestion::NOTE) {
                continue;
            }

            $question_id = (int)($question['id'] ?? 0);
            $max = max(1, (int)($question['number'] ?? 5));
            $value = (int)($answers[$question_id] ?? pgeservicos_satisfaction_default_question_value($question));
            $value = max(1, min($max, $value));

            if ($max === 5) {
                return max(1, min(5, $value));
            }

            return max(1, min(5, (int)round(($value / $max) * 5)));
        }

        return max(1, min(5, (int)$fallback));
    }
}

if (!function_exists('pgeservicos_satisfaction_submit')) {
    function pgeservicos_satisfaction_submit($tickets_id, array $raw_answers, $comment = '', $fallback_score = 3) {
        $context = pgeservicos_satisfaction_get_pending_context($tickets_id);

        if (empty($context['ok'])) {
            return $context;
        }

        /** @var TicketSatisfaction $satisfaction */
        $satisfaction = $context['satisfaction'];
        $answers = pgeservicos_satisfaction_sanitize_answers($context['questions'], $raw_answers);
        $score = pgeservicos_satisfaction_overall_score($context['questions'], $answers, $fallback_score);
        $input = [
            'tickets_id'                     => (int)$tickets_id,
            'satisfaction'                   => $score,
            'comment'                        => Html::cleanPostForTextArea((string)$comment),
            'plugin_satisfaction_surveys_id' => (int)$context['survey_id'],
            'answer'                         => $answers,
        ];

        if (!$satisfaction->update($input)) {
            return ['ok' => false, 'status' => 500, 'message' => 'Não foi possível salvar a pesquisa de satisfação.'];
        }

        if (class_exists('PluginSatisfactionSurveyAnswer')) {
            $updated_satisfaction = new TicketSatisfaction();
            if ($updated_satisfaction->getFromDB((int)$tickets_id)) {
                $updated_satisfaction->input = $input;
                PluginSatisfactionSurveyAnswer::preUpdateSatisfaction($updated_satisfaction);
            }
        }

        return ['ok' => true, 'message' => 'Pesquisa enviada com sucesso.'];
    }
}


if (!function_exists('pgeservicos_satisfaction_normalize_label')) {
    function pgeservicos_satisfaction_normalize_label($value) {
        $value = trim(mb_strtolower((string)$value, 'UTF-8'));
        $from = ['á', 'à', 'â', 'ã', 'ä', 'é', 'è', 'ê', 'ë', 'í', 'ì', 'î', 'ï', 'ó', 'ò', 'ô', 'õ', 'ö', 'ú', 'ù', 'û', 'ü', 'ç'];
        $to = ['a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'c'];
        $value = str_replace($from, $to, $value);

        return preg_replace('/\s+/', ' ', $value);
    }
}

if (!function_exists('pgeservicos_satisfaction_current_profile_name')) {
    function pgeservicos_satisfaction_current_profile_name() {
        if (function_exists('pgeservicos_current_profile_name')) {
            return pgeservicos_current_profile_name();
        }

        return trim((string)($_SESSION['glpiactiveprofile']['name'] ?? ''));
    }
}

if (!function_exists('pgeservicos_satisfaction_current_profile_is_super_admin')) {
    function pgeservicos_satisfaction_current_profile_is_super_admin() {
        $profile = pgeservicos_satisfaction_normalize_label(pgeservicos_satisfaction_current_profile_name());

        return in_array($profile, ['super-admin', 'super admin', 'superadmin'], true);
    }
}

if (!function_exists('pgeservicos_satisfaction_current_profile_is_user')) {
    function pgeservicos_satisfaction_current_profile_is_user() {
        $profile = pgeservicos_satisfaction_normalize_label(pgeservicos_satisfaction_current_profile_name());

        return $profile === 'usuario';
    }
}

if (!function_exists('pgeservicos_satisfaction_user_can_view_results')) {
    function pgeservicos_satisfaction_user_can_view_results() {
        if (pgeservicos_satisfaction_current_profile_is_super_admin()) {
            return true;
        }

        if (pgeservicos_satisfaction_current_profile_is_user()) {
            return false;
        }

        $read = defined('READ') ? READ : 1;

        return Session::haveRight('ticket', $read)
            || Session::haveRight('plugin_satisfaction', $read);
    }
}

if (!function_exists('pgeservicos_satisfaction_user_can_create_surveys')) {
    function pgeservicos_satisfaction_user_can_create_surveys() {
        return pgeservicos_satisfaction_current_profile_is_super_admin();
    }
}

if (!function_exists('pgeservicos_satisfaction_entity_tree_ids')) {
    function pgeservicos_satisfaction_entity_tree_ids($entities_id) {
        $entities_id = (int)$entities_id;
        $entities = [$entities_id => $entities_id];

        if ($entities_id >= 0) {
            foreach (getSonsOf('glpi_entities', $entities_id) as $son_id => $son_name) {
                $entities[(int)$son_id] = (int)$son_id;
            }
        }

        ksort($entities);

        return array_values($entities);
    }
}

if (!function_exists('pgeservicos_satisfaction_allowed_result_entities')) {
    function pgeservicos_satisfaction_allowed_result_entities() {
        if (pgeservicos_satisfaction_current_profile_is_super_admin()) {
            return null;
        }

        $entities = [];

        if (!empty($_SESSION['glpiactiveentities']) && is_array($_SESSION['glpiactiveentities'])) {
            foreach ($_SESSION['glpiactiveentities'] as $entities_id) {
                $entities[(int)$entities_id] = (int)$entities_id;
            }
        }

        if (empty($entities) && isset($_SESSION['glpiactive_entity'])) {
            $entities_id = (int)$_SESSION['glpiactive_entity'];
            if ($entities_id >= 0) {
                $entities[$entities_id] = $entities_id;
            }
        }

        if (empty($entities) && !empty($_SESSION['glpiactiveprofile']['entities'])) {
            foreach ($_SESSION['glpiactiveprofile']['entities'] as $profile_entity) {
                $entities_id = (int)($profile_entity['id'] ?? -1);

                if ($entities_id < 0) {
                    continue;
                }

                $entities[$entities_id] = $entities_id;

                if (!empty($profile_entity['is_recursive'])) {
                    foreach (getSonsOf('glpi_entities', $entities_id) as $son_id => $son_name) {
                        $entities[(int)$son_id] = (int)$son_id;
                    }
                }
            }
        }

        ksort($entities);

        return array_values($entities);
    }
}

if (!function_exists('pgeservicos_satisfaction_require_results_access')) {
    function pgeservicos_satisfaction_require_results_access() {
        if (!pgeservicos_satisfaction_user_can_view_results()) {
            return ['ok' => false, 'status' => 403, 'message' => 'Você não tem permissão para visualizar resultados de satisfação.'];
        }

        $entities = pgeservicos_satisfaction_allowed_result_entities();

        if (is_array($entities) && empty($entities)) {
            return ['ok' => false, 'status' => 403, 'message' => 'Nenhuma entidade disponível para consulta de resultados.'];
        }

        if (!pgeservicos_satisfaction_plugin_ready()) {
            return ['ok' => false, 'status' => 503, 'message' => 'O plugin Satisfaction não está disponível.'];
        }

        return ['ok' => true, 'entities' => $entities];
    }
}

if (!function_exists('pgeservicos_satisfaction_sql_escape')) {
    function pgeservicos_satisfaction_sql_escape($value) {
        global $DB;

        return method_exists($DB, 'escape') ? $DB->escape((string)$value) : addslashes((string)$value);
    }
}

if (!function_exists('pgeservicos_satisfaction_normalize_result_filters')) {
    function pgeservicos_satisfaction_normalize_result_filters(array $input) {
        $allowed_sorts = ['recent', 'low_rating', 'high_rating', 'ticket', 'entity'];
        $per_page = isset($input['per_page']) ? (int)$input['per_page'] : (int)($_SESSION['glpilist_limit'] ?? 20);
        $per_page = in_array($per_page, [10, 20, 50, 100], true) ? $per_page : 20;
        $page = max(1, (int)($input['page'] ?? 1));
        $date_from = trim((string)($input['date_from'] ?? ''));
        $date_to = trim((string)($input['date_to'] ?? ''));
        $sort = (string)($input['sort'] ?? 'recent');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
            $date_from = '';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            $date_to = '';
        }

        if (!in_array($sort, $allowed_sorts, true)) {
            $sort = 'recent';
        }

        $rating = isset($input['rating']) && $input['rating'] !== '' ? (int)$input['rating'] : -1;
        if ($rating < 1 || $rating > 10) {
            $rating = -1;
        }

        return [
            'q'          => trim((string)($input['q'] ?? '')),
            'entity'     => isset($input['entity']) ? (int)$input['entity'] : -1,
            'survey_id'  => isset($input['survey_id']) ? (int)$input['survey_id'] : 0,
            'rating'     => $rating,
            'date_from'  => $date_from,
            'date_to'    => $date_to,
            'sort'       => $sort,
            'page'       => $page,
            'per_page'   => $per_page,
            'start'      => ($page - 1) * $per_page,
        ];
    }
}

if (!function_exists('pgeservicos_satisfaction_result_where_sql')) {
    function pgeservicos_satisfaction_result_where_sql(array $filters, $with_permissions = true) {
        $where = [
            't.`is_deleted` = 0',
            'ts.`type` = 1',
            'ts.`date_answered` IS NOT NULL',
        ];

        if ($with_permissions) {
            $allowed_entities = pgeservicos_satisfaction_allowed_result_entities();

            if (is_array($allowed_entities)) {
                if (empty($allowed_entities)) {
                    $where[] = '1 = 0';
                } else {
                    $where[] = 't.`entities_id` IN (' . implode(',', array_map('intval', $allowed_entities)) . ')';
                }
            }
        }

        $entity_filter = (int)($filters['entity'] ?? -1);
        if ($entity_filter >= 0) {
            $entity_ids = pgeservicos_satisfaction_entity_tree_ids($entity_filter);
            $allowed_entities = pgeservicos_satisfaction_allowed_result_entities();

            if (is_array($allowed_entities)) {
                $allowed_lookup = array_fill_keys(array_map('intval', $allowed_entities), true);
                $entity_ids = array_values(array_filter($entity_ids, static function ($entities_id) use ($allowed_lookup) {
                    return isset($allowed_lookup[(int)$entities_id]);
                }));
            }

            if (empty($entity_ids)) {
                $where[] = '1 = 0';
            } else {
                $where[] = 't.`entities_id` IN (' . implode(',', array_map('intval', $entity_ids)) . ')';
            }
        }

        $survey_id = (int)($filters['survey_id'] ?? 0);
        if ($survey_id > 0) {
            $where[] = 'sa.`plugin_satisfaction_surveys_id` = ' . $survey_id;
        }

        if (!empty($filters['date_from'])) {
            $where[] = "ts.`date_answered` >= '" . pgeservicos_satisfaction_sql_escape($filters['date_from']) . " 00:00:00'";
        }

        if (!empty($filters['date_to'])) {
            $where[] = "ts.`date_answered` <= '" . pgeservicos_satisfaction_sql_escape($filters['date_to']) . " 23:59:59'";
        }

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $escaped_q = pgeservicos_satisfaction_sql_escape($q);
            $like = "'%" . $escaped_q . "%'";
            if (ctype_digit($q)) {
                $where[] = '(t.`id` = ' . (int)$q . ' OR t.`name` LIKE ' . $like . ')';
            } else {
                $where[] = 't.`name` LIKE ' . $like;
            }
        }

        return implode(' AND ', $where);
    }
}

if (!function_exists('pgeservicos_satisfaction_result_order_sql')) {
    function pgeservicos_satisfaction_result_order_sql($sort) {
        switch ((string)$sort) {
            case 'low_rating':
                return 'ts.`satisfaction` ASC, ts.`date_answered` DESC';
            case 'high_rating':
                return 'ts.`satisfaction` DESC, ts.`date_answered` DESC';
            case 'ticket':
                return 't.`id` DESC';
            case 'entity':
                return 'e.`completename` ASC, ts.`date_answered` DESC';
            case 'recent':
            default:
                return 'ts.`date_answered` DESC, t.`id` DESC';
        }
    }
}

if (!function_exists('pgeservicos_satisfaction_load_ticket_people')) {
    function pgeservicos_satisfaction_load_ticket_people(array $ticket_ids) {
        global $DB;

        $ticket_ids = array_values(array_unique(array_filter(array_map('intval', $ticket_ids))));
        $people = [];

        foreach ($ticket_ids as $tickets_id) {
            $people[$tickets_id] = [
                'requesters' => [],
                'assignees'  => [],
            ];
        }

        if (empty($ticket_ids)) {
            return $people;
        }

        $ticket_list = implode(',', $ticket_ids);
        $requester_type = (int)CommonITILActor::REQUESTER;
        $assign_type = (int)CommonITILActor::ASSIGN;

        $query = "SELECT tu.`tickets_id`, tu.`type`, u.`id` AS users_id, u.`name`, u.`realname`, u.`firstname`
                  FROM `glpi_tickets_users` tu
                  INNER JOIN `glpi_users` u ON u.`id` = tu.`users_id`
                  WHERE tu.`tickets_id` IN ({$ticket_list})
                    AND tu.`type` IN ({$requester_type}, {$assign_type})";
        $result = $DB->query($query);

        if ($result) {
            while ($row = $DB->fetchAssoc($result)) {
                $tickets_id = (int)$row['tickets_id'];
                $name = trim(formatUserName((int)$row['users_id'], (string)$row['name'], (string)$row['realname'], (string)$row['firstname'], 0));
                if ($name === '') {
                    $name = (string)$row['name'];
                }

                if ((int)$row['type'] === $requester_type) {
                    $people[$tickets_id]['requesters'][] = $name;
                } else {
                    $people[$tickets_id]['assignees'][] = $name;
                }
            }
        }

        $query = "SELECT gt.`tickets_id`, g.`completename`
                  FROM `glpi_groups_tickets` gt
                  INNER JOIN `glpi_groups` g ON g.`id` = gt.`groups_id`
                  WHERE gt.`tickets_id` IN ({$ticket_list})
                    AND gt.`type` = {$assign_type}";
        $result = $DB->query($query);

        if ($result) {
            while ($row = $DB->fetchAssoc($result)) {
                $tickets_id = (int)$row['tickets_id'];
                $group_name = trim((string)$row['completename']);
                if ($group_name !== '') {
                    $people[$tickets_id]['assignees'][] = $group_name;
                }
            }
        }

        foreach ($people as $tickets_id => $values) {
            $people[$tickets_id]['requesters'] = array_values(array_unique($values['requesters']));
            $people[$tickets_id]['assignees'] = array_values(array_unique($values['assignees']));
        }

        return $people;
    }
}

if (!function_exists('pgeservicos_satisfaction_decode_answers')) {
    function pgeservicos_satisfaction_decode_answers($raw_answer) {
        if (empty($raw_answer)) {
            return [];
        }

        $dbu = new DbUtils();
        $answers = $dbu->importArrayFromDB($raw_answer);

        return is_array($answers) ? $answers : [];
    }
}

if (!function_exists('pgeservicos_satisfaction_format_answer_value')) {
    function pgeservicos_satisfaction_format_answer_value(array $question, $value) {
        $type = (string)($question['type'] ?? '');

        switch ($type) {
            case PluginSatisfactionSurveyQuestion::YESNO:
                return Dropdown::getYesNo((int)$value);
            case PluginSatisfactionSurveyQuestion::NOTE:
                $max = max(1, (int)($question['number'] ?? 5));
                return (int)$value . '/' . $max;
            case PluginSatisfactionSurveyQuestion::TEXTAREA:
                return Html::cleanPostForTextArea((string)$value);
            default:
                return trim((string)$value);
        }
    }
}

if (!function_exists('pgeservicos_satisfaction_load_answer_details')) {
    function pgeservicos_satisfaction_load_answer_details($survey_id, array $answers) {
        $details = [];
        $questions = pgeservicos_satisfaction_load_questions((int)$survey_id, $answers);

        foreach ($questions as $question) {
            $question_id = (int)($question['id'] ?? 0);
            $value = $answers[$question_id] ?? pgeservicos_satisfaction_default_question_value($question);
            $details[] = [
                'question_id' => $question_id,
                'label'       => pgeservicos_satisfaction_question_label($question),
                'type'        => (string)($question['type'] ?? ''),
                'value'       => $value,
                'display'     => pgeservicos_satisfaction_format_answer_value($question, $value),
            ];
        }

        return $details;
    }
}


if (!function_exists('pgeservicos_satisfaction_note_question_map')) {
    function pgeservicos_satisfaction_note_question_map(array $survey_ids) {
        global $DB;

        $survey_ids = array_values(array_unique(array_filter(array_map('intval', $survey_ids))));
        $map = [];

        foreach ($survey_ids as $survey_id) {
            $map[$survey_id] = [];
        }

        if (empty($survey_ids) || !pgeservicos_satisfaction_plugin_ready()) {
            return $map;
        }

        $query = "SELECT `id`, `plugin_satisfaction_surveys_id`, `number`
                  FROM `glpi_plugin_satisfaction_surveyquestions`
                  WHERE `plugin_satisfaction_surveys_id` IN (" . implode(',', $survey_ids) . ")
                    AND `type` = '" . pgeservicos_satisfaction_sql_escape(PluginSatisfactionSurveyQuestion::NOTE) . "'
                  ORDER BY `id` ASC";
        $result = $DB->query($query);

        if ($result) {
            while ($row = $DB->fetchAssoc($result)) {
                $survey_id = (int)$row['plugin_satisfaction_surveys_id'];
                $question_id = (int)$row['id'];

                if ($survey_id <= 0 || $question_id <= 0) {
                    continue;
                }

                if (!isset($map[$survey_id])) {
                    $map[$survey_id] = [];
                }

                $map[$survey_id][$question_id] = max(1, min(10, (int)($row['number'] ?? 5)));
            }
        }

        return $map;
    }
}

if (!function_exists('pgeservicos_satisfaction_result_note_average')) {
    function pgeservicos_satisfaction_result_note_average($survey_id, array $answers, array $note_question_map) {
        $survey_id = (int)$survey_id;
        $notes = [];

        foreach (($note_question_map[$survey_id] ?? []) as $question_id => $max) {
            if (!array_key_exists((int)$question_id, $answers)) {
                continue;
            }

            $value = (int)$answers[(int)$question_id];
            $notes[] = max(0, min((int)$max, $value));
        }

        if (empty($notes)) {
            return null;
        }

        return array_sum($notes) / count($notes);
    }
}

if (!function_exists('pgeservicos_satisfaction_rating_label')) {
    function pgeservicos_satisfaction_rating_label($rating) {
        if ($rating === null) {
            return 'Sem nota';
        }

        $rating = (float)$rating;
        $rounded = round($rating, 1);

        if (abs($rounded - round($rounded)) < 0.001) {
            return (string)(int)round($rounded);
        }

        return number_format($rounded, 1, ',', '.');
    }
}

if (!function_exists('pgeservicos_satisfaction_rating_filter_matches')) {
    function pgeservicos_satisfaction_rating_filter_matches($average, $rating_filter) {
        $rating_filter = (int)$rating_filter;

        if ($rating_filter < 1 || $rating_filter > 10) {
            return true;
        }

        if ($average === null) {
            return false;
        }

        $average = (float)$average;

        if ($rating_filter === 10) {
            return $average >= 10;
        }

        return $average >= $rating_filter && $average < ($rating_filter + 1);
    }
}

if (!function_exists('pgeservicos_get_satisfaction_results')) {
    function pgeservicos_get_satisfaction_results(array $filters = []) {
        global $DB;

        $filters = pgeservicos_satisfaction_normalize_result_filters($filters);
        $access = pgeservicos_satisfaction_require_results_access();

        if (empty($access['ok'])) {
            return [
                'ok'      => false,
                'message' => $access['message'] ?? 'Acesso negado.',
                'filters' => $filters,
                'items'   => [],
                'total'   => 0,
                'pages'   => 1,
                'summary' => ['average' => null, 'entities' => 0],
            ];
        }

        $where = pgeservicos_satisfaction_result_where_sql($filters);
        $order = pgeservicos_satisfaction_result_order_sql($filters['sort']);
        $start = max(0, (int)$filters['start']);
        $limit = max(1, (int)$filters['per_page']);
        $from = "FROM `glpi_ticketsatisfactions` ts
                 INNER JOIN `glpi_tickets` t ON t.`id` = ts.`tickets_id`
                 INNER JOIN `glpi_entities` e ON e.`id` = t.`entities_id`
                 LEFT JOIN `glpi_locations` loc ON loc.`id` = t.`locations_id`
                 LEFT JOIN `glpi_plugin_satisfaction_surveyanswers` sa ON sa.`ticketsatisfactions_id` = ts.`id`
                 LEFT JOIN `glpi_plugin_satisfaction_surveys` sv ON sv.`id` = sa.`plugin_satisfaction_surveys_id`";

        $query = "SELECT ts.`id` AS satisfaction_id,
                         ts.`tickets_id`,
                         ts.`satisfaction`,
                         ts.`comment`,
                         ts.`date_begin`,
                         ts.`date_answered`,
                         t.`name` AS ticket_name,
                         t.`status` AS ticket_status,
                         t.`date` AS ticket_date,
                         t.`closedate`,
                         t.`entities_id`,
                         t.`locations_id`,
                         e.`completename` AS entity_name,
                         loc.`completename` AS location_name,
                         sa.`id` AS survey_answer_id,
                         sa.`plugin_satisfaction_surveys_id` AS survey_id,
                         sa.`answer` AS raw_answer,
                         sv.`name` AS survey_name
                  {$from}
                  WHERE {$where}
                  ORDER BY {$order}";
        $result = $DB->query($query);
        $rows = [];
        $survey_ids = [];

        if ($result) {
            while ($row = $DB->fetchAssoc($result)) {
                $survey_id = (int)($row['survey_id'] ?? 0);
                if ($survey_id > 0) {
                    $survey_ids[$survey_id] = $survey_id;
                }

                $row['answers'] = pgeservicos_satisfaction_decode_answers($row['raw_answer'] ?? '');
                $rows[] = $row;
            }
        }

        $note_question_map = pgeservicos_satisfaction_note_question_map(array_values($survey_ids));
        $items = [];
        $entity_ids = [];
        $average_values = [];

        foreach ($rows as $row) {
            $survey_id = (int)($row['survey_id'] ?? 0);
            $average = $survey_id > 0
                ? pgeservicos_satisfaction_result_note_average($survey_id, $row['answers'], $note_question_map)
                : null;

            if (!pgeservicos_satisfaction_rating_filter_matches($average, $filters['rating'])) {
                continue;
            }

            $row['rating_average'] = $average;
            $row['rating_average_label'] = pgeservicos_satisfaction_rating_label($average);
            $items[] = $row;
            $entity_ids[(int)$row['entities_id']] = (int)$row['entities_id'];

            if ($average !== null) {
                $average_values[] = (float)$average;
            }
        }

        if ((string)$filters['sort'] === 'low_rating' || (string)$filters['sort'] === 'high_rating') {
            usort($items, static function ($left, $right) use ($filters) {
                $left_average = $left['rating_average'];
                $right_average = $right['rating_average'];

                if ($left_average === null && $right_average === null) {
                    return (int)$right['satisfaction_id'] <=> (int)$left['satisfaction_id'];
                }

                if ($left_average === null) {
                    return 1;
                }

                if ($right_average === null) {
                    return -1;
                }

                if ((string)$filters['sort'] === 'low_rating') {
                    return $left_average <=> $right_average;
                }

                return $right_average <=> $left_average;
            });
        }

        $total = count($items);
        $summary = [
            'average'  => !empty($average_values) ? array_sum($average_values) / count($average_values) : null,
            'entities' => count($entity_ids),
        ];
        $page_items = array_slice($items, $start, $limit);
        $ticket_ids = [];

        foreach ($page_items as $item) {
            $ticket_ids[] = (int)$item['tickets_id'];
        }

        $people = pgeservicos_satisfaction_load_ticket_people($ticket_ids);
        foreach ($page_items as &$item) {
            $tickets_id = (int)$item['tickets_id'];
            $item['requesters'] = $people[$tickets_id]['requesters'] ?? [];
            $item['assignees'] = $people[$tickets_id]['assignees'] ?? [];
        }
        unset($item);

        return [
            'ok'      => true,
            'filters' => $filters,
            'items'   => $page_items,
            'total'   => $total,
            'pages'   => max(1, (int)ceil($total / $limit)),
            'summary' => $summary,
        ];
    }
}

if (!function_exists('pgeservicos_get_satisfaction_result_detail')) {
    function pgeservicos_get_satisfaction_result_detail($satisfaction_id = 0, $tickets_id = 0) {
        global $DB;

        $satisfaction_id = (int)$satisfaction_id;
        $tickets_id = (int)$tickets_id;
        $access = pgeservicos_satisfaction_require_results_access();

        if (empty($access['ok'])) {
            return ['ok' => false, 'status' => (int)($access['status'] ?? 403), 'message' => $access['message'] ?? 'Você não tem permissão para visualizar esta avaliação.'];
        }

        $filters = pgeservicos_satisfaction_normalize_result_filters([]);
        $where = pgeservicos_satisfaction_result_where_sql($filters);

        if ($satisfaction_id > 0) {
            $where .= ' AND ts.`id` = ' . $satisfaction_id;
        } elseif ($tickets_id > 0) {
            $where .= ' AND ts.`tickets_id` = ' . $tickets_id;
        } else {
            return ['ok' => false, 'status' => 400, 'message' => 'Avaliação inválida.'];
        }

        $query = "SELECT ts.`id` AS satisfaction_id,
                         ts.`tickets_id`,
                         ts.`satisfaction`,
                         ts.`comment`,
                         ts.`date_begin`,
                         ts.`date_answered`,
                         t.`name` AS ticket_name,
                         t.`status` AS ticket_status,
                         t.`date` AS ticket_date,
                         t.`closedate`,
                         t.`entities_id`,
                         t.`locations_id`,
                         e.`completename` AS entity_name,
                         loc.`completename` AS location_name,
                         sa.`id` AS survey_answer_id,
                         sa.`plugin_satisfaction_surveys_id` AS survey_id,
                         sa.`answer` AS raw_answer,
                         sv.`name` AS survey_name
                  FROM `glpi_ticketsatisfactions` ts
                  INNER JOIN `glpi_tickets` t ON t.`id` = ts.`tickets_id`
                  INNER JOIN `glpi_entities` e ON e.`id` = t.`entities_id`
                  LEFT JOIN `glpi_locations` loc ON loc.`id` = t.`locations_id`
                  LEFT JOIN `glpi_plugin_satisfaction_surveyanswers` sa ON sa.`ticketsatisfactions_id` = ts.`id`
                  LEFT JOIN `glpi_plugin_satisfaction_surveys` sv ON sv.`id` = sa.`plugin_satisfaction_surveys_id`
                  WHERE {$where}
                  LIMIT 1";
        $result = $DB->query($query);

        if (!$result || $DB->numrows($result) === 0) {
            return ['ok' => false, 'status' => 404, 'message' => 'Avaliação não encontrada ou fora da sua entidade.'];
        }

        $row = $DB->fetchAssoc($result);
        $row['answers'] = pgeservicos_satisfaction_decode_answers($row['raw_answer'] ?? '');
        $row['answer_details'] = !empty($row['survey_id'])
            ? pgeservicos_satisfaction_load_answer_details((int)$row['survey_id'], $row['answers'])
            : [];
        $note_map = pgeservicos_satisfaction_note_question_map([(int)($row['survey_id'] ?? 0)]);
        $row['rating_average'] = !empty($row['survey_id'])
            ? pgeservicos_satisfaction_result_note_average((int)$row['survey_id'], $row['answers'], $note_map)
            : null;
        $row['rating_average_label'] = pgeservicos_satisfaction_rating_label($row['rating_average']);
        $people = pgeservicos_satisfaction_load_ticket_people([(int)$row['tickets_id']]);
        $row['requesters'] = $people[(int)$row['tickets_id']]['requesters'] ?? [];
        $row['assignees'] = $people[(int)$row['tickets_id']]['assignees'] ?? [];

        return ['ok' => true, 'item' => $row];
    }
}

if (!function_exists('pgeservicos_satisfaction_entity_options')) {
    function pgeservicos_satisfaction_entity_options($for_creation = false) {
        global $DB;

        $where = [];
        if (!$for_creation && !pgeservicos_satisfaction_current_profile_is_super_admin()) {
            $entities = pgeservicos_satisfaction_allowed_result_entities();
            if (empty($entities)) {
                return [];
            }
            $where[] = '`id` IN (' . implode(',', array_map('intval', $entities)) . ')';
        }

        $query = 'SELECT `id`, `completename` FROM `glpi_entities`';
        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', $where);
        }
        $query .= ' ORDER BY `completename` ASC';

        $result = $DB->query($query);
        $options = [];
        if ($result) {
            while ($row = $DB->fetchAssoc($result)) {
                $options[(int)$row['id']] = (string)$row['completename'];
            }
        }

        return $options;
    }
}

if (!function_exists('pgeservicos_satisfaction_survey_options')) {
    function pgeservicos_satisfaction_survey_options() {
        global $DB;

        $filters = pgeservicos_satisfaction_normalize_result_filters([]);
        $where = pgeservicos_satisfaction_result_where_sql($filters);
        $query = "SELECT DISTINCT sv.`id`, sv.`name`
                  FROM `glpi_plugin_satisfaction_surveys` sv
                  INNER JOIN `glpi_plugin_satisfaction_surveyanswers` sa ON sa.`plugin_satisfaction_surveys_id` = sv.`id`
                  INNER JOIN `glpi_ticketsatisfactions` ts ON ts.`id` = sa.`ticketsatisfactions_id`
                  INNER JOIN `glpi_tickets` t ON t.`id` = ts.`tickets_id`
                  INNER JOIN `glpi_entities` e ON e.`id` = t.`entities_id`
                  WHERE {$where}
                    AND sv.`id` IS NOT NULL
                  ORDER BY sv.`name` ASC";
        $result = $DB->query($query);
        $options = [];

        if ($result) {
            while ($row = $DB->fetchAssoc($result)) {
                $options[(int)$row['id']] = (string)$row['name'];
            }
        }

        return $options;
    }
}

if (!function_exists('pgeservicos_satisfaction_survey_answer_count')) {
    function pgeservicos_satisfaction_survey_answer_count($survey_id) {
        global $DB;

        $survey_id = (int)$survey_id;
        if ($survey_id <= 0 || !pgeservicos_satisfaction_plugin_ready()) {
            return 0;
        }

        return countElementsInTable('glpi_plugin_satisfaction_surveyanswers', [
            'plugin_satisfaction_surveys_id' => $survey_id,
        ]);
    }
}

if (!function_exists('pgeservicos_satisfaction_get_surveys')) {
    function pgeservicos_satisfaction_get_surveys() {
        global $DB;

        if (!pgeservicos_satisfaction_user_can_create_surveys() || !pgeservicos_satisfaction_plugin_ready()) {
            return [];
        }

        $query = "SELECT sv.`id`, sv.`name`, sv.`comment`, sv.`entities_id`, sv.`is_recursive`, sv.`is_active`,
                         sv.`date_creation`, sv.`date_mod`,
                         e.`completename` AS entity_name,
                         (SELECT COUNT(*) FROM `glpi_plugin_satisfaction_surveyquestions` q WHERE q.`plugin_satisfaction_surveys_id` = sv.`id`) AS question_count,
                         (SELECT COUNT(*) FROM `glpi_plugin_satisfaction_surveyanswers` a WHERE a.`plugin_satisfaction_surveys_id` = sv.`id`) AS answer_count
                  FROM `glpi_plugin_satisfaction_surveys` sv
                  LEFT JOIN `glpi_entities` e ON e.`id` = sv.`entities_id`
                  ORDER BY sv.`is_active` DESC, e.`completename` ASC, sv.`name` ASC";
        $result = $DB->query($query);
        $surveys = [];

        if ($result) {
            while ($row = $DB->fetchAssoc($result)) {
                $row['questions'] = pgeservicos_satisfaction_load_questions((int)$row['id'], []);
                $surveys[] = $row;
            }
        }

        return $surveys;
    }
}

if (!function_exists('pgeservicos_satisfaction_get_survey_for_management')) {
    function pgeservicos_satisfaction_get_survey_for_management($survey_id) {
        global $DB;

        $survey_id = (int)$survey_id;
        if ($survey_id <= 0 || !pgeservicos_satisfaction_user_can_create_surveys() || !pgeservicos_satisfaction_plugin_ready()) {
            return ['ok' => false, 'message' => 'Pesquisa inválida.'];
        }

        $query = "SELECT sv.`id`, sv.`name`, sv.`comment`, sv.`entities_id`, sv.`is_recursive`, sv.`is_active`,
                         sv.`date_creation`, sv.`date_mod`, e.`completename` AS entity_name
                  FROM `glpi_plugin_satisfaction_surveys` sv
                  LEFT JOIN `glpi_entities` e ON e.`id` = sv.`entities_id`
                  WHERE sv.`id` = {$survey_id}
                  LIMIT 1";
        $result = $DB->query($query);

        if (!$result || $DB->numrows($result) === 0) {
            return ['ok' => false, 'message' => 'Pesquisa não encontrada.'];
        }

        $survey = $DB->fetchAssoc($result);
        $survey['answer_count'] = pgeservicos_satisfaction_survey_answer_count($survey_id);
        $survey['questions'] = pgeservicos_satisfaction_load_questions($survey_id, []);

        return ['ok' => true, 'survey' => $survey];
    }
}

if (!function_exists('pgeservicos_satisfaction_set_survey_active')) {
    function pgeservicos_satisfaction_set_survey_active($survey_id, $active) {
        if (!pgeservicos_satisfaction_user_can_create_surveys()) {
            return ['ok' => false, 'message' => 'Apenas o perfil Super-Admin pode gerenciar pesquisas de satisfação.'];
        }

        if (!pgeservicos_satisfaction_plugin_ready()) {
            return ['ok' => false, 'message' => 'O plugin Satisfaction não está disponível.'];
        }

        $survey_id = (int)$survey_id;
        $survey = new PluginSatisfactionSurvey();

        if ($survey_id <= 0 || !$survey->getFromDB($survey_id)) {
            return ['ok' => false, 'message' => 'Pesquisa não encontrada.'];
        }

        if (!$survey->update([
            'id'        => $survey_id,
            'is_active' => !empty($active) ? 1 : 0,
        ])) {
            return ['ok' => false, 'message' => 'Não foi possível atualizar o status da pesquisa. Verifique se já existe uma pesquisa ativa para essa entidade.'];
        }

        return ['ok' => true, 'message' => !empty($active) ? 'Pesquisa ativada com sucesso.' : 'Pesquisa inativada com sucesso.'];
    }
}

if (!function_exists('pgeservicos_satisfaction_create_survey')) {
    function pgeservicos_satisfaction_create_survey(array $input) {
        if (!pgeservicos_satisfaction_user_can_create_surveys()) {
            return ['ok' => false, 'message' => 'Apenas o perfil Super-Admin pode criar pesquisas de satisfação.'];
        }

        if (!pgeservicos_satisfaction_plugin_ready()) {
            return ['ok' => false, 'message' => 'O plugin Satisfaction não está disponível.'];
        }

        $survey_id = isset($input['survey_id']) ? (int)$input['survey_id'] : 0;
        $entities_id = isset($input['entities_id']) ? (int)$input['entities_id'] : -1;
        $name = trim((string)($input['name'] ?? ''));
        $comment = Html::cleanPostForTextArea((string)($input['comment'] ?? ''));
        $is_active = !empty($input['is_active']) ? 1 : 0;
        $is_recursive = !empty($input['is_recursive']) ? 1 : 0;

        if ($entities_id < 0) {
            return ['ok' => false, 'message' => 'Selecione uma entidade para a pesquisa.'];
        }

        if ($name === '') {
            return ['ok' => false, 'message' => 'Informe o nome da pesquisa.'];
        }

        if ($survey_id > 0 && pgeservicos_satisfaction_survey_answer_count($survey_id) > 0) {
            return ['ok' => false, 'message' => 'Você não pode editar as perguntas quando houver respostas para essa pesquisa. Desative esta pesquisa e crie uma nova.'];
        }

        $question_names = isset($input['question_name']) && is_array($input['question_name']) ? $input['question_name'] : [];
        $question_types = isset($input['question_type']) && is_array($input['question_type']) ? $input['question_type'] : [];
        $question_comments = isset($input['question_comment']) && is_array($input['question_comment']) ? $input['question_comment'] : [];
        $question_numbers = isset($input['question_number']) && is_array($input['question_number']) ? $input['question_number'] : [];
        $question_defaults = isset($input['question_default_value']) && is_array($input['question_default_value']) ? $input['question_default_value'] : [];
        $allowed_types = [
            PluginSatisfactionSurveyQuestion::NOTE,
            PluginSatisfactionSurveyQuestion::YESNO,
            PluginSatisfactionSurveyQuestion::TEXTAREA,
        ];
        $questions = [];

        foreach ($question_names as $index => $question_name) {
            $question_name = trim((string)$question_name);
            if ($question_name === '') {
                continue;
            }

            $type = (string)($question_types[$index] ?? PluginSatisfactionSurveyQuestion::NOTE);
            if (!in_array($type, $allowed_types, true)) {
                $type = PluginSatisfactionSurveyQuestion::NOTE;
            }

            if ($type === PluginSatisfactionSurveyQuestion::NOTE) {
                $raw_number = isset($question_numbers[$index]) ? (int)$question_numbers[$index] : 5;
                $raw_default = isset($question_defaults[$index]) ? (int)$question_defaults[$index] : 1;

                if ($raw_number < 1 || $raw_number > 10) {
                    return ['ok' => false, 'message' => 'A nota máxima deve estar entre 1 e 10.'];
                }

                if ($raw_default < 1 || $raw_default > $raw_number) {
                    return ['ok' => false, 'message' => 'O valor padrão da pergunta de nota deve estar entre 1 e a nota máxima.'];
                }

                $number = $raw_number;
                $default_value = $raw_default;
            } elseif ($type === PluginSatisfactionSurveyQuestion::TEXTAREA) {
                $number = 0;
                $default_value = 0;
            } else {
                $number = 0;
                $default_value = 0;
            }

            $questions[] = [
                'name'          => Html::cleanPostForTextArea($question_name),
                'type'          => $type,
                'comment'       => Html::cleanPostForTextArea((string)($question_comments[$index] ?? '')),
                'number'        => $number,
                'default_value' => $default_value,
            ];
        }

        if (empty($questions)) {
            return ['ok' => false, 'message' => 'Adicione ao menos uma pergunta para a pesquisa.'];
        }

        $survey = new PluginSatisfactionSurvey();
        if ($survey_id > 0) {
            if (!$survey->getFromDB($survey_id)) {
                return ['ok' => false, 'message' => 'Pesquisa não encontrada.'];
            }

            $saved = $survey->update([
                'id'           => $survey_id,
                'entities_id'  => $entities_id,
                'is_recursive' => $is_recursive,
                'is_active'    => $is_active,
                'name'         => $name,
                'comment'      => $comment,
            ]);

            if (!$saved) {
                return ['ok' => false, 'message' => 'Não foi possível salvar a pesquisa. Verifique se já existe uma pesquisa ativa para essa entidade.'];
            }

            $question_obj = new PluginSatisfactionSurveyQuestion();
            $question_obj->deleteByCriteria([
                PluginSatisfactionSurveyQuestion::$items_id => $survey_id,
            ]);
        } else {
            $survey_id = $survey->add([
                'entities_id'  => $entities_id,
                'is_recursive' => $is_recursive,
                'is_active'    => $is_active,
                'name'         => $name,
                'comment'      => $comment,
            ]);

            if (!$survey_id) {
                return ['ok' => false, 'message' => 'Não foi possível criar a pesquisa. Verifique se já existe uma pesquisa ativa para essa entidade.'];
            }
        }

        $question_obj = new PluginSatisfactionSurveyQuestion();
        foreach ($questions as $question) {
            $question_input = $question + [
                PluginSatisfactionSurveyQuestion::$items_id => (int)$survey_id,
            ];
            $question_obj->add($question_input);
        }

        return [
            'ok'        => true,
            'message'   => isset($input['survey_id']) && (int)$input['survey_id'] > 0
                ? 'Pesquisa de satisfação atualizada com sucesso.'
                : 'Pesquisa de satisfação criada com sucesso.',
            'survey_id' => (int)$survey_id,
        ];
    }
}
