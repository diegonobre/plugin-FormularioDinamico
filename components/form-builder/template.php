<form method="POST" :action="urlSalvar" class="form-builder">
    <input type="hidden" name="id" :value="formId">
    <input type="hidden" name="entidade" :value="entidade">
    <input type="hidden" name="campos" :value="JSON.stringify(camposSerialized)">

    <!-- Metadados do formulário -->
    <div class="form-builder__meta">
        <div class="form-builder__field">
            <label><?php \MapasCulturais\i::_e('Título') ?> <span class="required">*</span></label>
            <input type="text" name="titulo" v-model="titulo" class="input-text" required>
        </div>
        <div class="form-builder__field">
            <label><?php \MapasCulturais\i::_e('Slug') ?> <span class="required">*</span></label>
            <input type="text" name="slug" v-model="slug" class="input-text" required>
        </div>
        <div class="form-builder__field">
            <label><?php \MapasCulturais\i::_e('Descrição') ?></label>
            <textarea name="descricao" v-model="descricao" class="input-text" rows="2"></textarea>
        </div>
        <div class="form-builder__field">
            <label><?php \MapasCulturais\i::_e('Entidade') ?></label>
            <input type="text" class="input-text" :value="entidadeLabel" readonly disabled>
        </div>
        <div class="form-builder__field">
            <label class="checkbox-label">
                <input type="checkbox" name="ativo" v-model="ativo" value="1">
                <?php \MapasCulturais\i::_e('Ativo') ?>
            </label>
        </div>
    </div>

    <!-- Layout principal: toolbox + canvas -->
    <div class="form-builder__layout">
        <!-- Toolbox -->
        <div class="form-builder__toolbox">
            <h4><?php \MapasCulturais\i::_e('Tipos de Campo') ?></h4>
            <p class="form-builder__hint"><?php \MapasCulturais\i::_e('Clique ou arraste para adicionar') ?></p>
            <div class="form-builder__toolbox-list">
                <div v-for="ft in fieldTypes"
                     :key="ft.type"
                     class="form-builder__toolbox-item"
                     @click="addField(ft.type)"
                     draggable="true"
                     @dragstart="onDragStart($event, ft.type)">
                    <span class="form-builder__toolbox-icon">{{ ft.label.charAt(0) }}</span>
                    <span>{{ ft.label }}</span>
                </div>
            </div>
        </div>

        <!-- Canvas -->
        <div class="form-builder__canvas"
             @dragover.prevent
             @drop="onDrop">
            <h4><?php \MapasCulturais\i::_e('Campos do Formulário') ?></h4>

            <!-- Campos nativos obrigatórios (bloqueados) -->
            <div v-if="camposNativos.length > 0" class="form-builder__nativos">
                <h5><?php \MapasCulturais\i::_e('Campos obrigatórios existentes') ?></h5>
                <div v-for="(campo, idx) in camposNativos"
                     :key="'nativo-' + idx"
                     class="form-builder__field-item form-builder__field-item--nativo">
                    <div class="form-builder__field-header">
                        <span class="form-builder__field-icon">&#x1f512;</span>
                        <span class="form-builder__field-label">{{ campo.rotulo }}</span>
                        <span class="form-builder__field-type">{{ campo.tipo }}</span>
                        <span class="form-builder__field-badge badge badge-secondary"><?php \MapasCulturais\i::_e('nativo') ?></span>
                    </div>
                </div>
            </div>

            <!-- Campos dinâmicos -->
            <div ref="sortableContainer" class="form-builder__campos" id="form-builder-sortable">
                <div v-for="(campo, idx) in campos"
                     :key="campo._uid"
                     class="form-builder__field-item"
                     :class="{'form-builder__field-item--editing': editingIndex === idx}"
                     draggable="true"
                     @dragstart="onFieldDragStart($event, idx)"
                     @dragover.prevent
                     @drop="onDropOnField($event, idx)">
                    <div class="form-builder__field-header">
                        <span class="form-builder__field-grip">&#x2630;</span>
                        <span class="form-builder__field-label">{{ campo.rotulo || '<?php \MapasCulturais\i::_e('Novo campo') ?>' }}</span>
                        <span class="form-builder__field-type">{{ campo.tipo }}</span>
                        <span v-if="campo.obrigatorio" class="form-builder__field-badge badge badge-danger"><?php \MapasCulturais\i::_e('obrigatório') ?></span>
                    </div>
                    <div class="form-builder__field-actions">
                        <button type="button" class="btn btn-sm btn-secondary" @click="editField(idx)">&#x270E;</button>
                        <button type="button" class="btn btn-sm btn-danger" @click="removeField(idx)">&#x2716;</button>
                    </div>
                    <div class="form-builder__field-span">
                        <small><?php \MapasCulturais\i::_e('Largura') ?>: {{ campo.coluna_span }}/12</small>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="campos.length === 0 && camposNativos.length === 0" class="form-builder__empty">
                <p><?php \MapasCulturais\i::_e('Arraste campos da toolbox ou clique em um tipo para começar.') ?></p>
            </div>
        </div>
    </div>

    <!-- Editor de campo (modal) -->
    <form-field-editor
        v-if="editingIndex !== null"
        :campo="campos[editingIndex]"
        @save="saveField"
        @cancel="editingIndex = null"
    ></form-field-editor>

    <!-- Ações -->
    <div class="form-builder__actions">
        <button type="submit" class="btn btn-primary">
            <?php \MapasCulturais\i::_e('Salvar Formulário') ?>
        </button>
    </div>
</form>
