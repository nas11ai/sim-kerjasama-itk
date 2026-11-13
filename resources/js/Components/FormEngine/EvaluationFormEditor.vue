<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import RowErrors from '@/Components/app/RowErrors.vue'
import SortableRows from '@/Components/app/SortableRows.vue'
import DraftPublishBar from '@/Components/FormEngine/DraftPublishBar.vue'
import EvaluationFieldDialog from '@/Components/FormEngine/EvaluationFieldDialog.vue'
import FieldRowActions from '@/Components/FormEngine/FieldRowActions.vue'
import VersionHistory from '@/Components/FormEngine/VersionHistory.vue'
import EvaluationFormPanel from '@/Components/Review/EvaluationFormPanel.vue'
import { Button } from '@/Components/ui/button'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs'
import {
    publishImpactText,
    toDraftPayload,
    toEditableEvaluationFields,
    typeOf,
    type EditableEvaluationField,
} from '@/lib/evaluationDefinition'
import { toPreviewForm } from '@/lib/evaluationPreview'
import { errorsUnder } from '@/lib/formErrors'
import type { EvaluationFormEditPageData, FieldTypeOption } from '@/types/form-engine'

// BR-FE-31 — isi Formulir Evaluasi disunting sebagai draf; Terbitkan menerbitkan draf tersimpan menjadi
// versi berikutnya. Trap S2b — halaman memberi :key per draf/versi, jadi state ini lahir ulang
// sesudah Buang draf atau Terbitkan.
const props = defineProps<{
    form: EvaluationFormEditPageData
    fieldTypeOptions: FieldTypeOption[]
}>()

const editor = useForm({ fields: toEditableEvaluationFields(props.form.fields) })

const errors = computed<Record<string, string>>(() => ({ ...editor.errors }))
const fieldErrors = (index: number) => errorsUnder(errors.value, `fields.${index}.`)
const typeLabel = (id: number) => typeOf(props.fieldTypeOptions, id)?.label ?? ''

const editing = ref<EditableEvaluationField | null>(null)
const dialogOpen = ref(false)
const edit = (field: EditableEvaluationField | null) => {
    editing.value = field
    dialogOpen.value = true
}
const store = (field: EditableEvaluationField) => {
    const exists = editor.fields.some((f) => f.key === field.key)
    editor.fields = exists
        ? editor.fields.map((f) => (f.key === field.key ? field : f))
        : [...editor.fields, field]
}
const drop = (key: string) => (editor.fields = editor.fields.filter((f) => f.key !== key))

const saveDraft = () =>
    editor
        .transform((data) => toDraftPayload(data.fields, props.fieldTypeOptions))
        .put(route('admin.evaluation-forms.draft.update', props.form.id), {
            preserveScroll: true,
            onSuccess: () => editor.defaults(),
        })

const formUrl = (name: string) => route(name, props.form.id)
const discardText = computed(() =>
    props.form.latestVersionLabel === null
        ? 'Isi draf dihapus.'
        : `Isi draf dihapus; editor kembali ke ${props.form.latestVersionLabel}.`
)

// §4.5 — Pratinjau dari isi editor saat ini, persis seperti lembar reviewer.
const previewForm = computed(() =>
    toPreviewForm(props.form.title, props.form.description, editor.fields, props.fieldTypeOptions)
)
</script>

<template>
    <Tabs default-value="fields" class="space-y-4">
        <TabsList>
            <TabsTrigger value="fields">Isian</TabsTrigger>
            <TabsTrigger value="preview">Pratinjau</TabsTrigger>
            <TabsTrigger value="versions">Versi &amp; pemakaian</TabsTrigger>
        </TabsList>

        <TabsContent value="fields" class="space-y-4">
            <div class="flex justify-end">
                <Button type="button" size="sm" variant="outline" @click="edit(null)"
                    ><Plus class="size-4" />Tambah Isian</Button
                >
            </div>
            <SortableRows v-model="editor.fields" :label-of="(field) => field.label">
                <template #row="{ item, index }">
                    <p class="text-sm font-medium">{{ item.label }}</p>
                    <p class="text-xs text-muted-foreground">{{ typeLabel(item.fieldTypeId) }}</p>
                    <RowErrors :messages="fieldErrors(index)" />
                </template>
                <template #actions="{ item }">
                    <FieldRowActions
                        :label="item.label"
                        :is-required="item.isRequired"
                        :remove-disabled="false"
                        @edit="edit(item)"
                        @remove="drop(item.key)"
                    />
                </template>
            </SortableRows>
            <p v-if="editor.fields.length === 0" class="text-sm text-muted-foreground">
                Belum ada isian. Tambahkan minimal satu isian sebelum menerbitkan.
            </p>
            <DraftPublishBar
                :has-draft="form.hasDraft"
                :dirty="editor.isDirty"
                :saving="editor.processing"
                :publish-url="formUrl('admin.evaluation-forms.publish')"
                :discard-url="formUrl('admin.evaluation-forms.draft.destroy')"
                :impact-text="publishImpactText(form.impact)"
                :next-label="form.impact.nextLabel"
                :discard-text="discardText"
                @save="saveDraft"
            />
            <EvaluationFieldDialog
                v-model:open="dialogOpen"
                :field="editing"
                :field-type-options="fieldTypeOptions"
                @save="store"
            />
        </TabsContent>

        <TabsContent value="preview">
            <div data-preview class="rounded-lg border p-4">
                <EvaluationFormPanel :submission-id="0" :form="previewForm" preview />
            </div>
        </TabsContent>

        <TabsContent value="versions" class="space-y-6">
            <VersionHistory :versions="form.versions" />
            <section class="space-y-2">
                <h3 class="text-sm font-semibold">Dipasang di</h3>
                <ul v-if="form.usedBy.length > 0" class="divide-y rounded-lg border">
                    <li
                        v-for="use in form.usedBy"
                        :key="`${use.phaseId}-${use.stepLabel}`"
                        class="flex flex-wrap justify-between gap-2 p-3 text-sm"
                    >
                        <Link class="underline" :href="route('admin.phases.edit', use.phaseId)">{{
                            use.phaseTitle
                        }}</Link>
                        <span class="text-muted-foreground">{{ use.stepLabel }}</span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    Belum dipasang di tahap mana pun. Pasang dari editor tahap.
                </p>
            </section>
        </TabsContent>
    </Tabs>
</template>
