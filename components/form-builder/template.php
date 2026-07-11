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
        <div class="form-builder__field" style="grid-column:span 2">
            <label><?php \MapasCulturais\i::_e('Descrição') ?></label>
            <textarea name="descricao" v-model="descricao" class="input-text" rows="2"></textarea>
        </div>
        <div class="form-builder__field">
            <label><?php \MapasCulturais\i::_e('Entidade') ?></label>
            <input type="text" class="input-text" :value="entidadeLabel" readonly disabled>
        </div>
        <div class="form-builder__field" style="display:flex;align-items:center;gap:1rem;">
            <label class="checkbox-label">
                <input type="checkbox" name="ativo" v-model="ativo" value="1">
                <?php \MapasCulturais\i::_e('Ativo') ?>
            </label>
            <span v-if="isDraft" class="badge badge-secondary"><?php \MapasCulturais\i::_e('Rascunho') ?></span>
            <span v-else class="badge badge-success"><?php \MapasCulturais\i::_e('Publicado') ?></span>
        </div>
    </div>

    <!-- Layout principal -->
    <div class="form-builder__layout">
        <!-- Toolbox -->
        <div class="form-builder__toolbox">
            <h4><?php \MapasCulturais\i::_e('Grupos') ?></h4>
            <div class="form-builder__toolbox-groups">
                <div v-for="(g, gidx) in grupos" :key="g.id"
                     class="form-builder__toolbox-group"
                     :class="{'form-builder__toolbox-group--active': activeGroupId === g.id}"
                     @click="selectGroup(g.id)">
                    <span>{{ g.titulo }}</span>
                    <span class="badge badge-secondary">{{ countCamposInGroup(g.id) }}</span>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-secondary" @click="addGroup" style="margin-top:0.5rem;width:100%;">
                + <?php \MapasCulturais\i::_e('Grupo') ?>
            </button>

            <hr style="margin:0.75rem 0">
            <h4><?php \MapasCulturais\i::_e('Campos Disponíveis') ?></h4>
            <p class="form-builder__hint"><?php \MapasCulturais\i::_e('Clique ou arraste para adicionar') ?></p>
            <div class="form-builder__toolbox-list">
                <div v-for="ft in fieldTypes" :key="ft.type"
                     class="form-builder__toolbox-item"
                     @click="addFieldToActiveGroup(ft.type)">
                    <span class="form-builder__toolbox-icon">{{ ft.label.charAt(0) }}</span>
                    <span>{{ ft.label }}</span>
                </div>
            </div>
        </div>

        <!-- Canvas -->
        <div class="form-builder__canvas">
            <!-- Campos nativos obrigatórios -->
            <div v-if="camposNativos.length > 0" class="form-builder__nativos">
                <h5><?php \MapasCulturais\i::_e('Campos obrigatórios existentes (não removíveis)') ?></h5>
                <div v-for="(campo, idx) in camposNativos" :key="'nativo-'+idx"
                     class="form-builder__field-item form-builder__field-item--nativo">
                    <div class="form-builder__field-header">
                        <span class="form-builder__field-icon">&#x1f512;</span>
                        <span class="form-builder__field-label">{{ campo.rotulo }}</span>
                        <span class="form-builder__field-type">{{ campo.tipo }}</span>
                        <span class="form-builder__field-badge badge badge-secondary">nativo</span>
                    </div>
                </div>
            </div>

            <!-- Grupos -->
            <div v-for="(g, gidx) in grupos" :key="g.id" class="form-builder__grupo"
                 :class="{'form-builder__grupo--active': activeGroupId === g.id}"
                 @click="activeGroupId = g.id" :data-gid="g.id">

                <div class="form-builder__grupo-header">
                    <div class="form-builder__grupo-title">
                        <input type="text" v-model="g.titulo" class="input-text"
                               :placeholder="'<?php \MapasCulturais\i::_e('Nome do grupo') ?>'"
                               @click.stop>
                    </div>
                    <div class="form-builder__grupo-controls">
                        <label style="font-size:0.8rem;color:#666;">
                            <?php \MapasCulturais\i::_e('Colunas') ?>:
                            <select v-model.number="g.colunas" class="input-select" style="width:auto;display:inline-block;padding:0.15rem 0.4rem;font-size:0.8rem;" @click.stop>
                                <option v-for="n in 4" :key="n" :value="n">{{ n }}</option>
                            </select>
                        </label>
                        <button type="button" class="btn btn-sm btn-danger" @click.stop="removeGroup(gidx)"
                                v-if="grupos.length > 1">&#x2716;</button>
                    </div>
                </div>

                <div class="form-builder__grupo-fields" :style="grupoGridStyle(g)">
                    <div v-for="(campo, idx) in camposInGroup(g.id)" :key="campo._uid"
                         :data-uid="campo._uid"
                         class="form-builder__field-item"
                         :class="{'form-builder__field-item--editing': editingIndex === campo._uid}"
                         :style="fieldSpanStyle(campo, g)">
                        <div class="form-builder__field-header">
                            <span class="form-builder__field-grip">&#x2630;</span>
                            <span class="form-builder__field-label">{{ campo.rotulo || '<?php \MapasCulturais\i::_e('Novo campo') ?>' }}</span>
                            <span class="form-builder__field-type">{{ campo.tipo }}</span>
                            <span v-if="campo.obrigatorio" class="form-builder__field-badge badge badge-danger">obrigatório</span>
                        </div>
                        <div class="form-builder__field-actions">
                            <button type="button" class="btn btn-sm btn-secondary" @click.stop="editField(campo._uid)">&#x270E;</button>
                            <button type="button" class="btn btn-sm btn-danger" @click.stop="removeField(campo._uid)">&#x2716;</button>
                        </div>
                        <div class="form-builder__field-span">
                            <small><?php \MapasCulturais\i::_e('Largura') ?>: {{ campo.coluna_span }}/{{ g.colunas*12 }}</small>
                        </div>
                    </div>
                    <div v-if="camposInGroup(g.id).length === 0" class="form-builder__grupo-empty" style="grid-column: 1 / -1;">
                        <small><?php \MapasCulturais\i::_e('Arraste campos para cá') ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Editor de campo -->
    <form-field-editor
        v-if="editingIndex !== null"
        :campo="getEditingCampo()"
        :grupos="grupos"
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
