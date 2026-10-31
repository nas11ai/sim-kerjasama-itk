<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import { Send } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import InfoGrid from '@/Components/app/InfoGrid.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Button } from '@/Components/ui/button'
import { formatRupiah } from '@/lib/format'
import type { SubmissionAbilities } from '@/types/submission-page'
import type { WizardReview, WizardSubmission } from '@/types/submission-wizard'

const props = defineProps<{
    submission: WizardSubmission
    review: WizardReview
    can: SubmissionAbilities
}>()

const emit = defineEmits<{ goto: [step: number] }>()

const page = usePage()
const confirming = ref(false)
const processing = ref(false)

const budget = computed(() => {
    const total = formatRupiah(props.review.budgetTotal)
    return props.review.maxBudget === null
        ? total
        : `${total} dari ${formatRupiah(props.review.maxBudget)}`
})
const summary = computed(() => [
    { label: 'Judul', value: props.submission.title },
    { label: 'Skema', value: props.review.scheme ?? '—' },
    { label: 'TRL', value: props.review.trl ?? '—' },
    { label: 'Anggota', value: `${props.review.memberCount} orang` },
    { label: 'Target Luaran', value: `${props.review.targetOutputCount} target` },
    { label: 'Total Anggaran', value: budget.value },
    { label: 'Berkas', value: props.review.fileNames.join(', ') || '—' },
])
// BR-SM-03 — revisi dikirim lewat tindakan tersendiri, bukan Submit.
const mode = computed(() => (props.can.resubmit ? 'resubmit' : props.can.submit ? 'submit' : null))
const blocked = computed(
    () => props.review.accountBlock !== null || props.review.missingFields.length > 0
)
const rejections = computed(() => Object.values(page.props.errors ?? {}))

const send = () => {
    const name = mode.value === 'resubmit' ? 'user.submissions.resubmit' : 'user.submissions.submit'
    router.post(
        route(name, String(props.submission.id)),
        {},
        {
            onStart: () => (processing.value = true),
            onFinish: () => {
                processing.value = false
                confirming.value = false
            },
        }
    )
}
</script>

<template>
    <SectionCard title="Step 7 — Periksa Sebelum Mengirim">
        <InfoGrid :items="summary" />
    </SectionCard>
    <SectionCard title="Pemeriksaan Kelengkapan" class="mt-6">
        <!-- BR-IAM-04, BR-IAM-13 -->
        <AppBanner v-if="review.accountBlock" tone="denial">{{ review.accountBlock }}</AppBanner>
        <template v-if="review.missingFields.length > 0">
            <AppBanner tone="warning">
                {{ review.missingFields.length }} field wajib belum terisi. Perbaiki sebelum
                mengirim.
            </AppBanner>
            <ul class="flex flex-col divide-y rounded-md border text-sm">
                <li
                    v-for="missing in review.missingFields"
                    :key="missing.fieldId"
                    class="flex items-center justify-between gap-3 p-3"
                >
                    <span>{{ missing.label }} — {{ missing.message }}</span>
                    <Button
                        variant="outline"
                        size="sm"
                        :data-goto="missing.step"
                        @click="emit('goto', missing.step)"
                    >
                        Ke Langkah {{ missing.step }}
                    </Button>
                </li>
            </ul>
        </template>
        <!-- BR-FE-11 -->
        <AppBanner v-else tone="success">
            Semua field wajib sudah terisi. Field yang baru diwajibkan setelah pengajuan ini dibuat
            tidak ikut diperiksa.
        </AppBanner>
        <AppBanner v-for="message in rejections" :key="message" tone="denial">{{
            message
        }}</AppBanner>
        <!-- BR-SM-04 -->
        <p class="text-xs text-muted-foreground">
            Setelah disubmit, pengajuan tidak dapat diubah kecuali Operator meminta revisi. Hanya
            ketua pengaju yang dapat menekan tombol Submit.
        </p>
        <div v-if="mode" class="flex justify-end">
            <Button
                :data-action="mode"
                :disabled="blocked || processing"
                @click="confirming = true"
            >
                <Send class="size-4" />
                {{ mode === 'resubmit' ? 'Kirim Ulang Revisi' : 'Submit Pengajuan' }}
            </Button>
        </div>
        <!-- [SM-SUBMIT-13], ADR 0015 — Submit tindakan ketua, tak dilimpahkan ke peran lain -->
        <AppBanner v-else tone="info"
            >Hanya ketua pengaju yang dapat mengirim pengajuan ini.</AppBanner
        >
    </SectionCard>
    <ConfirmDialog
        v-model:open="confirming"
        :title="mode === 'resubmit' ? 'Kirim ulang revisi?' : 'Kirim pengajuan?'"
        description="Setelah dikirim, pengajuan tidak dapat diubah kecuali Operator meminta revisi."
        :confirm-label="mode === 'resubmit' ? 'Kirim Ulang' : 'Submit'"
        :processing="processing"
        @confirm="send"
    />
</template>
