<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 *
 * Vincular formulário a oportunidades específicas
 */

use MapasCulturais\i;

$this->import('
    fd-confirm-action
    mc-icon
');

$formulario = $formulario ?? [];
$oportunidades = $oportunidades ?? [];
$linkedMap = $linkedMap ?? [];
$formId = (int)($formulario['id'] ?? 0);
?>
<div class="entity-form">
    <header class="entity-form__header">
        <h1><?= i::__('Vincular Oportunidades') ?></h1>
        <h2><?= htmlspecialchars($formulario['titulo'] ?? '') ?></h2>
        <a class="button button--outline button--icon" href="<?= $app->createUrl('formulario-dinamico') ?>">
            <mc-icon name="arrowBack"></mc-icon>
            <?= i::__('Voltar') ?>
        </a>
    </header>

    <p class="entity-form__help">
        <?= i::__('Cada oportunidade pode ter apenas um formulário vinculado. Os campos do formulário passam a fazer parte do formulário de inscrição da oportunidade.') ?>
    </p>

    <div class="entity-list__table" style="margin-top:1rem;">
        <?php if (empty($oportunidades)): ?>
            <p><?= i::__('Nenhuma oportunidade cadastrada.') ?></p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th style="width:60px"><?= i::__('ID') ?></th>
                        <th><?= i::__('Oportunidade') ?></th>
                        <th style="width:160px"><?= i::__('Ações') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($oportunidades as $op): ?>
                        <?php
                        $isLinked = isset($linkedMap[(int)$op['id']]);
                        $opId = (int)$op['id'];
                        $opName = $op['name'] ?? '';
                        ?>
                        <tr>
                            <td><?= $opId ?></td>
                            <td><?= htmlspecialchars($opName) ?></td>
                            <td>
                                <?php if ($isLinked): ?>
                                    <fd-confirm-action
                                        action="<?= $app->createUrl('formulario-dinamico', 'removerOportunidade') ?>"
                                        :fields='{"id": <?= $opId ?>, "formulario_id": <?= $formId ?>}'
                                        title="<?= htmlspecialchars(i::__('Remover vínculo'), ENT_QUOTES) ?>"
                                        message="<?= htmlspecialchars(sprintf(i::__('Remover o vínculo do formulário com a oportunidade "%s"? Os campos deixarão de aparecer no formulário de inscrição.'), $opName), ENT_QUOTES) ?>"
                                        yes="<?= htmlspecialchars(i::__('Remover'), ENT_QUOTES) ?>"
                                        no="<?= htmlspecialchars(i::__('Cancelar'), ENT_QUOTES) ?>"
                                        icon="delete"
                                        label="<?= htmlspecialchars(i::__('Remover'), ENT_QUOTES) ?>"
                                        button-class="button--text-danger"
                                    ></fd-confirm-action>
                                <?php else: ?>
                                    <fd-confirm-action
                                        action="<?= $app->createUrl('formulario-dinamico', 'associarOportunidade') ?>"
                                        :fields='{"formulario_id": <?= $formId ?>, "oportunidade_id": <?= $opId ?>}'
                                        title="<?= htmlspecialchars(i::__('Confirmar vínculo'), ENT_QUOTES) ?>"
                                        message="<?= htmlspecialchars(sprintf(i::__('Vincular o formulário à oportunidade "%s"? Os campos serão adicionados ao formulário de inscrição e um vínculo anterior, se existir, será substituído.'), $opName), ENT_QUOTES) ?>"
                                        yes="<?= htmlspecialchars(i::__('Vincular'), ENT_QUOTES) ?>"
                                        no="<?= htmlspecialchars(i::__('Cancelar'), ENT_QUOTES) ?>"
                                        icon="add"
                                        label="<?= htmlspecialchars(i::__('Vincular'), ENT_QUOTES) ?>"
                                        button-class="button--primary"
                                    ></fd-confirm-action>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
