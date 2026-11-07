<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { Clock, FileText, Play, Users } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import AssignCycleReviewerDialog from '@/Components/Monev/AssignCycleReviewerDialog.vue'
import ExtendDeadlineDialog from '@/Components/Submission/Manage/ExtendDeadlineDialog.vue'
import { Button } from '@/Components/ui/button'
import { formatDate } from '@/lib/format'
import type { MonevManagedCycle } from '@/types/monev'
import type { ReviewerCandidate } from '@/types/submission-manage'

const props = defineProps<{
    cycle: MonevManagedCycle
    parentId: number
    suggested: number[]
    first: boolean
    candidates?: ReviewerCandidate[]
}>()

const assigning = ref(false)
const extending = ref(false)

const c = computed(() => props.cycle.cycle)
// Label kartu dari keadaan backend (state + openedAt), bukan hitungan sendiri.
const heading = computed(() => {
    if (c.value.state === 'done') return 'Selesai'
    if (c.value.state === 'frozen') return 'Dibekukan'
    return c.value.openedAt === null ? 'Belum Aktif' : 'Siklus Aktif'
})
const active = computed(() => props.cycle.reviewers.filter((r) => !r.replaced))
const report = computed(() => {
    if (c.value.reportId === null) return 'Belum dimulai'
    return c.value.submittedAt === null ? 'Draft' : `Disubmit ${formatDate(c.value.submittedAt)}`
})
const info = computed(() => [
    { label: 'Deadline', value: formatDate(c.value.deadline) },
    { label: 'Laporan Researcher', value: report.value },
    {
        label: 'Reviewer',
        value: active.value.length
            ? active.value.map((r) => r.name).join(', ')
            : 'Belum ditugaskan',
    },
])
const showOpen = computed(() => props.cycle.canOpen || props.cycle.openHint !== null)

const openCycle = () =>
    router.post(
        route('monev.manage.open', { submission: props.parentId, detail: c.value.detailId }),
        {},
        { preserveScroll: true }
    )
</script>

<template>
    <SectionCard :title="`${c.name} — ${heading}`">
        <template v-if="cycle.canExtend && cycle.phaseAccess && cycle.overrideTarget" #actions>
            <Button variant="outline" size="sm" @click="extending = true">
                <Clock class="size-4" /> Perpanjang Tenggat
            </Button>
        </template>
        <div class="flex flex-col gap-3">
            <InfoGrid :items="info" />
            <!-- BR-MON-04 -->
            <AppBanner v-if="!first && cycle.canAssign && active.length === 0" tone="warning">
                Siklus {{ c.name }} belum punya reviewer. Reviewer siklus sebelumnya tidak ikut
                otomatis.
            </AppBanner>
            <div class="flex flex-wrap items-center gap-2">
                <Button v-if="cycle.canAssign" @click="assigning = true">
                    <Users class="size-4" /> Tugaskan Reviewer
                </Button>
                <Button
                    v-if="showOpen"
                    variant="outline"
                    :disabled="!cycle.canOpen"
                    @click="openCycle"
                >
                    <Play class="size-4" /> Aktifkan Siklus {{ c.name }}
                </Button>
                <Button v-if="c.reportId !== null" as-child variant="ghost">
                    <Link :href="route('monev.reports.show', c.reportId)">
                        <FileText class="size-4" /> Lihat Laporan Researcher
                    </Link>
                </Button>
            </div>
            <p v-if="cycle.openHint" class="text-xs text-muted-foreground">{{ cycle.openHint }}</p>
        </div>
        <AssignCycleReviewerDialog
            v-if="cycle.canAssign"
            v-model:open="assigning"
            :parent-id="parentId"
            :detail-id="c.detailId"
            :suggested="suggested"
            :candidates="candidates"
        />
        <ExtendDeadlineDialog
            v-if="cycle.canExtend && cycle.phaseAccess && cycle.overrideTarget && c.reportId"
            v-model:open="extending"
            :submission-id="c.reportId"
            :phase-access="cycle.phaseAccess"
            :target="cycle.overrideTarget"
        />
    </SectionCard>
</template>
