<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 *
 * Relatório dos dados coletados por um formulário dinâmico.
 * Variáveis: $formulario (object), $columns (array slug => rotulo), $rows (array)
 */

use MapasCulturais\i;

$this->import('
    mc-icon
');

$isOpportunity = $formulario->entidade === 'opportunity';
?>
<div class="entity-list fd-report">
    <header class="entity-list__header">
        <div>
            <h1><?= i::__('Relatório') ?> — <?= htmlspecialchars($formulario->titulo) ?></h1>
            <p class="entity-form__help">
                <?= sprintf(i::__('%d registro(s) coletado(s)'), count($rows)) ?>
            </p>
        </div>
        <div class="entity-list__actions">
            <a class="button button--outline button--icon" href="<?= $app->createUrl('formulario-dinamico') ?>">
                <mc-icon name="arrowBack"></mc-icon>
                <?= i::__('Voltar') ?>
            </a>
            <?php if (!empty($rows)): ?>
                <a class="button button--secondary button--icon"
                   href="<?= $app->createUrl('formulario-dinamico', 'exportar', ['id' => $formulario->id, 'formato' => 'csv']) ?>">
                    <mc-icon name="download"></mc-icon>
                    <?= i::__('Exportar CSV') ?>
                </a>
                <a class="button button--primary button--icon"
                   href="<?= $app->createUrl('formulario-dinamico', 'exportar', ['id' => $formulario->id, 'formato' => 'xlsx']) ?>">
                    <mc-icon name="download"></mc-icon>
                    <?= i::__('Exportar Excel') ?>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <?php if (empty($rows)): ?>
        <div class="entity-list__empty">
            <p><?= i::__('Nenhum dado coletado por este formulário ainda.') ?></p>
            <?php if ($isOpportunity): ?>
                <p><?= i::__('Os dados aparecem aqui conforme as inscrições das oportunidades vinculadas são preenchidas.') ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="entity-list__table fd-report__table">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?= i::__('ID') ?></th>
                        <th><?= $isOpportunity ? i::__('Inscrição') : i::__('Nome') ?></th>
                        <?php if ($isOpportunity): ?>
                            <th><?= i::__('Oportunidade') ?></th>
                        <?php endif; ?>
                        <th><?= i::__('Status') ?></th>
                        <th><?= i::__('Data') ?></th>
                        <?php foreach ($columns as $rotulo): ?>
                            <th><?= htmlspecialchars($rotulo) ?></th>
                        <?php endforeach; ?>
                        <th><?= i::__('Ações') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= (int)$row['_id'] ?></td>
                            <td><?= htmlspecialchars($row['_label']) ?></td>
                            <?php if ($isOpportunity): ?>
                                <td><?= htmlspecialchars($row['_opportunity'] ?? '') ?></td>
                            <?php endif; ?>
                            <td><?= htmlspecialchars($row['_status_label']) ?></td>
                            <td><?= htmlspecialchars($row['_date'] ? date('d/m/Y H:i', strtotime($row['_date'])) : '') ?></td>
                            <?php foreach (array_keys($columns) as $slug): ?>
                                <td class="fd-report__value"><?= nl2br(htmlspecialchars($row['values'][$slug] ?? '')) ?></td>
                            <?php endforeach; ?>
                            <td>
                                <a class="button button--primary-outline button--sm button--icon" target="_blank"
                                   title="<?= i::__('Abrir versão para impressão/PDF') ?>"
                                   href="<?= $app->createUrl('formulario-dinamico', 'relatorioItem', ['id' => $formulario->id, 'item' => $row['_id']]) ?>">
                                    <mc-icon name="download"></mc-icon>
                                    PDF
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.fd-report__table {
    overflow-x: auto;
}

.fd-report__table table {
    min-width: 100%;
}

.fd-report__table th,
.fd-report__table td {
    white-space: nowrap;
}

.fd-report__value {
    max-width: 28rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap !important;
}
</style>
