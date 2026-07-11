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
            'titulo' => trim((string)($campo->grupo_titulo ?? '')) ?: i::__('Geral'),
            'campos' => [],
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
                        <div class="grid-12">
                            <?php foreach ($grupo['campos'] as $campo):
                                $key = "{$form->slug}_{$campo->slug}";
                                $span = min(max((int)($campo->coluna_span ?: 12), 1), 12);
                                $classes = $span >= 12 ? 'col-12' : "col-{$span} sm:col-12";
                            ?>
                                <entity-field :entity="entity" classes="<?= $classes ?>" prop="<?= htmlspecialchars($key, ENT_QUOTES) ?>"></entity-field>
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
</style>
