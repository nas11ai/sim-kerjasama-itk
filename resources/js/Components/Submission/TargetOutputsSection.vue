<script setup lang="ts">
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import TimelineItem from '@/Components/Submission/TimelineItem.vue'
import TargetOutputEditor from '@/Components/Submission/Wizard/TargetOutputEditor.vue'
import type {
    FilterOption,
    SubmissionDetail,
    SubmissionTargetOutput,
    TargetOutputChange,
} from '@/types/submission-page'

// Boleh-tidaknya menyunting dari `canManage` (Policy); status dan peran hanya memilih teks banner.
const props = defineProps<{
    submission: SubmissionDetail
    targetOutputs: SubmissionTargetOutput[]
    types: FilterOption[]
    history: TargetOutputChange[]
    canManage: boolean
}>()

const lockMessage = computed(() => {
    if (props.canManage) return null
    if (['rejected', 'withdrawn'].includes(props.submission.status.key)) {
        return 'Target luaran terkunci karena pengajuan ditolak atau ditarik.'
    }
    if (props.submission.viewerRole !== 'lead_researcher') {
        return 'Hanya ketua pengaju yang dapat mengubah target luaran.'
    }
    return null
})
</script>

<template>
    <div class="flex flex-col gap-4">
        <TargetOutputEditor
            v-if="canManage"
            :submission-id="submission.id"
            :target-outputs="targetOutputs"
            :types="types"
        />
        <SectionCard v-else title="Target Luaran">
            <!-- BR-SM-15 — hanya ketua pengaju, sampai REJECTED atau WITHDRAWN -->
            <AppBanner v-if="lockMessage" tone="lock">{{ lockMessage }}</AppBanner>
            <ul v-if="targetOutputs.length > 0" class="divide-y rounded-md border text-sm">
                <li v-for="target in targetOutputs" :key="target.id" class="p-3">
                    <p class="font-medium">{{ target.type }}</p>
                    <p v-if="target.description" class="text-muted-foreground">
                        {{ target.description }}
                    </p>
                </li>
            </ul>
            <EmptyState v-else title="Belum ada target luaran" />
        </SectionCard>

        <!-- BR-SM-15 -->
        <AppBanner v-if="canManage && submission.status.key === 'approved'" tone="info">
            Target luaran masih dapat diubah meski pengajuan sudah disetujui, target menyesuaikan
            jalannya kegiatan.
        </AppBanner>

        <SectionCard title="Jejak Perubahan Target">
            <ol v-if="history.length > 0" class="flex flex-col gap-3 border-l pl-4 text-sm">
                <TimelineItem
                    v-for="(change, index) in history"
                    :key="index"
                    :title="change.description"
                    :at="change.at"
                    :actor="change.actor"
                >
                    <p v-if="change.from || change.to" class="text-muted-foreground">
                        {{ [change.from, change.to].filter(Boolean).join(' → ') }}
                    </p>
                </TimelineItem>
            </ol>
            <EmptyState v-else title="Belum ada perubahan target" />
        </SectionCard>
    </div>
</template>
