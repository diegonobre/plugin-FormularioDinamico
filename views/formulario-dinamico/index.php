<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 *
 * Listagem de formulários dinâmicos
 */

use MapasCulturais\i;

$this->import('
    mc-link
    mc-icon
    mc-card
    mc-modal
');
?>
<div class="entity-list">
    <header class="entity-list__header">
        <h1><?= i::__('Formulários Dinâmicos') ?></h1>
        <div class="entity-list__actions">
            <a class="btn btn-primary" href="<?= $app->createUrl('formulario-dinamico', 'novo') ?>">
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
                                $labels = [
                                    'agent' => i::__('Agente'),
                                    'space' => i::__('Espaço'),
                                    'event' => i::__('Evento'),
                                    'opportunity' => i::__('Oportunidade'),
                                ];
                                echo $labels[$form->entidade] ?? $form->entidade;
                                ?>
                            </td>
                            <td><?= (int)$form->total_campos ?></td>
                            <td>
                                <?php if ($form->ativo): ?>
                                    <span class="badge badge-success"><?= i::__('Ativo') ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary"><?= i::__('Inativo') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="entity-list__actions-cell">
                                <a class="btn btn-primary btn-sm"
                                   href="<?= $app->createUrl('formulario-dinamico', 'editar', [$form->id]) ?>">
                                    <mc-icon name="edit"></mc-icon>
                                    <?= i::__('Editar') ?>
                                </a>

                                <?php if ($form->entidade === 'opportunity'): ?>
                                    <a class="btn btn-secondary btn-sm"
                                       href="<?= $app->createUrl('formulario-dinamico', 'oportunidades', [$form->id]) ?>">
                                        <mc-icon name="link"></mc-icon>
                                        <?= i::__('Vincular Oportunidades') ?>
                                    </a>
                                <?php endif; ?>

                                <form method="POST"
                                      action="<?= $app->createUrl('formulario-dinamico', 'excluir') ?>"
                                      style="display:inline"
                                      onsubmit="return confirm('<?= i::__('Tem certeza que deseja excluir este formulário?') ?>')">
                                    <input type="hidden" name="id" value="<?= $form->id ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <mc-icon name="delete"></mc-icon>
                                        <?= i::__('Excluir') ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
