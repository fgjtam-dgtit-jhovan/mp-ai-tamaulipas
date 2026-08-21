<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <img
        src="/images/app_icon.png"
        alt="App logo"
        v-bind="$attrs"
        :class="props.className"
        class="h-auto w-auto object-contain"
        draggable="false"
    />
</template>
