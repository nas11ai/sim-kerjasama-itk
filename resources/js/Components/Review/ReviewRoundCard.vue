<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { roundProgressLabel } from '@/lib/reviewRound'
import type { ReviewerRound } from '@/types/review'

const props = defineProps<{ round: ReviewerRound | null; submissionId: number }>()

const confirming = ref(false)
const sending = ref(false)

const progress = computed(() =>
    props.round === null ? null : roundProgressLabel(props.round.progress)
)

// BR-REV-16 — tanda Selesai per reviewer per putaran; aturannya dijaga FinishReviewRound.
const finish = () => {
    sending.value = true
    router.post(
        route('reviewer.assignments.finish', String(props.submissionId)),
        {},
        { onFinish: () => (sending.value = confirming.value = false) }
    )
}
</script>

<template>
    <SectionCard
        title="Putaran Penilaian"
        description="Hasil penilaian dihitung setelah semua peserta putaran menekan Selesai."
    >
        <template v-if="round !== null">
            <p class="text-sm">{{ progress }}</p>
            <div v-if="round.isParticipant && !round.finished" class="flex flex-col gap-2">
                <Button
                    data-action="finish-round"
                    class="self-start"
                    :disabled="!round.canFinish"
                    @click="confirming = true"
                >
                    {{ round.finishLabel }}
                </Button>
                <p v-if="round.reason" class="text-sm text-muted-foreground">{{ round.reason }}</p>
            </div>
            <AppBanner v-else-if="round.reason" tone="info">{{ round.reason }}</AppBanner>
        </template>
        <p v-else class="text-sm text-muted-foreground">
            Tidak ada putaran penilaian yang sedang berjalan.
        </p>
        <!-- BR-REV-07, BR-REV-14 — tidak ada kombinasi skor yang menolak otomatis -->
        <AppBanner tone="lock">
            Penolakan tidak tersedia di layar penilai. Hanya Operator yang dapat menolak, lewat aksi
            tertulis.
        </AppBanner>
    </SectionCard>
    <ConfirmDialog
        v-if="round !== null"
        v-model:open="confirming"
        :title="`${round.finishLabel}?`"
        description="Tanda ini tidak bisa dibatalkan."
        :confirm-label="round.finishLabel"
        :processing="sending"
        @confirm="finish"
    />
</template>
