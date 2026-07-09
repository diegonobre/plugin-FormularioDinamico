<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 *
 * Editor de formulário dinâmico com drag-and-drop
 */

use MapasCulturais\i;

$this->import('
    mc-icon
    mc-card
    mc-modal
    form-field-editor
    form-builder
');
?>
<div class="entity-form">
    <header class="entity-form__header">
        <h1><?= i::__('Editar Formulário') ?></h1>
        <a class="btn btn-secondary" href="<?= $app->createUrl('formulario-dinamico') ?>">
            <mc-icon name="arrowBack"></mc-icon>
            <?= i::__('Voltar') ?>
        </a>
    </header>

    <form-builder
        :form-id="<?= htmlspecialchars($formData['id'] ?? 0) ?>"
        :entidade="'<?= htmlspecialchars($formData['entidade'] ?? '') ?>'"
        initial-titulo="<?= htmlspecialchars($formData['titulo'] ?? '') ?>"
        initial-slug="<?= htmlspecialchars($formData['slug'] ?? '') ?>"
        initial-descricao="<?= htmlspecialchars($formData['descricao'] ?? '') ?>"
        initial-ativo="<?= $formData['ativo'] ? 'true' : 'false' ?>"
        :initial-campos='<?= json_encode($formData['campos'] ?? [], JSON_UNESCAPED_UNICODE) ?>'
        :campos-nativos='<?= json_encode($formData['camposNativos'] ?? [], JSON_UNESCAPED_UNICODE) ?>'
        url-salvar="<?= $app->createUrl('formulario-dinamico', 'editar') ?>"
        entidade-label="<?php
            $labels = ['agent' => i::__('Agente'), 'space' => i::__('Espaço'), 'event' => i::__('Evento'), 'opportunity' => i::__('Oportunidade')];
            echo $labels[$formData['entidade'] ?? ''] ?? $formData['entidade'] ?? '';
        ?>"
    ></form-builder>
</div>
