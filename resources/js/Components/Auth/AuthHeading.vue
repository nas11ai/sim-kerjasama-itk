<script setup lang="ts">
defineProps<{ title: string; subtitle?: string }>()
</script>

<template>
    <div class="mb-6 space-y-1">
        <h1 class="text-xl font-semibold">{{ title }}</h1>
        <p v-if="subtitle || $slots.default" class="text-sm text-muted-foreground">
            <slot>{{ subtitle }}</slot>
        </p>
    </div>
</template>
