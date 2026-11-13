<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next'

import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/Components/ui/empty'

defineProps<{ title: string; description?: string; icon?: LucideIcon }>()
</script>

<template>
    <Empty>
        <EmptyHeader>
            <EmptyMedia v-if="icon" variant="icon"><component :is="icon" /></EmptyMedia>
            <EmptyTitle>{{ title }}</EmptyTitle>
            <EmptyDescription v-if="description">{{ description }}</EmptyDescription>
        </EmptyHeader>
        <!-- Paket 2 P1 — keadaan kosong menawarkan langkah berikutnya, bukan hanya penjelasan. -->
        <EmptyContent v-if="$slots.action"><slot name="action" /></EmptyContent>
    </Empty>
</template>
