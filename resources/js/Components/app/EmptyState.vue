<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next'

import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from '@/Components/ui/empty'

defineProps<{ title: string; description?: string; icon?: LucideIcon }>()
</script>

<template>
    <Empty>
        <EmptyHeader>
            <EmptyMedia v-if="icon" variant="icon"><component :is="icon" /></EmptyMedia>
            <EmptyTitle>{{ title }}</EmptyTitle>
            <EmptyDescription v-if="description">{{ description }}</EmptyDescription>
        </EmptyHeader>
    </Empty>
</template>
