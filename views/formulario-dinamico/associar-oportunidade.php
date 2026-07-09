<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 *
 * Vincular formulário a oportunidades específicas
 */

use MapasCulturais\i;

$this->import('
    mc-icon
    mc-modal
');

$formulario = $formulario ?? [];
$vinculos = $vinculos ?? [];
?>
<div class="entity-form">
    <header class="entity-form__header">
        <h1><?= i::__('Vincular Oportunidades') ?></h1>
        <h2><?= htmlspecialchars($formulario['titulo'] ?? '') ?></h2>
        <a class="btn btn-secondary" href="<?= $app->createUrl('formulario-dinamico') ?>">
            <mc-icon name="arrowBack"></mc-icon>
            <?= i::__('Voltar') ?>
        </a>
    </header>

    <!-- Formulário para adicionar vínculo -->
    <form method="POST" action="<?= $app->createUrl('formulario-dinamico', 'associar-oportunidade') ?>"
          class="entity-form__form">
        <input type="hidden" name="formulario_id" value="<?= (int)$formulario['id'] ?>">

        <div class="entity-form__field">
            <label for="oportunidade_id"><?= i::__('Adicionar Oportunidade') ?></label>
            <div style="display:flex; gap:0.5rem; align-items:center;">
                <input type="text" id="oportunidade_search"
                       placeholder="<?= i::__('Digite o nome da oportunidade...') ?>"
                       class="input-text" style="flex:1"
                       oninput="buscarOportunidades(this.value)">
                <select id="oportunidade_id" name="oportunidade_id" required class="input-select" style="flex:1;display:none;">
                    <option value=""><?= i::__('Selecione...') ?></option>
                </select>
                <button type="submit" class="btn btn-primary">
                    <mc-icon name="add"></mc-icon>
                    <?= i::__('Vincular') ?>
                </button>
            </div>
        </div>
    </form>

    <!-- Lista de oportunidades vinculadas -->
    <div class="entity-list__table" style="margin-top:2rem;">
        <h3><?= i::__('Oportunidades Vinculadas') ?></h3>
        <?php if (empty($vinculos)): ?>
            <p><?= i::__('Nenhuma oportunidade vinculada ainda. Quando não há vínculos, o formulário é exibido em todas as oportunidades.') ?></p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?= i::__('ID') ?></th>
                        <th><?= i::__('Oportunidade') ?></th>
                        <th><?= i::__('Ações') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vinculos as $v): ?>
                        <tr>
                            <td><?= (int)$v['oportunidade_id'] ?></td>
                            <td><?= htmlspecialchars($v['oportunidade_nome'] ?? '') ?></td>
                            <td>
                                <form method="POST"
                                      action="<?= $app->createUrl('formulario-dinamico', 'remover-oportunidade') ?>"
                                      style="display:inline"
                                      onsubmit="return confirm('<?= i::__('Remover vínculo?') ?>')">
                                    <input type="hidden" name="id" value="<?= (int)$v['vinculo_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <mc-icon name="delete"></mc-icon>
                                        <?= i::__('Remover') ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function buscarOportunidades(query) {
    const select = document.getElementById('oportunidade_id');
    if (query.length < 3) {
        select.style.display = 'none';
        return;
    }
    select.style.display = 'block';
    select.innerHTML = '<option value=""><?= i::__('Buscando...') ?></option>';

    fetch('<?= $app->createUrl('oportunidade', 'find') ?>?q=' + encodeURIComponent(query))
        .then(r => r.json())
        .then(data => {
            select.innerHTML = '<option value=""><?= i::__('Selecione...') ?></option>';
            (data || []).forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = item.name + ' (#' + item.id + ')';
                select.appendChild(opt);
            });
        })
        .catch(() => {
            select.innerHTML = '<option value=""><?= i::__('Erro ao buscar') ?></option>';
        });
}
</script>
