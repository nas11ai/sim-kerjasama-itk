<script setup lang="ts">
// Frame: ADMIN-08-09
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import PageHeader from '@/Components/app/PageHeader.vue'
import EvaluationSection from '@/Components/FormEngine/EvaluationSection.vue'
import PhaseEditor from '@/Components/FormEngine/PhaseEditor.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { PhaseEditPageProps } from '@/types/form-engine'

const props = defineProps<PhaseEditPageProps>()

const title = computed(() =>
    props.phase.id === null ? 'Buat Tahap Baru' : `Edit Phase — ${props.phase.title}`
)
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <PageHeader
            :title="title"
            subtitle="Susun tahapan pengisian beserta form evaluasi reviewer-nya."
            :back-href="route('admin.forms.phases.index', form.id)"
            back-label="Phases"
        />
        <!-- Trap S2b — Inertia memakai ulang halaman antar-tahap; state editor di child ber-key -->
        <PhaseEditor
            :key="phase.id ?? 'new'"
            :form="form"
            :phase="phase"
            :access-options="accessOptions"
            :phase-type-options="phaseTypeOptions"
        />
        <!-- W95 — form evaluasi disimpan per kartu, terpisah dari Simpan Tahap; key per detail (S2b) -->
        <div v-if="evaluation.length > 0" class="mt-8 max-w-3xl space-y-8">
            <EvaluationSection
                v-for="section in evaluation"
                :key="section.detailId"
                :section="section"
                :field-type-options="fieldTypeOptions"
            />
        </div>
    </AppLayout>
</template>
