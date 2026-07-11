<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 *
 * Injeta o formulário dinâmico publicado como uma aba na tela de edição
 * da entidade (agent/space/event), usando os componentes do design system.
 * Os valores são salvos pelo fluxo padrão da entidade (entity-actions),
 * pois os campos são metadados registrados.
 *
 * Variáveis recebidas: $form (object)
 */

use MapasCulturais\i;

if (empty($form) || empty($form->campos)) return;

$this->import('
    entity-field
    mc-card
    mc-container
    mc-tab
');

// agrupa campos preservando a ordem
$grupos = [];
foreach ($form->campos as $campo) {
    $gid = (int)($campo->grupo_id ?? 0);
    if (!isset($grupos[$gid])) {
        $grupos[$gid] = [
            'titulo'  => trim((string)($campo->grupo_titulo ?? '')) ?: i::__('Geral'),
            'colunas' => min(max((int)($campo->grupo_colunas ?? 1), 1), 4),
            'campos'  => [],
        ];
    }
    $grupos[$gid]['campos'][] = $campo;
}
?>
<mc-tab label="<?= htmlspecialchars($form->titulo, ENT_QUOTES) ?>" slug="formulario-<?= htmlspecialchars($form->slug, ENT_QUOTES) ?>">
    <mc-container>
        <main>
            <?php if (!empty($form->descricao)): ?>
                <p class="dynamic-form-tab__desc"><?= htmlspecialchars($form->descricao) ?></p>
            <?php endif; ?>

            <?php foreach ($grupos as $grupo): ?>
                <mc-card>
                    <template #title>
                        <label><?= htmlspecialchars($grupo['titulo']) ?></label>
                    </template>
                    <template #content>
                        <?php $total = $grupo['colunas'] * 12; ?>
                        <div class="dynamic-form-tab__grid" style="display:grid;grid-template-columns:repeat(<?= $total ?>,1fr);column-gap:1.5rem;">
                            <?php foreach ($grupo['campos'] as $campo):
                                $key = "{$form->slug}_{$campo->slug}";
                                $span = min(max((int)($campo->coluna_span ?: 12), 1), $total);
                            ?>
                                <div style="grid-column: span <?= $span ?>; min-width:0;">
                                    <entity-field :entity="entity" prop="<?= htmlspecialchars($key, ENT_QUOTES) ?>"></entity-field>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </template>
                </mc-card>
            <?php endforeach; ?>
        </main>
    </mc-container>
</mc-tab>

<style>
.dynamic-form-tab__desc {
    color: #666;
    margin: 0 0 1rem;
}

/* em telas estreitas cada campo ocupa a linha inteira */
@media (max-width: 800px) {
    .dynamic-form-tab__grid > * {
        grid-column: 1 / -1 !important;
    }
}
</style>
