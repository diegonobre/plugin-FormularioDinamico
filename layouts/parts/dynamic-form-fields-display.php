<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 *
 * Exibe os valores dos campos dinâmicos no single da entidade.
 * Variáveis recebidas: $form (object), $entity (object)
 */

if (empty($form) || empty($form->campos)) return;

$entity = $entity ?? null;
?>
<div class="dynamic-form-fields-display">
    <h3><?= htmlspecialchars($form->titulo) ?></h3>

    <dl class="dynamic-form-fields-display__list">
        <?php foreach ($form->campos as $campo):
            $key = "{$form->slug}_{$campo->slug}";
            $value = $entity ? $entity->getMetadata($key) : null;

            if (empty($value)) continue;
        ?>
            <div class="dynamic-form-fields-display__item">
                <dt><?= htmlspecialchars($campo->rotulo) ?></dt>
                <dd>
                    <?php if (in_array($campo->tipo, ['multiselect']) && is_array($value)): ?>
                        <ul class="dynamic-form-fields-display__list-value">
                            <?php foreach ($value as $v): ?>
                                <li><?= htmlspecialchars($v) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php elseif ($campo->tipo === 'url'): ?>
                        <a href="<?= htmlspecialchars($value) ?>" target="_blank" rel="noopener">
                            <?= htmlspecialchars($value) ?>
                        </a>
                    <?php elseif ($campo->tipo === 'email'): ?>
                        <a href="mailto:<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></a>
                    <?php else: ?>
                        <?= nl2br(htmlspecialchars($value)) ?>
                    <?php endif; ?>
                </dd>
            </div>
        <?php endforeach; ?>
    </dl>
</div>

<style>
.dynamic-form-fields-display__list {
    display: grid;
    gap: 1rem;
}
.dynamic-form-fields-display__item dt {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.25rem;
}
.dynamic-form-fields-display__item dd {
    margin: 0;
    color: #555;
}
.dynamic-form-fields-display__list-value {
    margin: 0;
    padding-left: 1.25rem;
}
</style>
