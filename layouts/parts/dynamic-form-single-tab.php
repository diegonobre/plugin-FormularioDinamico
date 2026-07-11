<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 *
 * Exibe os valores do formulário dinâmico como uma aba na tela de
 * visualização (single) da entidade.
 *
 * Variáveis recebidas: $form (object), $entity (entidade)
 */

if (empty($form) || empty($form->campos) || empty($entity)) return;

$this->import('
    mc-container
    mc-tab
');

// só exibe a aba se algum campo tiver valor preenchido
$hasValue = false;
foreach ($form->campos as $campo) {
    $key = "{$form->slug}_{$campo->slug}";
    if (!empty($entity->getMetadata($key))) {
        $hasValue = true;
        break;
    }
}
if (!$hasValue) return;
?>
<mc-tab label="<?= htmlspecialchars($form->titulo, ENT_QUOTES) ?>" slug="formulario-<?= htmlspecialchars($form->slug, ENT_QUOTES) ?>">
    <mc-container>
        <main>
            <?php $this->part('dynamic-form-fields-display', [
                'form'   => $form,
                'entity' => $entity,
            ]); ?>
        </main>
    </mc-container>
</mc-tab>
