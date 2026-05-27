<!-- components/PermissionChip.vue -->
<script setup lang="ts">
import Badge from '@/Components/ui/badge/Badge.vue'
import type { Permission } from '@/types'

const props = defineProps<{
    permissions: Permission[]
}>()
</script>

<template>
    <div class="flex flex-wrap gap-x-1.5 gap-y-2">
        <Badge
            v-for="(permission, index) in permissions"
            :key="typeof permission === 'string' ? permission : (permission.id ?? index)"
            variant="outline"
            class="rounded-full border-zinc-200 bg-zinc-100 px-2.5 py-1.5 text-zinc-700 shadow-2xs transition-colors duration-150 hover:border-zinc-300 hover:bg-zinc-200 hover:text-zinc-900"
        >
            {{ typeof permission === 'string' ? permission : permission.name }}
        </Badge>
    </div>
</template>
