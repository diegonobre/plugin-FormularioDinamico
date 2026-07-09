<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 *
 * Formulário de criação de formulário dinâmico
 */

use MapasCulturais\i;

$this->import('
    mc-icon
    mc-card
');
?>
<div class="entity-form">
    <header class="entity-form__header">
        <h1><?= i::__('Novo Formulário Dinâmico') ?></h1>
        <a class="btn btn-secondary" href="<?= $app->createUrl('formulario-dinamico') ?>">
            <mc-icon name="arrowBack"></mc-icon>
            <?= i::__('Voltar') ?>
        </a>
    </header>

    <form method="POST" action="<?= $app->createUrl('formulario-dinamico', 'novo') ?>" class="entity-form__form">
        <div class="entity-form__field">
            <label for="titulo"><?= i::__('Título') ?> <span class="required">*</span></label>
            <input type="text" id="titulo" name="titulo" required
                   placeholder="<?= i::__('Ex: Cadastro de Artistas') ?>"
                   oninput="document.getElementById('slug').value = this.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')"
                   class="input-text">
        </div>

        <div class="entity-form__field">
            <label for="slug"><?= i::__('Slug') ?> <span class="required">*</span></label>
            <input type="text" id="slug" name="slug" required
                   placeholder="<?= i::__('Ex: cadastro-artistas') ?>"
                   class="input-text">
            <p class="entity-form__help"><?= i::__('Usado como prefixo dos metadados. Auto-gerado a partir do título.') ?></p>
        </div>

        <div class="entity-form__field">
            <label for="descricao"><?= i::__('Descrição') ?></label>
            <textarea id="descricao" name="descricao" rows="3" class="input-text"
                      placeholder="<?= i::__('Descrição opcional do formulário') ?>"></textarea>
        </div>

        <div class="entity-form__field">
            <label for="entidade"><?= i::__('Entidade') ?> <span class="required">*</span></label>
            <select id="entidade" name="entidade" required class="input-select">
                <option value=""><?= i::__('Selecione...') ?></option>
                <option value="agent"><?= i::__('Agente') ?></option>
                <option value="space"><?= i::__('Espaço') ?></option>
                <option value="event"><?= i::__('Evento') ?></option>
                <option value="opportunity"><?= i::__('Oportunidade') ?></option>
            </select>
            <p class="entity-form__help">
                <?= i::__('Para Agentes, Espaços e Eventos, apenas um formulário pode estar ativo por vez.') ?>
            </p>
        </div>

        <div class="entity-form__field">
            <label class="checkbox-label">
                <input type="checkbox" name="ativo" value="1" checked>
                <?= i::__('Ativo') ?>
            </label>
        </div>

        <div class="entity-form__actions">
            <button type="submit" class="btn btn-primary">
                <mc-icon name="save"></mc-icon>
                <?= i::__('Criar Formulário') ?>
            </button>
        </div>
    </form>
</div>
