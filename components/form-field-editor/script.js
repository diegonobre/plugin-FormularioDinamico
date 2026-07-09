app.component('form-field-editor', {
    template: $TEMPLATES['form-field-editor'],

    props: {
        campo: { type: Object, required: true },
        grupos: { type: Array, default: () => [] },
    },

    emits: ['save', 'cancel'],

    data() { return { edit: {} }; },

    computed: {
        hasOptions() { return ['select','multiselect','gender'].includes(this.edit.tipo); },
    },

    mounted() { this.edit = JSON.parse(JSON.stringify(this.campo)); },

    methods: {
        autoSlug() {
            if (!this.edit.slug || this.edit.slug === this.slugify(this.campo.rotulo || '')) {
                this.edit.slug = this.slugify(this.edit.rotulo);
            }
        },
        slugify(text) { return text.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'').substring(0,100); },
        addOption() { if (!Array.isArray(this.edit.opcoes)) this.edit.opcoes = []; this.edit.opcoes.push(''); },
        removeOption(idx) { if (Array.isArray(this.edit.opcoes)) this.edit.opcoes.splice(idx, 1); },
        save() {
            if (!this.edit.rotulo.trim()) { alert('O rótulo é obrigatório'); return; }
            if (this.hasOptions && (!Array.isArray(this.edit.opcoes) || this.edit.opcoes.length === 0)) {
                alert('Campos do tipo ' + this.edit.tipo + ' precisam de ao menos uma opção.'); return;
            }
            this.$emit('save', {...this.edit});
        },
    },
});
