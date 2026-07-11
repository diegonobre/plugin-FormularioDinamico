<?php
use MapasCulturais\i;

$this->import('
    fd-confirm-action
    mc-icon
');
?>
<div class="entity-list">
    <header class="entity-list__header">
        <h1><?= i::__('Formulários Dinâmicos') ?></h1>
        <div class="entity-list__actions">
            <a class="button button--primary button--icon" href="<?= $app->createUrl('formulario-dinamico', 'novo') ?>">
                <mc-icon name="add"></mc-icon>
                <?= i::__('Novo Formulário') ?>
            </a>
        </div>
    </header>

    <?php if (empty($forms)): ?>
        <div class="entity-list__empty">
            <p><?= i::__('Nenhum formulário cadastrado ainda.') ?></p>
            <p><?= i::__('Clique em "Novo Formulário" para criar o primeiro.') ?></p>
        </div>
    <?php else: ?>
        <div class="entity-list__table">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?= i::__('Título') ?></th>
                        <th><?= i::__('Slug') ?></th>
                        <th><?= i::__('Entidade') ?></th>
                        <th><?= i::__('Campos') ?></th>
                        <th><?= i::__('Status') ?></th>
                        <th><?= i::__('Ações') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forms as $form): ?>
                        <tr>
                            <td><?= htmlspecialchars($form->titulo) ?></td>
                            <td><code><?= htmlspecialchars($form->slug) ?></code></td>
                            <td>
                                <?php
                                $labels = ['agent' => i::__('Agente'), 'space' => i::__('Espaço'), 'event' => i::__('Evento'), 'opportunity' => i::__('Oportunidade')];
                                echo $labels[$form->entidade] ?? $form->entidade;
                                ?>
                            </td>
                            <td><?= (int)$form->total_campos ?></td>
                            <td>
                                <?php if ($form->status === 'published'): ?>
                                    <span class="badge badge-success"><?= i::__('Publicado') ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary"><?= i::__('Rascunho') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="entity-list__actions-cell">
                                <a class="button button--primary-outline button--sm button--icon"
                                   href="<?= $app->createUrl('formulario-dinamico', 'editar', [$form->id]) ?>">
                                    <mc-icon name="edit"></mc-icon>
                                    <?= i::__('Editar') ?>
                                </a>

                                <?php if ($form->status !== 'published'): ?>
                                    <fd-confirm-action
                                        action="<?= $app->createUrl('formulario-dinamico', 'publicar') ?>"
                                        :fields='{"id": <?= (int)$form->id ?>}'
                                        title="<?= htmlspecialchars(i::__('Publicar formulário'), ENT_QUOTES) ?>"
                                        message="<?= htmlspecialchars(sprintf(i::__('Deseja publicar o formulário "%s"? Ele substituirá o formulário publicado atualmente para esta entidade.'), $form->titulo), ENT_QUOTES) ?>"
                                        yes="<?= htmlspecialchars(i::__('Publicar'), ENT_QUOTES) ?>"
                                        no="<?= htmlspecialchars(i::__('Cancelar'), ENT_QUOTES) ?>"
                                        label="<?= htmlspecialchars(i::__('Publicar'), ENT_QUOTES) ?>"
                                        button-class="button--primary"
                                    ></fd-confirm-action>
                                <?php endif; ?>

                                <?php if ($form->entidade === 'opportunity'): ?>
                                    <a class="button button--secondary button--sm button--icon"
                                       href="<?= $app->createUrl('formulario-dinamico', 'oportunities', [$form->id]) ?>">
                                        <mc-icon name="link"></mc-icon>
                                        <?= i::__('Vincular Oportunidades') ?>
                                    </a>
                                <?php endif; ?>

                                <fd-confirm-action
                                    action="<?= $app->createUrl('formulario-dinamico', 'excluir') ?>"
                                    :fields='{"id": <?= (int)$form->id ?>}'
                                    title="<?= htmlspecialchars(i::__('Excluir formulário'), ENT_QUOTES) ?>"
                                    message="<?= htmlspecialchars(sprintf(i::__('Tem certeza que deseja excluir o formulário "%s"? Esta ação não pode ser desfeita.'), $form->titulo), ENT_QUOTES) ?>"
                                    yes="<?= htmlspecialchars(i::__('Excluir'), ENT_QUOTES) ?>"
                                    no="<?= htmlspecialchars(i::__('Cancelar'), ENT_QUOTES) ?>"
                                    icon="delete"
                                    label="<?= htmlspecialchars(i::__('Excluir'), ENT_QUOTES) ?>"
                                    button-class="button--text-danger"
                                ></fd-confirm-action>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
