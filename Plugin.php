<?php

namespace FormularioDinamico;

use MapasCulturais\App;
use MapasCulturais\i;
use MapasCulturais\Definitions\Metadata;
use MapasCulturais\Exceptions\PermissionDenied;
use MapasCulturais\Exceptions\ValidationError;

/**
 * Plugin FormularioDinamico
 *
 * Permite a criação de formulários dinâmicos com interface de arrastar-e-soltar
 * para as entidades Agente, Espaço, Evento e Oportunidade.
 */
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

        // Cria as tabelas do banco se não existirem (primeira execução)
        $this->createTablesIfNeeded();

        // Registra o ícone "form" para o mc-icon
        $app->hook('component(mc-icon).iconset', function (&$iconset) {
            $iconset['form'] = 'cil:notes';
            $iconset['form-field'] = 'cil:input';
            $iconset['form-builder'] = 'cil:pencil';
        });

        // ================================================================
        // Hook panel.nav: Adiciona "Formulários" no menu de Administração
        // ================================================================
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

        // ================================================================
        // API pública: retorna campos dinâmicos de uma entidade (JSON)
        // ================================================================
        $app->hook('GET(formulario-dinamico.campos)', function () use ($app) {
            $this->requireAuthentication();
            $controller = new Controllers\Forms();
            $controller->setRequest($this->data);
            $controller->GET_fields();
        });

        // ================================================================
        // Injeção nos templates de criação/edição das entidades
        // ================================================================
        $entity_types = ['agent', 'space', 'event'];
        foreach ($entity_types as $type) {
            $app->hook("template({$type}.<<create|edit>>.tab-about):end", function () use ($app, $type) {
                $entity = $this->data->entity;
                $form = self::$_instance ? self::$_instance->getActiveForm($type) : null;
                if ($form) {
                    $this->part('dynamic-form-fields', [
                        'form'   => $form,
                        'entity' => $entity,
                    ]);
                }
            });

            $app->hook("template({$type}.single.tab-about):end", function () use ($app, $type) {
                $entity = $this->data->entity;
                $form = self::$_instance ? self::$_instance->getActiveForm($type) : null;
                if ($form) {
                    $this->part('dynamic-form-fields-display', [
                        'form'   => $form,
                        'entity' => $entity,
                    ]);
                }
            });
        }

        // Oportunidade — formulário vinculado à oportunidade específica
        $app->hook("template(opportunity.<<create|edit>>.tab-about):end", function () use ($app) {
            $entity = $this->data->entity;
            $form = self::$_instance ? self::$_instance->getFormForOpportunity($entity->id) : null;
            if ($form) {
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
        // Validação no servidor
        // ================================================================
        foreach (['agent', 'space', 'event'] as $type) {
            $entityClass = self::ENTITY_MAP[$type];
            $app->hook("entity({$entityClass}).save:before", function () use ($app, $type) {
                $entity = $this;
                $form = self::$_instance ? self::$_instance->getActiveForm($type) : null;
                if (!$form) return;

                foreach ($form->campos as $campo) {
                    if (!$campo->obrigatorio) continue;
                    $key = "{$form->slug}_{$campo->slug}";
                    $value = $entity->getMetadata($key);

                    if (empty($value)) {
                        throw new ValidationError(
                            i::__("O campo {$campo->rotulo} é obrigatório.")
                        );
                    }
                }
            });
        }

        // Oportunidade
        $app->hook("entity(MapasCulturais\Entities\Opportunity).save:before", function () use ($app) {
            $entity = $this;
            $form = self::$_instance ? self::$_instance->getFormForOpportunity($entity->id) : null;
            if (!$form) return;

            foreach ($form->campos as $campo) {
                if (!$campo->obrigatorio) continue;
                $key = "{$form->slug}_{$campo->slug}";
                $value = $entity->getMetadata($key);

                if (empty($value)) {
                    throw new ValidationError(
                        i::__("O campo {$campo->rotulo} é obrigatório.")
                    );
                }
            }
        });

        // Enfileira assets
        $app->hook('GET(formulario-dinamico.<<*>>)', function () use ($app) {
            $app->view->enqueueStyle('app-v2', 'formulario-dinamico', 'css/plugin-FormularioDinamico.css');
        });
    }

    public function register()
    {
        $app = App::i();

        // Registra controller principal para /formulario-dinamico/
        $app->registerController('formulario-dinamico', Controllers\Admin::class);

        // Registra metadados dinamicamente a partir dos formulários ativos
        $forms = $this->getActiveForms();
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
                    $cfg['validations'] = [
                        'required' => i::__("O campo {$campo->rotulo} é obrigatório")
                    ];
                }

                if (!empty($campo->opcoes)) {
                    $cfg['options'] = $campo->opcoes;
                }

                $this->registerMetadata($entityClass, $key, $cfg);
            }
        }
    }

    // ================================================================
    // Métodos auxiliares
    // ================================================================

    /**
     * Cria as tabelas do banco na primeira execução.
     */
    private function createTablesIfNeeded(): void
    {
        $app = App::i();
        try {
            $app->em->getConnection()->executeQuery("SELECT 1 FROM formulario_dinamico LIMIT 1");
            return;
        } catch (\Exception $e) {
            // Tabela não existe — cria
        }

        $sql = "
            CREATE TABLE IF NOT EXISTS formulario_dinamico (
                id SERIAL PRIMARY KEY,
                slug VARCHAR(100) NOT NULL UNIQUE,
                titulo VARCHAR(255) NOT NULL,
                descricao TEXT,
                entidade VARCHAR(20) NOT NULL CHECK (entidade IN ('agent', 'space', 'event', 'opportunity')),
                ativo BOOLEAN DEFAULT true,
                criado_por INTEGER REFERENCES agent(id),
                criado_em TIMESTAMP DEFAULT NOW(),
                atualizado_em TIMESTAMP DEFAULT NOW()
            );
            CREATE UNIQUE INDEX IF NOT EXISTS idx_fd_ativo_agent ON formulario_dinamico (entidade) WHERE entidade = 'agent' AND ativo = true;
            CREATE UNIQUE INDEX IF NOT EXISTS idx_fd_ativo_space ON formulario_dinamico (entidade) WHERE entidade = 'space' AND ativo = true;
            CREATE UNIQUE INDEX IF NOT EXISTS idx_fd_ativo_event ON formulario_dinamico (entidade) WHERE entidade = 'event' AND ativo = true;
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
                UNIQUE(formulario_id, slug)
            );
            CREATE INDEX IF NOT EXISTS idx_fdc_ordem ON formulario_dinamico_campo (formulario_id, ordem);
            CREATE TABLE IF NOT EXISTS formulario_dinamico_oportunidade (
                id SERIAL PRIMARY KEY,
                formulario_id INTEGER NOT NULL REFERENCES formulario_dinamico(id) ON DELETE CASCADE,
                oportunidade_id INTEGER NOT NULL REFERENCES opportunity(id) ON DELETE CASCADE,
                UNIQUE(formulario_id, oportunidade_id)
            );
        ";

        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if ($stmt && stripos($stmt, '--') !== 0) {
                try {
                    $app->em->getConnection()->executeStatement($stmt);
                } catch (\Exception $e) {
                    // Ignora erros de criação (table/index já existe)
                }
            }
        }
    }

    /**
     * Retorna todos os formulários ativos com seus campos.
     */
    public function getActiveForms(): array
    {
        $app = App::i();
        $conn = $app->em->getConnection();

        try {
            $rows = $conn->fetchAllAssociative("
                SELECT f.id, f.slug, f.titulo, f.descricao, f.entidade, f.ativo,
                       c.id AS campo_id, c.slug AS campo_slug,
                       c.rotulo, c.placeholder, c.tipo,
                       c.opcoes, c.obrigatorio, c.coluna_span, c.ordem, c.editavel
                FROM formulario_dinamico f
                JOIN formulario_dinamico_campo c ON c.formulario_id = f.id
                WHERE f.ativo = true
                ORDER BY f.id, c.ordem
            ");
        } catch (\Exception $e) {
            // Tabelas podem não existir ainda (plugin recém-instalado)
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
            ];
        }

        return array_values($forms);
    }

    /**
     * Retorna o formulário ativo para uma entidade (agent, space, event).
     */
    public function getActiveForm(string $entityType): ?object
    {
        $forms = $this->getActiveForms();
        foreach ($forms as $form) {
            if ($form->entidade === $entityType) {
                return $form;
            }
        }
        return null;
    }

    /**
     * Retorna o formulário vinculado a uma oportunidade específica.
     */
    public function getFormForOpportunity(int $opportunityId): ?object
    {
        $app = App::i();
        $conn = $app->em->getConnection();

        try {
            // 1. Verifica se há formulário vinculado diretamente
            $row = $conn->fetchAssociative("
                SELECT f.id
                FROM formulario_dinamico f
                JOIN formulario_dinamico_oportunidade fo ON fo.formulario_id = f.id
                WHERE f.entidade = 'opportunity' AND f.ativo = true
                  AND fo.oportunidade_id = ?
                LIMIT 1
            ", [$opportunityId]);

            if ($row) {
                $forms = $this->getActiveForms();
                foreach ($forms as $form) {
                    if ($form->id == $row['id']) {
                        return $form;
                    }
                }
            }

            // 2. Fallback: formulário padrão (sem vinculação específica)
            $row = $conn->fetchAssociative("
                SELECT f.id
                FROM formulario_dinamico f
                WHERE f.entidade = 'opportunity' AND f.ativo = true
                  AND NOT EXISTS (
                    SELECT 1 FROM formulario_dinamico_oportunidade fo
                    WHERE fo.formulario_id = f.id
                  )
                LIMIT 1
            ");

            if ($row) {
                $forms = $this->getActiveForms();
                foreach ($forms as $form) {
                    if ($form->id == $row['id']) {
                        return $form;
                    }
                }
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Mapeia o tipo do formulário para o tipo de metadado do Mapas Culturais.
     */
    private function mapFieldType(string $type): string
    {
        $map = [
            'text'       => 'string',
            'textarea'   => 'text',
            'number'     => 'string',
            'email'      => 'string',
            'url'        => 'string',
            'date'       => 'date',
            'datetime'   => 'date',
            'phone'      => 'string',
            'cep'        => 'string',
            'cpf'        => 'string',
            'cnpj'       => 'string',
            'gender'     => 'select',
            'select'     => 'select',
            'multiselect'=> 'multiselect',
        ];
        return $map[$type] ?? 'string';
    }

    /**
     * Retorna os campos nativos obrigatórios de uma entidade.
     */
    public function getNativeRequiredFields(string $entityType): array
    {
        $app = App::i();
        $entityClass = self::ENTITY_MAP[$entityType] ?? null;
        if (!$entityClass) return [];

        $fields = [];
        $registeredMetadata = $app->getRegisteredMetadata($entityClass);
        foreach ($registeredMetadata as $key => $def) {
            $validations = $def->_validations ?? [];
            if (isset($validations['required'])) {
                $fields[] = (object)[
                    'key'         => $key,
                    'rotulo'      => $def->label,
                    'tipo'        => $def->type,
                    'placeholder' => $def->placeholder ?? '',
                    'obrigatorio' => true,
                    'editavel'    => false,
                    'nativo'      => true,
                ];
            }
        }

        return $fields;
    }
}
