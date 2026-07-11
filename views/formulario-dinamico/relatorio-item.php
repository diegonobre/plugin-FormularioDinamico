<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 *
 * Visão individual imprimível de um registro coletado pelo formulário.
 * O botão "Baixar PDF" abre o diálogo de impressão do navegador
 * (destino "Salvar como PDF").
 *
 * Variáveis: $formulario (object), $columns (array slug => rotulo), $row (array)
 */

use MapasCulturais\i;

$this->import('
    mc-icon
');

$isOpportunity = $formulario->entidade === 'opportunity';
?>
<div class="fd-report-item">
    <div class="fd-report-item__actions fd-no-print">
        <a class="button button--outline button--icon"
           href="<?= $app->createUrl('formulario-dinamico', 'relatorio', ['id' => $formulario->id]) ?>">
            <mc-icon name="arrowBack"></mc-icon>
            <?= i::__('Voltar ao relatório') ?>
        </a>
        <button type="button" class="button button--primary button--icon" onclick="window.print()">
            <mc-icon name="download"></mc-icon>
            <?= i::__('Baixar PDF') ?>
        </button>
    </div>

    <header class="fd-report-item__header">
        <h1><?= htmlspecialchars($formulario->titulo) ?></h1>
        <?php if (!empty($formulario->descricao)): ?>
            <p><?= htmlspecialchars($formulario->descricao) ?></p>
        <?php endif; ?>
    </header>

    <dl class="fd-report-item__meta">
        <div>
            <dt><?= i::__('ID') ?></dt>
            <dd><?= (int)$row['_id'] ?></dd>
        </div>
        <div>
            <dt><?= $isOpportunity ? i::__('Inscrição') : i::__('Nome') ?></dt>
            <dd><?= htmlspecialchars($row['_label']) ?></dd>
        </div>
        <?php if ($isOpportunity && !empty($row['_opportunity'])): ?>
            <div>
                <dt><?= i::__('Oportunidade') ?></dt>
                <dd><?= htmlspecialchars($row['_opportunity']) ?></dd>
            </div>
        <?php endif; ?>
        <div>
            <dt><?= i::__('Status') ?></dt>
            <dd><?= htmlspecialchars($row['_status_label']) ?></dd>
        </div>
        <div>
            <dt><?= i::__('Data') ?></dt>
            <dd><?= htmlspecialchars($row['_date'] ? date('d/m/Y H:i', strtotime($row['_date'])) : '') ?></dd>
        </div>
    </dl>

    <div class="fd-report-item__fields">
        <?php foreach ($columns as $slug => $rotulo): ?>
            <div class="fd-report-item__field">
                <dt><?= htmlspecialchars($rotulo) ?></dt>
                <dd><?= ($row['values'][$slug] ?? '') !== '' ? nl2br(htmlspecialchars($row['values'][$slug])) : '<em>' . i::__('Não preenchido') . '</em>' ?></dd>
            </div>
        <?php endforeach; ?>
    </div>

    <footer class="fd-report-item__footer">
        <?= sprintf(i::__('Gerado em %s'), date('d/m/Y H:i')) ?>
    </footer>
</div>

<style>
.fd-report-item {
    max-width: 860px;
    margin: 0 auto;
    padding: 1rem;
}

.fd-report-item__actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    margin-bottom: 1.5rem;
}

.fd-report-item__header h1 {
    margin: 0 0 0.25rem;
}

.fd-report-item__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem 2.5rem;
    margin: 1.5rem 0;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 6px;
}

.fd-report-item__meta dt,
.fd-report-item__field dt {
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    color: #555;
    margin-bottom: 0.15rem;
}

.fd-report-item__meta dd,
.fd-report-item__field dd {
    margin: 0;
}

.fd-report-item__field {
    padding: 0.75rem 0;
    border-bottom: 1px solid #eee;
    break-inside: avoid;
}

.fd-report-item__footer {
    margin-top: 2rem;
    color: #888;
    font-size: 0.8rem;
}

@media print {
    .fd-no-print,
    .main-header,
    .main-footer,
    header.header,
    nav,
    footer:not(.fd-report-item__footer) {
        display: none !important;
    }

    body {
        background: #fff !important;
    }

    .fd-report-item {
        max-width: 100%;
        padding: 0;
    }
}
</style>
