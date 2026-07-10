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
$oportunidades = $oportunidades ?? [];
$linkedMap = $linkedMap ?? [];
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

    <div class="entity-list__table" style="margin-top:1rem;">
        <?php if (empty($oportunidades)): ?>
            <p><?= i::__('Nenhuma oportunidade cadastrada.') ?></p>
        <?php else: ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th style="width:60px"><?= i::__('ID') ?></th>
                        <th><?= i::__('Oportunidade') ?></th>
                        <th style="width:120px"><?= i::__('Ações') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($oportunidades as $op): ?>
                        <?php
                        $isLinked = isset($linkedMap[(int)$op['id']]);
                        $opId = (int)$op['id'];
                        $opName = htmlspecialchars($op['name'] ?? '', ENT_QUOTES);
                        ?>
                        <tr>
                            <td><?= $opId ?></td>
                            <td><?= $opName ?></td>
                            <td>
                                <?php if ($isLinked): ?>
                                    <form method="POST"
                                          action="<?= $app->createUrl('formulario-dinamico', 'removerOportunidade') ?>"
                                          style="display:inline"
                                          onsubmit="return confirm('<?= i::__('Remover vínculo?') ?>')">
                                        <input type="hidden" name="id" value="<?= $opId ?>">
                                        <input type="hidden" name="formulario_id" value="<?= (int)$formulario['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <mc-icon name="delete"></mc-icon>
                                            <?= i::__('Remover') ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-primary btn-sm"
                                            onclick="showConfirmModal('<?= $opName ?>', <?= $opId ?>)">
                                        <mc-icon name="add"></mc-icon>
                                        <?= i::__('Vincular') ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de confirmação -->
<div id="confirm-modal" class="modal-overlay" style="display:none;">
    <div class="modal-card">
        <div class="modal-header">
            <h3><?= i::__('Confirmar vínculo') ?></h3>
        </div>
        <div class="modal-body">
            <p id="confirm-modal-message"></p>
        </div>
        <div class="modal-footer">
            <form id="confirm-modal-form" method="POST"
                  action="<?= $app->createUrl('formulario-dinamico', 'associarOportunidade') ?>">
                <input type="hidden" name="formulario_id" value="<?= (int)$formulario['id'] ?>">
                <input type="hidden" name="oportunidade_id" id="confirm-modal-opid" value="">
                <button type="button" class="btn btn-secondary" onclick="hideConfirmModal()">
                    <?= i::__('Cancelar') ?>
                </button>
                <button type="submit" class="btn btn-primary">
                    <?= i::__('Confirmar') ?>
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.modal-overlay {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5); z-index: 9999;
    display: flex; align-items: center; justify-content: center;
}
.modal-card {
    background: #fff; border-radius: 8px; padding: 1.5rem;
    min-width: 400px; max-width: 500px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.modal-header h3 { margin: 0 0 0.5rem 0; }
.modal-body { margin: 1rem 0; }
.modal-footer { display: flex; gap: 0.5rem; justify-content: flex-end; }
.modal-footer form { display: flex; gap: 0.5rem; }
</style>

<script>
function showConfirmModal(opportunityName, opportunityId) {
    document.getElementById('confirm-modal-message').textContent =
        '<?= i::__('Tem certeza que deseja vincular o formulário à oportunidade') ?> "' + opportunityName + '"?';
    document.getElementById('confirm-modal-opid').value = opportunityId;
    document.getElementById('confirm-modal').style.display = 'flex';
}
function hideConfirmModal() {
    document.getElementById('confirm-modal').style.display = 'none';
}
// Fecha ao clicar fora do modal
document.getElementById('confirm-modal').addEventListener('click', function(e) {
    if (e.target === this) hideConfirmModal();
});
</script>
