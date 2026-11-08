<script setup lang="ts">
// W152 — pengajuan berjalan per tahap; umur terlama sebagai fakta, bukan peringatan berambang.
import { Link } from '@inertiajs/vue3'

import { formatAge } from '@/lib/statistics'
import type { StageBacklog } from '@/types/reporting'

defineProps<{ rows: StageBacklog[] }>()
</script>

<template>
    <ul class="flex flex-col">
        <li v-for="row in rows" :key="row.key">
            <component
                :is="row.href ? Link : 'div'"
                v-bind="row.href ? { href: row.href } : {}"
                :class="[
                    'flex items-baseline gap-3 rounded-md px-2 py-1.5 text-sm',
                    row.href && 'hover:bg-accent/40 focus-visible:outline-2',
                ]"
            >
                <span class="flex-1">{{ row.label }}</span>
                <span v-if="row.oldestAgeDays !== null" class="text-muted-foreground">
                    terlama {{ formatAge(row.oldestAgeDays) }}
                </span>
                <span class="w-8 text-right font-medium tabular-nums">{{ row.count }}</span>
            </component>
        </li>
    </ul>
</template>
