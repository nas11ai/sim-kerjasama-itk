<script setup lang="ts">
// Frame: RESEARCHER-02-02, RESEARCHER-02-06, RESEARCHER-02-07, RESEARCHER-02-08, RESEARCHER-02-04, RESEARCHER-02-09, RESEARCHER-02-10
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppStepper from '@/Components/app/AppStepper.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import DraftWizard from '@/Components/Submission/Wizard/DraftWizard.vue'
import StartStep from '@/Components/Submission/Wizard/StartStep.vue'
import WizardFooter from '@/Components/Submission/Wizard/WizardFooter.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { SubmissionWizardPageProps } from '@/types/submission-wizard'

const props = defineProps<SubmissionWizardPageProps>()

const label = computed(() => props.steps.find((s) => s.number === props.step)?.label ?? '')
const title = computed(() =>
    props.submission ? `Buat Pengajuan — ${label.value}` : 'Buat Pengajuan'
)
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <template v-if="submission && can">
            <PageHeader
                :title="title"
                :subtitle="submission.title"
                :back-href="route('user.submissions.index')"
                back-label="Daftar Pengajuan"
            >
                <template #badge><StatusBadge :status="submission.status" /></template>
            </PageHeader>
            <DraftWizard
                :key="`${submission.id}-${step}`"
                :step="step"
                :steps="steps"
                :label="label"
                :submission="submission"
                :fields="fields"
                :target-outputs="targetOutputs"
                :target-output-types="targetOutputTypes"
                :review="review"
                :can="can"
            />
        </template>
        <template v-else>
            <PageHeader
                :title="title"
                subtitle="Isi pengajuan Anda tahap demi tahap — kemajuan tersimpan setiap berpindah langkah."
            />
            <!-- ADR 0012 — langkah lain terbuka setelah langkah 1 melahirkan draft -->
            <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
                <AppStepper :steps="steps" :active="1" disabled />
                <div class="min-w-0">
                    <StartStep :submission="null" :options="startOptions" />
                    <WizardFooter
                        :step="1"
                        :saved-at="null"
                        :processing="false"
                        :show-next="false"
                    />
                </div>
            </div>
        </template>
    </AppLayout>
</template>
