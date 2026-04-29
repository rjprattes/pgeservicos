<?php

return [
    'ti' => [
        'title' => 'Serviços de Informática',
        'short_title' => 'Informática',
        'icon' => 'ti ti-headset',
        'color' => 'blue',
	'dynamic_source' => 'formcreator',
	'formcreator_display' => 'categories',
	'formcreator_entities_id' => 0,
        'description' => 'Atendimento de demandas relacionadas à tecnologia da informação, suporte ao usuário, sistemas, equipamentos, rede, acessos e segurança.',
        'services' => [
            'suporte' => [
                'title' => 'Suporte Técnico',
                'category' => 'Atendimento ao usuário',
                'description' => 'Atendimento para incidentes e solicitações relacionadas a computadores, sistemas, acessos, rede e demais recursos de TI.',
                'items' => [
                    'Suporte a computadores e notebooks;',
                    'Apoio em sistemas e acessos;',
                    'Problemas de rede e conectividade;',
                    'Instalação de softwares autorizados;',
                    'Orientações gerais de TI.'
                ],
                'request' => 'A solicitação deve ser registrada pelo GLPI ou pelos canais oficiais do Service Desk.'
            ]
        ]
    ],

    'administrativo' => [
        'title' => 'Serviços Administrativos',
        'short_title' => 'Administrativo',
        'icon' => 'ti ti-building',
        'color' => 'green',
	'dynamic_source' => 'formcreator',
	'formcreator_display' => 'forms',
	'formcreator_entities_id' => 1,
        'description' => 'Atendimento de demandas administrativas, apoio operacional, logística interna, patrimônio, infraestrutura predial e serviços gerais.',
        'services' => [
            'apoio' => [
                'title' => 'Apoio Administrativo',
                'category' => 'Serviços administrativos',
                'description' => 'Atendimento para solicitações administrativas relacionadas à rotina interna, apoio operacional e encaminhamento de demandas.',
                'items' => [
                    'Apoio em rotinas administrativas;',
                    'Solicitação de materiais;',
                    'Demandas de patrimônio;',
                    'Serviços gerais;',
                    'Apoio a salas e eventos.'
                ],
                'request' => 'Informe a necessidade, local, setor solicitante e prazo desejado.'
            ]
        ]
    ],
    'elpi' => [
        'title' => 'Serviços da Comissão ELPI',
        'hero_title' => 'Escritório Local de Processos e Inovação - ELPI',
        'short_title' => 'ELPI',
        'icon' => 'ti ti-bulb',
        'color' => 'purple',
	'description' => 'Apoio à melhoria de processos internos, criação de soluções tecnológicas, automações e iniciativas de inovação institucional.',
        'services' => [
            'eflow' => [
                'title' => 'Criação de Fluxo no E-Flow',
                'icon' => 'ti ti-route',
                'category' => 'Processos e inovação',
                'description' => 'Apoio na estruturação, criação e melhoria de fluxos administrativos no E-Flow, conforme a necessidade da área demandante.',
                'items' => [
                    'Levantamento da necessidade;',
                    'Definição de etapas e responsáveis;',
                    'Criação ou ajuste de formulário;',
                    'Configuração de aprovações e notificações;',
                    'Teste e validação do fluxo.'
                ],
                'request' => 'Informe o objetivo do fluxo, áreas envolvidas, etapas desejadas e regras de encaminhamento.'
            ],
            'mapeamento' => [
                'title' => 'Mapeamento de Procedimentos',
                'icon' => 'ti ti-hierarchy-3',
                'category' => 'Gestão de processos',
                'description' => 'Levantamento e organização das etapas de trabalho para identificar responsabilidades, gargalos e oportunidades de melhoria.',
                'items' => [
                    'Entendimento da rotina atual;',
                    'Identificação dos atores envolvidos;',
                    'Registro das etapas e decisões;',
                    'Identificação de gargalos;',
                    'Proposição de fluxo otimizado.'
                ],
                'request' => 'Informe o procedimento a ser mapeado, unidade responsável e principais envolvidos.'
            ],
            'solucoes' => [
                'title' => 'Criação de Soluções Tecnológicas',
                'icon' => 'ti ti-device-laptop',
                'category' => 'Inovação e tecnologia',
                'description' => 'Desenvolvimento de pequenas soluções, automações e ferramentas internas para apoiar rotinas administrativas e operacionais.',
                'items' => [
                    'Análise da necessidade apresentada;',
                    'Avaliação de viabilidade técnica;',
                    'Prototipação da solução;',
                    'Validação com a área demandante;',
                    'Entrega da versão funcional.'
                ],
                'request' => 'Descreva o problema, rotina atual, resultado esperado e usuários envolvidos.'
            ],
            'automacao' => [
                'title' => 'Automação de Processos',
                'icon' => 'ti ti-settings-automation',
                'category' => 'Automação',
                'description' => 'Análise de tarefas repetitivas e proposta de automações para reduzir retrabalho, padronizar etapas e aumentar a eficiência.',
                'items' => [
                    'Identificação de tarefas repetitivas;',
                    'Mapeamento de pontos de automação;',
                    'Proposição de solução automatizada;',
                    'Validação da automação;',
                    'Acompanhamento da implantação.'
                ],
                'request' => 'Informe a rotina que deseja automatizar, frequência de execução e principais dificuldades encontradas.'
            ],
            'consultoria' => [
                'title' => 'Consultoria em Inovação',
                'icon' => 'ti ti-bulb',
                'category' => 'Inovação',
                'description' => 'Orientação para áreas que desejam aprimorar seus processos, modernizar rotinas ou transformar ideias em soluções práticas.',
                'items' => [
                    'Escuta da necessidade da área;',
                    'Análise de cenário;',
                    'Sugestão de melhorias;',
                    'Proposição de alternativas viáveis;',
                    'Apoio inicial na estruturação da ideia.'
                ],
                'request' => 'Explique a ideia, problema ou oportunidade identificada e o resultado esperado.'
            ],
            'indicadores' => [
                'title' => 'Indicadores e Monitoramento',
                'icon' => 'ti ti-chart-bar',
                'category' => 'Dados e gestão',
                'description' => 'Apoio na definição de indicadores, organização de dados e construção de visões para acompanhamento de processos e resultados.',
                'items' => [
                    'Definição dos indicadores desejados;',
                    'Identificação das fontes de dados;',
                    'Organização das informações;',
                    'Criação de visualizações ou relatórios;',
                    'Apoio na interpretação dos resultados.'
                ],
                'request' => 'Informe quais resultados deseja acompanhar, fontes de dados disponíveis e periodicidade esperada.'
            ]
        ]
    ],
    'reservas' => [
        'title' => 'Reserva de Salas de Reunião',
        'short_title' => 'Reservas',
        'icon' => 'ti ti-calendar-event',
        'color' => 'orange',
        'description' => 'Serviço destinado à consulta, criação e acompanhamento de reservas de salas de reunião disponíveis na PGE.',
        'direct_url' => '/plugins/pgeservicos/front/reservas.php',
        'button_label' => 'Acessar reservas',
        'services' => []
    ],
    'ascom' => [
        'title' => 'Assessoria de Comunicação',
        'short_title' => 'Comunicação',
        'icon' => 'ti ti-speakerphone',
        'color' => 'blue',
        'description' => 'Serviços voltados à comunicação institucional da PGE, incluindo produção de conteúdo, divulgação de ações, campanhas, atendimento à imprensa, redes sociais, identidade visual, comunicação interna e apoio audiovisual.',
        'services' => [
            'sugestao-pauta-institucional' => [
                'title' => 'Sugestão de pauta institucional',
                'icon' => 'ti ti-bulb',
                'category' => 'Divulgação Institucional',
                'description' => 'Envio de sugestão de pauta para avaliação da Assessoria de Comunicação, voltada à divulgação de temas de interesse institucional.',
                'items' => [
                    'Avaliação editorial pela Ascom;',
                    'Verificação do melhor formato de divulgação;',
                    'Análise de alinhamento institucional;',
                    'Encaminhamento para matéria, post, campanha, vídeo ou comunicado, quando aplicável.'
                ],
                'request' => 'Use quando a unidade tiver uma ideia de divulgação, mas ainda não souber qual formato é mais adequado. A sugestão será filtrada pela Ascom para garantir alinhamento com a linha editorial e evitar desgastes institucionais.'
            ],
            'materia-noticia-site' => [
                'title' => 'Solicitação de matéria/notícia para o site',
                'icon' => 'ti ti-news',
                'category' => 'Divulgação Institucional',
                'description' => 'Solicitação de produção de matéria jornalística para divulgação de ações, projetos, eventos, decisões ou iniciativas institucionais da PGE.',
                'items' => [
                    'Título ou tema sugerido;',
                    'Unidade ou setor responsável;',
                    'Resumo da pauta;',
                    'Data do fato ou evento;',
                    'Nomes das fontes;',
                    'Anexos, fotos ou documentos de apoio;',
                    'Urgência da publicação.'
                ],
                'request' => 'Use para pautas que devam ser divulgadas no site institucional da PGE, em linguagem jornalística e com possibilidade de uso de textos, vídeos, áudios e fotos.'
            ],
            'divulgacao-acoes-projetos-resultados' => [
                'title' => 'Divulgação de ações, projetos e resultados',
                'icon' => 'ti ti-chart-infographic',
                'category' => 'Divulgação Institucional',
                'description' => 'Solicitação de divulgação institucional de ações, projetos, entregas, resultados ou iniciativas desenvolvidas pelas áreas da PGE.',
                'items' => [
                    'Projetos desenvolvidos nas setoriais;',
                    'Ações judiciais relevantes;',
                    'Decisões do Conselho;',
                    'Eventos, cursos e ações internas;',
                    'Valorização de servidores e iniciativas institucionais.'
                ],
                'request' => 'Use para divulgar projetos, entregas, resultados, eventos, cursos, ações internas e demais iniciativas alinhadas à linha editorial institucional.'
            ],
            'divulgacao-eventos-cursos-acoes' => [
                'title' => 'Divulgação de eventos, cursos e ações internas',
                'icon' => 'ti ti-calendar-bolt',
                'category' => 'Divulgação Institucional',
                'description' => 'Solicitação de divulgação de eventos, cursos, reuniões, ações internas ou atividades institucionais.',
                'items' => [
                    'Divulgação antes ou depois da realização;',
                    'Criação de card, matéria, post ou vídeo;',
                    'Comunicado interno;',
                    'Apoio na definição do canal de divulgação.'
                ],
                'request' => 'Use quando uma área precisar divulgar evento, curso, reunião, ação interna ou atividade institucional para público interno ou externo.'
            ],
            'comunicado-interno' => [
                'title' => 'Comunicado interno',
                'icon' => 'ti ti-message-circle',
                'category' => 'Comunicação Interna',
                'description' => 'Solicitação de divulgação de comunicados institucionais destinados ao público interno da PGE.',
                'items' => [
                    'E-mail institucional;',
                    'WhatsApp institucional;',
                    'Notícia Legal;',
                    'Mural;',
                    'Intranet, quando aplicável;',
                    'Fundo de tela, se adotado futuramente.'
                ],
                'request' => 'Use para comunicados sobre prazos, orientações internas, eventos, campanhas, avisos administrativos e informações de interesse dos servidores.'
            ],
            'whatsapp-institucional' => [
                'title' => 'Divulgação no WhatsApp institucional',
                'icon' => 'ti ti-brand-whatsapp',
                'category' => 'Comunicação Interna',
                'description' => 'Solicitação de envio de conteúdo pelo canal institucional de WhatsApp da PGE.',
                'items' => [
                    'Público-alvo;',
                    'Texto sugerido;',
                    'Data desejada de envio;',
                    'Imagem ou card anexo, se houver;',
                    'Necessidade de adaptação visual.'
                ],
                'request' => 'Use para avisos internos, campanhas, comunicados rápidos e divulgações de interesse dos públicos internos.'
            ],
            'noticia-legal' => [
                'title' => 'Divulgação no Notícia Legal',
                'icon' => 'ti ti-file-text',
                'category' => 'Comunicação Interna',
                'description' => 'Solicitação de inclusão de conteúdo no informativo interno Notícia Legal.',
                'items' => [
                    'Informações internas simples e diretas;',
                    'Conteúdos voltados aos servidores;',
                    'Adaptação para formato visual;',
                    'Texto curto e adequado ao meio digital.'
                ],
                'request' => 'Use para divulgar informações internas de forma simples, direta e visual, especialmente conteúdos voltados aos servidores.'
            ],
            'email-marketing-boletim' => [
                'title' => 'E-mail marketing / boletim informativo',
                'icon' => 'ti ti-mail',
                'category' => 'Comunicação Interna',
                'description' => 'Solicitação de criação ou envio de boletim eletrônico, newsletter ou comunicado institucional por e-mail.',
                'items' => [
                    'Comunicações internas estruturadas;',
                    'Convites e comunicados da alta direção;',
                    'Newsletters e campanhas;',
                    'Segmentação de público;',
                    'Mensuração de abertura e cliques, quando disponível.'
                ],
                'request' => 'Use para comunicações por e-mail que exijam organização visual, periodicidade, segmentação ou acompanhamento de desempenho.'
            ],
            'publicacao-redes-sociais' => [
                'title' => 'Publicação nas redes sociais',
                'icon' => 'ti ti-share',
                'category' => 'Redes Sociais e Conteúdo Digital',
                'description' => 'Solicitação de produção ou divulgação de conteúdo para os perfis institucionais da PGE nas redes sociais.',
                'items' => [
                    'Post de feed;',
                    'Stories;',
                    'Reels;',
                    'E-card;',
                    'Legenda;',
                    'Carrossel;',
                    'Vídeo curto.'
                ],
                'request' => 'Use para conteúdos objetivos e visuais destinados às redes sociais institucionais da PGE.'
            ],
            'cards-ecards-institucionais' => [
                'title' => 'Produção de cards/e-cards',
                'icon' => 'ti ti-photo-edit',
                'category' => 'Redes Sociais e Conteúdo Digital',
                'description' => 'Solicitação de criação de arte visual para divulgação institucional em canais digitais ou internos.',
                'items' => [
                    'Datas comemorativas;',
                    'Avisos e campanhas;',
                    'Comunicados;',
                    'Eventos;',
                    'Chamadas para cursos;',
                    'Materiais de divulgação.'
                ],
                'request' => 'Use quando a demanda exigir criação de arte visual, card, e-card, cartaz ou publicação digital.'
            ],
            'video-curto-redes' => [
                'title' => 'Produção de vídeo curto',
                'icon' => 'ti ti-video',
                'category' => 'Redes Sociais e Conteúdo Digital',
                'description' => 'Solicitação de produção ou edição de vídeo curto para divulgação em redes sociais, especialmente reels, stories ou publicações institucionais.',
                'items' => [
                    'Eventos e campanhas;',
                    'Falas institucionais;',
                    'Bastidores;',
                    'Cursos;',
                    'Ações da PGE;',
                    'Conteúdos leves e visuais.'
                ],
                'request' => 'Use para vídeos curtos destinados a ampliar alcance, humanizar mensagens e tornar a comunicação mais leve e palatável.'
            ],
            'series-conteudos-recorrentes' => [
                'title' => 'Criação de séries/conteúdos recorrentes',
                'icon' => 'ti ti-repeat',
                'category' => 'Redes Sociais e Conteúdo Digital',
                'description' => 'Solicitação de planejamento de série temática ou conteúdo recorrente para divulgação institucional.',
                'items' => [
                    'Você sabia?;',
                    'Direito em 1 minuto;',
                    'Dica jurídica;',
                    'Histórias da PGE;',
                    'Outros formatos recorrentes de conteúdo de valor.'
                ],
                'request' => 'Use para estruturar publicações recorrentes que ampliem alcance, engajamento e presença institucional.'
            ],
            'peca-grafica-institucional' => [
                'title' => 'Peça gráfica institucional',
                'icon' => 'ti ti-brush',
                'category' => 'Identidade Visual e Materiais Gráficos',
                'description' => 'Solicitação de criação de arte, layout ou peça gráfica institucional para divulgação interna ou externa.',
                'items' => [
                    'Cartaz, card ou banner;',
                    'Convite ou informativo;',
                    'Arte para evento;',
                    'Capa de apresentação;',
                    'Material impresso;',
                    'Material para redes sociais.'
                ],
                'request' => 'Use quando for necessária a criação de peça visual institucional para canais internos, externos, impressos ou digitais.'
            ],
            'validacao-logomarca-assinatura' => [
                'title' => 'Validação de uso de logomarca/assinatura',
                'icon' => 'ti ti-certificate',
                'category' => 'Identidade Visual e Materiais Gráficos',
                'description' => 'Solicitação de análise, orientação ou validação do uso da assinatura institucional, logomarcas internas e elementos de identidade visual da PGE.',
                'items' => [
                    'Uso da assinatura institucional;',
                    'Uso de brasão ou marca de programa;',
                    'Aplicação em peças internas e externas;',
                    'Materiais de fornecedores ou parceiros;',
                    'Orientação sobre guarda e uso correto das marcas.'
                ],
                'request' => 'Use quando uma área, fornecedor, parceiro ou servidor precisar aplicar assinatura, brasão, marca de programa ou identidade visual da PGE.'
            ],
            'padronizacao-visual' => [
                'title' => 'Orientação sobre padronização visual',
                'icon' => 'ti ti-layout',
                'category' => 'Identidade Visual e Materiais Gráficos',
                'description' => 'Solicitação de orientação para aplicação das diretrizes visuais da PGE em materiais institucionais.',
                'items' => [
                    'Materiais institucionais;',
                    'Apresentações;',
                    'Cards e banners;',
                    'Conteúdos que precisam manter coerência visual;',
                    'Diretrizes de signos identificadores e composições.'
                ],
                'request' => 'Use para orientar a criação de materiais e fortalecer a identidade institucional da PGE.'
            ],
            'cobertura-evento' => [
                'title' => 'Cobertura de evento',
                'icon' => 'ti ti-camera',
                'category' => 'Cobertura e Audiovisual',
                'description' => 'Solicitação de cobertura jornalística, fotográfica ou audiovisual de evento institucional da PGE.',
                'items' => [
                    'Nome do evento;',
                    'Data, horário e local;',
                    'Público-alvo;',
                    'Autoridades ou fontes presentes;',
                    'Necessidade de foto, vídeo, matéria ou redes sociais;',
                    'Responsável pelo evento.'
                ],
                'request' => 'Use para cursos, reuniões, palestras, seminários, eventos internos, eventos externos com participação da PGE e ações institucionais.'
            ],
            'gravacao-edicao-video' => [
                'title' => 'Gravação ou edição de vídeo',
                'icon' => 'ti ti-movie',
                'category' => 'Cobertura e Audiovisual',
                'description' => 'Solicitação de apoio para gravação, edição ou publicação de vídeo institucional.',
                'items' => [
                    'Vídeos de eventos;',
                    'Chamadas institucionais;',
                    'Mensagens internas;',
                    'Cursos e webinários;',
                    'Entrevistas;',
                    'Divulgação de ações.'
                ],
                'request' => 'Use quando a demanda envolver produção audiovisual, gravação, edição ou preparação de vídeo institucional.'
            ],
            'publicacao-youtube' => [
                'title' => 'Publicação no YouTube',
                'icon' => 'ti ti-brand-youtube',
                'category' => 'Cobertura e Audiovisual',
                'description' => 'Solicitação de publicação, organização ou divulgação de conteúdo audiovisual no canal oficial da PGE no YouTube.',
                'items' => [
                    'Vídeos institucionais;',
                    'Cursos e webinários;',
                    'Entrevistas e palestras;',
                    'Registros de eventos;',
                    'Organização e divulgação no canal oficial.'
                ],
                'request' => 'Use para conteúdos audiovisuais que devam ser publicados ou organizados no canal oficial da PGE no YouTube.'
            ],
            'pauta-videocast' => [
                'title' => 'Sugestão de pauta para videocast',
                'icon' => 'ti ti-microphone-2',
                'category' => 'Cobertura e Audiovisual',
                'description' => 'Envio de sugestão de tema, convidado ou pauta para o videocast institucional da PGE.',
                'items' => [
                    'Tema sugerido;',
                    'Convidado ou fonte indicada;',
                    'Relação com Direito do Estado ou Advocacia Pública;',
                    'Atuação institucional da PGE;',
                    'Decisões relevantes.'
                ],
                'request' => 'Use para propor temas ao videocast institucional, em formato de entrevista ou bate-papo noticioso.'
            ],
            'producao-episodio-videocast' => [
                'title' => 'Apoio à produção de episódio do videocast',
                'icon' => 'ti ti-device-tv',
                'category' => 'Cobertura e Audiovisual',
                'description' => 'Solicitação ou acompanhamento de produção de episódio do videocast, incluindo pauta, roteiro, gravação, edição, aprovação e publicação.',
                'items' => [
                    'Reunião editorial;',
                    'Pré-produção;',
                    'Gravação;',
                    'Pós-produção;',
                    'Aprovação;',
                    'Publicação e divulgação institucional.'
                ],
                'request' => 'Use para acompanhar o fluxo operacional de produção de episódio, incluindo roteiro, convidados, edição, legendagem, revisão, upload no YouTube e divulgação.'
            ],
            'demanda-imprensa' => [
                'title' => 'Comunicação de demanda de imprensa',
                'icon' => 'ti ti-message-report',
                'category' => 'Imprensa e Fontes',
                'description' => 'Registro de demanda recebida de jornalista, veículo de comunicação ou imprensa externa.',
                'items' => [
                    'Identificação do jornalista ou veículo;',
                    'Tema solicitado;',
                    'Prazo informado pela imprensa;',
                    'Unidade ou fonte procurada;',
                    'Material recebido, quando houver.'
                ],
                'request' => 'Use quando procuradores, servidores ou unidades forem procurados diretamente por jornalistas. Toda demanda jornalística deve passar primeiramente pela Ascom.'
            ],
            'entrevista-manifestacao-institucional' => [
                'title' => 'Entrevista ou manifestação institucional',
                'icon' => 'ti ti-news',
                'category' => 'Imprensa e Fontes',
                'description' => 'Solicitação de análise e encaminhamento de entrevista, posicionamento institucional ou manifestação à imprensa.',
                'items' => [
                    'Entrevistas;',
                    'Respostas a jornalistas;',
                    'Notas e esclarecimentos;',
                    'Posicionamentos públicos;',
                    'Alinhamento com Procurador-Geral e Secom, quando necessário.'
                ],
                'request' => 'Use para demandas que envolvam manifestação pública, resposta à imprensa ou posicionamento institucional da PGE.'
            ],
            'artigo-opiniao' => [
                'title' => 'Apoio para artigo de opinião',
                'icon' => 'ti ti-pencil',
                'category' => 'Imprensa e Fontes',
                'description' => 'Solicitação de análise, orientação ou encaminhamento para publicação de artigo de opinião relacionado à atuação da PGE.',
                'items' => [
                    'Análise do tema;',
                    'Orientação de publicação;',
                    'Alinhamento institucional;',
                    'Deliberação junto ao Procurador-Geral, quando aplicável;',
                    'Encaminhamento à Secom, quando necessário.'
                ],
                'request' => 'Use quando o artigo for assinado por membro da PGE em contexto institucional ou relacionado à atuação da Procuradoria.'
            ],
            'media-training' => [
                'title' => 'Media training',
                'icon' => 'ti ti-school',
                'category' => 'Imprensa e Fontes',
                'description' => 'Solicitação de capacitação ou orientação para fontes institucionais que possam se relacionar com a imprensa.',
                'items' => [
                    'Preparação para entrevistas;',
                    'Orientação para eventos e posicionamentos;',
                    'Situações de exposição pública;',
                    'Capacitação de procuradores em cargos de chefia;',
                    'Participação de outras fontes autorizadas.'
                ],
                'request' => 'Use para preparar fontes institucionais autorizadas que possam se relacionar com imprensa, eventos ou situações de exposição pública.'
            ],
            'campanha-institucional' => [
                'title' => 'Solicitação de campanha institucional',
                'icon' => 'ti ti-flag',
                'category' => 'Campanhas Institucionais',
                'description' => 'Solicitação de planejamento e desenvolvimento de campanha institucional interna ou externa.',
                'items' => [
                    'Atuação da PGE e função de cada setorial;',
                    'CPRACES, Dívida Ativa e Regularize Capixaba;',
                    'Comissão de Equidade e Programa de Integridade;',
                    'PiGE, campanha contra assédio moral e sexual;',
                    'Prata da Casa;',
                    'Revisão de conteúdos e árvore de navegação do site.'
                ],
                'request' => 'Use para ações de comunicação que envolvam vários materiais, canais, etapas de divulgação e adequação de formato aos canais institucionais.'
            ],
            'valorizacao-servidores' => [
                'title' => 'Campanha de valorização de servidores',
                'icon' => 'ti ti-heart-handshake',
                'category' => 'Campanhas Institucionais',
                'description' => 'Solicitação de divulgação de histórias, perfis profissionais ou ações de valorização de servidores e colaboradores.',
                'items' => [
                    'Projeto Prata da Casa;',
                    'Perfis profissionais e pessoais;',
                    'Histórias de servidores;',
                    'Reconhecimento interno;',
                    'Ações de valorização institucional.'
                ],
                'request' => 'Use para iniciativas de valorização de servidores, colaboradores e boas práticas internas.'
            ],
            'campanhas-tematicas' => [
                'title' => 'Campanhas temáticas institucionais',
                'icon' => 'ti ti-layers-intersect',
                'category' => 'Campanhas Institucionais',
                'description' => 'Solicitação de apoio para campanhas temáticas previstas ou alinhadas à estratégia institucional da PGE.',
                'items' => [
                    'Campanhas internas ou externas;',
                    'Materiais por canal de comunicação;',
                    'Planejamento de etapas;',
                    'Definição de linguagem e formato;',
                    'Acompanhamento de execução.'
                ],
                'request' => 'Use para campanhas temáticas que precisem de planejamento, produção de conteúdo, identidade visual e divulgação coordenada.'
            ],
            'atualizacao-site-pge' => [
                'title' => 'Atualização no site da PGE',
                'icon' => 'ti ti-world-www',
                'category' => 'Site e Comunicação Administrativa',
                'description' => 'Solicitação de inclusão, alteração ou revisão de conteúdo no site institucional da PGE.',
                'items' => [
                    'Páginas e informações institucionais;',
                    'Conteúdos de áreas;',
                    'Notícias e documentos;',
                    'Menus e páginas de programas;',
                    'Atualização de informações administrativas.'
                ],
                'request' => 'Use para atualizar páginas, conteúdos, documentos, menus ou informações institucionais no site da PGE.'
            ],
            'portarias-resolucoes-editais' => [
                'title' => 'Publicação de portarias, resoluções e editais',
                'icon' => 'ti ti-file-certificate',
                'category' => 'Site e Comunicação Administrativa',
                'description' => 'Solicitação de divulgação ou atualização no site relacionada a portarias, resoluções, editais e demais atos administrativos.',
                'items' => [
                    'Portarias;',
                    'Resoluções;',
                    'Editais;',
                    'Atos administrativos;',
                    'Informações necessárias para atualização do site.'
                ],
                'request' => 'Use quando a publicação administrativa exigir comunicação, atualização no site ou divulgação institucional.'
            ],
            'revisao-conteudo-navegacao-site' => [
                'title' => 'Revisão de conteúdo/árvore de navegação',
                'icon' => 'ti ti-sitemap',
                'category' => 'Site e Comunicação Administrativa',
                'description' => 'Solicitação de revisão, reorganização ou melhoria de conteúdos e estrutura de navegação do site institucional.',
                'items' => [
                    'Melhoria de páginas antigas;',
                    'Reorganização de menus e seções;',
                    'Organização de conteúdos;',
                    'Melhoria da jornada do usuário;',
                    'Revisão da árvore de navegação.'
                ],
                'request' => 'Use para melhorar a organização do site institucional, seus conteúdos, menus, seções e navegação.'
            ],
            'planejamento-comunicacao' => [
                'title' => 'Apoio em planejamento de comunicação',
                'icon' => 'ti ti-target-arrow',
                'category' => 'Planejamento e Gestão de Imagem',
                'description' => 'Solicitação de apoio estratégico para definir plano de divulgação, canais, formatos e abordagem de comunicação para uma ação institucional.',
                'items' => [
                    'Definição de plano de divulgação;',
                    'Escolha de canais e formatos;',
                    'Abordagem de comunicação;',
                    'Matéria, campanha, post, vídeo, evento ou comunicado;',
                    'Atuação estratégica da comunicação.'
                ],
                'request' => 'Use quando a área tiver uma ação relevante e precisar definir como comunicar de forma institucional e estratégica.'
            ],
            'calendario-editorial' => [
                'title' => 'Calendário editorial',
                'icon' => 'ti ti-calendar-stats',
                'category' => 'Planejamento e Gestão de Imagem',
                'description' => 'Solicitação de organização de publicações ou campanhas em calendário editorial.',
                'items' => [
                    'Publicações recorrentes;',
                    'Campanhas;',
                    'Datas comemorativas;',
                    'Séries de conteúdo;',
                    'Ações de redes sociais.'
                ],
                'request' => 'Use para planejar publicações, campanhas, séries e ações recorrentes em calendário editorial.'
            ],
            'monitoramento-imagem-redes' => [
                'title' => 'Monitoramento de imagem/redes',
                'icon' => 'ti ti-radar',
                'category' => 'Planejamento e Gestão de Imagem',
                'description' => 'Solicitação de acompanhamento de menções, repercussões, engajamento ou riscos de imagem relacionados à PGE.',
                'items' => [
                    'Menções à PGE nas redes sociais;',
                    'Repercussão de tema sensível;',
                    'Campanhas, notícias ou eventos;',
                    'Riscos de imagem;',
                    'Protocolo de resposta rápida em crise ou desinformação.'
                ],
                'request' => 'Use para acompanhar repercussões, engajamento, menções ou riscos de imagem em redes sociais e veículos digitais.'
            ],
            'relatorio-desempenho-comunicacao' => [
                'title' => 'Relatório de desempenho',
                'icon' => 'ti ti-report-analytics',
                'category' => 'Planejamento e Gestão de Imagem',
                'description' => 'Solicitação de relatório ou avaliação de desempenho de ação de comunicação, campanha, rede social ou canal digital.',
                'items' => [
                    'Alcance e engajamento;',
                    'Crescimento de canais;',
                    'Taxa de abertura e cliques;',
                    'Resultados de campanhas;',
                    'Relatórios semestrais ou anuais de comunicação.'
                ],
                'request' => 'Use para medir resultados de campanhas, publicações, redes sociais, e-mail marketing ou demais ações de comunicação.'
            ],
            'comunicacao-crise' => [
                'title' => 'Comunicação de crise',
                'icon' => 'ti ti-alert-triangle',
                'category' => 'Planejamento e Gestão de Imagem',
                'description' => 'Solicitação de apoio da Ascom para avaliação, orientação e encaminhamento de situações sensíveis com potencial impacto à imagem institucional.',
                'items' => [
                    'Temas com risco de repercussão negativa;',
                    'Imprensa, redes sociais ou desinformação;',
                    'Discurso institucional alinhado;',
                    'Alinhamento com Secom;',
                    'Comitê interno, quando necessário.'
                ],
                'request' => 'Use para situações sensíveis que exijam avaliação de risco, orientação institucional, discurso único e encaminhamento coordenado.'
            ],
            'risco-comunicacional-pauta' => [
                'title' => 'Análise de risco comunicacional de pauta',
                'icon' => 'ti ti-shield-exclamation',
                'category' => 'Planejamento e Gestão de Imagem',
                'description' => 'Solicitação de avaliação prévia de risco comunicacional antes da divulgação de tema sensível.',
                'items' => [
                    'Avaliação prévia de tema sensível;',
                    'Possível interpretação negativa;',
                    'Risco de repercussão externa;',
                    'Necessidade de alinhamento institucional;',
                    'Orientação sobre divulgar, ajustar ou não publicar.'
                ],
                'request' => 'Use quando a unidade desejar divulgar algo que possa gerar interpretação negativa, repercussão externa ou necessidade de alinhamento institucional.'
            ]
        ]
    ]
];
