<?php

include('../../../inc/includes.php');

Session::checkLoginUser();

global $CFG_GLPI;

$catalog = include(__DIR__ . '/../data/catalog/services.php');
require_once(__DIR__ . '/../inc/catalog/formcreator_catalog.php');
require_once(__DIR__ . '/../inc/theme.php');

if (!function_exists('pgeservicos_h')) {
    function pgeservicos_h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

Html::header(
    'PGE Serviços - Área',
    $_SERVER['PHP_SELF'],
    'tools',
    'PluginPgeservicosPortal'
);

$asset_version = defined('PLUGIN_PGESERVICOS_VERSION')
    ? PLUGIN_PGESERVICOS_VERSION
    : '1';

echo "<link rel='stylesheet' href='"
    . pgeservicos_h($CFG_GLPI['root_doc'])
    . "/plugins/pgeservicos/css/shared/pgeservicos.css?v="
    . pgeservicos_h($asset_version)
    . "'>";

echo "<link rel='stylesheet' href='"
    . pgeservicos_h($CFG_GLPI['root_doc'])
    . "/plugins/pgeservicos/css/pages/area.css?v="
    . pgeservicos_h($asset_version)
    . "'>";

pgeservicos_theme_print_vars();

$area_key = $_GET['area'] ?? '';

echo "<div class='pgeservicos-container pgeservicos-area-page'>";

if (!is_array($catalog) || !isset($catalog[$area_key])) {
    $home_url = ($CFG_GLPI['root_doc'] ?? '') . "/plugins/pgeservicos/front/index.php";

    echo "
    <div class='pgeservicos-service-page'>
        <a class='pgeservicos-back-link' href='" . pgeservicos_h($home_url) . "'>&larr; Voltar para o Portal de Serviços</a>
        <h1>Área não encontrada</h1>
        <p>A área solicitada não foi localizada no catálogo de serviços.</p>
    </div>
    ";

    echo "</div>";
    Html::footer();
    exit;
}

$area = $catalog[$area_key];

$hero_title  = pgeservicos_h($area['hero_title'] ?? ($area['title'] ?? 'Área sem título'));
$description = pgeservicos_h($area['description'] ?? '');
$color       = pgeservicos_h($area['color'] ?? 'blue');
$defaultIcon = pgeservicos_h($area['icon'] ?? 'ti ti-folder');

$home_url = ($CFG_GLPI['root_doc'] ?? '') . "/plugins/pgeservicos/front/index.php";

echo "
<section class='pgeservicos-hero'>
    <h1>{$hero_title}</h1>
    <p>{$description}</p>
</section>
";

echo "
<a class='pgeservicos-back-link' href='" . pgeservicos_h($home_url) . "'>&larr; Voltar para o Portal de Serviços</a>
";

$is_formcreator_area = (($area['dynamic_source'] ?? '') === 'formcreator');

if ($is_formcreator_area) {
    $forced_entities_id = $area['formcreator_entities_id'] ?? null;
    $display_mode = $area['formcreator_display'] ?? 'categories';

    $fc_catalog = pgeservicos_get_formcreator_catalog($forced_entities_id);

    if ($display_mode === 'forms') {
        echo "
        <h2 class='pgeservicos-section-title'>Formulários disponíveis</h2>
        <p class='pgeservicos-subtitle'>
            Selecione o formulário que melhor corresponde à sua necessidade.
        </p>
        ";

        if (!$fc_catalog['available']) {
            echo "
            <div class='pgeservicos-info-box'>
                <h3>Form Creator não localizado</h3>
                <p>" . pgeservicos_h($fc_catalog['message']) . "</p>
            </div>
            ";
        } elseif (empty($fc_catalog['categories'])) {
            echo "
            <div class='pgeservicos-info-box'>
                <h3>Nenhum formulário encontrado</h3>
                <p>Não foram localizados formulários ativos para exibição nesta área.</p>
            </div>
            ";
        } else {
            echo "<div class='pgeservicos-grid'>";

            foreach ($fc_catalog['categories'] as $category) {
                $category_name = $category['name'] ?? 'Sem categoria';

                foreach ($category['forms'] as $form) {
                    $form_url = ($CFG_GLPI['root_doc'] ?? '')
                        . "/plugins/pgeservicos/front/abrir_formulario.php?form_id="
                        . (int)$form['id']
                        . "&entity_id="
                        . (int)($form['entities_id'] ?? -1);

                    echo "
                    <div class='pgeservicos-card pgeservicos-area-{$color}'>
                        <div class='pgeservicos-card-icon'>
                            <i class='ti ti-forms'></i>
                        </div>
                        <h3>" . pgeservicos_h($form['name']) . "</h3>
                        <p>
                            <strong>Categoria:</strong> " . pgeservicos_h($category_name) . "<br>
                            " . pgeservicos_h($form['description']) . "
                        </p>
                        <a href='" . pgeservicos_h($form_url) . "'>Abrir formulário</a>
                    </div>
                    ";
                }
            }

            echo "</div>";
        }

        echo "</div>";
        Html::footer();
        exit;
    }

    echo "
    <h2 class='pgeservicos-section-title'>Categorias de formulários</h2>
    <p class='pgeservicos-subtitle'>
        Selecione uma categoria para visualizar os formulários disponíveis.
    </p>
    ";

    if (!$fc_catalog['available']) {
        echo "
        <div class='pgeservicos-info-box'>
            <h3>Form Creator não localizado</h3>
            <p>" . pgeservicos_h($fc_catalog['message']) . "</p>
        </div>
        ";
    } elseif (empty($fc_catalog['categories'])) {
        echo "
        <div class='pgeservicos-info-box'>
            <h3>Nenhuma categoria encontrada</h3>
            <p>Não foram localizadas categorias com formulários ativos para exibição.</p>
        </div>
        ";
    } else {
        echo "<div class='pgeservicos-grid'>";

        foreach ($fc_catalog['categories'] as $category) {
            $category_id    = (int)$category['id'];
            $category_name  = pgeservicos_h($category['name']);
            $forms_count    = count($category['forms']);
            $forms_label    = $forms_count === 1 ? '1 formulário disponível' : $forms_count . ' formulários disponíveis';

            $is_solicitacao_geral = (
                mb_strtolower(trim($category['name']), 'UTF-8') === mb_strtolower('Solicitação Geral', 'UTF-8')
                && $forms_count === 1
            );

            if ($is_solicitacao_geral) {
                $first_form = reset($category['forms']);

                $url = ($CFG_GLPI['root_doc'] ?? '')
                    . "/plugins/pgeservicos/front/abrir_formulario.php?form_id="
                    . (int)$first_form['id']
                    . "&entity_id="
                    . (int)($first_form['entities_id'] ?? -1);

                $button_label = 'Abrir formulário';
            } else {
                $url = ($CFG_GLPI['root_doc'] ?? '')
                    . "/plugins/pgeservicos/front/servico.php?area="
                    . urlencode($area_key)
                    . "&categoria="
                    . urlencode((string)$category_id);

                $button_label = 'Ver formulários';
            }

            echo "
            <div class='pgeservicos-card pgeservicos-area-{$color}'>
                <div class='pgeservicos-card-icon'>
                    <i class='ti ti-category'></i>
                </div>
                <h3>{$category_name}</h3>
                <p>{$forms_label}</p>
                <a href='" . pgeservicos_h($url) . "'>" . pgeservicos_h($button_label) . "</a>
            </div>
            ";
        }

        echo "</div>";
    }

    echo "</div>";
    Html::footer();
    exit;
}

echo "
<h2 class='pgeservicos-section-title'>Serviços disponíveis</h2>
<p class='pgeservicos-subtitle'>
    Selecione abaixo o serviço desejado para consultar mais informações e orientações.
</p>
";

if (!isset($area['services']) || !is_array($area['services']) || count($area['services']) === 0) {
    echo "
    <div class='pgeservicos-info-box'>
        <h3>Nenhum serviço cadastrado</h3>
        <p>Esta área ainda não possui serviços cadastrados.</p>
    </div>
    ";
} else {
    echo "<div class='pgeservicos-grid'>";

    foreach ($area['services'] as $service_key => $service) {
        $service_title = pgeservicos_h($service['title'] ?? 'Serviço sem título');
        $service_desc  = pgeservicos_h($service['description'] ?? '');
        $service_icon  = pgeservicos_h($service['icon'] ?? $defaultIcon);

        $url = ($CFG_GLPI['root_doc'] ?? '') . "/plugins/pgeservicos/front/servico.php?area=" . urlencode($area_key) . "&servico=" . urlencode($service_key);

        echo "
        <div class='pgeservicos-card pgeservicos-area-{$color}'>
            <div class='pgeservicos-card-icon'>
                <i class='{$service_icon}'></i>
            </div>
            <h3>{$service_title}</h3>
            <p>{$service_desc}</p>
            <a href='" . pgeservicos_h($url) . "'>Ver detalhes</a>
        </div>
        ";
    }

    echo "</div>";
}

echo "</div>";

Html::footer();
