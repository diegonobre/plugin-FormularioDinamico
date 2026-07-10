<?php

namespace FormularioDinamico;

use MapasCulturais\App;
use MapasCulturais\i;
use MapasCulturais\Definitions\Metadata;
use MapasCulturais\Exceptions\PermissionDenied;
use MapasCulturais\Exceptions\BadRequest;

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
        // Injeção nos forms de criação/edição (apenas formulários publicados)
        // ================================================================
        $entity_types = ['agent', 'space', 'event'];
        foreach ($entity_types as $type) {
            // Edit: injeta campos dinâmicos + CSS para esconder cards originais
            $app->hook("template({$type}.edit.tab-about):end", function () use ($app, $type) {
                $entity = $this->data->entity;
                $form = self::$_instance ? self::$_instance->getPublishedForm($type) : null;
                if ($form) {
                    echo '<style>.mc-card { display: none; } [data-dynamic-form="true"] { display: block; }</style>';
                    $this->part('dynamic-form-fields', [
                        'form'   => $form,
                        'entity' => $entity,
                    ]);
                }
            });

            // Create: injeta campos dinâmicos sem esconder originais
            $app->hook("template({$type}.create.tab-about):end", function () use ($app, $type) {
                $entity = $this->data->entity;
                $form = self::$_instance ? self::$_instance->getPublishedForm($type) : null;
                if ($form) {
                    $this->part('dynamic-form-fields', [
                        'form'   => $form,
                        'entity' => $entity,
                    ]);
                }
            });

            $app->hook("template({$type}.single.tab-about):end", function () use ($app, $type) {
                $entity = $this->data->entity;
                $form = self::$_instance ? self::$_instance->getPublishedForm($type) : null;
                if ($form) {
                    $this->part('dynamic-form-fields-display', [
                        'form'   => $form,
                        'entity' => $entity,
                    ]);
                }
            });
        }

        // Oportunidade
        $app->hook("template(opportunity.create.tab-about):end", function () use ($app) {
            $entity = $this->data->entity;
            $form = self::$_instance ? self::$_instance->getFormForOpportunity($entity->id) : null;
            if ($form) {
                $this->part('dynamic-form-fields', [
                    'form'   => $form,
                    'entity' => $entity,
                ]);
            }
        });

        $app->hook("template(opportunity.edit.tab-about):end", function () use ($app) {
            $entity = $this->data->entity;
            $form = self::$_instance ? self::$_instance->getFormForOpportunity($entity->id) : null;
            if ($form) {
                echo '<style>.mc-card { display: none; } [data-dynamic-form="true"] { display: block; }</style>';
                $this->part('dynamic-form-fields', [
                    'form'   => $form,
                    'entity' => $entity,
                ]);
            }
        });

        $app->hook("template(opportunity.single.tab-about):end", function () use ($app) {
            $entity = $this->data->entity;
            $form = self::$_instance ? self::$_instance->getFormForOpportunity($entity->id) : null;
            if ($form) {
                $this->part('dynamic-form-fields-display', [
                    'form'   => $form,
                    'entity' => $entity,
                ]);
            }
        });

        // ================================================================
        // Injeção no formulário de inscrição (Registration)
        // ================================================================

        // Edit mode (draft — usuário preenchendo a inscrição)
        $app->hook("template(registration.edit).form:end", function () use ($app) {
            $entity = $this->data->entity;
            $opportunity = $entity->opportunity ?? null;
            if (!$opportunity) return;
            $form = self::$_instance ? self::$_instance->getFormForOpportunity($opportunity->id) : null;
            if ($form) {
                $this->part('dynamic-form-fields', [
                    'form'   => $form,
                    'entity' => $entity,
                ]);
                // Bridge: copia valores dos campos dinâmicos para o scope do Angular antes do save
                echo '<script>
                (function() {
                    var checkInterval = setInterval(function() {
                        if (window.$registrationScope && window.$registrationScope.saveRegistration) {
                            clearInterval(checkInterval);
                            var origSave = window.$registrationScope.saveRegistration;
                            window.$registrationScope.saveRegistration = function() {
                                var fields = document.querySelectorAll("[data-dynamic-form] input, [data-dynamic-form] select, [data-dynamic-form] textarea");
                                fields.forEach(function(f) {
                                    if (f.name && window.$registrationScope.data && window.$registrationScope.data.editableEntity) {
                                        window.$registrationScope.data.editableEntity[f.name] = f.value;
                                    }
                                });
                                return origSave.call(this);
                            };
                        }
                    }, 200);
                })();
                </script>';
            }
        });

        // View mode (após envio da inscrição — somente leitura)
        $app->hook("template(registration.single).form:end", function () use ($app) {
            $entity = $this->data->entity;
            $opportunity = $entity->opportunity ?? null;
            if (!$opportunity) return;
            $form = self::$_instance ? self::$_instance->getFormForOpportunity($opportunity->id) : null;
            if ($form) {
                $this->part('dynamic-form-fields-display', [
                    'form'   => $form,
                    'entity' => $entity,
                ]);
            }
        });

        // Validação no servidor
        foreach (['agent', 'space', 'event'] as $type) {
            $entityClass = self::ENTITY_MAP[$type];
            $app->hook("entity({$entityClass}).save:before", function () use ($app, $type) {
                $entity = $this;
                $form = self::$_instance ? self::$_instance->getPublishedForm($type) : null;
                if (!$form) return;
                foreach ($form->campos as $campo) {
                    if (!$campo->obrigatorio) continue;
                    $key = "{$form->slug}_{$campo->slug}";
                    $value = $entity->getMetadata($key);
                    if (empty($value)) {
                        throw new BadRequest(i::__("O campo {$campo->rotulo} é obrigatório."));
                    }
                }
            });
        }

        $app->hook("entity(MapasCulturais\Entities\Opportunity).save:before", function () use ($app) {
            $entity = $this;
            $form = self::$_instance ? self::$_instance->getPublishedForm('opportunity') : null;
            if (!$form) return;
            foreach ($form->campos as $campo) {
                if (!$campo->obrigatorio) continue;
                $key = "{$form->slug}_{$campo->slug}";
                $value = $entity->getMetadata($key);
                if (empty($value)) {
                    throw new BadRequest(i::__("O campo {$campo->rotulo} é obrigatório."));
                }
            }
        });

        // Validação para campos dinâmicos na inscrição (Registration)
        $app->hook("entity(MapasCulturais\Entities\Registration).save:before", function () use ($app) {
            $entity = $this;
            $opportunity = $entity->opportunity ?? null;
            if (!$opportunity) return;
            $form = self::$_instance ? self::$_instance->getFormForOpportunity($opportunity->id) : null;
            if (!$form) return;
            foreach ($form->campos as $campo) {
                if (!$campo->obrigatorio) continue;
                $key = "{$form->slug}_{$campo->slug}";
                $value = $entity->getMetadata($key);
                if (empty($value)) {
                    throw new BadRequest(i::__("O campo {$campo->rotulo} é obrigatório."));
                }
            }
        });

        // Assets
        $app->hook('GET(formulario-dinamico.<<*>>)', function () use ($app) {
            $app->view->enqueueStyle('app-v2', 'formulario-dinamico', 'css/plugin-FormularioDinamico.css');
        });
    }

    public function register()
    {
        $app = App::i();
        $app->registerController('formulario-dinamico', Controllers\Admin::class);

        // Registra metadados APENAS de formulários publicados
        $forms = $this->getPublishedForms();
        foreach ($forms as $form) {
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
                    $cfg['validations'] = ['required' => i::__("O campo {$campo->rotulo} é obrigatório")];
                }
                if (!empty($campo->opcoes)) {
                    $cfg['options'] = $campo->opcoes;
                }
                $this->registerMetadata($entityClass, $key, $cfg);

                // Para formulários de oportunidade, registra os mesmos metadados
                // na entidade Registration para o formulário de inscrição
                if ($form->entidade === 'opportunity') {
                    $this->registerRegistrationMetadata($key, $cfg);
                }
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
            "UPDATE formulario_dinamico SET status = 'published' WHERE status IS NULL OR status = ''",
            "UPDATE formulario_dinamico SET ativo = true WHERE ativo IS NULL",
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
                       c.grupo_id, c.grupo_titulo
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
                $forms = $this->getPublishedForms();
                foreach ($forms as $form) {
                    if ($form->id == $row['id']) return $form;
                }
            }
            $row = $conn->fetchAssociative("
                SELECT f.id FROM formulario_dinamico f
                WHERE f.entidade='opportunity' AND f.status='published'
                AND NOT EXISTS (SELECT 1 FROM formulario_dinamico_oportunidade fo WHERE fo.formulario_id=f.id)
                LIMIT 1");
            if ($row) {
                $forms = $this->getPublishedForms();
                foreach ($forms as $form) {
                    if ($form->id == $row['id']) return $form;
                }
            }
        } catch (\Exception $e) {}
        return null;
    }

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
                'tipo'        => $def->type,
                'placeholder' => $def->placeholder ?? '',
                'obrigatorio' => $isRequired,
                'editavel'    => !$isRequired,
                'nativo'      => $isRequired,
            ];
        }
        return $fields;
    }

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
}
