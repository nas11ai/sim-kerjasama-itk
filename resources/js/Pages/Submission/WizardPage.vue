<script setup lang="ts">
// Frame: RESEARCHER-02-02, RESEARCHER-02-06, RESEARCHER-02-07, RESEARCHER-02-08, RESEARCHER-02-04, RESEARCHER-02-09, RESEARCHER-02-10
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import AppStepper from '@/Components/app/AppStepper.vue'
import StartStep from '@/Components/Submission/Wizard/StartStep.vue'
import WizardFooter from '@/Components/Submission/Wizard/WizardFooter.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { SubmissionWizardPageProps } from '@/types/submission-wizard'

const props = defineProps<SubmissionWizardPageProps>()

const processing = ref(false)
const label = computed(() => props.steps.find((s) => s.number === props.step)?.label ?? '')
const title = computed(() =>
    props.submission ? `Buat Pengajuan — ${label.value}` : 'Buat Pengajuan'
)

const go = (goto: number) => {
    if (props.submission === null) return
    router.post(
        route('user.submissions.wizard.save', [String(props.submission.id), String(props.step)]),
        { goto, last_updated_at: props.submission.lastUpdatedAt },
        {
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        }
    )
}
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <PageHeader
            v-if="submission"
            :title="title"
            :subtitle="submission.title"
            :back-href="route('user.submissions.index')"
            back-label="Daftar Pengajuan"
        >
            <template #badge><StatusBadge :status="submission.status" /></template>
        </PageHeader>
        <PageHeader
            v-else
            :title="title"
            subtitle="Isi pengajuan Anda tahap demi tahap — kemajuan tersimpan setiap berpindah langkah."
        />

        <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
            <AppStepper
                :steps="steps"
                :active="step"
                :disabled="submission === null || processing"
                @select="go"
            />
            <div class="min-w-0">
                <StartStep v-if="step === 1" :submission="submission" :options="startOptions" />
                <EmptyState
                    v-else-if="step === 4 || step === 5"
                    title="Langkah ini disiapkan di irisan berikutnya."
                />
                <WizardFooter
                    v-if="submission"
                    :step="step"
                    :saved-at="submission.lastUpdatedAt"
                    :processing="processing"
                    :show-next="step < 7"
                    @back="go(step - 1)"
                    @next="go(step + 1)"
                />
                <WizardFooter
                    v-else
                    :step="1"
                    :saved-at="null"
                    :processing="processing"
                    :show-next="false"
                />
            </div>
        </div>
    </AppLayout>
</template>
