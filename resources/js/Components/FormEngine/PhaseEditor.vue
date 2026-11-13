<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import { Check } from 'lucide-vue-next'
import { computed } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import DraftPublishBar from '@/Components/FormEngine/DraftPublishBar.vue'
import PhaseDetailList from '@/Components/FormEngine/PhaseDetailList.vue'
import PhaseGeneralCard from '@/Components/FormEngine/PhaseGeneralCard.vue'
import PhaseStepPreview from '@/Components/FormEngine/PhaseStepPreview.vue'
import VersionHistory from '@/Components/FormEngine/VersionHistory.vue'
import { Button } from '@/Components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs'
import {
    blankDetail,
    phasePublishImpactText,
    stepHeading,
    toEditableDetails,
    toPhasePayload,
    toStepsPayload,
    type PhaseGeneral,
} from '@/lib/phaseDefinition'
import type { PhaseEditPageProps } from '@/types/form-engine'

// BR-FE-33 — struktur tahap disunting sebagai draf; Terbitkan menerbitkan draf tersimpan menjadi
// versi berikutnya. Trap S2b — halaman memberi :key per draf/versi, jadi state ini lahir ulang
// sesudah Buang draf atau Terbitkan.
const props = defineProps<PhaseEditPageProps>()

// W94 + BR-FE-28 — mode buat: satu langkah Pengajuan kosong berakses pilihan pertama; tanpa
// pilihan, tak bisa disimpan.
const creating = props.phase.id === null
const firstRule = props.accessOptions[0]
const initialDetails = () =>
    creating && firstRule
        ? [blankDetail(Number(firstRule.value), Number(props.phaseTypeOptions[0]?.value ?? 0))]
        : toEditableDetails(props.phase.steps)

const editor = useForm({
    title: props.phase.title,
    description: props.phase.description ?? '',
    isActive: props.phase.isActive,
    details: initialDetails(),
})

const general = computed({
    get: (): PhaseGeneral => ({
        title: editor.title,
        description: editor.description,
        isActive: editor.isActive,
    }),
    set: (value: PhaseGeneral) => Object.assign(editor, value),
})

// Galat baris `phase_details.{i}.*` tampil di barisnya; galat kartu Tahap di kartunya.
const OWN_KEYS = ['title', 'description', 'is_active']
const errors = computed<Record<string, string>>(() => ({ ...editor.errors }))
const otherErrors = computed(() =>
    Object.entries(errors.value)
        .filter(([key]) => !OWN_KEYS.includes(key) && !key.startsWith('phase_details.'))
        .map(([, message]) => message)
)

// B5 — `form` dibawa ke store supaya editornya tetap kembali ke pustaka bertapis.
const libraryHref = computed(() =>
    props.returnForm === null
        ? route('admin.phases.index')
        : route('admin.phases.index', { form: props.returnForm })
)

const create = () =>
    editor
        .transform((data) => ({ ...toPhasePayload(data, data.details), form: props.returnForm }))
        .post(route('admin.phases.store'))

const saveDraft = () =>
    editor
        .transform((data) => toStepsPayload(data.details))
        .put(phaseUrl('admin.phases.draft.update'), {
            preserveScroll: true,
            onSuccess: () => editor.defaults(),
        })

const phaseUrl = (name: string) => route(name, props.phase.id ?? 0)
const discardText = computed(() =>
    props.phase.versionLabel === null
        ? 'Isi draf dihapus.'
        : `Isi draf dihapus; editor kembali ke ${props.phase.versionLabel}.`
)

// G3-W8 — Pratinjau dari isi editor saat ini.
const previewSteps = computed(() =>
    editor.details.map((detail) => {
        const heading = stepHeading(
            detail,
            props.phase.steps,
            props.accessOptions,
            props.phaseTypeOptions
        )
        return {
            key: detail.key,
            title: heading.title,
            access: heading.access,
            deadlineLabel: detail.deadlineLabel,
            needsReview: detail.needsReview,
            evaluationForms: detail.evaluationForms,
        }
    })
)
</script>

<template>
    <form v-if="creating" class="max-w-3xl space-y-6" @submit.prevent="create">
        <AppBanner v-if="!firstRule" tone="warning">
            Belum ada Formulir aktif yang punya aturan akses. Buat aturan akses di Pembuat Formulir
            lebih dulu.
            <Link class="underline" :href="route('admin.forms.index')">Buka Pembuat Formulir</Link>
        </AppBanner>
        <PhaseGeneralCard v-model="general" :errors="errors" />
        <AppBanner v-if="otherErrors.length > 0" tone="denial">
            <p v-for="message in otherErrors" :key="message">{{ message }}</p>
        </AppBanner>
        <PhaseDetailList
            v-model="editor.details"
            :saved="phase.steps"
            :access-options="accessOptions"
            :phase-type-options="phaseTypeOptions"
            :evaluation-options="evaluationOptions"
            :errors="errors"
        />
        <div class="flex flex-wrap justify-between gap-2">
            <Button as-child variant="ghost">
                <Link :href="libraryHref">Batalkan</Link>
            </Button>
            <Button type="submit" :disabled="editor.processing || !firstRule"
                ><Check class="size-4" />Buat Tahap</Button
            >
        </div>
    </form>

    <Tabs v-else default-value="steps" class="max-w-3xl space-y-4">
        <TabsList>
            <TabsTrigger value="steps">Langkah</TabsTrigger>
            <TabsTrigger value="preview">Pratinjau</TabsTrigger>
            <TabsTrigger value="versions">Versi &amp; pemakaian</TabsTrigger>
        </TabsList>

        <TabsContent value="steps" class="space-y-4">
            <AppBanner v-if="otherErrors.length > 0" tone="denial">
                <p v-for="message in otherErrors" :key="message">{{ message }}</p>
            </AppBanner>
            <PhaseDetailList
                v-model="editor.details"
                :saved="phase.steps"
                :access-options="accessOptions"
                :phase-type-options="phaseTypeOptions"
                :evaluation-options="evaluationOptions"
                :errors="errors"
            />
            <DraftPublishBar
                :has-draft="phase.hasDraft"
                :dirty="editor.isDirty"
                :saving="editor.processing"
                :publish-url="phaseUrl('admin.phases.publish')"
                :discard-url="phaseUrl('admin.phases.draft.destroy')"
                :impact-text="phasePublishImpactText(phase.impact)"
                :next-label="phase.impact.nextLabel"
                :discard-text="discardText"
                @save="saveDraft"
            />
        </TabsContent>

        <TabsContent value="preview">
            <PhaseStepPreview :steps="previewSteps" />
        </TabsContent>

        <TabsContent value="versions">
            <VersionHistory :versions="phase.versions" />
        </TabsContent>
    </Tabs>
</template>
