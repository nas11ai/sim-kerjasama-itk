<script setup lang="ts">
// Frame: REVIEWER-03-03
import { Head, Link } from '@inertiajs/vue3'

import AppBanner from '@/Components/app/AppBanner.vue'
import EmptyState from '@/Components/app/EmptyState.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import StatusBadge from '@/Components/app/StatusBadge.vue'
import EvaluationFormPanel from '@/Components/Review/EvaluationFormPanel.vue'
import EvaluationSubmissionPanel from '@/Components/Review/EvaluationSubmissionPanel.vue'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { EvaluationPageProps } from '@/types/review'

const props = defineProps<EvaluationPageProps>()

const initialTab = props.activeFormId === null ? undefined : String(props.activeFormId)
</script>

<template>
    <Head title="Form Evaluasi" />
    <AppLayout>
        <PageHeader
            title="Form Evaluasi"
            :subtitle="submission.title"
            :back-href="route('reviewer.assignments.index')"
            back-label="Penugasan Review"
        >
            <template #badge><StatusBadge :status="submission.status" /></template>
        </PageHeader>

        <!-- BR-FE-06 — ringkasan baru bisa dibuat setelah evaluasi beres -->
        <AppBanner v-if="evaluationSettled" tone="success" class="mb-4">
            Evaluasi Anda selesai.
            <Link
                :href="route('reviewer.assignments.summary', String(submission.id))"
                class="font-medium underline underline-offset-4"
            >
                Lanjutkan ke ringkasan dan diskusi
            </Link>
        </AppBanner>

        <div class="grid gap-6 lg:grid-cols-2">
            <EvaluationSubmissionPanel :submission="submission" :answers="answers" />

            <section class="flex min-w-0 flex-col gap-4">
                <h2 class="text-lg font-semibold">Evaluasi</h2>
                <EmptyState
                    v-if="forms.length === 0"
                    title="Tahap ini tidak memiliki form evaluasi aktif"
                />
                <Tabs v-else :default-value="initialTab">
                    <TabsList class="flex-wrap">
                        <TabsTrigger
                            v-for="form in forms"
                            :key="form.formId"
                            :value="String(form.formId)"
                        >
                            {{ form.title }}
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent
                        v-for="form in forms"
                        :key="form.formId"
                        :value="String(form.formId)"
                    >
                        <!-- Kunci ikut status: form yang baru dimulai/dikirim dipasang ulang. -->
                        <EvaluationFormPanel
                            :key="`${form.formId}-${form.state}`"
                            :submission-id="submission.id"
                            :form="form"
                        />
                    </TabsContent>
                </Tabs>
            </section>
        </div>
    </AppLayout>
</template>
