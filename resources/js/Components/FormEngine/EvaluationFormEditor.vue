<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3'
import { Plus, Save, Send, Undo2 } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import RowErrors from '@/Components/app/RowErrors.vue'
import SortableRows from '@/Components/app/SortableRows.vue'
import EvaluationFieldDialog from '@/Components/FormEngine/EvaluationFieldDialog.vue'
import FieldRowActions from '@/Components/FormEngine/FieldRowActions.vue'
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
import { formatDate } from '@/lib/format'
import { errorsUnder } from '@/lib/formErrors'
import type { EvaluationFormEditPageData, FieldTypeOption } from '@/types/form-engine'

// BR-FE-31 — isi Form Evaluasi disunting sebagai draf; Terbitkan menerbitkan draf tersimpan menjadi
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
        .put(route('admin.evaluation-forms.draft.update', props.form.id), { preserveScroll: true })

// Terbitkan memakai draf tersimpan: perubahan yang belum disimpan harus disimpan dulu.
const canPublish = computed(() => props.form.hasDraft && !editor.isDirty)
const publishing = ref(false)
const discarding = ref(false)
const processing = ref(false)
const refusals = ref<string[]>([])

const visit = {
    preserveScroll: true,
    onStart: () => {
        processing.value = true
        refusals.value = []
    },
    onSuccess: () => {
        publishing.value = false
        discarding.value = false
    },
    onError: (errors: Record<string, string>) => {
        refusals.value = Object.values(errors)
        publishing.value = false
    },
    onFinish: () => (processing.value = false),
}

const publish = () => router.post(route('admin.evaluation-forms.publish', props.form.id), {}, visit)
const discard = () =>
    router.delete(route('admin.evaluation-forms.draft.destroy', props.form.id), visit)

// §4.5 — Pratinjau dari isi editor saat ini, persis seperti lembar reviewer.
const previewForm = computed(() =>
    toPreviewForm(props.form.title, props.form.description, editor.fields, props.fieldTypeOptions)
)
</script>

<template>
    <Tabs default-value="fields" class="space-y-4">
        <TabsList>
            <TabsTrigger value="fields">Field</TabsTrigger>
            <TabsTrigger value="preview">Pratinjau</TabsTrigger>
            <TabsTrigger value="versions">Versi &amp; pemakaian</TabsTrigger>
        </TabsList>

        <TabsContent value="fields" class="space-y-4">
            <AppBanner v-if="refusals.length > 0" tone="denial">
                <p v-for="message in refusals" :key="message">{{ message }}</p>
            </AppBanner>
            <div class="flex justify-end">
                <Button type="button" size="sm" variant="outline" @click="edit(null)"
                    ><Plus class="size-4" />Tambah Field</Button
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
                Belum ada field. Tambahkan minimal satu field sebelum menerbitkan.
            </p>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <p v-if="editor.isDirty" class="mr-auto text-xs text-muted-foreground">
                    Simpan draf dulu sebelum menerbitkan.
                </p>
                <Button
                    v-if="form.hasDraft"
                    type="button"
                    variant="ghost"
                    @click="discarding = true"
                    ><Undo2 class="size-4" />Buang draf</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    :disabled="editor.processing"
                    @click="saveDraft"
                    ><Save class="size-4" />Simpan draf</Button
                >
                <Button type="button" :disabled="!canPublish" @click="publishing = true"
                    ><Send class="size-4" />Terbitkan</Button
                >
            </div>
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
            <section class="space-y-2">
                <h3 class="text-sm font-semibold">Riwayat versi</h3>
                <ul v-if="form.versions.length > 0" class="divide-y rounded-lg border">
                    <li
                        v-for="version in form.versions"
                        :key="version.label"
                        class="flex flex-wrap justify-between gap-2 p-3 text-sm"
                    >
                        <span class="font-medium">{{ version.label }}</span>
                        <span class="text-muted-foreground">
                            Terbit {{ formatDate(version.publishedAt) }}
                            <template v-if="version.publishedBy">
                                oleh {{ version.publishedBy }}</template
                            >
                        </span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">Belum pernah diterbitkan.</p>
            </section>
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
    <ConfirmDialog
        v-model:open="publishing"
        title="Terbitkan draf?"
        :description="publishImpactText(form.impact)"
        :confirm-label="`Terbitkan ${form.impact.nextLabel}`"
        :processing="processing"
        @confirm="publish"
    />
    <ConfirmDialog
        v-model:open="discarding"
        title="Buang draf?"
        :description="
            form.latestVersionLabel === null
                ? 'Isi draf dihapus.'
                : `Isi draf dihapus; editor kembali ke ${form.latestVersionLabel}.`
        "
        confirm-label="Buang draf"
        destructive
        :processing="processing"
        @confirm="discard"
    />
</template>
