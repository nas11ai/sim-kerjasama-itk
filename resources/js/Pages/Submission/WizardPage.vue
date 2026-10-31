<script setup lang="ts">
// Frame: RESEARCHER-02-02, RESEARCHER-02-06, RESEARCHER-02-07, RESEARCHER-02-08, RESEARCHER-02-04, RESEARCHER-02-09, RESEARCHER-02-10
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppStepper from '@/Components/app/AppStepper.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import AnswersStep from '@/Components/Submission/Wizard/AnswersStep.vue'
import SchemeStep from '@/Components/Submission/Wizard/SchemeStep.vue'
import StartStep from '@/Components/Submission/Wizard/StartStep.vue'
import WizardFooter from '@/Components/Submission/Wizard/WizardFooter.vue'
import { useWizardStep } from '@/composables/useWizardStep'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { SubmissionWizardPageProps, WizardFieldValue } from '@/types/submission-wizard'

const props = defineProps<SubmissionWizardPageProps>()

// Halaman dipasang ulang setiap berpindah langkah, jadi isian dibaca dari props sekali.
const wizard = props.submission ? useWizardStep(props.submission, props.step, props.fields) : null

const processing = computed(() => wizard?.processing.value ?? false)
const errors = computed(() => wizard?.errors.value ?? {})
const label = computed(() => props.steps.find((s) => s.number === props.step)?.label ?? '')
const title = computed(() =>
    props.submission ? `Buat Pengajuan — ${label.value}` : 'Buat Pengajuan'
)

const go = (goto: number) => wizard?.go(goto)
const update = (fieldId: number, value: WizardFieldValue) => {
    if (wizard) wizard.values[fieldId] = value
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
                <template v-else-if="submission && wizard">
                    <AnswersStep
                        v-if="step === 2 || step === 6"
                        :step="step"
                        :label="label"
                        :fields="fields"
                        :values="wizard.values"
                        :errors="errors"
                        @update="update"
                    />
                    <SchemeStep
                        v-else-if="step === 3"
                        :fields="fields"
                        :values="wizard.values"
                        :errors="errors"
                        :submission-id="submission.id"
                        :target-outputs="targetOutputs"
                        :target-output-types="targetOutputTypes"
                        @update="update"
                    />
                    <EmptyState
                        v-else-if="step === 4 || step === 5"
                        title="Langkah ini disiapkan di irisan berikutnya."
                    />
                </template>
                <WizardFooter
                    :step="step"
                    :saved-at="submission?.lastUpdatedAt ?? null"
                    :processing="processing"
                    :show-next="submission !== null && step < 7"
                    @back="go(step - 1)"
                    @next="go(step + 1)"
                />
            </div>
        </div>
    </AppLayout>
</template>
