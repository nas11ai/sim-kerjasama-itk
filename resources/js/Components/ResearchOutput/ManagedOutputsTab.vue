<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { CalendarClock, Plus } from 'lucide-vue-next'
import { ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import OutputRow from '@/Components/ResearchOutput/OutputRow.vue'
import ExtendDeadlineDialog from '@/Components/Submission/Manage/ExtendDeadlineDialog.vue'
import { Button } from '@/Components/ui/button'
import { formatDate } from '@/lib/format'
import type { OutputRow as OutputRowView, OutputWriteAccess } from '@/types/research-output'
import type { OverrideTarget } from '@/types/submission-manage'
import type { PhaseAccess } from '@/types/submission-page'

// W144 — koreksi Operator (BR-RO-07, RO-OUTPUT-20) dan perpanjangan Batas Akhir (RO-OUTPUT-16).
defineProps<{
    submissionId: number
    outputs: OutputRowView[]
    access: OutputWriteAccess
    phaseAccess: PhaseAccess | null
    target: OverrideTarget | null
    canExtend: boolean
}>()

const extending = ref(false)
</script>

<template>
    <SectionCard title="Luaran">
        <template #actions>
            <Button v-if="access.canWrite" size="sm" as-child>
                <Link :href="route('research-outputs.create', { submission: submissionId })">
                    <Plus class="size-4" /> Tambah Luaran
                </Link>
            </Button>
        </template>
        <AppBanner v-if="access.blockedReason" tone="lock">{{ access.blockedReason }}</AppBanner>
        <div v-if="phaseAccess" class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <span class="text-muted-foreground">
                Batas Akhir: {{ formatDate(phaseAccess.deadline) }}
                <template v-if="!phaseAccess.is_open">, terlewat</template>
            </span>
            <Button v-if="canExtend" variant="outline" size="sm" @click="extending = true">
                <CalendarClock class="size-4" /> Perpanjang Batas Unggah Luaran
            </Button>
        </div>
        <p v-if="outputs.length === 0" class="text-sm text-muted-foreground">Belum ada luaran.</p>
        <OutputRow v-for="output in outputs" :key="output.id" :output="output" />
        <ExtendDeadlineDialog
            v-if="phaseAccess && target"
            v-model:open="extending"
            :submission-id="submissionId"
            :phase-access="phaseAccess"
            :target="target"
        />
    </SectionCard>
</template>
