// Botão de ação com modal de confirmação do design system (mc-confirm-button).
// Ao confirmar, envia um POST tradicional (form oculto) para a action informada.

app.component('fd-confirm-action', {
    template: $TEMPLATES['fd-confirm-action'],

    props: {
        action: { type: String, required: true },
        fields: { type: Object, default: () => ({}) },
        title: String,
        message: String,
        yes: String,
        no: String,
        icon: String,
        label: String,
        buttonClass: { type: [String, Array], default: 'button--primary' },
    },

    methods: {
        submit(modal) {
            modal.loading(true);
            this.$refs.form.submit();
        },
    },
});
