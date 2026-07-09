<?php

namespace FormularioDinamico\Controllers;

use MapasCulturais\App;
use MapasCulturais\i;
use MapasCulturais\Exceptions\PermissionDenied;
use MapasCulturais\Exceptions\BadRequest;
use MapasCulturais\Definitions\Metadata;

class Admin extends \MapasCulturais\Controller
{
    function __construct()
    {
        $this->layout = 'panel';
    }

    // ================================================================
    // GET /formulario-dinamico/ — Lista
    // ================================================================

    function GET_index()
    {
        $app = App::i();
        $this->requireAuthentication();
        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }

        $conn = $app->em->getConnection();
        $forms = [];
        try {
            $rows = $conn->fetchAllAssociative("
                SELECT f.id, f.slug, f.titulo, f.entidade, f.status, f.criado_em,
                       (SELECT COUNT(*) FROM formulario_dinamico_campo c WHERE c.formulario_id = f.id) AS total_campos
                FROM formulario_dinamico f
                ORDER BY f.criado_em DESC
            ");
            foreach ($rows as $row) {
                $forms[] = (object)[
                    'id'           => $row['id'],
                    'slug'         => $row['slug'],
                    'titulo'       => $row['titulo'],
                    'entidade'     => $row['entidade'],
                    'status'       => $row['status'],
                    'total_campos' => (int)$row['total_campos'],
                    'criado_em'    => $row['criado_em'],
                ];
            }
        } catch (\Exception $e) {}

        $this->render('index', ['forms' => $forms]);
    }

    // ================================================================
    // GET /formulario-dinamico/novo — Tela de criação
    // ================================================================

    function GET_novo()
    {
        $app = App::i();
        $this->requireAuthentication();
        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }
        $this->render('novo');
    }

    // ================================================================
    // POST /formulario-dinamico/novo — Salva novo form + pré-popula campos
    // ================================================================

    function POST_novo()
    {
        $app = App::i();
        $this->requireAuthentication();
        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }

        $conn = $app->em->getConnection();
        $data = $this->postData;

        $titulo   = trim($data['titulo'] ?? '');
        $slug     = trim($data['slug'] ?? '');
        $entidade = trim($data['entidade'] ?? '');

        if (empty($titulo)) throw new BadRequest(i::__('O título é obrigatório.'));
        if (empty($slug)) $slug = $this->generateSlug($titulo);
        if (!in_array($entidade, ['agent','space','event','opportunity'])) throw new BadRequest(i::__('Entidade inválida.'));

        if ($conn->fetchOne("SELECT id FROM formulario_dinamico WHERE slug=?", [$slug])) {
            throw new BadRequest(i::__('Já existe um formulário com este slug.'));
        }

        $userId = $app->user->profile->id ?? null;

        $conn->insert('formulario_dinamico', [
            'slug'      => $slug,
            'titulo'    => $titulo,
            'descricao' => trim($data['descricao'] ?? ''),
            'entidade'  => $entidade,
            'status'    => 'draft',
            'ativo'     => 'f',
            'criado_por' => $userId,
            'criado_em'  => date('Y-m-d H:i:s'),
            'atualizado_em' => date('Y-m-d H:i:s'),
        ]);
        $formId = (int)$conn->lastInsertId();

        // Pré-popula com TODOS os campos da entidade
        $plugin = \FormularioDinamico\Plugin::getInstance();
        if ($plugin) {
            $allFields = $plugin->getAllEntityFields($entidade);
            $this->saveCampos($formId, array_map(function ($f, $idx) {
                return [
                    'slug'        => $f->key,
                    'rotulo'      => $f->rotulo,
                    'placeholder' => $f->placeholder,
                    'tipo'        => $f->tipo,
                    'opcoes'      => [],
                    'obrigatorio' => $f->obrigatorio,
                    'coluna_span' => 12,
                    'editavel'    => $f->editavel,
                    'grupo_id'    => 0,
                    'grupo_titulo'=> 'Geral',
                ];
            }, $allFields, array_keys($allFields)));
        }

        $app->redirect($app->baseUrl . 'formulario-dinamico/editar?id=' . $formId);
    }

    // ================================================================
    // GET /formulario-dinamico/editar — Editor com grupos
    // ================================================================

    function GET_editar()
    {
        $app = App::i();
        $this->requireAuthentication();
        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }
        $id = (int)($this->data['id'] ?? 0);
        if (!$id) { $app->pass(); return; }

        $conn = $app->em->getConnection();
        $formRow = $conn->fetchAssociative("SELECT * FROM formulario_dinamico WHERE id=?", [$id]);
        if (!$formRow) { $app->pass(); return; }

        $campoRows = $conn->fetchAllAssociative(
            "SELECT * FROM formulario_dinamico_campo WHERE formulario_id=? ORDER BY grupo_id, ordem", [$id]
        );

        // Agrupa campos por grupo
        $grupos = [];
        $campos = [];
        foreach ($campoRows as $c) {
            $gid = (int)($c['grupo_id'] ?? 0);
            $gtitulo = $c['grupo_titulo'] ?? '';
            if (!isset($grupos[$gid])) {
                $grupos[$gid] = ['id' => $gid, 'titulo' => $gtitulo ?: 'Geral', 'colunas' => 1];
            }
            $campos[] = [
                'slug'        => $c['slug'],
                'rotulo'      => $c['rotulo'],
                'placeholder' => $c['placeholder'],
                'tipo'        => $c['tipo'],
                'opcoes'      => $c['opcoes'] ? json_decode($c['opcoes'], true) : [],
                'obrigatorio' => (bool)$c['obrigatorio'],
                'coluna_span' => (int)($c['coluna_span'] ?: 12),
                'editavel'    => (bool)$c['editavel'],
                'grupo_id'    => $gid,
                'grupo_titulo'=> $gtitulo,
            ];
        }

        if (empty($grupos)) {
            $grupos[0] = ['id' => 0, 'titulo' => 'Geral', 'colunas' => 1];
        }

        $plugin = \FormularioDinamico\Plugin::getInstance();
        $entityClass = \FormularioDinamico\Plugin::ENTITY_MAP[$formRow['entidade']] ?? null;
        $nativeFields = [];
        if ($plugin && $entityClass) {
            foreach ($app->getRegisteredMetadata($entityClass) as $key => $def) {
                if ($def->is_required ?? false) {
                    $nativeFields[] = (object)[
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
        }

        $formData = [
            'id'           => $formRow['id'],
            'slug'         => $formRow['slug'],
            'titulo'       => $formRow['titulo'],
            'descricao'    => $formRow['descricao'],
            'entidade'     => $formRow['entidade'],
            'status'       => $formRow['status'],
            'campos'       => $campos,
            'grupos'       => array_values($grupos),
            'camposNativos'=> $nativeFields,
        ];

        $this->render('editar', ['formData' => $formData]);
    }

    // ================================================================
    // POST /formulario-dinamico/editar — Salva alterações
    // ================================================================

    function POST_editar()
    {
        $app = App::i();
        $this->requireAuthentication();
        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }

        $conn = $app->em->getConnection();
        $data = $this->postData;

        $id = (int)($data['id'] ?? 0);
        if (!$id) throw new BadRequest(i::__('ID não informado.'));

        $titulo   = trim($data['titulo'] ?? '');
        $slug     = trim($data['slug'] ?? '');
        $entidade = trim($data['entidade'] ?? '');
        if (empty($titulo)) throw new BadRequest(i::__('O título é obrigatório.'));
        if (!in_array($entidade, ['agent','space','event','opportunity'])) throw new BadRequest(i::__('Entidade inválida.'));

        $existing = $conn->fetchOne("SELECT id FROM formulario_dinamico WHERE slug=? AND id!=?", [$slug, $id]);
        if ($existing) throw new BadRequest(i::__('Já existe outro formulário com este slug.'));

        $conn->update('formulario_dinamico', [
            'slug'      => $slug,
            'titulo'    => $titulo,
            'descricao' => trim($data['descricao'] ?? ''),
            'entidade'  => $entidade,
            'atualizado_em' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        // Salva campos (JSON string do form-builder)
        if (!empty($data['campos']) && is_string($data['campos'])) {
            $campos = json_decode($data['campos'], true);
            if (is_array($campos)) {
                $conn->delete('formulario_dinamico_campo', ['formulario_id' => $id]);
                $this->saveCampos($id, $campos);
            }
        }

        $app->redirect($app->baseUrl . 'formulario-dinamico');
    }

    // ================================================================
    // POST /formulario-dinamico/publicar — Publica formulário
    // ================================================================

    function POST_publicar()
    {
        $app = App::i();
        $this->requireAuthentication();
        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }

        $conn = $app->em->getConnection();
        $id = (int)($this->postData['id'] ?? 0);
        if (!$id) { $app->redirect($app->baseUrl . 'formulario-dinamico'); return; }

        $formRow = $conn->fetchAssociative("SELECT * FROM formulario_dinamico WHERE id=?", [$id]);
        if (!$formRow) { $app->redirect($app->baseUrl . 'formulario-dinamico'); return; }

        // Despublica outros formulários da mesma entidade
        if (in_array($formRow['entidade'], ['agent','space','event'])) {
            $conn->executeStatement(
                "UPDATE formulario_dinamico SET status='draft' WHERE entidade=? AND status='published' AND id!=?",
                [$formRow['entidade'], $id]
            );
        }

        $conn->update('formulario_dinamico', [
            'status' => 'published',
            'ativo'  => 't',
            'atualizado_em' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        $app->redirect($app->baseUrl . 'formulario-dinamico');
    }

    // ================================================================
    // POST /formulario-dinamico/excluir
    // ================================================================

    function POST_excluir()
    {
        $app = App::i();
        $this->requireAuthentication();
        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }
        $conn = $app->em->getConnection();
        $id = (int)($this->postData['id'] ?? 0);
        if (!$id) return;
        $conn->delete('formulario_dinamico_oportunidade', ['formulario_id' => $id]);
        $conn->delete('formulario_dinamico_campo', ['formulario_id' => $id]);
        $conn->delete('formulario_dinamico', ['id' => $id]);
        $app->redirect($app->baseUrl . 'formulario-dinamico');
    }

    // ================================================================
    // GET /formulario-dinamico/oportunidades
    // ================================================================

    function GET_oportunidades()
    {
        $app = App::i();
        $this->requireAuthentication();
        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }
        $id = (int)($this->data['id'] ?? 0);
        if (!$id) { $app->pass(); return; }

        $conn = $app->em->getConnection();
        $formRow = $conn->fetchAssociative("SELECT * FROM formulario_dinamico WHERE id=?", [$id]);
        if (!$formRow) { $app->pass(); return; }

        $linkedRows = $conn->fetchAllAssociative(
            "SELECT fdo.id AS vinculo_id, fdo.oportunidade_id, o.name AS oportunidade_nome
             FROM formulario_dinamico_oportunidade fdo
             JOIN opportunity o ON o.id = fdo.oportunidade_id
             WHERE fdo.formulario_id=?", [$id]
        );
        $this->render('associar-oportunidade', [
            'formulario' => $formRow,
            'vinculos'   => $linkedRows,
        ]);
    }

    function POST_associarOportunidade()
    {
        $app = App::i();
        $this->requireAuthentication();
        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }
        $conn = $app->em->getConnection();
        $formId = (int)($this->postData['formulario_id'] ?? 0);
        $opportunityId = (int)($this->postData['oportunidade_id'] ?? 0);
        if (!$formId || !$opportunityId) throw new BadRequest(i::__('Parâmetros inválidos.'));

        $exists = $conn->fetchOne(
            "SELECT id FROM formulario_dinamico_oportunidade WHERE formulario_id=? AND oportunidade_id=?",
            [$formId, $opportunityId]
        );
        if (!$exists) {
            $conn->insert('formulario_dinamico_oportunidade', [
                'formulario_id' => $formId,
                'oportunidade_id' => $opportunityId,
            ]);
        }
        $app->redirect($app->baseUrl . 'formulario-dinamico/oportunidades?id=' . $formId);
    }

    function POST_removerOportunidade()
    {
        $app = App::i();
        $this->requireAuthentication();
        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }
        $conn = $app->em->getConnection();
        $id = (int)($this->postData['id'] ?? 0);
        if (!$id) return;
        $vinculo = $conn->fetchAssociative("SELECT formulario_id FROM formulario_dinamico_oportunidade WHERE id=?", [$id]);
        $formId = $vinculo ? (int)$vinculo['formulario_id'] : 0;
        $conn->delete('formulario_dinamico_oportunidade', ['id' => $id]);
        $redirect = $formId ? $app->baseUrl . 'formulario-dinamico/oportunidades?id=' . $formId : $app->baseUrl . 'formulario-dinamico';
        $app->redirect($redirect);
    }

    // ================================================================
    // Utilitários
    // ================================================================

    private function saveCampos(int $formId, array $campos): void
    {
        $conn = App::i()->em->getConnection();
        foreach ($campos as $ordem => $campo) {
            $slug = $campo['slug'] ?? $this->generateSlug($campo['rotulo'] ?? "campo_{$ordem}");
            $conn->insert('formulario_dinamico_campo', [
                'formulario_id' => $formId,
                'slug'          => $slug,
                'rotulo'        => $campo['rotulo'] ?? '',
                'placeholder'   => $campo['placeholder'] ?? '',
                'tipo'          => $campo['tipo'] ?? 'text',
                'opcoes'        => !empty($campo['opcoes']) ? json_encode($campo['opcoes']) : null,
                'obrigatorio'   => (!empty($campo['obrigatorio']) ? 't' : 'f'),
                'ordem'         => $ordem,
                'coluna_span'   => (int)($campo['coluna_span'] ?? 12),
                'editavel'      => (isset($campo['editavel']) && !$campo['editavel']) ? 'f' : 't',
                'grupo_id'      => (int)($campo['grupo_id'] ?? 0),
                'grupo_titulo'  => $campo['grupo_titulo'] ?? '',
            ]);
        }
    }

    private function generateSlug(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($text, 'UTF-8')), '-');
    }
}
