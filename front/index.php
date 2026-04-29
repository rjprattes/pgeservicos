<?php

include('../../../inc/includes.php');

Session::checkLoginUser();

global $CFG_GLPI;

$catalog = include(__DIR__ . '/../data/catalog/services.php');
require_once(__DIR__ . '/../inc/catalog/formcreator_catalog.php');
require_once(__DIR__ . '/../inc/theme.php');

function pgeservicos_h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pgeservicos_search_text($parts) {
    $text_parts = [];

    foreach ($parts as $part) {
        if (is_array($part)) {
            $text_parts[] = pgeservicos_search_text($part);
        } elseif ($part !== null && $part !== '') {
            $text_parts[] = (string)$part;
        }
    }

    return trim(implode(' ', $text_parts));
}

function pgeservicos_build_search_index($catalog, $root_doc) {
    $results = [];

    if (!is_array($catalog)) {
        return $results;
    }

    foreach ($catalog as $area_key => $area) {
        $area_title = $area['title'] ?? 'Área sem título';
        $area_url = !empty($area['direct_url'])
            ? ($root_doc ?? '') . $area['direct_url']
            : ($root_doc ?? '') . '/plugins/pgeservicos/front/area.php?area=' . urlencode($area_key);

        $results[] = [
            'title'       => $area_title,
            'description' => $area['description'] ?? '',
            'type'        => 'Área',
            'url'         => $area_url,
            'keywords'    => pgeservicos_search_text([
                $area_title,
                $area['short_title'] ?? '',
                $area['hero_title'] ?? '',
                $area['description'] ?? '',
                $area['button_label'] ?? ''
            ])
        ];

        if (empty($area['services']) || !is_array($area['services'])) {
            if (($area['dynamic_source'] ?? '') === 'formcreator') {
                $results = array_merge(
                    $results,
                    pgeservicos_build_formcreator_search_results($area_key, $area, $root_doc)
                );
            }

            continue;
        }

        foreach ($area['services'] as $service_key => $service) {
            $service_title = $service['title'] ?? 'Serviço sem título';
            $service_url = (($area['dynamic_source'] ?? '') === 'formcreator')
                ? $area_url
                : ($root_doc ?? '') . '/plugins/pgeservicos/front/servico.php?area='
                    . urlencode($area_key)
                    . '&servico='
                    . urlencode($service_key);

            $results[] = [
                'title'       => $service_title,
                'description' => $service['description'] ?? '',
                'type'        => $area_title,
                'url'         => $service_url,
                'keywords'    => pgeservicos_search_text([
                    $service_title,
                    $area_title,
                    $area['short_title'] ?? '',
                    $service['category'] ?? '',
                    $service['description'] ?? '',
                    $service['items'] ?? [],
                    $service['request'] ?? ''
                ])
            ];
        }

        if (($area['dynamic_source'] ?? '') === 'formcreator') {
            $results = array_merge(
                $results,
                pgeservicos_build_formcreator_search_results($area_key, $area, $root_doc)
            );
        }
    }

    return $results;
}

function pgeservicos_build_formcreator_search_results($area_key, $area, $root_doc) {
    if (!function_exists('pgeservicos_get_formcreator_catalog')) {
        return [];
    }

    $fc_catalog = pgeservicos_get_formcreator_catalog($area['formcreator_entities_id'] ?? null);

    if (!$fc_catalog['available'] || empty($fc_catalog['categories'])) {
        return [];
    }

    $results = [];
    $area_title = $area['title'] ?? 'Área sem título';
    $display_mode = $area['formcreator_display'] ?? 'categories';

    foreach ($fc_catalog['categories'] as $category) {
        $category_name = $category['name'] ?? 'Sem categoria';

        if ($display_mode === 'categories') {
            $results[] = [
                'title'       => $category_name,
                'description' => 'Categoria de formulários de ' . $area_title . '.',
                'type'        => 'Categoria de formulário',
                'url'         => ($root_doc ?? '') . '/plugins/pgeservicos/front/servico.php?area='
                    . urlencode($area_key)
                    . '&categoria='
                    . urlencode((string)($category['id'] ?? 0)),
                'keywords'    => pgeservicos_search_text([
                    $category_name,
                    $area_title,
                    $area['short_title'] ?? '',
                    'formulário formulário nativo glpi formcreator categoria solicitação'
                ])
            ];
        }

        foreach ($category['forms'] as $form) {
            $form_name = $form['name'] ?? 'Formulário sem título';
            $form_description = $form['description'] ?? 'Formulário disponível para abertura de solicitação.';
            $form_url = ($root_doc ?? '')
                . '/plugins/pgeservicos/front/abrir_formulario.php?form_id='
                . (int)($form['id'] ?? 0)
                . '&entity_id='
                . (int)($form['entities_id'] ?? -1);

            $results[] = [
                'title'       => $form_name,
                'description' => $form_description,
                'type'        => 'Formulário',
                'url'         => $form_url,
                'keywords'    => pgeservicos_search_text([
                    $form_name,
                    $form_description,
                    $category_name,
                    $area_title,
                    $area['short_title'] ?? '',
                    'formulário formulário nativo glpi formcreator solicitação pedido'
                ])
            ];
        }
    }

    return $results;
}

$search_results = pgeservicos_build_search_index($catalog, $CFG_GLPI['root_doc'] ?? '');
$index_url = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/pgeservicos/front/index.php';
$search_results[] = [
    'title'       => 'Canais de atendimento',
    'description' => 'Telefones, WhatsApp e e-mails dos suportes de TI, GEAD e PGE.Net.',
    'type'        => 'Portal',
    'url'         => $index_url . '#pgeservicos-canais-atendimento',
    'keywords'    => 'canais atendimento suporte telefone whatsapp email contato ti gead pge.net saj procuradorias'
];
$search_results[] = [
    'title'       => 'Suporte de TI',
    'description' => 'WhatsApp (27) 99934-7037, telefone 0800 8801 802 e e-mail suporte@pge.es.gov.br.',
    'type'        => 'Canal de atendimento',
    'url'         => $index_url . '#pgeservicos-suporte-ti',
    'keywords'    => 'suporte ti informática whatsapp 27999347037 telefone 08008801802 email suporte@pge.es.gov.br'
];
$search_results[] = [
    'title'       => 'Suporte administrativo (GEAD)',
    'description' => 'Telefone (27) 3636-5065 e e-mail gead@pge.es.gov.br.',
    'type'        => 'Canal de atendimento',
    'url'         => $index_url . '#pgeservicos-suporte-gead',
    'keywords'    => 'suporte administrativo gead telefone 2736365065 email gead@pge.es.gov.br'
];
$search_results[] = [
    'title'       => 'Suporte PGE.Net (SAJ Procuradorias)',
    'description' => 'WhatsApp (48) 99905-6437, telefones (27) 3636-5072 ou 5073 e e-mail pgenet@pge.es.gov.br.',
    'type'        => 'Canal de atendimento',
    'url'         => $index_url . '#pgeservicos-suporte-pgenet',
    'keywords'    => 'suporte pge.net pgenet saj procuradorias whatsapp 48999056437 telefone 2736365072 2736365073 email pgenet@pge.es.gov.br'
];
$search_results_json = json_encode($search_results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

Html::header(
    'PGE Serviços',
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
    . "/plugins/pgeservicos/css/pages/index.css?v="
    . pgeservicos_h($asset_version)
    . "'>";

pgeservicos_theme_print_vars();

echo "<div class='pgeservicos-container pgeservicos-index-page'>";

echo "
<section class='pgeservicos-hero'>
    <h1>Portal de Serviços da PGE</h1>
    <p>
        Ambiente centralizado para consulta dos serviços prestados pelas áreas internas,
        reunindo orientações, escopo de atendimento e caminhos adequados para solicitação
        de demandas de Informática, Administração, Comissões e Comunicação.
    </p>
</section>
";

echo "
<section class='pgeservicos-search' aria-labelledby='pgeservicos-search-title'>
    <div>
        <h2 id='pgeservicos-search-title'>Pesquisar no portal</h2>
        <p>Digite uma área, serviço, assunto ou canal de atendimento.</p>
    </div>

    <div class='pgeservicos-search-box'>
        <i class='ti ti-search' aria-hidden='true'></i>
        <input
            type='search'
            id='pgeservicos-search-input'
            placeholder='Buscar serviços, áreas e orientações'
            autocomplete='off'
            aria-controls='pgeservicos-search-results'
            aria-expanded='false'
        >
        <button
            type='button'
            id='pgeservicos-search-clear'
            class='pgeservicos-search-clear'
            aria-label='Limpar pesquisa'
            hidden
        >
            <i class='ti ti-x' aria-hidden='true'></i>
        </button>
    </div>

    <div
        id='pgeservicos-search-results'
        class='pgeservicos-search-results'
        aria-live='polite'
        hidden
    ></div>

    <div
        id='pgeservicos-search-data'
        data-results='" . pgeservicos_h($search_results_json ?: '[]') . "'
        hidden
    ></div>
</section>
";

echo "
<h2 class='pgeservicos-section-title'>Áreas de atendimento</h2>
<p class='pgeservicos-subtitle'>
    Selecione uma área para visualizar os serviços disponíveis e orientações para abertura de solicitação.
</p>
";

if (!is_array($catalog) || count($catalog) === 0) {
    echo "
    <div class='pgeservicos-info-box'>
        <h3>Nenhuma área cadastrada</h3>
        <p>O catálogo de serviços não retornou áreas para exibição.</p>
    </div>
    ";
} else {
    echo "<div class='pgeservicos-grid'>";

    foreach ($catalog as $area_key => $area) {
        $title       = pgeservicos_h($area['title'] ?? 'Área sem título');
        $description = pgeservicos_h($area['description'] ?? '');
        $icon        = pgeservicos_h($area['icon'] ?? 'ti ti-folder');
        $color       = pgeservicos_h($area['color'] ?? 'blue');

	if (!empty($area['direct_url'])) {
    	    $url = ($CFG_GLPI['root_doc'] ?? '') . $area['direct_url'];
    	    $button_label = $area['button_label'] ?? 'Acessar';
	} else {
    	    $url = ($CFG_GLPI['root_doc'] ?? '') . "/plugins/pgeservicos/front/area.php?area=" . urlencode($area_key);
 	    $button_label = $area['button_label'] ?? 'Ver serviços';
	}

        echo "
        <div class='pgeservicos-card pgeservicos-area-{$color}'>
            <div class='pgeservicos-card-icon'>
                <i class='{$icon}'></i>
            </div>
            <h3>{$title}</h3>
            <p>{$description}</p>
            <a href='" . pgeservicos_h($url) . "'>" . pgeservicos_h($button_label) . "</a>
        </div>
        ";
    }

    echo "</div>";
}

echo "
<section class='pgeservicos-howto' aria-labelledby='pgeservicos-howto-title'>
    <div class='pgeservicos-howto-heading'>
        <h2 id='pgeservicos-howto-title'>Como utilizar o portal</h2>
        <p>
            Encontre serviços, formulários, reservas e canais oficiais em um só lugar. Use a busca quando
            souber o assunto ou navegue pelas áreas de atendimento para localizar a orientação correta.
        </p>
    </div>

    <div class='pgeservicos-howto-layout'>
        <div class='pgeservicos-howto-steps' aria-label='Etapas de uso do portal'>
            <div class='pgeservicos-howto-step'>
                <span class='pgeservicos-howto-number'>1</span>
                <div>
                    <h3>Pesquise pelo que precisa</h3>
                    <p>
                        Digite o serviço, assunto, formulário ou canal de atendimento para ver resultados
                        correspondentes conforme a busca é preenchida.
                    </p>
                </div>
            </div>

            <div class='pgeservicos-howto-step'>
                <span class='pgeservicos-howto-number'>2</span>
                <div>
                    <h3>Confira a área responsável</h3>
                    <p>
                        Acesse a página da área ou do serviço para verificar escopo, orientações,
                        documentos necessários e o melhor caminho para registrar a demanda.
                    </p>
                </div>
            </div>

            <div class='pgeservicos-howto-step'>
                <span class='pgeservicos-howto-number'>3</span>
                <div>
                    <h3>Registre pelo canal indicado</h3>
                    <p>
                        Abra o formulário, solicite o serviço, reserve uma sala ou utilize o canal oficial
                        informado para garantir rastreabilidade e acompanhamento adequado.
                    </p>
                </div>
            </div>

            <div class='pgeservicos-howto-step'>
                <span class='pgeservicos-howto-number'>4</span>
                <div>
                    <h3>Acompanhe a solicitação</h3>
                    <p>
                        Quando houver chamado no GLPI, acompanhe o andamento pela própria plataforma e
                        mantenha as informações atualizadas para agilizar o atendimento.
                    </p>
                </div>
            </div>
        </div>

        <div class='pgeservicos-info-box pgeservicos-contact-panel' id='pgeservicos-canais-atendimento'>
            <h3>Canais de atendimento</h3>
            <p>
                Utilize o Portal de Serviços como canal principal para abertura e acompanhamento das solicitações. Os canais secundários permanecem disponíveis como apoio para dúvidas, orientações e situações que exijam contato complementar.
            </p>

            <div class='pgeservicos-contact-list'>
                <div class='pgeservicos-contact-group' id='pgeservicos-suporte-ti'>
                    <h4>Suporte de TI</h4>
                    <div class='pgeservicos-contact-actions'>
                        <a href='https://wa.me/5527999347037' target='_blank' rel='noopener noreferrer'>
                            <i class='ti ti-brand-whatsapp' aria-hidden='true'></i>
                            <span>
                                <strong>WhatsApp</strong>
                                <small>(27) 99934-7037</small>
                            </span>
                        </a>
                        <a href='tel:08008801802'>
                            <i class='ti ti-phone' aria-hidden='true'></i>
                            <span>
                                <strong>Telefone</strong>
                                <small>0800 8801 802</small>
                            </span>
                        </a>
                        <a href='mailto:suporte@pge.es.gov.br'>
                            <i class='ti ti-mail' aria-hidden='true'></i>
                            <span>
                                <strong>Email</strong>
                                <small>suporte@pge.es.gov.br</small>
                            </span>
                        </a>
                    </div>
                </div>

                <div class='pgeservicos-contact-group' id='pgeservicos-suporte-gead'>
                    <h4>Suporte administrativo (GEAD)</h4>
                    <div class='pgeservicos-contact-actions'>
                        <a href='tel:2736365065'>
                            <i class='ti ti-phone' aria-hidden='true'></i>
                            <span>
                                <strong>Telefone</strong>
                                <small>(27) 3636-5065</small>
                            </span>
                        </a>
                        <a href='mailto:gead@pge.es.gov.br'>
                            <i class='ti ti-mail' aria-hidden='true'></i>
                            <span>
                                <strong>Email</strong>
                                <small>gead@pge.es.gov.br</small>
                            </span>
                        </a>
                    </div>
                </div>

                <div class='pgeservicos-contact-group' id='pgeservicos-suporte-pgenet'>
                    <h4>Suporte PGE.Net (SAJ Procuradorias)</h4>
                    <div class='pgeservicos-contact-actions'>
                        <a href='https://wa.me/5548999056437' target='_blank' rel='noopener noreferrer'>
                            <i class='ti ti-brand-whatsapp' aria-hidden='true'></i>
                            <span>
                                <strong>WhatsApp</strong>
                                <small>(48) 99905-6437</small>
                            </span>
                        </a>
                        <a href='tel:2736365072'>
                            <i class='ti ti-phone' aria-hidden='true'></i>
                            <span>
                                <strong>Telefone</strong>
                                <small>(27) 3636-5072 ou 5073</small>
                            </span>
                        </a>
                        <a href='mailto:pgenet@pge.es.gov.br'>
                            <i class='ti ti-mail' aria-hidden='true'></i>
                            <span>
                                <strong>Email</strong>
                                <small>pgenet@pge.es.gov.br</small>
                            </span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
";

echo "</div>";

echo "<script src='"
    . pgeservicos_h($CFG_GLPI['root_doc'])
    . "/plugins/pgeservicos/js/pages/index.js?v="
    . pgeservicos_h($asset_version)
    . "'></script>";

Html::footer();
