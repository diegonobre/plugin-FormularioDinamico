<div class="form-field-editor">
    <div class="form-field-editor__overlay" @click="$emit('cancel')"></div>
    <div class="form-field-editor__modal">
        <div class="form-field-editor__header">
            <h3><?php \MapasCulturais\i::_e('Editar Campo') ?></h3>
            <button type="button" class="btn btn-sm btn-secondary" @click="$emit('cancel')">&#x2716;</button>
        </div>

        <div class="form-field-editor__body">
            <div class="form-field-editor__field">
                <label><?php \MapasCulturais\i::_e('Rótulo') ?></label>
                <input type="text" v-model="edit.rotulo" class="input-text"
                       @input="autoSlug">
            </div>

            <div class="form-field-editor__field">
                <label><?php \MapasCulturais\i::_e('Slug') ?></label>
                <input type="text" v-model="edit.slug" class="input-text">
            </div>

            <div class="form-field-editor__field">
                <label><?php \MapasCulturais\i::_e('Placeholder') ?></label>
                <input type="text" v-model="edit.placeholder" class="input-text">
            </div>

            <div class="form-field-editor__field">
                <label><?php \MapasCulturais\i::_e('Tipo') ?></label>
                <input type="text" :value="edit.tipo" class="input-text" disabled>
            </div>

            <!-- Opções para select/multiselect/gender -->
            <div v-if="hasOptions" class="form-field-editor__field">
                <label><?php \MapasCulturais\i::_e('Opções') ?></label>
                <div class="form-field-editor__options">
                    <div v-for="(opt, idx) in edit.opcoes" :key="idx" class="form-field-editor__option">
                        <input type="text" v-model="edit.opcoes[idx]" class="input-text" style="flex:1">
                        <button type="button" class="btn btn-sm btn-danger" @click="removeOption(idx)">&#x2716;</button>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" @click="addOption">
                        + <?php \MapasCulturais\i::_e('Adicionar opção') ?>
                    </button>
                </div>
            </div>

            <div class="form-field-editor__field">
                <label class="checkbox-label">
                    <input type="checkbox" v-model="edit.obrigatorio">
                    <?php \MapasCulturais\i::_e('Obrigatório') ?>
                </label>
            </div>

            <div class="form-field-editor__field">
                <label><?php \MapasCulturais\i::_e('Largura (colunas)') ?></label>
                <select v-model.number="edit.coluna_span" class="input-select">
                    <option v-for="n in 12" :key="n" :value="n">{{ n }}/12</option>
                </select>
            </div>
        </div>

        <div class="form-field-editor__actions">
            <button type="button" class="btn btn-secondary" @click="$emit('cancel')">
                <?php \MapasCulturais\i::_e('Cancelar') ?>
            </button>
            <button type="button" class="btn btn-primary" @click="save">
                <?php \MapasCulturais\i::_e('Salvar') ?>
            </button>
        </div>
    </div>
</div>
