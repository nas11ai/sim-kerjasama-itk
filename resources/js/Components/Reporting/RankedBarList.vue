<script setup lang="ts">
// W151 — batang horizontal berperingkat, satu warna; label dan angka di luar batang.
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

import { SERIES } from '@/lib/chartPalette'
import type { RankedCount } from '@/types/reporting'

const props = withDefaults(defineProps<{ rows: RankedCount[]; emptyText?: string }>(), {
    emptyText: 'Tidak ada data',
})

const max = computed(() => Math.max(1, ...props.rows.map((row) => row.count)))
</script>

<template>
    <p v-if="rows.length === 0" class="text-sm text-muted-foreground">{{ emptyText }}</p>
    <ul v-else class="flex flex-col gap-1">
        <li v-for="row in rows" :key="row.label">
            <component
                :is="row.href ? Link : 'div'"
                v-bind="row.href ? { href: row.href } : {}"
                :class="[
                    'flex flex-col gap-1 rounded-md px-2 py-1.5 text-sm',
                    row.href && 'hover:bg-accent/40 focus-visible:outline-2',
                ]"
            >
                <span class="flex items-baseline gap-3">
                    <span class="flex-1 truncate">{{ row.label }}</span>
                    <span class="font-medium tabular-nums">{{ row.count }}</span>
                </span>
                <span
                    data-testid="ranked-bar"
                    class="h-2 rounded-r"
                    :style="{ width: `${(row.count / max) * 100}%`, backgroundColor: SERIES[0] }"
                />
            </component>
        </li>
    </ul>
</template>
