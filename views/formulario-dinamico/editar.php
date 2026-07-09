<?php
use MapasCulturais\i;

$this->import('mc-icon mc-card mc-modal form-field-editor form-builder');
?><script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<div class="entity-form">
    <header class="entity-form__header">
        <h1><?= i::__('Editar Formulário') ?></h1>
        <a class="btn btn-secondary" href="<?= $app->createUrl('formulario-dinamico') ?>">
            <mc-icon name="arrowBack"></mc-icon>
            <?= i::__('Voltar') ?>
        </a>
        <span class="badge <?= $formData['status'] === 'published' ? 'badge-success' : 'badge-secondary' ?>"
              style="font-size:1rem;padding:0.3rem 0.8rem;">
            <?= $formData['status'] === 'published' ? i::__('Publicado') : i::__('Rascunho') ?>
        </span>
    </header>

    <form-builder
        :form-id="<?= htmlspecialchars($formData['id'] ?? 0) ?>"
        :entidade="'<?= htmlspecialchars($formData['entidade'] ?? '') ?>'"
        initial-titulo="<?= htmlspecialchars($formData['titulo'] ?? '') ?>"
        initial-slug="<?= htmlspecialchars($formData['slug'] ?? '') ?>"
        initial-descricao="<?= htmlspecialchars($formData['descricao'] ?? '') ?>"
        :initial-campos='<?= json_encode($formData['campos'] ?? [], JSON_UNESCAPED_UNICODE) ?>'
        :initial-grupos='<?= json_encode($formData['grupos'] ?? [["id"=>0,"titulo"=>"Geral","colunas"=>1]], JSON_UNESCAPED_UNICODE) ?>'
        :campos-nativos='<?= json_encode($formData['camposNativos'] ?? [], JSON_UNESCAPED_UNICODE) ?>'
        url-salvar="<?= $app->createUrl('formulario-dinamico', 'editar') ?>"
        entidade-label="<?php
            $labels = ['agent' => i::__('Agente'), 'space' => i::__('Espaço'), 'event' => i::__('Evento'), 'opportunity' => i::__('Oportunidade')];
            echo $labels[$formData['entidade'] ?? ''] ?? $formData['entidade'] ?? '';
        ?>"
        :is-draft="<?= $formData['status'] !== 'published' ? 'true' : 'false' ?>"
    ></form-builder>
</div>
