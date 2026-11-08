<script setup lang="ts">
// Frame: SHARED-02-01..04, OPERATOR-07-01 (lima KPI statistik).
import { Link } from '@inertiajs/vue3'

import { Card, CardContent } from '@/Components/ui/card'
import type { KpiCard } from '@/types/dashboard'

withDefaults(defineProps<{ cards: KpiCard[]; columns?: 4 | 5 }>(), { columns: 4 })

// Kelas literal: Tailwind tidak membangkitkan kelas yang disusun saat runtime.
const XL_COLUMNS = { 4: 'xl:grid-cols-4', 5: 'xl:grid-cols-5' } as const
</script>

<template>
    <div :class="['grid gap-4 sm:grid-cols-2', XL_COLUMNS[columns]]">
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
                    <p
                        :class="[
                            'font-semibold whitespace-nowrap tabular-nums',
                            columns === 5 ? 'text-xl 2xl:text-2xl' : 'text-2xl',
                        ]"
                    >
                        {{ card.value }}
                    </p>
                    <p v-if="card.meta" class="truncate text-xs text-muted-foreground">
                        {{ card.meta }}
                    </p>
                </CardContent>
            </Card>
        </component>
    </div>
</template>
