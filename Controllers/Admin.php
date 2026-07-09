<?php

namespace FormularioDinamico\Controllers;

use MapasCulturais\App;
use MapasCulturais\i;
use MapasCulturais\Exceptions\PermissionDenied;
use MapasCulturais\Exceptions\ValidationError;
use MapasCulturais\Definitions\Metadata;

/**
 * Controller Admin do plugin FormularioDinamico
 *
 * Gerencia o CRUD de formulários dinâmicos no painel de controle.
 * Rotas em /formulario-dinamico/
 */
class Admin extends \MapasCulturais\Controller
{
    function __construct()
    {
        $this->layout = 'panel';
    }

    /**
     * GET /formulario-dinamico/ — Lista todos os formulários
     */
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
                SELECT f.id, f.slug, f.titulo, f.entidade, f.ativo, f.criado_em,
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
                    'ativo'        => (bool)$row['ativo'],
                    'total_campos' => (int)$row['total_campos'],
                    'criado_em'    => $row['criado_em'],
                ];
            }
        } catch (\Exception $e) {
            // Tabelas podem não existir ainda
        }

        $this->render('index', ['forms' => $forms]);
    }

    /**
     * GET /formulario-dinamico/novo — Formulário de criação
     */
    function GET_novo()
    {
        $app = App::i();
        $this->requireAuthentication();

        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }

        $this->render('novo');
    }

    /**
     * POST /formulario-dinamico/novo — Salva novo formulário
     */
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

        if (empty($titulo)) {
            throw new ValidationError(i::__('O título do formulário é obrigatório.'));
        }

        if (empty($slug)) {
            $slug = $this->generateSlug($titulo);
        }

        if (!in_array($entidade, ['agent', 'space', 'event', 'opportunity'])) {
            throw new ValidationError(i::__('Entidade inválida.'));
        }

        $existing = $conn->fetchOne("SELECT id FROM formulario_dinamico WHERE slug = ?", [$slug]);
        if ($existing) {
            throw new ValidationError(i::__('Já existe um formulário com este slug.'));
        }

        if (in_array($entidade, ['agent', 'space', 'event'])) {
            if (!empty($data['ativo'])) {
                $conn->executeStatement(
                    "UPDATE formulario_dinamico SET ativo = false WHERE entidade = ? AND ativo = true",
                    [$entidade]
                );
            }
        }

        $userId = $app->user->profile->id ?? null;

        $conn->insert('formulario_dinamico', [
            'slug'      => $slug,
            'titulo'    => $titulo,
            'descricao' => trim($data['descricao'] ?? ''),
            'entidade'  => $entidade,
            'ativo'     => !empty($data['ativo']),
            'criado_por' => $userId,
            'criado_em'  => date('Y-m-d H:i:s'),
            'atualizado_em' => date('Y-m-d H:i:s'),
        ]);

        $formId = $conn->lastInsertId();

        if (!empty($data['campos']) && is_string($data['campos'])) {
            $campos = json_decode($data['campos'], true);
            if (is_array($campos)) {
                $this->saveCampos($formId, $campos);
            }
        }

        // Registra os metadados imediatamente
        $this->registerFormMetadata($formId);

        $app->redirect('formulario-dinamico/editar?id=' . $formId);
    }

    /**
     * GET /formulario-dinamico/editar — Editor do formulário com drag-and-drop
     */
    function GET_editar()
    {
        $app = App::i();
        $this->requireAuthentication();

        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }

        $id = (int)($this->data['id'] ?? 0);
        if (!$id) {
            $app->pass();
            return;
        }

        $conn = $app->em->getConnection();
        $formRow = $conn->fetchAssociative("SELECT * FROM formulario_dinamico WHERE id = ?", [$id]);
        if (!$formRow) {
            $app->pass();
            return;
        }

        $campoRows = $conn->fetchAllAssociative(
            "SELECT * FROM formulario_dinamico_campo WHERE formulario_id = ? ORDER BY ordem",
            [$id]
        );

        $plugin = \FormularioDinamico\Plugin::getInstance();
        $camposNativos = $plugin ? $plugin->getNativeRequiredFields($formRow['entidade']) : [];

        $campos = [];
        if (!empty($campoRows)) {
            // Campos já salvos no banco
            foreach ($campoRows as $c) {
                $campos[] = [
                    'slug'        => $c['slug'],
                    'rotulo'      => $c['rotulo'],
                    'placeholder' => $c['placeholder'],
                    'tipo'        => $c['tipo'],
                    'opcoes'      => $c['opcoes'] ? json_decode($c['opcoes'], true) : [],
                    'obrigatorio' => (bool)$c['obrigatorio'],
                    'coluna_span' => (int)$c['coluna_span'],
                    'editavel'    => (bool)$c['editavel'],
                ];
            }
        } else {
            // Primeira edição: pré-popula com campos nativos obrigatórios da entidade
            $nativeFields = $plugin ? $plugin->getNativeRequiredFields($formRow['entidade']) : [];
            $formSlug = $formRow['slug'];
            foreach ($nativeFields as $nf) {
                $campos[] = [
                    'slug'        => $nf->key,
                    'rotulo'      => $nf->rotulo,
                    'placeholder' => $nf->placeholder,
                    'tipo'        => $nf->tipo,
                    'opcoes'      => [],
                    'obrigatorio' => $nf->obrigatorio,
                    'coluna_span' => 12,
                    'editavel'    => true,
                ];
            }
        }

        $formData = [
            'id'           => $formRow['id'],
            'slug'         => $formRow['slug'],
            'titulo'       => $formRow['titulo'],
            'descricao'    => $formRow['descricao'],
            'entidade'     => $formRow['entidade'],
            'ativo'        => (bool)$formRow['ativo'],
            'campos'       => $campos,
            'camposNativos'=> $camposNativos,
        ];

        $this->render('editar', ['formData' => $formData]);
    }

    /**
     * POST /formulario-dinamico/editar — Salva alterações do formulário
     */
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
        if (!$id) {
            throw new ValidationError(i::__('ID do formulário não informado.'));
        }

        $titulo   = trim($data['titulo'] ?? '');
        $slug     = trim($data['slug'] ?? '');
        $entidade = trim($data['entidade'] ?? '');

        if (empty($titulo)) {
            throw new ValidationError(i::__('O título do formulário é obrigatório.'));
        }

        if (!in_array($entidade, ['agent', 'space', 'event', 'opportunity'])) {
            throw new ValidationError(i::__('Entidade inválida.'));
        }

        $existing = $conn->fetchOne(
            "SELECT id FROM formulario_dinamico WHERE slug = ? AND id != ?",
            [$slug, $id]
        );
        if ($existing) {
            throw new ValidationError(i::__('Já existe outro formulário com este slug.'));
        }

        $ativo = !empty($data['ativo']);

        if ($ativo && in_array($entidade, ['agent', 'space', 'event'])) {
            $conn->executeStatement(
                "UPDATE formulario_dinamico SET ativo = false WHERE entidade = ? AND ativo = true AND id != ?",
                [$entidade, $id]
            );
        }

        $conn->update('formulario_dinamico', [
            'slug'      => $slug,
            'titulo'    => $titulo,
            'descricao' => trim($data['descricao'] ?? ''),
            'entidade'  => $entidade,
            'ativo'     => $ativo,
            'atualizado_em' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        // Salva campos (vem como JSON string do form-builder)
        if (!empty($data['campos']) && is_string($data['campos'])) {
            $campos = json_decode($data['campos'], true);
            if (is_array($campos)) {
                $conn->delete('formulario_dinamico_campo', ['formulario_id' => $id]);
                $this->saveCampos($id, $campos);
            }
        }

        $app->redirect('formulario-dinamico');
    }

    /**
     * POST /formulario-dinamico/excluir — Exclui formulário
     */
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

        $app->redirect('formulario-dinamico');
    }

    /**
     * GET /formulario-dinamico/oportunidades — Gerenciar vínculos com oportunidades
     */
    function GET_oportunidades()
    {
        $app = App::i();
        $this->requireAuthentication();

        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }

        $id = (int)($this->data['id'] ?? 0);
        if (!$id) {
            $app->pass();
            return;
        }

        $conn = $app->em->getConnection();
        $formRow = $conn->fetchAssociative("SELECT * FROM formulario_dinamico WHERE id = ?", [$id]);
        if (!$formRow) {
            $app->pass();
            return;
        }

        $linkedRows = $conn->fetchAllAssociative(
            "SELECT fdo.id AS vinculo_id, fdo.oportunidade_id, o.name AS oportunidade_nome
             FROM formulario_dinamico_oportunidade fdo
             JOIN opportunity o ON o.id = fdo.oportunidade_id
             WHERE fdo.formulario_id = ?",
            [$id]
        );

        $this->render('associar-oportunidade', [
            'formulario' => $formRow,
            'vinculos'   => $linkedRows,
        ]);
    }

    /**
     * POST /formulario-dinamico/associar-oportunidade — Vincula formulário a uma oportunidade
     */
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

        if (!$formId || !$opportunityId) {
            throw new ValidationError(i::__('Parâmetros inválidos.'));
        }

        $exists = $conn->fetchOne(
            "SELECT id FROM formulario_dinamico_oportunidade WHERE formulario_id = ? AND oportunidade_id = ?",
            [$formId, $opportunityId]
        );

        if (!$exists) {
            $conn->insert('formulario_dinamico_oportunidade', [
                'formulario_id'   => $formId,
                'oportunidade_id' => $opportunityId,
            ]);
        }

        $app->redirect('formulario-dinamico/oportunidades?id=' . $formId);
    }

    /**
     * POST /formulario-dinamico/remover-oportunidade — Remove vínculo com oportunidade
     */
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

        // Busca o formulario_id antes de deletar
        $vinculo = $conn->fetchAssociative(
            "SELECT formulario_id FROM formulario_dinamico_oportunidade WHERE id = ?",
            [$id]
        );
        $formId = $vinculo ? (int)$vinculo['formulario_id'] : 0;

        $conn->delete('formulario_dinamico_oportunidade', ['id' => $id]);

        $redirect = $formId
            ? 'formulario-dinamico/oportunidades?id=' . $formId
            : 'formulario-dinamico';
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
                'obrigatorio'   => !empty($campo['obrigatorio']),
                'ordem'         => $ordem,
                'coluna_span'   => (int)($campo['coluna_span'] ?? 12),
                'editavel'      => !isset($campo['editavel']) || $campo['editavel'],
            ]);
        }
    }

    private function registerFormMetadata(int $formId): void
    {
        $app = App::i();
        $conn = $app->em->getConnection();

        $formRow = $conn->fetchAssociative(
            "SELECT f.* FROM formulario_dinamico f WHERE f.id = ? AND f.ativo = true",
            [$formId]
        );
        if (!$formRow) return;

        $entityMap = [
            'agent'       => 'MapasCulturais\Entities\Agent',
            'space'       => 'MapasCulturais\Entities\Space',
            'event'       => 'MapasCulturais\Entities\Event',
            'opportunity' => 'MapasCulturais\Entities\Opportunity',
        ];

        $entityClass = $entityMap[$formRow['entidade']] ?? null;
        if (!$entityClass) return;

        $campoRows = $conn->fetchAllAssociative(
            "SELECT * FROM formulario_dinamico_campo WHERE formulario_id = ?",
            [$formId]
        );

        $typeMap = [
            'text' => 'string', 'textarea' => 'text', 'number' => 'string',
            'email' => 'string', 'url' => 'string', 'date' => 'date',
            'datetime' => 'date', 'phone' => 'string', 'cep' => 'string',
            'cpf' => 'string', 'cnpj' => 'string', 'gender' => 'select',
            'select' => 'select', 'multiselect' => 'multiselect',
        ];

        foreach ($campoRows as $c) {
            $key = $formRow['slug'] . '_' . $c['slug'];
            $cfg = [
                'label'       => $c['rotulo'],
                'type'        => $typeMap[$c['tipo']] ?? 'string',
                'placeholder' => $c['placeholder'] ?? '',
            ];

            if ($c['obrigatorio']) {
                $cfg['validations'] = [
                    'required' => i::__("O campo {$c['rotulo']} é obrigatório")
                ];
            }

            if ($c['opcoes']) {
                $cfg['options'] = json_decode($c['opcoes'], true);
            }

            $metadataDef = new Metadata($key, $cfg);
            $app->registerMetadata($metadataDef, $entityClass);
        }
    }

    private function generateSlug(string $text): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($text, 'UTF-8'));
        return trim($slug, '-');
    }
}
