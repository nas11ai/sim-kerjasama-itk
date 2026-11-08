<script setup lang="ts">
// W156 — tab = route; href dari backend dan membawa filter pilihan pengguna.
import { Link } from '@inertiajs/vue3'

import type { StatisticsTab, StatisticsTabLink } from '@/types/reporting'

defineProps<{ tabs: StatisticsTabLink[]; active: StatisticsTab }>()
</script>

<template>
    <nav
        aria-label="Bagian statistik"
        class="mb-6 inline-flex flex-wrap items-center gap-1 rounded-lg bg-muted p-1 text-muted-foreground"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.value"
            :href="tab.href"
            :aria-current="tab.value === active ? 'page' : undefined"
            :class="[
                'inline-flex items-center rounded-md px-3 py-1.5 text-sm font-medium',
                tab.value === active
                    ? 'bg-background text-foreground shadow-sm'
                    : 'hover:text-foreground',
            ]"
            >{{ tab.label }}</Link
        >
    </nav>
</template>
