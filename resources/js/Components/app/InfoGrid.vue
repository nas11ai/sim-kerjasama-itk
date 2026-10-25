<script setup lang="ts">
defineProps<{ items: { label: string; value: string }[] }>()
</script>

<template>
    <dl class="grid grid-cols-1 gap-x-4 gap-y-2 rounded-md border p-3 text-sm sm:grid-cols-2">
        <div v-for="item in items" :key="item.label" class="min-w-0">
            <dt class="text-xs text-muted-foreground">{{ item.label }}</dt>
            <dd class="font-medium break-words">{{ item.value }}</dd>
        </div>
    </dl>
</template>
