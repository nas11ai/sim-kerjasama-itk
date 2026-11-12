<script setup lang="ts">
// Frame: ADMIN-08-09
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PhaseEditor from '@/Components/FormEngine/PhaseEditor.vue'
import PhaseGeneralForm from '@/Components/FormEngine/PhaseGeneralForm.vue'
import { Badge } from '@/Components/ui/badge'
import AppLayout from '@/Layouts/AppLayout.vue'
import { phaseDraftBannerText } from '@/lib/phaseDefinition'
import type { PhaseEditPageProps } from '@/types/form-engine'

const props = defineProps<PhaseEditPageProps>()

// B5 — kembali ke pustaka bertapis bila editor dibuka dari sana.
const libraryHref = computed(() =>
    props.returnForm === null
        ? route('admin.phases.index')
        : route('admin.phases.index', { form: props.returnForm })
)

const creating = computed(() => props.phase.id === null)
const title = computed(() =>
    creating.value ? 'Buat Tahap Baru' : `Edit Phase — ${props.phase.title}`
)

// Trap S2b — editor lahir ulang saat draf dibuang/dibuat atau versi baru terbit.
const editorKey = computed(() =>
    creating.value
        ? 'new'
        : `${props.phase.id}:${props.phase.hasDraft}:${props.phase.versionLabel ?? '-'}`
)
</script>

<template>
    <Head :title="title" />
    <AppLayout>
        <PageHeader
            :title="title"
            subtitle="Susun langkah sebagai draf, pratinjau, lalu terbitkan sebagai versi baru."
            :back-href="libraryHref"
            back-label="Fase"
        >
            <template v-if="!creating" #badge>
                <Badge variant="outline">{{ phase.versionLabel ?? 'Belum terbit' }}</Badge>
            </template>
        </PageHeader>
        <div class="space-y-6">
            <!-- BR-FE-33 — draf tidak berlaku bagi angkatan mana pun sampai diterbitkan -->
            <AppBanner v-if="phase.hasDraft" tone="warning" class="max-w-3xl">
                {{ phaseDraftBannerText(phase.versionLabel) }}
            </AppBanner>
            <PhaseGeneralForm
                v-if="phase.id !== null"
                :key="`general-${phase.id}`"
                class="max-w-3xl"
                :phase-id="phase.id"
                :phase="phase"
            />
            <PhaseEditor
                :key="editorKey"
                :phase="phase"
                :access-options="accessOptions"
                :phase-type-options="phaseTypeOptions"
                :evaluation-options="evaluationOptions"
                :return-form="returnForm"
            />
        </div>
    </AppLayout>
</template>
