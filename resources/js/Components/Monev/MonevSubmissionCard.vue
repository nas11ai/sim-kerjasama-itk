<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { FileText, SquarePen } from 'lucide-vue-next'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import PhaseProgression from '@/Components/Monev/PhaseProgression.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { phaseNodes } from '@/lib/monev'
import type { MonevCycle, MonevSubmission } from '@/types/monev'

const props = defineProps<{ submission: MonevSubmission }>()

const nodes = computed(() => phaseNodes(props.submission.cycles))
const withdrawn = computed(() => props.submission.status.key === 'withdrawn')
const fillable = computed(() =>
    props.submission.canFill ? props.submission.cycles.filter((c) => c.state === 'open') : []
)
const startable = computed(() => fillable.value.filter((c) => c.reportId === null))
const drafts = computed(() =>
    fillable.value.flatMap((c) => (c.reportId === null ? [] : [{ ...c, reportId: c.reportId }]))
)
const closed = computed(() => props.submission.cycles.filter((c) => c.state === 'closed'))
const sent = computed(() =>
    props.submission.cycles.flatMap((c) =>
        (c.state === 'submitted' || c.state === 'done') && c.reportId !== null
            ? [{ ...c, reportId: c.reportId }]
            : []
    )
)
const memberOnly = computed(
    () => !props.submission.canFill && props.submission.cycles.some((c) => c.state === 'open')
)

const start = (cycle: MonevCycle) =>
    router.post(
        route('monev.reports.store', { submission: props.submission.id, detail: cycle.detailId }),
        {},
        { preserveScroll: true }
    )
</script>

<template>
    <SectionCard :title="submission.title" :description="submission.scheme ?? undefined">
        <template #actions><StatusBadge :status="submission.status" /></template>
        <PhaseProgression :nodes="nodes" />
        <!-- BR-MON-08 -->
        <AppBanner v-if="withdrawn" tone="lock">
            Pengajuan yang ditarik tidak dapat dikembalikan. Kalau penarikannya keliru, jalurnya
            pengajuan baru yang merujuk yang lama.
        </AppBanner>
        <div
            v-if="fillable.length || sent.length || closed.length || memberOnly"
            class="flex flex-wrap items-center gap-2"
        >
            <Button v-for="cycle in startable" :key="cycle.detailId" @click="start(cycle)">
                <SquarePen class="size-4" /> Isi Laporan {{ cycle.name }}
            </Button>
            <Button v-for="cycle in drafts" :key="cycle.detailId" as-child>
                <Link :href="route('monev.reports.edit', cycle.reportId)">
                    <SquarePen class="size-4" /> Lanjutkan Laporan {{ cycle.name }}
                </Link>
            </Button>
            <Button v-for="cycle in sent" :key="cycle.detailId" variant="outline" as-child>
                <Link :href="route('monev.reports.show', cycle.reportId)">
                    <FileText class="size-4" /> Lihat Laporan {{ cycle.name }}
                </Link>
            </Button>
            <span
                v-for="cycle in closed"
                :key="cycle.detailId"
                title="Batas waktu terlewat. Hubungi Operator LPPM bila membutuhkan perpanjangan."
            >
                <Badge variant="outline">{{ cycle.name }} Ditutup</Badge>
            </span>
            <!-- BR-SM-04 -->
            <p v-if="memberOnly" class="text-sm text-muted-foreground">
                Laporan diisi oleh ketua pengajuan.
            </p>
        </div>
    </SectionCard>
</template>
