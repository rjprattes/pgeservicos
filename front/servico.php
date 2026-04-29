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
    'PGE Serviços - Serviço',
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
    . "/plugins/pgeservicos/css/pages/servico.css?v="
    . pgeservicos_h($asset_version)
    . "'>";

pgeservicos_theme_print_vars();

$area_key     = $_GET['area'] ?? '';
$service_key  = $_GET['servico'] ?? '';
$category_key = $_GET['categoria'] ?? '';

echo "<div class='pgeservicos-container pgeservicos-servico-page'>";

$home_url = ($CFG_GLPI['root_doc'] ?? '') . "/plugins/pgeservicos/front/index.php";

if (!is_array($catalog) || !isset($catalog[$area_key])) {
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

$area_url = ($CFG_GLPI['root_doc'] ?? '') . "/plugins/pgeservicos/front/area.php?area=" . urlencode($area_key);

$is_formcreator_area = (($area['dynamic_source'] ?? '') === 'formcreator');

if ($is_formcreator_area && $category_key !== '') {
    $forced_entities_id = $area['formcreator_entities_id'] ?? null;
    $fc_catalog = pgeservicos_get_formcreator_catalog($forced_entities_id);

    $category_id = (int)$category_key;

    if (!$fc_catalog['available'] || !isset($fc_catalog['categories'][$category_id])) {
        echo "
        <div class='pgeservicos-service-page'>
	    <a class='pgeservicos-back-link' href='" . pgeservicos_h($area_url) . "'>&larr; Voltar para " . pgeservicos_h($area['title'] ?? 'Área') . "</a>
            <h1>Categoria não encontrada</h1>
            <p>A categoria solicitada não foi localizada ou não possui formulários ativos.</p>
        </div>
        ";

        echo "</div>";
        Html::footer();
        exit;
    }

    $category = $fc_catalog['categories'][$category_id];

    echo "
    <section class='pgeservicos-hero'>
        <h1>" . pgeservicos_h($category['name']) . "</h1>
        <p>
            Formulários disponíveis para abertura de solicitações relacionadas a esta categoria.
        </p>
    </section>

    <a class='pgeservicos-back-link' href='" . pgeservicos_h($area_url) . "'>&larr; Voltar para Serviços de Informática</a>

    <h2 class='pgeservicos-section-title'>Formulários disponíveis</h2>
    <p class='pgeservicos-subtitle'>
        Selecione o formulário que melhor corresponde à sua necessidade.
    </p>
    ";

    if (empty($category['forms'])) {
        echo "
        <div class='pgeservicos-info-box'>
            <h3>Nenhum formulário encontrado</h3>
            <p>Esta categoria não possui formulários ativos disponíveis.</p>
        </div>
        ";
    } else {
        echo "<div class='pgeservicos-grid'>";

        foreach ($category['forms'] as $form) {
	    $form_url = ($CFG_GLPI['root_doc'] ?? '')
    		. "/plugins/pgeservicos/front/abrir_formulario.php?form_id="
    		. (int)$form['id']
    		. "&entity_id="
    		. (int)($form['entities_id'] ?? -1);
            echo "
            <div class='pgeservicos-card pgeservicos-area-blue'>
                <div class='pgeservicos-card-icon'>
                    <i class='ti ti-forms'></i>
                </div>
                <h3>" . pgeservicos_h($form['name']) . "</h3>
                <p>" . pgeservicos_h($form['description']) . "</p>
                <a href='" . pgeservicos_h($form_url) . "'>Abrir formulário</a>
            </div>
            ";
        }

        echo "</div>";
    }

    echo "</div>";
    Html::footer();
    exit;
}

if (!isset($area['services']) || !is_array($area['services']) || !isset($area['services'][$service_key])) {
    echo "
    <div class='pgeservicos-service-page'>
        <a class='pgeservicos-back-link' href='" . pgeservicos_h($area_url) . "'>&larr; Voltar para " . pgeservicos_h($area['title'] ?? 'Área') . "</a>
        <h1>Serviço não encontrado</h1>
        <p>O serviço solicitado não foi localizado no catálogo desta área.</p>
    </div>
    ";

    echo "</div>";
    Html::footer();
    exit;
}

$service = $area['services'][$service_key];

$area_title    = pgeservicos_h($area['title'] ?? 'Área');
$area_color    = pgeservicos_h($area['color'] ?? 'blue');
$title         = pgeservicos_h($service['title'] ?? 'Serviço sem título');
$category      = pgeservicos_h($service['category'] ?? 'Serviço');
$description   = pgeservicos_h($service['description'] ?? '');
$request       = pgeservicos_h($service['request'] ?? 'Registre a solicitação pelo canal oficial de atendimento.');

echo "
<div class='pgeservicos-service-page pgeservicos-area-{$area_color}'>
    <a class='pgeservicos-back-link' href='" . pgeservicos_h($area_url) . "'>&larr; Voltar para {$area_title}</a>

    <div class='pgeservicos-badge'>{$category}</div>

    <h1>{$title}</h1>

    <p>{$description}</p>

    <h2>O que este serviço contempla</h2>
";

if (isset($service['items']) && is_array($service['items']) && count($service['items']) > 0) {
    echo "<ul>";

    foreach ($service['items'] as $item) {
        echo "<li>" . pgeservicos_h($item) . "</li>";
    }

    echo "</ul>";
} else {
    echo "<p>Nenhum item detalhado foi cadastrado para este serviço.</p>";
}

echo "
    <h2>Como solicitar</h2>
    <p>{$request}</p>
</div>
";

echo "</div>";

Html::footer();
