// Componente form-builder
// Construtor de formulários dinâmicos com drag-and-drop (SortableJS)

app.component('form-builder', {
    template: $TEMPLATES['form-builder'],

    props: {
        formId: { type: Number, default: 0 },
        entidade: { type: String, default: '' },
        initialTitulo: { type: String, default: '' },
        initialSlug: { type: String, default: '' },
        initialDescricao: { type: String, default: '' },
        initialAtivo: { type: [Boolean, String], default: true },
        initialCampos: { type: Array, default: () => [] },
        camposNativos: { type: Array, default: () => [] },
        urlSalvar: { type: String, default: '' },
        entidadeLabel: { type: String, default: '' },
    },

    data() {
        return {
            titulo: this.initialTitulo,
            slug: this.initialSlug,
            descricao: this.initialDescricao,
            ativo: String(this.initialAtivo) === 'true' || this.initialAtivo === true,
            campos: [],
            editingIndex: null,
            dragType: null,
            dragIndex: null,
            _uidCounter: 0,
        };
    },

    computed: {
        fieldTypes() {
            return $MAPAS.formBuilderFieldTypes || [];
        },

        camposSerialized() {
            return this.campos.map(c => ({
                slug: c.slug,
                rotulo: c.rotulo,
                placeholder: c.placeholder,
                tipo: c.tipo,
                opcoes: c.opcoes,
                obrigatorio: c.obrigatorio,
                coluna_span: c.coluna_span,
                editavel: c.editavel !== false,
            }));
        },
    },

    mounted() {
        this.initCampos();
        this.initSortable();
    },

    methods: {
        _uid() {
            return ++this._uidCounter;
        },

        initCampos() {
            if (Array.isArray(this.initialCampos)) {
                this.campos = this.initialCampos.map(c => ({
                    ...c,
                    _uid: this._uid(),
                }));
            }
        },

        initSortable() {
            const container = this.$refs.sortableContainer;
            if (!container || typeof Sortable === 'undefined') return;

            const self = this;
            Sortable.create(container, {
                animation: 150,
                handle: '.form-builder__field-grip',
                ghostClass: 'form-builder__field-item--ghost',
                onEnd(evt) {
                    const item = self.campos.splice(evt.oldIndex, 1)[0];
                    if (item) {
                        self.campos.splice(evt.newIndex, 0, item);
                    }
                },
            });
        },

        onDragStart(event, type) {
            this.dragType = type;
            event.dataTransfer.effectAllowed = 'copy';
            event.dataTransfer.setData('text/plain', type);
        },

        onFieldDragStart(event, idx) {
            this.dragIndex = idx;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', 'move');
        },

        onNativeDragStart(event, idx) {
            // Native fields can be reordered but we track position for reference
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', 'native-' + idx);
        },

        onDrop(event) {
            const type = event.dataTransfer.getData('text/plain');
            if (type && type !== 'move' && !type.startsWith('native-')) {
                this.addField(type);
            }
        },

        onDropOnField(event, idx) {
            const type = event.dataTransfer.getData('text/plain');
            if (type && type !== 'move' && !type.startsWith('native-')) {
                const newField = this.createField(type);
                this.campos.splice(idx, 0, newField);
            }
        },

        onDropOnNative(event, idx) {
            // Drop on native area — ignore for dynamic fields
        },

        createField(type) {
            const field = {
                _uid: this._uid(),
                slug: 'campo_' + this._uid(),
                rotulo: this.getDefaultLabel(type),
                placeholder: '',
                tipo: type,
                opcoes: type === 'gender' ? ['Masculino', 'Feminino', 'Não binário', 'Prefiro não informar'] : [],
                obrigatorio: false,
                coluna_span: 12,
                editavel: true,
            };
            return field;
        },

        getDefaultLabel(type) {
            const labels = {
                text: 'Novo campo de texto',
                textarea: 'Novo campo de texto longo',
                number: 'Novo campo numérico',
                email: 'Novo campo de email',
                url: 'Novo campo de URL',
                date: 'Nova data',
                datetime: 'Nova data e hora',
                phone: 'Novo telefone',
                cep: 'Novo CEP',
                cpf: 'Novo CPF',
                cnpj: 'Novo CNPJ',
                select: 'Nova seleção',
                multiselect: 'Nova multi seleção',
                gender: 'Gênero',
            };
            return labels[type] || 'Novo campo';
        },

        addField(type) {
            this.campos.push(this.createField(type));
        },

        removeField(idx) {
            this.campos.splice(idx, 1);
            if (this.editingIndex === idx) {
                this.editingIndex = null;
            } else if (this.editingIndex > idx) {
                this.editingIndex--;
            }
        },

        editField(idx) {
            this.editingIndex = idx;
        },

        saveField(data) {
            if (this.editingIndex !== null && this.campos[this.editingIndex]) {
                Object.assign(this.campos[this.editingIndex], data);
                this.editingIndex = null;
            }
        },
    },
});
