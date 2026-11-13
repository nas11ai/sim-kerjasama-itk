<script setup lang="ts">
// Frame: ADMIN-08-09
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import EvaluationFormDetailsCard from '@/Components/FormEngine/EvaluationFormDetailsCard.vue'
import EvaluationFormEditor from '@/Components/FormEngine/EvaluationFormEditor.vue'
import { Badge } from '@/Components/ui/badge'
import AppLayout from '@/Layouts/AppLayout.vue'
import { draftBannerText } from '@/lib/evaluationDefinition'
import type { EvaluationFormEditPageProps } from '@/types/form-engine'

// Editor pustaka Formulir Evaluasi (BR-FE-30/31); `form` null = mode buat, hanya nama.
const props = defineProps<EvaluationFormEditPageProps>()

const title = computed(() =>
    props.form === null ? 'Buat Formulir Evaluasi' : `Edit Formulir Evaluasi: ${props.form.title}`
)

// Trap S2b — editor lahir ulang saat draf dibuang/dibuat atau versi baru terbit.
const editorKey = computed(() =>
    props.form === null
        ? 'new'
        : `${props.form.id}:${props.form.hasDraft}:${props.form.latestVersionLabel ?? '-'}`
)
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <PageHeader
            :title="title"
            subtitle="Susun field sebagai draf, pratinjau, lalu terbitkan sebagai versi baru."
            :back-href="route('admin.evaluation-forms.index')"
            back-label="Formulir Evaluasi"
        >
            <template v-if="form" #badge>
                <Badge variant="outline">{{ form.latestVersionLabel ?? 'Belum terbit' }}</Badge>
            </template>
        </PageHeader>
        <div class="max-w-3xl space-y-6">
            <!-- BR-FE-31 — draf tidak berlaku bagi reviewer sampai diterbitkan -->
            <AppBanner v-if="form?.hasDraft" tone="warning">
                {{ draftBannerText(form.latestVersionLabel) }}
            </AppBanner>
            <EvaluationFormDetailsCard :key="form?.id ?? 'new'" :form="form" />
            <EvaluationFormEditor
                v-if="form"
                :key="editorKey"
                :form="form"
                :field-type-options="fieldTypeOptions"
            />
        </div>
    </AppLayout>
</template>
