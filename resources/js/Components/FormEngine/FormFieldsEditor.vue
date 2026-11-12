<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import AffectedDraftsNotice from '@/Components/FormEngine/AffectedDraftsNotice.vue'
import DraftPublishBar from '@/Components/FormEngine/DraftPublishBar.vue'
import FormFieldList from '@/Components/FormEngine/FormFieldList.vue'
import FormPreview from '@/Components/FormEngine/FormPreview.vue'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs'
import {
    formDraftBannerText,
    formPublishImpactText,
    toDefinitionFields,
    toEditableFields,
} from '@/lib/formDefinition'
import type { FieldTypeOption, FormGeneralData } from '@/types/form-engine'

// BR-FE-34 — field Form disunting sebagai draf; Terbitkan menulis draf tersimpan ke field hidup.
// Prompt notifikasi BR-FE-13 ada di dialog Terbitkan. Trap S2b — induk memberi :key per keadaan
// draf, jadi state ini lahir ulang sesudah draf pertama, Buang draf, atau Terbitkan.
const props = defineProps<{
    formId: number
    general: FormGeneralData
    fieldTypeOptions: FieldTypeOption[]
}>()

const editor = useForm({ fields: toEditableFields(props.general.fields) })
const notify = ref(false)

const errors = computed<Record<string, string>>(() => ({ ...editor.errors }))
const otherErrors = computed(() =>
    Object.entries(errors.value)
        .filter(([key]) => !key.startsWith('fields.'))
        .map(([, message]) => message)
)
const showBanner = computed(() => props.general.hasDraft || props.general.publishedAt === null)
const discardText = computed(() =>
    props.general.publishedAt === null
        ? 'Isi draf dihapus.'
        : 'Isi draf dihapus; editor kembali ke field terbitan terakhir.'
)

const formUrl = (name: string) => route(name, props.formId)
const saveDraft = () =>
    editor
        .transform((data) => ({ fields: toDefinitionFields(data.fields, props.fieldTypeOptions) }))
        .put(formUrl('admin.forms.draft.update'), {
            preserveScroll: true,
            onSuccess: () => editor.defaults(),
        })
</script>

<template>
    <div class="max-w-3xl space-y-4">
        <AppBanner v-if="showBanner" tone="info">{{
            formDraftBannerText(general.publishedAt)
        }}</AppBanner>
        <Tabs default-value="fields" class="space-y-4">
            <TabsList>
                <TabsTrigger value="fields">Field</TabsTrigger>
                <TabsTrigger value="preview">Pratinjau</TabsTrigger>
            </TabsList>

            <TabsContent value="fields" class="space-y-4">
                <AppBanner v-if="otherErrors.length > 0" tone="denial">
                    <p v-for="message in otherErrors" :key="message">{{ message }}</p>
                </AppBanner>
                <FormFieldList
                    v-model="editor.fields"
                    :field-type-options="fieldTypeOptions"
                    :archived="general.archivedFields"
                    :errors="errors"
                    :published="general.publishedFields"
                />
                <AppBanner tone="info">
                    Perubahan field berlaku saat diterbitkan. Field yang dihapus diarsipkan, bukan
                    dihilangkan permanen; draft berjalan tidak dipaksa mengisi field wajib baru.
                </AppBanner>
                <DraftPublishBar
                    :has-draft="general.hasDraft"
                    :dirty="editor.isDirty"
                    :saving="editor.processing"
                    :publish-url="formUrl('admin.forms.publish')"
                    :discard-url="formUrl('admin.forms.draft.destroy')"
                    :impact-text="formPublishImpactText(general.impact)"
                    :discard-text="discardText"
                    :publish-data="{ notify_affected: notify }"
                    @save="saveDraft"
                >
                    <AffectedDraftsNotice v-model="notify" :count="general.impact.draftOwners" />
                </DraftPublishBar>
            </TabsContent>

            <TabsContent value="preview">
                <FormPreview :fields="editor.fields" :field-type-options="fieldTypeOptions" />
            </TabsContent>
        </Tabs>
    </div>
</template>
