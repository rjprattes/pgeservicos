<?php

if (!function_exists('pgeservicos_fc_table_exists')) {
    function pgeservicos_fc_table_exists($table) {
        global $DB;

        return method_exists($DB, 'tableExists') && $DB->tableExists($table);
    }
}

if (!function_exists('pgeservicos_fc_field_exists')) {
    function pgeservicos_fc_field_exists($table, $field) {
        global $DB;

        if (method_exists($DB, 'fieldExists')) {
            return $DB->fieldExists($table, $field);
        }

        return true;
    }
}

if (!function_exists('pgeservicos_fc_clean_text')) {
    function pgeservicos_fc_clean_text($value, $limit = 180) {
        $value = (string)$value;
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = strip_tags($value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strlen') && mb_strlen($value) > $limit) {
            return mb_substr($value, 0, $limit - 3) . '...';
        }

        if (strlen($value) > $limit) {
            return substr($value, 0, $limit - 3) . '...';
        }

        return $value;
    }
}

if (!function_exists('pgeservicos_get_formcreator_catalog')) {
    function pgeservicos_get_formcreator_catalog($forced_entities_id = null) {
        global $DB;

        $category_table = 'glpi_plugin_formcreator_categories';
        $form_table     = 'glpi_plugin_formcreator_forms';

        if (
            !pgeservicos_fc_table_exists($category_table)
            || !pgeservicos_fc_table_exists($form_table)
        ) {
            return [
                'available'  => false,
                'categories' => [],
                'message'    => 'As tabelas do Form Creator não foram localizadas.'
            ];
        }

        $categories = [];

        $cat_where = [];

        if (pgeservicos_fc_field_exists($category_table, 'is_active')) {
            $cat_where['is_active'] = 1;
        }

        if (pgeservicos_fc_field_exists($category_table, 'is_deleted')) {
            $cat_where['is_deleted'] = 0;
        }

        $cat_request = [
            'SELECT' => ['id', 'name'],
            'FROM'   => $category_table,
            'ORDER'  => ['name ASC']
        ];

        if (!empty($cat_where)) {
            $cat_request['WHERE'] = $cat_where;
        }

        foreach ($DB->request($cat_request) as $cat) {
            $categories[(int)$cat['id']] = [
                'id'          => (int)$cat['id'],
                'name'        => $cat['name'],
                'description' => '',
                'forms'       => []
            ];
        }

        $form_select = ['id', 'name', 'plugin_formcreator_categories_id'];

        if (pgeservicos_fc_field_exists($form_table, 'content')) {
            $form_select[] = 'content';
        }

        if (pgeservicos_fc_field_exists($form_table, 'description')) {
            $form_select[] = 'description';
        }

        if (pgeservicos_fc_field_exists($form_table, 'entities_id')) {
            $form_select[] = 'entities_id';
        }

        $form_where = [];

        if (pgeservicos_fc_field_exists($form_table, 'is_active')) {
            $form_where['is_active'] = 1;
        }

        if (pgeservicos_fc_field_exists($form_table, 'is_deleted')) {
            $form_where['is_deleted'] = 0;
        }

        /*
         * Medida paliativa:
         * Se uma entidade for informada no services.php, ignora a entidade ativa do usuário
         * e busca apenas formulários daquela entidade específica.
         */
        if (
            $forced_entities_id !== null
            && pgeservicos_fc_field_exists($form_table, 'entities_id')
        ) {
            $form_where['entities_id'] = (int)$forced_entities_id;
        } elseif (pgeservicos_fc_field_exists($form_table, 'entities_id')) {
            $form_where += getEntitiesRestrictCriteria(
                $form_table,
                'entities_id',
                '',
                true
            );
        }

        $form_request = [
            'SELECT' => $form_select,
            'FROM'   => $form_table,
            'ORDER'  => ['name ASC']
        ];

        if (!empty($form_where)) {
            $form_request['WHERE'] = $form_where;
        }

        foreach ($DB->request($form_request) as $form) {
            $category_id = (int)($form['plugin_formcreator_categories_id'] ?? 0);

            if (!isset($categories[$category_id])) {
                $categories[$category_id] = [
                    'id'          => $category_id,
                    'name'        => $category_id > 0 ? 'Categoria #' . $category_id : 'Sem categoria',
                    'description' => '',
                    'forms'       => []
                ];
            }

            $description = '';

            if (isset($form['description'])) {
                $description = pgeservicos_fc_clean_text($form['description']);
            }

            if ($description === '' && isset($form['content'])) {
                $description = pgeservicos_fc_clean_text($form['content']);
            }

            if ($description === '') {
                $description = 'Formulário disponível para abertura de solicitação.';
            }

	    $categories[$category_id]['forms'][] = [
    		'id'          => (int)$form['id'],
   		 'name'        => $form['name'],
    		'description' => $description,
    		'entities_id' => isset($form['entities_id']) ? (int)$form['entities_id'] : -1
	    ];

        }

        foreach ($categories as $id => $category) {
            if (count($category['forms']) === 0) {
                unset($categories[$id]);
            }
        }

        uasort($categories, function ($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });

        return [
            'available'  => true,
            'categories' => $categories,
            'message'    => ''
        ];
    }
}
