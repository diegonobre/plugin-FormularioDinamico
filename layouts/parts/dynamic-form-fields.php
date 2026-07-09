<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 *
 * Renderiza os campos dinâmicos de um formulário.
 * Variáveis recebidas: $form (object), $entity (object)
 */

use MapasCulturais\i;

if (empty($form) || empty($form->campos)) return;

$entity = $entity ?? null;
?>
<div class="dynamic-form-fields">
    <h3><?= htmlspecialchars($form->titulo) ?></h3>
    <?php if (!empty($form->descricao)): ?>
        <p class="dynamic-form-fields__desc"><?= htmlspecialchars($form->descricao) ?></p>
    <?php endif; ?>

    <div class="dynamic-form-fields__grid">
        <?php foreach ($form->campos as $campo):
            $key = "{$form->slug}_{$campo->slug}";
            $value = $entity ? $entity->getMetadata($key) : null;
            $span = (int)($campo->coluna_span ?: 12);
        ?>
            <div class="dynamic-form-fields__field" style="grid-column: span <?= min($span, 12) ?>;">
                <label for="field-<?= $key ?>">
                    <?= htmlspecialchars($campo->rotulo) ?>
                    <?php if ($campo->obrigatorio): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>

                <?php switch ($campo->tipo):
                    case 'textarea': ?>
                        <textarea id="field-<?= $key ?>"
                                  name="<?= $key ?>"
                                  placeholder="<?= htmlspecialchars($campo->placeholder ?? '') ?>"
                                  <?= $campo->obrigatorio ? 'required' : '' ?>
                                  class="input-text"><?= htmlspecialchars($value ?? '') ?></textarea>
                    <?php break; ?>

                    <?php case 'select':
                    case 'gender': ?>
                        <select id="field-<?= $key ?>"
                                name="<?= $key ?>"
                                <?= $campo->obrigatorio ? 'required' : '' ?>
                                class="input-select">
                            <option value=""><?= i::__('Selecione...') ?></option>
                            <?php foreach (($campo->opcoes ?: []) as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>"
                                    <?= $value == $opt ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($opt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php break; ?>

                    <?php case 'multiselect': ?>
                        <select id="field-<?= $key ?>"
                                name="<?= $key ?>[]"
                                multiple
                                <?= $campo->obrigatorio ? 'required' : '' ?>
                                class="input-select">
                            <?php foreach (($campo->opcoes ?: []) as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>"
                                    <?= (is_array($value) && in_array($opt, $value)) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($opt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php break; ?>

                    <?php case 'date': ?>
                        <input type="date" id="field-<?= $key ?>"
                               name="<?= $key ?>"
                               value="<?= htmlspecialchars($value ?? '') ?>"
                               placeholder="<?= htmlspecialchars($campo->placeholder ?? '') ?>"
                               <?= $campo->obrigatorio ? 'required' : '' ?>
                               class="input-text">
                    <?php break; ?>

                    <?php case 'datetime': ?>
                        <input type="datetime-local" id="field-<?= $key ?>"
                               name="<?= $key ?>"
                               value="<?= htmlspecialchars($value ?? '') ?>"
                               placeholder="<?= htmlspecialchars($campo->placeholder ?? '') ?>"
                               <?= $campo->obrigatorio ? 'required' : '' ?>
                               class="input-text">
                    <?php break; ?>

                    <?php case 'cpf': ?>
                        <input type="text" id="field-<?= $key ?>"
                               name="<?= $key ?>"
                               value="<?= htmlspecialchars($value ?? '') ?>"
                               placeholder="<?= htmlspecialchars($campo->placeholder ?? '') ?>"
                               <?= $campo->obrigatorio ? 'required' : '' ?>
                               class="input-text mask-cpf"
                               maxlength="14">
                    <?php break; ?>

                    <?php case 'cnpj': ?>
                        <input type="text" id="field-<?= $key ?>"
                               name="<?= $key ?>"
                               value="<?= htmlspecialchars($value ?? '') ?>"
                               placeholder="<?= htmlspecialchars($campo->placeholder ?? '') ?>"
                               <?= $campo->obrigatorio ? 'required' : '' ?>
                               class="input-text mask-cnpj"
                               maxlength="18">
                    <?php break; ?>

                    <?php case 'phone': ?>
                        <input type="tel" id="field-<?= $key ?>"
                               name="<?= $key ?>"
                               value="<?= htmlspecialchars($value ?? '') ?>"
                               placeholder="<?= htmlspecialchars($campo->placeholder ?? '') ?>"
                               <?= $campo->obrigatorio ? 'required' : '' ?>
                               class="input-text mask-phone"
                               maxlength="15">
                    <?php break; ?>

                    <?php case 'cep': ?>
                        <input type="text" id="field-<?= $key ?>"
                               name="<?= $key ?>"
                               value="<?= htmlspecialchars($value ?? '') ?>"
                               placeholder="<?= htmlspecialchars($campo->placeholder ?? '') ?>"
                               <?= $campo->obrigatorio ? 'required' : '' ?>
                               class="input-text mask-cep"
                               maxlength="9">
                    <?php break; ?>

                    <?php default: // text, email, url, number ?>
                        <input type="<?= $campo->tipo === 'number' ? 'number' : ($campo->tipo === 'email' ? 'email' : ($campo->tipo === 'url' ? 'url' : 'text')) ?>"
                               id="field-<?= $key ?>"
                               name="<?= $key ?>"
                               value="<?= htmlspecialchars($value ?? '') ?>"
                               placeholder="<?= htmlspecialchars($campo->placeholder ?? '') ?>"
                               <?= $campo->obrigatorio ? 'required' : '' ?>
                               class="input-text">
                <?php endswitch; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.dynamic-form-fields__grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 1rem;
}
.dynamic-form-fields__field {
    min-width: 0;
}
.dynamic-form-fields__desc {
    color: #666;
    margin-bottom: 1rem;
}
</style>
