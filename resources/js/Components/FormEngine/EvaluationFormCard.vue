<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Check, Plus } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import ActiveStatusSelect from '@/Components/app/ActiveStatusSelect.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import SortableRows from '@/Components/app/SortableRows.vue'
import EvaluationFieldDialog from '@/Components/FormEngine/EvaluationFieldDialog.vue'
import EvaluationFormLifecycle from '@/Components/FormEngine/EvaluationFormLifecycle.vue'
import FieldRowActions from '@/Components/FormEngine/FieldRowActions.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Textarea } from '@/Components/ui/textarea'
import {
    toEditableEvaluationFields,
    toEvaluationGeneral,
    toEvaluationPayload,
    type EditableEvaluationField,
} from '@/lib/evaluationDefinition'
import type { EvaluationFormEditData, FieldTypeOption } from '@/types/form-engine'

// W95 — satu kartu satu form evaluasi, disimpan sendiri; `form` null = kartu baru (post).
const props = defineProps<{
    detailId: number
    form: EvaluationFormEditData | null
    fieldTypeOptions: FieldTypeOption[]
}>()
const emit = defineEmits<{ created: []; discard: [] }>()

const editor = useForm({
    ...toEvaluationGeneral(props.form),
    fields: toEditableEvaluationFields(props.form?.fields ?? []),
})
const prefix = `evaluation-${props.form?.id ?? 'new'}-${props.detailId}`
const heading = computed(
    () => `Form Evaluasi — ${props.form?.title ?? (editor.title.trim() || 'Baru')}`
)

// Galat `fields.{i}.*` tampil di barisnya; galat isian kartu di isiannya; sisanya di banner.
const errors = computed<Record<string, string>>(() => ({ ...editor.errors }))
const OWN_KEYS = ['title', 'description', 'is_required', 'is_active']
const otherErrors = computed(() =>
    Object.entries(errors.value).flatMap(([key, message]) =>
        OWN_KEYS.includes(key) || key.startsWith('fields.') ? [] : [message]
    )
)
const fieldErrors = (index: number) =>
    Object.keys(errors.value)
        .filter((key) => key.startsWith(`fields.${index}.`))
        .map((key) => errors.value[key])

const typeLabel = (id: number) =>
    props.fieldTypeOptions.find((type) => type.value === String(id))?.label ?? ''

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

function save() {
    const pending = editor.transform((data) =>
        toEvaluationPayload(data, data.fields, props.fieldTypeOptions)
    )
    if (props.form === null) {
        pending.post(route('admin.phase-details.evaluation-forms.store', props.detailId), {
            preserveScroll: true,
            onSuccess: () => emit('created'),
        })
        return
    }
    pending.put(route('admin.evaluation-forms.update', props.form.id), { preserveScroll: true })
}
</script>

<template>
    <form @submit.prevent="save">
        <SectionCard :title="heading">
            <template #actions>
                <EvaluationFormLifecycle v-if="form !== null" :form="form" />
                <!-- Draf belum ada di server: membuangnya cukup lokal -->
                <Button v-else type="button" variant="outline" size="sm" @click="emit('discard')"
                    >Buang Draf</Button
                >
                <Button type="button" size="sm" @click="edit(null)"
                    ><Plus class="size-4" />Tambah Field</Button
                >
            </template>
            <FormField :id="`${prefix}-title`" label="Judul" required :error="errors.title">
                <Input :id="`${prefix}-title`" v-model="editor.title" maxlength="255" />
            </FormField>
            <FormField :id="`${prefix}-description`" label="Deskripsi" :error="errors.description">
                <Textarea :id="`${prefix}-description`" v-model="editor.description" rows="2" />
            </FormField>
            <div class="flex items-center gap-2">
                <Switch :id="`${prefix}-required`" v-model="editor.isRequired" />
                <Label :for="`${prefix}-required`" class="font-normal">Wajib diisi reviewer</Label>
            </div>
            <FormField :id="`${prefix}-status`" label="Status" :error="errors.is_active">
                <ActiveStatusSelect :id="`${prefix}-status`" v-model="editor.isActive" />
            </FormField>
            <AppBanner v-if="otherErrors.length > 0" tone="denial">
                <p v-for="message in otherErrors" :key="message">{{ message }}</p>
            </AppBanner>
            <SortableRows v-model="editor.fields" :label-of="(field) => field.label">
                <template #row="{ item, index }">
                    <p class="text-sm font-medium">{{ item.label }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ typeLabel(item.fieldTypeId) }}
                        <template v-if="item.answerCount > 0">
                            · {{ item.answerCount }} jawaban reviewer
                        </template>
                    </p>
                    <p
                        v-for="message in fieldErrors(index)"
                        :key="message"
                        class="text-xs text-destructive"
                    >
                        {{ message }}
                    </p>
                </template>
                <template #actions="{ item }">
                    <!-- Cermin BR-FE-23 — field yang sudah dijawab tidak dihapus (W103) -->
                    <FieldRowActions
                        :label="item.label"
                        :is-required="item.isRequired"
                        :remove-disabled="item.answerCount > 0"
                        @edit="edit(item)"
                        @remove="drop(item.key)"
                    />
                </template>
            </SortableRows>
            <p v-if="editor.fields.length === 0" class="text-sm text-muted-foreground">
                Belum ada field.
            </p>
            <div class="flex justify-end">
                <Button type="submit" :disabled="editor.processing"
                    ><Check class="size-4" />Simpan Form Evaluasi</Button
                >
            </div>
            <EvaluationFieldDialog
                v-model:open="dialogOpen"
                :field="editing"
                :field-type-options="fieldTypeOptions"
                @save="store"
            />
        </SectionCard>
    </form>
</template>
