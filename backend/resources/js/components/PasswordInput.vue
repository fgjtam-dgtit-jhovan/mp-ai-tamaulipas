<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import type { HTMLAttributes } from 'vue';

import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

defineOptions({
    inheritAttrs: false,
});

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const showPassword = ref(false);

const inputRef = useTemplateRef('inputRef');

defineExpose({
    $el: inputRef,

    focus: () => inputRef.value?.$el?.focus(),
});
</script>

<template>
    <div class="password-input-wrapper">

        <!-- =====================================================
             INPUT
        ====================================================== -->

        <Input
            ref="inputRef"
            :type="showPassword ? 'text' : 'password'"
            :class="
                cn(
                    'password-input',
                    props.class
                )
            "
            v-bind="$attrs"
        />


        <!-- =====================================================
             BOTÓN MOSTRAR / OCULTAR
        ====================================================== -->

        <button
            type="button"
            class="password-toggle"
            @click="showPassword = !showPassword"
            :aria-label="
                showPassword
                    ? 'Ocultar contraseña'
                    : 'Mostrar contraseña'
            "
            :aria-pressed="showPassword"
            :tabindex="-1"
        >

            <EyeOff
                v-if="showPassword"
                class="password-eye"
            />

            <Eye
                v-else
                class="password-eye"
            />

        </button>

    </div>
</template>


<style scoped>

.password-input-wrapper {

    position: relative;

    width: 100%;
    height: 100%;

    min-width: 0;
}


/* =========================================================
   INPUT
========================================================= */

.password-input {

    width: 100% !important;

    height: 100% !important;

    min-width: 0;

    padding:
        0 48px 0 0 !important;

    border: none !important;

    border-radius: 0 !important;

    background: transparent !important;

    box-shadow: none !important;

    outline: none !important;

    color: #575756 !important;

    font-size: 12px !important;
}

.password-input::placeholder {

    color: #b2b2b2 !important;
}


/* =========================================================
   BOTÓN
========================================================= */

.password-toggle {

    position: absolute;

    top: 0;
    right: 0;

    width: 48px;
    height: 100%;

    display: flex;

    align-items: center;
    justify-content: center;

    padding: 0;

    border: none;

    border-radius:
        0 9px 9px 0;

    background: transparent;

    color: #999999;

    cursor: pointer;

    transition:
        color .2s ease,
        background .2s ease;
}

.password-toggle:hover {

    color: #575756;

    background:
        rgba(87,87,86,.035);
}

.password-toggle:focus-visible {

    outline: none;

    color: #575756;
}

.password-eye {

    width: 17px;
    height: 17px;

    stroke-width: 1.5;
}

</style>
