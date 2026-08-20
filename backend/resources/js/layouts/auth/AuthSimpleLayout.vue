<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>

    <div class="auth-layout">

        <slot />

    </div>

</template>

<style scoped>

.auth-layout {

    width: 100%;

    min-width: 100%;

    min-height: 100svh;

    margin: 0;

    padding: 0;
}

</style>
