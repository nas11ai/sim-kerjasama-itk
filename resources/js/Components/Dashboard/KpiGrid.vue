<script setup lang="ts">
// Frame: SHARED-02-01..04
import { Link } from '@inertiajs/vue3'

import { Card, CardContent } from '@/Components/ui/card'
import type { KpiCard } from '@/types/dashboard'

defineProps<{ cards: KpiCard[] }>()
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <component
            :is="card.href ? Link : 'div'"
            v-for="card in cards"
            :key="card.key"
            v-bind="card.href ? { href: card.href } : {}"
            :class="[
                'block rounded-xl',
                card.href && 'transition-colors hover:bg-accent/40 focus-visible:outline-2',
            ]"
        >
            <Card class="h-full">
                <CardContent class="flex flex-col gap-1">
                    <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                    <p class="text-2xl font-semibold tabular-nums">{{ card.value }}</p>
                    <p v-if="card.meta" class="truncate text-xs text-muted-foreground">
                        {{ card.meta }}
                    </p>
                </CardContent>
            </Card>
        </component>
    </div>
</template>
