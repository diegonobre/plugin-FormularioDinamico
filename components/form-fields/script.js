// Componente form-fields
// Renderiza campos dinâmicos nos formulários de criação/edição de entidades

app.component('form-fields', {
    template: $TEMPLATES['form-fields'],

    props: {
        fields: { type: Array, required: true },
        formSlug: { type: String, required: true },
        values: { type: Object, default: () => ({}) },
    },

    emits: ['update:values'],

    data() {
        return {
            localValues: {},
        };
    },

    mounted() {
        this.localValues = { ...this.values };
    },

    methods: {
        getFieldKey(campo) {
            return this.formSlug + '_' + campo.slug;
        },

        updateValue(key, value) {
            this.localValues[key] = value;
            this.$emit('update:values', { ...this.localValues });
        },
    },
});
