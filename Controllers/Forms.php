<?php

namespace FormularioDinamico\Controllers;

use MapasCulturais\App;
use MapasCulturais\i;
use MapasCulturais\Exceptions\PermissionDenied;
use MapasCulturais\Exceptions\ValidationError;

/**
 * Controller público do plugin FormularioDinamico
 *
 * Fornece endpoints para obter e salvar valores dos campos dinâmicos.
 */
class Forms extends \MapasCulturais\Controller
{
    public array $requestData = [];

    public function setRequest(array $data): void
    {
        $this->requestData = $data;
    }

    /**
     * Retorna os campos dinâmicos de uma entidade como JSON.
     *
     * GET /formulario-dinamico/campos?entidade=agent&id=123
     */
    public function GET_fields(): void
    {
        $app = App::i();
        $entidade = $this->requestData['entidade'] ?? '';
        $entityId = (int)($this->requestData['id'] ?? 0);

        $plugin = \FormularioDinamico\Plugin::getInstance();
        if (!$plugin) {
            $this->json(['error' => 'Plugin não inicializado'], 500);
            return;
        }

        if ($entidade === 'opportunity') {
            $form = $plugin->getFormForOpportunity($entityId);
        } else {
            $form = $plugin->getActiveForm($entidade);
        }

        if (!$form) {
            $this->json(['fields' => []]);
            return;
        }

        $entityClass = \FormularioDinamico\Plugin::ENTITY_MAP[$entidade] ?? null;
        $entity = $entityClass && $entityId ? $app->repo($entityClass)->find($entityId) : null;

        $fields = [];
        foreach ($form->campos as $campo) {
            $key = "{$form->slug}_{$campo->slug}";
            $fields[] = [
                'key'         => $key,
                'rotulo'      => $campo->rotulo,
                'placeholder' => $campo->placeholder,
                'tipo'        => $campo->tipo,
                'opcoes'      => $campo->opcoes,
                'obrigatorio' => $campo->obrigatorio,
                'coluna_span' => $campo->coluna_span,
                'valor'       => $entity ? $entity->getMetadata($key) : null,
            ];
        }

        $this->json([
            'form'   => [
                'slug'    => $form->slug,
                'titulo'  => $form->titulo,
            ],
            'fields' => $fields,
        ]);
    }

    /**
     * Salva os valores dos campos dinâmicos de uma entidade.
     *
     * POST /formulario-dinamico/salvar
     */
    public function POST_save(): void
    {
        $app = App::i();
        $data = $this->requestData;

        $entidade = $data['entidade'] ?? '';
        $entityId = (int)($data['id'] ?? 0);

        $entityClass = \FormularioDinamico\Plugin::ENTITY_MAP[$entidade] ?? null;
        if (!$entityClass || !$entityId) {
            throw new ValidationError(i::__('Parâmetros inválidos.'));
        }

        $entity = $app->repo($entityClass)->find($entityId);
        if (!$entity) {
            throw new ValidationError(i::__('Entidade não encontrada.'));
        }

        // Verifica permissão
        $entity->checkPermission('modify');

        $plugin = \FormularioDinamico\Plugin::getInstance();
        if (!$plugin) {
            throw new ValidationError(i::__('Plugin não inicializado.'));
        }

        if ($entidade === 'opportunity') {
            $form = $plugin->getFormForOpportunity($entityId);
        } else {
            $form = $plugin->getActiveForm($entidade);
        }

        if (!$form) {
            throw new ValidationError(i::__('Nenhum formulário ativo encontrado para esta entidade.'));
        }

        // Salva os valores
        $values = $data['valores'] ?? [];
        foreach ($form->campos as $campo) {
            $key = "{$form->slug}_{$campo->slug}";
            if (array_key_exists($key, $values)) {
                $entity->setMetadata($key, $values[$key]);
            }
        }

        $entity->save(true);

        $this->json(['success' => true]);
    }

    /**
     * Helper para retornar JSON
     */
    private function json(array $data, int $status = 200): void
    {
        $app = App::i();
        $app->response->setStatusCode($status);
        $app->response->setContentType('application/json');
        $app->response->setBody(json_encode($data, JSON_UNESCAPED_UNICODE));
        $app->response->send();
        exit;
    }
}
