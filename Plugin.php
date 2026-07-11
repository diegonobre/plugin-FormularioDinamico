<?php

namespace FormularioDinamico;

use MapasCulturais\App;
use MapasCulturais\i;
use MapasCulturais\Entities\RegistrationFieldConfiguration;

class Plugin extends \MapasCulturais\Plugin
{
    const ENTITY_MAP = [
        'agent'       => 'MapasCulturais\Entities\Agent',
        'space'       => 'MapasCulturais\Entities\Space',
        'event'       => 'MapasCulturais\Entities\Event',
        'opportunity' => 'MapasCulturais\Entities\Opportunity',
    ];

    const ENTITY_LABEL_MAP = [
        'agent'       => 'Agente',
        'space'       => 'Espaço',
        'event'       => 'Evento',
        'opportunity' => 'Oportunidade',
    ];

    /**
     * Views de edição (do tema ativo) usadas para descobrir os campos
     * realmente visíveis no app para cada entidade.
     */
    const ENTITY_EDIT_VIEWS = [
        'agent'       => [['agent', 'edit-1.php'], ['agent', 'edit-2.php']],
        'space'       => [['space', 'edit.php']],
        'event'       => [['event', 'edit.php']],
        'opportunity' => [['opportunity', 'edit.php']],
    ];

    static private $_instance;

    static function getInstance(): self {
        return self::$_instance;
    }

    function __construct(array $config = [])
    {
        self::$_instance = $this;
        parent::__construct($config);
    }

    public function _init()
    {
        $app = App::i();
        $this->createTablesIfNeeded();

        // Ícone
        $app->hook('component(mc-icon).iconset', function (&$iconset) {
            $iconset['form'] = 'cil:notes';
            $iconset['form-field'] = 'cil:input';
            $iconset['form-builder'] = 'cil:pencil';
            $iconset['form-report'] = 'cil:chart';
            $iconset['arrowBack'] = $iconset['arrowBack'] ?? 'cil:arrow-left';
        });

        // Nav: "Formulários" no admin (saasSuperAdmin)
        $app->hook('panel.nav', function (&$groups) use ($app) {
            $groups['admin']['items'][] = [
                'route'     => 'formulario-dinamico/index',
                'icon'      => 'form',
                'label'     => i::__('Formulários'),
                'condition' => function () use ($app) {
                    return $app->user->is('saasSuperAdmin');
                }
            ];
        });

        // API pública
        $app->hook('GET(formulario-dinamico.campos)', function () use ($app) {
            $this->requireAuthentication();
            $controller = new Controllers\Forms();
            $controller->setRequest($this->data);
            $controller->GET_fields();
        });

        // ================================================================
        // Injeção nos formulários das entidades via tema (BaseV2)
        //
        // As views de edição/single do tema disparam o hook de template
        // "tabs" — o formulário publicado é injetado como uma nova aba,
        // usando os componentes do design system (mc-tab + entity-field).
        //
        // Formulários da entidade "opportunity" NÃO são injetados aqui:
        // eles são materializados no formulário de inscrição da(s)
        // oportunidade(s) vinculada(s) — veja syncOpportunityRegistrationFields().
        // ================================================================
        foreach (['agent', 'space', 'event'] as $type) {
            $app->hook("template({$type}.edit.tabs):end", function () use ($type) {
                /** @var \MapasCulturais\Themes\BaseV2\Theme $this */
                $form = Plugin::getInstance()->getPublishedForm($type);
                if ($form) {
                    $this->part('dynamic-form-tab', ['form' => $form]);
                }
            });

            $app->hook("template({$type}.single.tabs):end", function () use ($type) {
                /** @var \MapasCulturais\Themes\BaseV2\Theme $this */
                $form = Plugin::getInstance()->getPublishedForm($type);
                $entity = $this->controller->requestedEntity ?? null;
                if ($form && $entity) {
                    $this->part('dynamic-form-single-tab', [
                        'form'   => $form,
                        'entity' => $entity,
                    ]);
                }
            });
        }

        // Assets
        $app->hook('GET(formulario-dinamico.<<*>>)', function () use ($app) {
            $app->view->enqueueStyle('app-v2', 'formulario-dinamico', 'css/plugin-FormularioDinamico.css');
        });

        // Grid de colunas dos grupos no formulário de inscrição
        $app->hook('GET(registration.<<*>>)', function () use ($app) {
            $app->view->enqueueStyle('app-v2', 'fd-registration-form', 'css/fd-registration-form.css');
        });
    }

    public function register()
    {
        $app = App::i();
        $app->registerController('formulario-dinamico', Controllers\Admin::class);

        // Registra metadados APENAS de formulários publicados.
        //
        // Formulários de oportunidade não registram metadados: seus campos
        // são materializados como RegistrationFieldConfiguration no formulário
        // de inscrição, e a própria plataforma cuida de renderização,
        // salvamento e validação de obrigatoriedade no envio.
        $forms = $this->getPublishedForms();
        foreach ($forms as $form) {
            if ($form->entidade === 'opportunity') {
                continue;
            }
            $entityClass = self::ENTITY_MAP[$form->entidade] ?? null;
            if (!$entityClass) continue;
            foreach ($form->campos as $campo) {
                $key = "{$form->slug}_{$campo->slug}";
                $cfg = [
                    'label'       => $campo->rotulo,
                    'type'        => $this->mapFieldType($campo->tipo),
                    'placeholder' => $campo->placeholder ?? '',
                ];
                if ($campo->obrigatorio) {
                    $cfg['validations'] = ['required' => sprintf(i::__('O campo %s é obrigatório'), $campo->rotulo)];
                }
                if (!empty($campo->opcoes)) {
                    $cfg['options'] = $campo->opcoes;
                }
                $this->registerMetadata($entityClass, $key, $cfg);
            }
        }
    }

    // ================================================================
    // Banco de dados
    // ================================================================

    private function createTablesIfNeeded(): void
    {
        $app = App::i();

        // Cria as tabelas (se não existirem)
        $sql = "
            CREATE TABLE IF NOT EXISTS formulario_dinamico (
                id SERIAL PRIMARY KEY,
                slug VARCHAR(100) NOT NULL UNIQUE,
                titulo VARCHAR(255) NOT NULL,
                descricao TEXT,
                entidade VARCHAR(20) NOT NULL CHECK (entidade IN ('agent','space','event','opportunity')),
                status VARCHAR(20) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','published')),
                ativo BOOLEAN DEFAULT true,
                criado_por INTEGER REFERENCES agent(id),
                criado_em TIMESTAMP DEFAULT NOW(),
                atualizado_em TIMESTAMP DEFAULT NOW()
            );
            CREATE UNIQUE INDEX IF NOT EXISTS idx_fd_published_agent ON formulario_dinamico (entidade) WHERE entidade='agent' AND status='published';
            CREATE UNIQUE INDEX IF NOT EXISTS idx_fd_published_space ON formulario_dinamico (entidade) WHERE entidade='space' AND status='published';
            CREATE UNIQUE INDEX IF NOT EXISTS idx_fd_published_event ON formulario_dinamico (entidade) WHERE entidade='event' AND status='published';
            CREATE TABLE IF NOT EXISTS formulario_dinamico_campo (
                id SERIAL PRIMARY KEY,
                formulario_id INTEGER NOT NULL REFERENCES formulario_dinamico(id) ON DELETE CASCADE,
                slug VARCHAR(100) NOT NULL,
                rotulo VARCHAR(255) NOT NULL,
                placeholder VARCHAR(255),
                tipo VARCHAR(30) NOT NULL,
                opcoes JSONB,
                obrigatorio BOOLEAN DEFAULT false,
                ordem INTEGER DEFAULT 0,
                coluna_span INTEGER DEFAULT 12,
                editavel BOOLEAN DEFAULT true,
                grupo_id INTEGER DEFAULT 0,
                grupo_titulo VARCHAR(255) DEFAULT '',
                grupo_colunas INTEGER DEFAULT 1,
                UNIQUE(formulario_id, slug)
            );
            CREATE INDEX IF NOT EXISTS idx_fdc_ordem ON formulario_dinamico_campo(formulario_id, ordem);
            CREATE TABLE IF NOT EXISTS formulario_dinamico_oportunidade (
                id SERIAL PRIMARY KEY,
                formulario_id INTEGER NOT NULL REFERENCES formulario_dinamico(id) ON DELETE CASCADE,
                oportunidade_id INTEGER NOT NULL REFERENCES opportunity(id) ON DELETE CASCADE,
                UNIQUE(formulario_id, oportunidade_id)
            );
        ";
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt && stripos($stmt, '--') !== 0) {
                try { $app->em->getConnection()->executeStatement($stmt); } catch (\Exception $e) {}
            }
        }

        // Migração: adiciona colunas que podem não existir em tabelas criadas antes da versão com grupos/status
        $migrations = [
            "ALTER TABLE formulario_dinamico ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'draft'",
            "ALTER TABLE formulario_dinamico ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT true",
            "ALTER TABLE formulario_dinamico_campo ADD COLUMN IF NOT EXISTS grupo_id INTEGER DEFAULT 0",
            "ALTER TABLE formulario_dinamico_campo ADD COLUMN IF NOT EXISTS grupo_titulo VARCHAR(255) DEFAULT ''",
            "ALTER TABLE formulario_dinamico_campo ADD COLUMN IF NOT EXISTS grupo_colunas INTEGER DEFAULT 1",
            "UPDATE formulario_dinamico SET status = 'published' WHERE status IS NULL OR status = ''",
            "UPDATE formulario_dinamico SET ativo = true WHERE ativo IS NULL",
            // garante uma oportunidade por formulário de inscrição
            "CREATE UNIQUE INDEX IF NOT EXISTS idx_fdo_oportunidade ON formulario_dinamico_oportunidade (oportunidade_id)",
        ];
        foreach ($migrations as $stmt) {
            try { $app->em->getConnection()->executeStatement($stmt); } catch (\Exception $e) {}
        }
    }

    // ================================================================
    // Queries
    // ================================================================

    public function getPublishedForms(): array
    {
        return $this->getFormsByStatus('published');
    }

    public function getActiveForms(): array
    {
        // Para compatibilidade — retorna publicados
        return $this->getFormsByStatus('published');
    }

    public function getAllForms(): array
    {
        return $this->getFormsByStatus(null);
    }

    private function getFormsByStatus(?string $status): array
    {
        $app = App::i();
        $conn = $app->em->getConnection();
        $where = $status ? "WHERE f.status = '$status'" : "";

        try {
            $rows = $conn->fetchAllAssociative("
                SELECT f.id, f.slug, f.titulo, f.descricao, f.entidade, f.status, f.ativo,
                       c.id AS campo_id, c.slug AS campo_slug,
                       c.rotulo, c.placeholder, c.tipo,
                       c.opcoes, c.obrigatorio, c.coluna_span, c.ordem, c.editavel,
                       c.grupo_id, c.grupo_titulo, c.grupo_colunas
                FROM formulario_dinamico f
                JOIN formulario_dinamico_campo c ON c.formulario_id = f.id
                $where
                ORDER BY f.id, c.grupo_id, c.ordem
            ");
        } catch (\Exception $e) {
            return [];
        }

        $forms = [];
        foreach ($rows as $row) {
            $fid = $row['id'];
            if (!isset($forms[$fid])) {
                $forms[$fid] = (object)[
                    'id'        => $row['id'],
                    'slug'      => $row['slug'],
                    'titulo'    => $row['titulo'],
                    'descricao' => $row['descricao'],
                    'entidade'  => $row['entidade'],
                    'status'    => $row['status'],
                    'campos'    => [],
                ];
            }
            $forms[$fid]->campos[] = (object)[
                'slug'        => $row['campo_slug'],
                'rotulo'      => $row['rotulo'],
                'placeholder' => $row['placeholder'],
                'tipo'        => $row['tipo'],
                'opcoes'      => $row['opcoes'] ? json_decode($row['opcoes'], true) : null,
                'obrigatorio' => (bool)$row['obrigatorio'],
                'coluna_span' => (int)$row['coluna_span'],
                'editavel'    => (bool)$row['editavel'],
                'ordem'       => (int)$row['ordem'],
                'grupo_id'    => (int)$row['grupo_id'],
                'grupo_titulo'=> $row['grupo_titulo'] ?? '',
                'grupo_colunas'=> max(1, (int)($row['grupo_colunas'] ?? 1)),
            ];
        }

        return array_values($forms);
    }

    public function getPublishedForm(string $entityType): ?object
    {
        $forms = $this->getPublishedForms();
        foreach ($forms as $form) {
            if ($form->entidade === $entityType) {
                return $form;
            }
        }
        return null;
    }

    public function getFormById(int $formId): ?object
    {
        foreach ($this->getAllForms() as $form) {
            if ((int)$form->id === $formId) {
                return $form;
            }
        }
        return null;
    }

    /**
     * Retorna o formulário publicado vinculado à oportunidade.
     *
     * O formulário precisa ter sido explicitamente vinculado à oportunidade
     * na tela "Vincular Oportunidades" do admin.
     */
    public function getFormForOpportunity(int $opportunityId): ?object
    {
        $app = App::i();
        $conn = $app->em->getConnection();
        try {
            $row = $conn->fetchAssociative("
                SELECT f.id FROM formulario_dinamico f
                JOIN formulario_dinamico_oportunidade fo ON fo.formulario_id = f.id
                WHERE f.entidade='opportunity' AND f.status='published' AND fo.oportunidade_id=?
                LIMIT 1", [$opportunityId]);
            if ($row) {
                foreach ($this->getPublishedForms() as $form) {
                    if ($form->id == $row['id']) return $form;
                }
            }
        } catch (\Exception $e) {}
        return null;
    }

    public function getLinkedOpportunityIds(int $formId): array
    {
        $app = App::i();
        try {
            return array_map('intval', $app->em->getConnection()->fetchFirstColumn(
                "SELECT oportunidade_id FROM formulario_dinamico_oportunidade WHERE formulario_id=?", [$formId]
            ));
        } catch (\Exception $e) {
            return [];
        }
    }

    // ================================================================
    // Campos padrão de um novo formulário
    // ================================================================

    /**
     * Todos os metadados registrados da entidade (fallback).
     */
    public function getAllEntityFields(string $entityType): array
    {
        $app = App::i();
        $entityClass = self::ENTITY_MAP[$entityType] ?? null;
        if (!$entityClass) return [];
        $fields = [];
        foreach ($app->getRegisteredMetadata($entityClass) as $key => $def) {
            $isRequired = $def->is_required ?? false;
            $fields[] = (object)[
                'key'         => $key,
                'rotulo'      => $def->label,
                'tipo'        => $this->mapMetaTypeToPluginType($def->type),
                'placeholder' => $def->placeholder ?? '',
                'obrigatorio' => $isRequired,
                'editavel'    => !$isRequired,
                'nativo'      => $isRequired,
                'opcoes'      => !empty($def->options) ? array_values($def->options) : [],
                'grupo_id'    => 0,
                'grupo_titulo'=> 'Geral',
            ];
        }
        return $fields;
    }

    /**
     * Campos realmente visíveis no app para a entidade.
     *
     * Lê as views de edição do tema ativo (cadeia de paths — o tema
     * GPSCultural tem precedência sobre o core) e extrai os
     * `<entity-field prop="...">` na ordem em que aparecem, agrupados
     * pelas abas (`<mc-tab label="...">`) em que estão.
     *
     * Só retorna campos que são metadados registrados: propriedades
     * nativas (name, type etc.) continuam aparecendo como "campos
     * nativos" não removíveis no editor de formulário.
     */
    public function getVisibleEntityFields(string $entityType): array
    {
        $app = App::i();
        $entityClass = self::ENTITY_MAP[$entityType] ?? null;
        $views = self::ENTITY_EDIT_VIEWS[$entityType] ?? null;
        if (!$entityClass || !$views) return [];

        $registered = $app->getRegisteredMetadata($entityClass);

        $fields = [];
        $grupos = []; // titulo => id

        foreach ($views as [$folder, $file]) {
            $filename = $app->view->resolveFilename("views/{$folder}", $file);
            if (!$filename || !is_readable($filename)) continue;
            $content = file_get_contents($filename);
            if (!$content) continue;

            // divide o conteúdo pelas abas; o trecho antes da primeira aba cai no grupo "Geral"
            $chunks = preg_split('/<mc-tab\s/', $content);
            foreach ($chunks as $i => $chunk) {
                $titulo = 'Geral';
                if ($i > 0 && preg_match('/label="([^"]*)"/', $chunk, $m)) {
                    $raw = $m[1];
                    if (preg_match("/i::[_a-z]+\(\s*[\"']([^\"']+)[\"']/", $raw, $mm)) {
                        $titulo = $mm[1];
                    } elseif ($raw !== '' && strpos($raw, '<?') === false) {
                        $titulo = $raw;
                    }
                }

                if (!preg_match_all('/\bprop="([^"]+)"/u', $chunk, $mm)) continue;

                foreach ($mm[1] as $prop) {
                    if (isset($fields[$prop])) continue;
                    $def = $registered[$prop] ?? null;
                    if (!$def) continue;

                    if (!isset($grupos[$titulo])) {
                        $grupos[$titulo] = count($grupos);
                    }
                    $isRequired = $def->is_required ?? false;
                    $fields[$prop] = (object)[
                        'key'         => $prop,
                        'rotulo'      => $def->label ?: $prop,
                        'tipo'        => $this->mapMetaTypeToPluginType($def->type),
                        'placeholder' => $def->placeholder ?? '',
                        'obrigatorio' => $isRequired,
                        'editavel'    => true,
                        'nativo'      => false,
                        'opcoes'      => !empty($def->options) ? array_values($def->options) : [],
                        'grupo_id'    => $grupos[$titulo],
                        'grupo_titulo'=> $titulo,
                    ];
                }
            }
        }

        return array_values($fields);
    }

    // ================================================================
    // Materialização no formulário de inscrição (Registration)
    // ================================================================

    /**
     * Cria/atualiza os campos do formulário dinâmico como
     * RegistrationFieldConfiguration da oportunidade. Com isso o
     * formulário de inscrição nativo renderiza, salva e valida os
     * campos (obrigatórios bloqueiam o envio da inscrição).
     */
    public function syncOpportunityRegistrationFields(object $form, int $opportunityId): void
    {
        $app = App::i();
        $opportunity = $app->repo('Opportunity')->find($opportunityId);
        if (!$opportunity) return;

        $app->disableAccessControl();

        $step = $app->repo('RegistrationStep')->findOneBy(
            ['opportunity' => $opportunity],
            ['displayOrder' => 'ASC', 'id' => 'ASC']
        ) ?: $opportunity->getOrCreateStep('');

        // separa campos já materializados deste formulário dos demais campos da oportunidade
        $existing = [];
        $maxOrder = 0;
        foreach ($opportunity->registrationFieldConfigurations as $rfc) {
            $meta = $rfc->config['formulario_dinamico'] ?? null;
            if ($meta && (int)($meta['form_id'] ?? 0) === (int)$form->id && !empty($meta['ref'])) {
                $existing[$meta['ref']] = $rfc;
            } else {
                $maxOrder = max($maxOrder, (int)$rfc->displayOrder);
            }
        }

        // monta a lista desejada: um "section" por grupo + um campo por campo do formulário
        $desired = [];
        $currentGroup = null;
        $groupCount = count(array_unique(array_map(fn($c) => (int)($c->grupo_id ?? 0), $form->campos)));
        foreach ($form->campos as $campo) {
            $gid = (int)($campo->grupo_id ?? 0);
            $gtitulo = trim((string)($campo->grupo_titulo ?? '')) ?: i::__('Geral');
            $gcolunas = min(max((int)($campo->grupo_colunas ?? 1), 1), 4);
            if ($currentGroup !== $gid && ($groupCount > 1 || $gcolunas > 1 || strcasecmp($gtitulo, 'Geral') !== 0)) {
                $desired[] = [
                    'ref'          => "section_{$gid}",
                    'fieldType'    => 'section',
                    'title'        => $gtitulo,
                    'description'  => null,
                    'required'     => false,
                    'fieldOptions' => [],
                    'extra'        => ['colunas' => $gcolunas],
                ];
            }
            $currentGroup = $gid;

            $opcoes = array_values(array_filter(
                array_map(fn($o) => trim((string)$o), (array)($campo->opcoes ?? [])),
                fn($o) => $o !== ''
            ));

            $desired[] = [
                'ref'          => "campo_{$campo->slug}",
                'fieldType'    => $this->mapRegistrationFieldType($campo->tipo),
                'title'        => $campo->rotulo,
                'description'  => $campo->placeholder ?: null,
                'required'     => (bool)$campo->obrigatorio,
                'fieldOptions' => $opcoes,
                'extra'        => ['span' => min(max((int)($campo->coluna_span ?: 12), 1), 12)],
            ];
        }

        $seen = [];
        $order = $maxOrder;
        foreach ($desired as $d) {
            $order++;
            $rfc = $existing[$d['ref']] ?? null;
            if (!$rfc) {
                $rfc = new RegistrationFieldConfiguration;
                $rfc->owner = $opportunity;
            }
            $rfc->title = $d['title'];
            $rfc->fieldType = $d['fieldType'];
            $rfc->required = $d['required'];
            $rfc->fieldOptions = $d['fieldOptions'];
            $rfc->description = $d['description'];
            $rfc->displayOrder = $order;
            $rfc->step = $step->id;
            $rfc->config = array_merge((array)($rfc->config ?: []), [
                'formulario_dinamico' => array_merge([
                    'form_id' => (int)$form->id,
                    'ref'     => $d['ref'],
                ], $d['extra'] ?? []),
            ]);
            $rfc->save();
            $seen[$d['ref']] = true;
        }

        // remove campos materializados que não existem mais no formulário
        foreach ($existing as $ref => $rfc) {
            if (!isset($seen[$ref])) {
                $rfc->delete();
            }
        }

        $app->em->flush();
        $app->enableAccessControl();
    }

    /**
     * Remove os campos materializados pelo plugin na oportunidade.
     * Se $formId for informado, remove apenas os campos daquele formulário.
     */
    public function removeOpportunityRegistrationFields(int $opportunityId, ?int $formId = null): void
    {
        $app = App::i();
        $opportunity = $app->repo('Opportunity')->find($opportunityId);
        if (!$opportunity) return;

        $app->disableAccessControl();
        $removed = false;
        foreach ($opportunity->registrationFieldConfigurations as $rfc) {
            $meta = $rfc->config['formulario_dinamico'] ?? null;
            if ($meta && ($formId === null || (int)($meta['form_id'] ?? 0) === $formId)) {
                $rfc->delete();
                $removed = true;
            }
        }
        if ($removed) {
            $app->em->flush();
        }
        $app->enableAccessControl();
    }

    /**
     * Ressincroniza os campos materializados em todas as oportunidades
     * vinculadas ao formulário (chamado ao publicar/editar o formulário).
     */
    public function syncLinkedOpportunities(int $formId): void
    {
        $form = $this->getFormById($formId);
        if (!$form || $form->entidade !== 'opportunity') return;

        foreach ($this->getLinkedOpportunityIds($formId) as $opportunityId) {
            if ($form->status === 'published') {
                $this->syncOpportunityRegistrationFields($form, $opportunityId);
            } else {
                $this->removeOpportunityRegistrationFields($opportunityId, $formId);
            }
        }
    }

    // ================================================================
    // Relatórios — coleta dos dados preenchidos
    // ================================================================

    /**
     * Rótulos de status das linhas do relatório.
     */
    const STATUS_LABELS = [
        -10 => 'Lixeira',
        -2  => 'Inválida',
        -1  => 'Não selecionada',
        0   => 'Rascunho',
        1   => 'Enviada/Ativa',
        2   => 'Suplente',
        8   => 'Selecionada',
        10  => 'Aprovada',
    ];

    /**
     * Retorna os dados coletados pelo formulário.
     *
     * Para formulários de agent/space/event, cada linha é uma entidade que
     * preencheu ao menos um campo (valores lidos de {entidade}_meta, chave
     * "{form_slug}_{campo_slug}").
     *
     * Para formulários de opportunity, cada linha é uma inscrição das
     * oportunidades vinculadas (valores lidos de registration_meta, chave
     * "field_{id}" dos campos materializados).
     *
     * @return array ['columns' => [slug => rotulo], 'rows' => [ [
     *   '_id', '_label', '_status', '_status_label', '_date', 'values' => [slug => valor]
     * ] ]]
     */
    public function getFormSubmissions(object $form): array
    {
        $columns = [];
        foreach ($form->campos as $campo) {
            $columns[$campo->slug] = $campo->rotulo;
        }

        if ($form->entidade === 'opportunity') {
            $rows = $this->getOpportunitySubmissions($form);
        } else {
            $rows = $this->getEntitySubmissions($form);
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * Retorna uma única linha do relatório (para a visão de item/PDF).
     */
    public function getFormSubmission(object $form, int $itemId): ?array
    {
        $data = $this->getFormSubmissions($form);
        foreach ($data['rows'] as $row) {
            if ((int)$row['_id'] === $itemId) {
                return ['columns' => $data['columns'], 'row' => $row];
            }
        }
        return null;
    }

    private function getEntitySubmissions(object $form): array
    {
        $app = App::i();
        $conn = $app->em->getConnection();

        $tables = [
            'agent' => ['agent', 'agent_meta'],
            'space' => ['space', 'space_meta'],
            'event' => ['event', 'event_meta'],
        ];
        [$table, $metaTable] = $tables[$form->entidade] ?? [null, null];
        if (!$table) return [];

        try {
            $rows = $conn->fetchAllAssociative("
                SELECT e.id, e.name, e.status, e.create_timestamp, m.key, m.value
                FROM {$metaTable} m
                JOIN {$table} e ON e.id = m.object_id
                WHERE m.key LIKE ? AND e.status > -10
                ORDER BY e.id
            ", [$form->slug . '\_%']);
        } catch (\Exception $e) {
            return [];
        }

        $prefixLen = strlen($form->slug) + 1;
        $result = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            if (!isset($result[$id])) {
                $result[$id] = [
                    '_id'           => $id,
                    '_label'        => $row['name'],
                    '_status'       => (int)$row['status'],
                    '_status_label' => self::STATUS_LABELS[(int)$row['status']] ?? $row['status'],
                    '_date'         => $row['create_timestamp'],
                    'values'        => [],
                ];
            }
            $slug = substr($row['key'], $prefixLen);
            $result[$id]['values'][$slug] = $this->formatMetaValue($row['value']);
        }

        return array_values($result);
    }

    private function getOpportunitySubmissions(object $form): array
    {
        $app = App::i();
        $conn = $app->em->getConnection();

        $opportunityIds = $this->getLinkedOpportunityIds((int)$form->id);
        if (!$opportunityIds) return [];

        $in = implode(',', array_map('intval', $opportunityIds));

        // mapa field_{id} => campo_slug, a partir dos campos materializados
        $fieldMap = [];
        try {
            $rfcs = $conn->fetchAllAssociative("
                SELECT id, config FROM registration_field_configuration
                WHERE opportunity_id IN ({$in}) AND field_type != 'section'
            ");
        } catch (\Exception $e) {
            return [];
        }
        foreach ($rfcs as $rfc) {
            $config = $rfc['config'] ? json_decode($rfc['config'], true) : [];
            $meta = $config['formulario_dinamico'] ?? null;
            if ($meta && (int)($meta['form_id'] ?? 0) === (int)$form->id && str_starts_with($meta['ref'] ?? '', 'campo_')) {
                $fieldMap['field_' . $rfc['id']] = substr($meta['ref'], strlen('campo_'));
            }
        }
        if (!$fieldMap) return [];

        try {
            $registrations = $conn->fetchAllAssociative("
                SELECT r.id, r.number, r.status, r.sent_timestamp, r.create_timestamp,
                       r.opportunity_id, a.name AS agent_name, o.name AS opportunity_name
                FROM registration r
                JOIN agent a ON a.id = r.agent_id
                JOIN opportunity o ON o.id = r.opportunity_id
                WHERE r.opportunity_id IN ({$in}) AND r.status > -10
                ORDER BY r.create_timestamp DESC
            ");
        } catch (\Exception $e) {
            return [];
        }
        if (!$registrations) return [];

        $regIds = implode(',', array_map(fn($r) => (int)$r['id'], $registrations));
        $keysIn = implode(',', array_map(fn($k) => $conn->quote($k), array_keys($fieldMap)));
        $metaRows = $conn->fetchAllAssociative("
            SELECT object_id, key, value FROM registration_meta
            WHERE object_id IN ({$regIds}) AND key IN ({$keysIn})
        ");
        $metaByReg = [];
        foreach ($metaRows as $m) {
            $slug = $fieldMap[$m['key']] ?? null;
            if ($slug) {
                $metaByReg[(int)$m['object_id']][$slug] = $this->formatMetaValue($m['value']);
            }
        }

        $result = [];
        foreach ($registrations as $reg) {
            $id = (int)$reg['id'];
            $result[] = [
                '_id'           => $id,
                '_label'        => trim(($reg['number'] ?: $id) . ' — ' . $reg['agent_name']),
                '_status'       => (int)$reg['status'],
                '_status_label' => self::STATUS_LABELS[(int)$reg['status']] ?? $reg['status'],
                '_date'         => $reg['sent_timestamp'] ?: $reg['create_timestamp'],
                '_opportunity'  => $reg['opportunity_name'],
                'values'        => $metaByReg[$id] ?? [],
            ];
        }

        return $result;
    }

    /**
     * Normaliza o valor bruto do metadado para exibição/exportação.
     * Valores de multiselect/checkboxes são armazenados como JSON.
     */
    private function formatMetaValue(?string $value): string
    {
        if ($value === null || $value === '') return '';
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return implode('; ', array_map(
                fn($v) => is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE),
                $decoded
            ));
        }
        return $value;
    }

    // ================================================================
    // Mapeamento de tipos
    // ================================================================

    /**
     * Tipo de campo do plugin → tipo de metadado (entity-field).
     */
    private function mapFieldType(string $type): string
    {
        $map = [
            'text'=>'string','textarea'=>'text','number'=>'string','email'=>'string',
            'url'=>'string','date'=>'date','datetime'=>'date','phone'=>'string',
            'cep'=>'string','cpf'=>'string','cnpj'=>'string','gender'=>'select',
            'select'=>'select','multiselect'=>'multiselect',
        ];
        return $map[$type] ?? 'string';
    }

    /**
     * Tipo de campo do plugin → fieldType de RegistrationFieldConfiguration
     * (módulo RegistrationFieldTypes).
     */
    public function mapRegistrationFieldType(string $type): string
    {
        $map = [
            'text'        => 'text',
            'textarea'    => 'textarea',
            'number'      => 'number',
            'email'       => 'email',
            'url'         => 'url',
            'date'        => 'date',
            'datetime'    => 'date',
            'phone'       => 'brPhone',
            'cep'         => 'text',
            'cpf'         => 'cpf',
            'cnpj'        => 'cnpj',
            'select'      => 'select',
            'gender'      => 'select',
            'multiselect' => 'checkboxes',
        ];
        return $map[$type] ?? 'text';
    }

    /**
     * Tipo de metadado registrado → tipo de campo do plugin.
     */
    private function mapMetaTypeToPluginType(?string $type): string
    {
        $map = [
            'string'      => 'text',
            'text'        => 'textarea',
            'textarea'    => 'textarea',
            'select'      => 'select',
            'multiselect' => 'multiselect',
            'checklist'   => 'multiselect',
            'date'        => 'date',
            'datetime'    => 'datetime',
            'int'         => 'number',
            'integer'     => 'number',
            'number'      => 'number',
            'float'       => 'number',
            'email'       => 'email',
            'url'         => 'url',
            'link'        => 'url',
            'cpf'         => 'cpf',
            'cnpj'        => 'cnpj',
            'phone'       => 'phone',
            'cep'         => 'cep',
        ];
        return $map[$type ?? ''] ?? 'text';
    }
}
