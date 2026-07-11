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

        // Pré-popula com os campos realmente visíveis no app para a entidade
        // (extraídos das views de edição do tema ativo, agrupados por aba).
        $plugin = \FormularioDinamico\Plugin::getInstance();
        if ($plugin) {
            $fields = $plugin->getVisibleEntityFields($entidade);
            if (empty($fields)) {
                $fields = $plugin->getAllEntityFields($entidade);
            }
            $this->saveCampos($formId, array_map(function ($f) {
                return [
                    'slug'        => $f->key,
                    'rotulo'      => $f->rotulo,
                    'placeholder' => $f->placeholder,
                    'tipo'        => $f->tipo,
                    'opcoes'      => $f->opcoes ?? [],
                    'obrigatorio' => $f->obrigatorio,
                    'coluna_span' => 12,
                    'editavel'    => $f->editavel,
                    'grupo_id'    => $f->grupo_id ?? 0,
                    'grupo_titulo'=> $f->grupo_titulo ?? 'Geral',
                ];
            }, $fields));
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
            $gcolunas = max(1, (int)($c['grupo_colunas'] ?? 1));
            if (!isset($grupos[$gid])) {
                $grupos[$gid] = ['id' => $gid, 'titulo' => $gtitulo ?: 'Geral', 'colunas' => $gcolunas];
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
        // Formulários de oportunidade viram campos do formulário de inscrição;
        // os metadados obrigatórios da entidade Opportunity não se aplicam lá.
        if ($formRow['entidade'] === 'opportunity') {
            $entityClass = null;
        }
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

        // Formulário de oportunidade publicado: ressincroniza os campos
        // materializados nos formulários de inscrição das oportunidades vinculadas
        $plugin = \FormularioDinamico\Plugin::getInstance();
        if ($plugin && $entidade === 'opportunity') {
            $plugin->syncLinkedOpportunities($id);
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

        // Formulário de oportunidade: materializa os campos nos formulários
        // de inscrição das oportunidades vinculadas
        $plugin = \FormularioDinamico\Plugin::getInstance();
        if ($plugin && $formRow['entidade'] === 'opportunity') {
            $plugin->syncLinkedOpportunities($id);
        }

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

        // Remove os campos materializados nas oportunidades vinculadas
        $plugin = \FormularioDinamico\Plugin::getInstance();
        if ($plugin) {
            foreach ($plugin->getLinkedOpportunityIds($id) as $opportunityId) {
                $plugin->removeOpportunityRegistrationFields($opportunityId, $id);
            }
        }

        $conn->delete('formulario_dinamico_oportunidade', ['formulario_id' => $id]);
        $conn->delete('formulario_dinamico_campo', ['formulario_id' => $id]);
        $conn->delete('formulario_dinamico', ['id' => $id]);
        $app->redirect($app->baseUrl . 'formulario-dinamico');
    }

    // ================================================================
    // GET /formulario-dinamico/oportunidades
    // ================================================================

    function GET_oportunities()
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

        // Oportunidades já vinculadas
        $linkedIds = $conn->fetchFirstColumn(
            "SELECT oportunidade_id FROM formulario_dinamico_oportunidade WHERE formulario_id=?", [$id]
        );

        $linkedMap = array_flip($linkedIds);

        // Todas as oportunidades
        $allOpportunities = $conn->fetchAllAssociative(
            "SELECT o.id, o.name, o.status FROM opportunity o ORDER BY o.id DESC"
        );

        $this->render('associar-oportunidade', [
            'formulario'       => $formRow,
            'oportunidades'    => $allOpportunities,
            'linkedMap'        => $linkedMap,
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

        $plugin = \FormularioDinamico\Plugin::getInstance();

        // Uma oportunidade só pode ter um formulário: remove vínculos anteriores
        // com outros formulários (e os campos materializados por eles)
        $previous = $conn->fetchFirstColumn(
            "SELECT formulario_id FROM formulario_dinamico_oportunidade WHERE oportunidade_id=? AND formulario_id!=?",
            [$opportunityId, $formId]
        );
        foreach ($previous as $previousFormId) {
            if ($plugin) {
                $plugin->removeOpportunityRegistrationFields($opportunityId, (int)$previousFormId);
            }
            $conn->delete('formulario_dinamico_oportunidade', [
                'formulario_id'   => $previousFormId,
                'oportunidade_id' => $opportunityId,
            ]);
        }

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

        // Materializa os campos no formulário de inscrição da oportunidade
        if ($plugin) {
            $form = $plugin->getFormById($formId);
            if ($form && $form->status === 'published') {
                $plugin->syncOpportunityRegistrationFields($form, $opportunityId);
            }
        }

        $app->redirect($app->createUrl('formulario-dinamico', 'oportunities', ['id' => $formId]));
    }

    function POST_removerOportunidade()
    {
        $app = App::i();
        $this->requireAuthentication();
        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }
        $conn = $app->em->getConnection();
        $opportunityId = (int)($this->postData['id'] ?? 0);
        $formId = (int)($this->postData['formulario_id'] ?? 0);
        if (!$formId || !$opportunityId) return;

        // Remove os campos materializados no formulário de inscrição
        $plugin = \FormularioDinamico\Plugin::getInstance();
        if ($plugin) {
            $plugin->removeOpportunityRegistrationFields($opportunityId, $formId);
        }

        $conn->delete('formulario_dinamico_oportunidade', [
            'formulario_id'   => $formId,
            'oportunidade_id' => $opportunityId,
        ]);
        $app->redirect($app->createUrl('formulario-dinamico', 'oportunities', ['id' => $formId]));
    }

    // ================================================================
    // Relatórios e exportação
    // ================================================================

    /**
     * GET /formulario-dinamico/relatorio?id={formId}
     * Lista os dados coletados pelo formulário.
     */
    function GET_relatorio()
    {
        $form = $this->requireFormForReport();
        $plugin = \FormularioDinamico\Plugin::getInstance();
        $data = $plugin->getFormSubmissions($form);

        $this->render('relatorio', [
            'formulario' => $form,
            'columns'    => $data['columns'],
            'rows'       => $data['rows'],
        ]);
    }

    /**
     * GET /formulario-dinamico/exportar?id={formId}&formato=csv|xlsx
     * Exporta todos os dados coletados.
     */
    function GET_exportar()
    {
        $form = $this->requireFormForReport();
        $plugin = \FormularioDinamico\Plugin::getInstance();
        $data = $plugin->getFormSubmissions($form);
        $formato = strtolower(trim($this->data['formato'] ?? 'csv'));

        $filename = $form->slug . '-' . date('Ymd-His');
        $header = array_merge(
            ['ID', i::__('Identificação'), i::__('Status'), i::__('Data')],
            $form->entidade === 'opportunity' ? [i::__('Oportunidade')] : [],
            array_values($data['columns'])
        );

        $lines = [];
        foreach ($data['rows'] as $row) {
            $line = [$row['_id'], $row['_label'], $row['_status_label'], $row['_date']];
            if ($form->entidade === 'opportunity') {
                $line[] = $row['_opportunity'] ?? '';
            }
            foreach (array_keys($data['columns']) as $slug) {
                $line[] = $row['values'][$slug] ?? '';
            }
            $lines[] = $line;
        }

        if ($formato === 'xlsx') {
            $this->outputXlsx($filename, $header, $lines);
        } else {
            $this->outputCsv($filename, $header, $lines);
        }
    }

    /**
     * GET /formulario-dinamico/relatorioItem?id={formId}&item={itemId}
     * Página imprimível de um item individual (usar "Salvar como PDF" do navegador).
     */
    function GET_relatorioItem()
    {
        $form = $this->requireFormForReport();
        $itemId = (int)($this->data['item'] ?? 0);
        $plugin = \FormularioDinamico\Plugin::getInstance();
        $data = $itemId ? $plugin->getFormSubmission($form, $itemId) : null;

        if (!$data) {
            App::i()->pass();
            return;
        }

        $this->render('relatorio-item', [
            'formulario' => $form,
            'columns'    => $data['columns'],
            'row'        => $data['row'],
        ]);
    }

    /**
     * Carrega o formulário e verifica permissão para as telas de relatório.
     */
    private function requireFormForReport(): object
    {
        $app = App::i();
        $this->requireAuthentication();
        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user, null, i::__('Gerenciar Formulários Dinâmicos'));
        }
        $id = (int)($this->data['id'] ?? 0);
        $plugin = \FormularioDinamico\Plugin::getInstance();
        $form = $id && $plugin ? $plugin->getFormById($id) : null;
        if (!$form) {
            $app->pass();
        }
        return $form;
    }

    private function outputCsv(string $filename, array $header, array $lines): void
    {
        $app = App::i();
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
        $out = fopen('php://output', 'w');
        // BOM para o Excel reconhecer UTF-8; ponto-e-vírgula para locale pt-BR
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header, ';');
        foreach ($lines as $line) {
            fputcsv($out, $line, ';');
        }
        fclose($out);
        exit;
    }

    private function outputXlsx(string $filename, array $header, array $lines): void
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr(i::__('Dados coletados'), 0, 31));

        $sheet->fromArray($header, null, 'A1');
        $sheet->getStyle('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($header)) . '1')
            ->getFont()->setBold(true);
        if ($lines) {
            $sheet->fromArray($lines, null, 'A2');
        }
        foreach (range(1, count($header)) as $i) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))
                ->setAutoSize(true);
        }

        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}.xlsx\"");
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
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
                'grupo_colunas' => min(max((int)($campo['grupo_colunas'] ?? 1), 1), 4),
            ]);
        }
    }

    private function generateSlug(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($text, 'UTF-8')), '-');
    }
}
