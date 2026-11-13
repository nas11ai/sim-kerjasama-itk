<script setup lang="ts">
// W151 — pengganti donut frame: segmented bar tipis + daftar baris yang bisa diklik.
import { Link } from '@inertiajs/vue3'
import { ArrowRight } from 'lucide-vue-next'
import { computed } from 'vue'

import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { SERIES } from '@/lib/chartPalette'
import { formatShare } from '@/lib/statistics'
import type { StatusShare } from '@/types/reporting'

const props = defineProps<{ rows: StatusShare[]; allHref?: string | null }>()

const filled = computed(() => props.rows.filter((row) => row.count > 0))
</script>

<template>
    <SectionCard title="Pengajuan per Status">
        <p v-if="filled.length === 0" class="text-sm text-muted-foreground">Tidak ada data</p>
        <template v-else>
            <div
                data-testid="status-bar"
                class="flex h-2 gap-0.5 overflow-hidden rounded-full"
                role="img"
                aria-label="Pangsa pengajuan per status"
            >
                <span
                    v-for="row in filled"
                    :key="row.key"
                    :title="`${row.label}: ${row.count} (${formatShare(row.share)})`"
                    class="h-full basis-0"
                    :style="{ flexGrow: row.count, backgroundColor: SERIES[0] }"
                />
            </div>
            <ul class="flex flex-col">
                <li v-for="row in rows" :key="row.key">
                    <component
                        :is="row.href ? Link : 'div'"
                        v-bind="row.href ? { href: row.href } : {}"
                        :class="[
                            'flex items-center gap-3 rounded-md px-2 py-1.5 text-sm',
                            row.href && 'hover:bg-accent/40 focus-visible:outline-2',
                        ]"
                    >
                        <span class="flex-1">{{ row.label }}</span>
                        <span class="font-medium tabular-nums">{{ row.count }}</span>
                        <span class="w-10 text-right text-muted-foreground tabular-nums">
                            {{ formatShare(row.share) }}
                        </span>
                    </component>
                </li>
            </ul>
        </template>
        <Button v-if="allHref" as-child variant="ghost" size="sm" class="self-start">
            <Link :href="allHref">Drill-down: Semua Pengajuan <ArrowRight class="size-4" /></Link>
        </Button>
    </SectionCard>
</template>
