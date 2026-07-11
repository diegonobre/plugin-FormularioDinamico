<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

$this->import('
    mc-confirm-button
    mc-icon
');
?>
<div class="fd-confirm-action">
    <form ref="form" method="POST" :action="action" class="fd-confirm-action__form" aria-hidden="true">
        <input v-for="(value, name) in fields" :key="name" type="hidden" :name="name" :value="value">
    </form>

    <mc-confirm-button
        :title="title"
        :message="message"
        :yes="yes"
        :no="no"
        dont-close-on-confirm
        @confirm="submit">
        <template #button="{open}">
            <button type="button" :class="['button', 'button--sm', 'button--icon', buttonClass]" @click="open()">
                <mc-icon v-if="icon" :name="icon"></mc-icon>
                <span>{{ label }}</span>
            </button>
        </template>
    </mc-confirm-button>
</div>
