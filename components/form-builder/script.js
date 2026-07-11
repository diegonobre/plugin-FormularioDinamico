app.component('form-builder', {
    template: $TEMPLATES['form-builder'],

    props: {
        formId: { type: Number, default: 0 },
        entidade: { type: String, default: '' },
        initialTitulo: { type: String, default: '' },
        initialSlug: { type: String, default: '' },
        initialDescricao: { type: String, default: '' },
        initialCampos: { type: Array, default: () => [] },
        initialGrupos: { type: Array, default: () => [{id:0, titulo:'Geral', colunas:1}] },
        camposNativos: { type: Array, default: () => [] },
        urlSalvar: { type: String, default: '' },
        entidadeLabel: { type: String, default: '' },
        isDraft: { type: Boolean, default: true },
    },

    data() {
        return {
            titulo: this.initialTitulo,
            slug: this.initialSlug,
            descricao: this.initialDescricao,
            ativo: true,
            campos: [],
            grupos: [],
            editingIndex: null,
            activeGroupId: 0,
            _sortableInstances: [],
            _uidCounter: 0,
        };
    },

    computed: {
        fieldTypes() { return $MAPAS.formBuilderFieldTypes || []; },

        camposSerialized() {
            return this.campos.map(c => {
                const gid = c.grupo_id !== undefined ? c.grupo_id : this.activeGroupId;
                return {
                    slug: c.slug,
                    rotulo: c.rotulo,
                    placeholder: c.placeholder,
                    tipo: c.tipo,
                    opcoes: c.opcoes,
                    obrigatorio: c.obrigatorio,
                    coluna_span: c.coluna_span,
                    editavel: c.editavel !== false,
                    grupo_id: gid,
                    grupo_titulo: this.getGrupoTitle(gid),
                    grupo_colunas: this.getGrupoColunas(gid),
                };
            });
        },

        grupoIds() { return this.grupos.map(g => g.id).join(','); },
    },

    watch: {
        grupoIds() { this.$nextTick(() => this.initSortable()); },
        'campos.length'() { this.$nextTick(() => this.initSortable()); },
    },

    mounted() {
        this.grupos = this.initialGrupos.map(g => ({...g}));
        if (this.grupos.length === 0) {
            this.grupos = [{id: 0, titulo: 'Geral', colunas: 1}];
        }
        this.activeGroupId = this.grupos[0].id;

        this.campos = this.initialCampos.map(c => ({
            ...c,
            _uid: this._uid(),
            grupo_id: c.grupo_id !== undefined ? c.grupo_id : 0,
            grupo_titulo: c.grupo_titulo || this.getGrupoTitle(c.grupo_id !== undefined ? c.grupo_id : 0),
        }));

        this.$nextTick(() => this.initSortable());
    },

    beforeUnmount() {
        this.destroySortable();
    },

    methods: {
        _uid() { return ++this._uidCounter; },

        getGrupoTitle(gid) {
            const g = this.grupos.find(x => x.id === gid);
            return g ? g.titulo : 'Geral';
        },

        getGrupoColunas(gid) {
            const g = this.grupos.find(x => x.id === gid);
            return g && g.colunas ? Math.min(Math.max(parseInt(g.colunas), 1), 4) : 1;
        },

        selectGroup(gid) {
            this.activeGroupId = gid;
            this.$nextTick(() => {
                const el = document.querySelector('.form-builder__grupo[data-gid="' + gid + '"]');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        },

        grupoGridStyle(g) {
            return {
                display: 'grid',
                gridTemplateColumns: 'repeat(' + (this.getGrupoColunas(g.id) * 12) + ', 1fr)',
                gap: '0.5rem',
            };
        },

        fieldSpanStyle(campo, g) {
            const total = this.getGrupoColunas(g.id) * 12;
            const span = Math.min(Math.max(parseInt(campo.coluna_span) || 12, 1), total);
            return { gridColumn: 'span ' + span };
        },

        camposInGroup(gid) {
            return this.campos.filter(c => (c.grupo_id || 0) === gid);
        },

        countCamposInGroup(gid) {
            return this.camposInGroup(gid).length;
        },

        destroySortable() {
            this._sortableInstances.forEach(s => {
                try { s.destroy(); } catch(e) {}
            });
            this._sortableInstances = [];
        },

        initSortable() {
            this.destroySortable();
            var containers = document.querySelectorAll('.form-builder__grupo-fields');
            if (!containers.length || typeof Sortable === 'undefined') return;

            var self = this;
            containers.forEach(function(el) {
                var s = Sortable.create(el, {
                    group: 'form-fields',
                    animation: 150,
                    handle: '.form-builder__field-grip',
                    ghostClass: 'form-builder__field-item--ghost',
                    onEnd: function(evt) {
                        // Use a small delay so DOM reflects the move
                        setTimeout(function() {
                            self.reorderFromDom();
                        }, 10);
                    },
                    onAdd: function(evt) {
                        // When a field moves from one group to another, update grupo_id
                        var grupoEl = evt.target.closest('.form-builder__grupo');
                        var targetGid = grupoEl ? parseInt(grupoEl.dataset.gid) : self.activeGroupId;
                        var uid = evt.item ? parseInt(evt.item.dataset.uid) : null;
                        if (uid) {
                            var campo = self.campos.find(function(c) { return c._uid === uid; });
                            if (campo) {
                                campo.grupo_id = targetGid;
                                campo.grupo_titulo = self.getGrupoTitle(targetGid);
                            }
                        }
                    },
                });
                self._sortableInstances.push(s);
            });
        },

        /** Rebuild campos[] order from DOM to reflect drag-and-drop result */
        reorderFromDom: function() {
            var allGrupoEls = document.querySelectorAll('.form-builder__grupo');
            var newOrder = [];

            allGrupoEls.forEach(function(grupoEl) {
                var gid = parseInt(grupoEl.dataset.gid);
                var fieldEls = grupoEl.querySelectorAll('.form-builder__field-item[data-uid]');
                fieldEls.forEach(function(el) {
                    var uid = parseInt(el.dataset.uid);
                    var campo = this.campos.find(function(c) { return c._uid === uid; });
                    if (campo) {
                        campo.grupo_id = gid;
                        campo.grupo_titulo = this.getGrupoTitle(gid);
                        newOrder.push(campo);
                    }
                }, this);
            }, this);

            if (newOrder.length > 0) {
                this.campos = newOrder;
            }
        },

        addFieldToActiveGroup: function(type) {
            var field = this.createField(type);
            field.grupo_id = this.activeGroupId;
            field.grupo_titulo = this.getGrupoTitle(this.activeGroupId);
            this.campos.push(field);
        },

        createField: function(type) {
            var uid = this._uid();
            return {
                _uid: uid,
                slug: 'campo_' + uid,
                rotulo: this.getDefaultLabel(type),
                placeholder: '',
                tipo: type,
                opcoes: type === 'gender' ? ['Masculino', 'Feminino', 'Não binário', 'Prefiro não informar'] : [],
                obrigatorio: false,
                coluna_span: 12,
                editavel: true,
                grupo_id: this.activeGroupId,
                grupo_titulo: this.getGrupoTitle(this.activeGroupId),
            };
        },

        getDefaultLabel: function(type) {
            var labels = {
                text:'Novo texto', textarea:'Texto longo', number:'Número',
                email:'Email', url:'URL', date:'Data', datetime:'Data e hora',
                phone:'Telefone', cep:'CEP', cpf:'CPF', cnpj:'CNPJ',
                select:'Seleção', multiselect:'Multi seleção', gender:'Gênero',
            };
            return labels[type] || 'Novo campo';
        },

        removeField: function(uid) {
            var idx = this.campos.findIndex(function(c) { return c._uid === uid; });
            if (idx !== -1) this.campos.splice(idx, 1);
            if (this.editingIndex === uid) this.editingIndex = null;
        },

        editField: function(uid) { this.editingIndex = uid; },

        getEditingCampo: function() { return this.campos.find(function(c) { return c._uid === this.editingIndex; }, this); },

        saveField: function(data) {
            var campo = this.campos.find(function(c) { return c._uid === this.editingIndex; }, this);
            if (campo) Object.assign(campo, data);
            this.editingIndex = null;
        },

        addGroup: function() {
            var id = this.grupos.length;
            this.grupos.push({id: id, titulo: 'Novo grupo', colunas: 1});
            this.activeGroupId = id;
        },

        removeGroup: function(gidx) {
            var g = this.grupos[gidx];
            if (!g || this.grupos.length <= 1) return;
            var targetId = this.grupos[0].id;
            this.campos.forEach(function(c) {
                if ((c.grupo_id || 0) === g.id) {
                    c.grupo_id = targetId;
                    c.grupo_titulo = this.getGrupoTitle(targetId);
                }
            }, this);
            this.grupos.splice(gidx, 1);
            if (this.activeGroupId === g.id) this.activeGroupId = targetId;
        },

    },
});
