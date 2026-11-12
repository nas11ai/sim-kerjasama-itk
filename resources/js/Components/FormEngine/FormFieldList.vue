<script setup lang="ts">
import { Plus } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import RowErrors from '@/Components/app/RowErrors.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import SortableRows from '@/Components/app/SortableRows.vue'
import FieldRowActions from '@/Components/FormEngine/FieldRowActions.vue'
import FormFieldDialog from '@/Components/FormEngine/FormFieldDialog.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { formatDate } from '@/lib/format'
import { errorsUnder } from '@/lib/formErrors'
import type { EditableField } from '@/lib/formDefinition'
import type { ArchivedFieldData, FieldTypeOption, FormFieldEditData } from '@/types/form-engine'

const props = withDefaults(
    defineProps<{
        fieldTypeOptions: FieldTypeOption[]
        archived: ArchivedFieldData[]
        errors: Record<string, string>
        // G3-W9 — field terbit, pembanding peringatan label di dialog.
        published?: FormFieldEditData[]
    }>(),
    { published: () => [] }
)
const fields = defineModel<EditableField[]>({ required: true })

const NEW = -1
const editing = ref<number | null>(null)
const dialogOpen = ref(false)
const removing = ref<number | null>(null)
const confirmOpen = ref(false)

const editedField = computed(() =>
    editing.value === null ? null : (fields.value[editing.value] ?? null)
)
const publishedOfEdited = computed(() => {
    const id = editedField.value?.id ?? null
    return id === null ? undefined : props.published.find((field) => field.id === id)
})
const removingLabel = computed(() =>
    removing.value === null ? '' : fields.value[removing.value]?.label
)

const typeLabel = (id: number) =>
    props.fieldTypeOptions.find((option) => option.value === String(id))?.label ?? ''
const rowErrors = (index: number) => errorsUnder(props.errors, `fields.${index}.`)

const openDialog = (index: number) => {
    editing.value = index
    dialogOpen.value = true
}
const store = (field: EditableField) => {
    const next = [...fields.value]
    if (editing.value === null || editing.value === NEW) next.push(field)
    else next.splice(editing.value, 1, field)
    fields.value = next
}
const drop = (index: number) => (fields.value = fields.value.filter((_, i) => i !== index))
// BR-FE-12 / FE-CHANGE-01 — field tersimpan diarsipkan lewat konfirmasi; field baru langsung dibuang.
const remove = (index: number) => {
    if (fields.value[index]?.id === null) return drop(index)
    removing.value = index
    confirmOpen.value = true
}
const confirmRemove = () => {
    if (removing.value !== null) drop(removing.value)
    confirmOpen.value = false
}
</script>

<template>
    <SectionCard title="Fields (seret untuk mengubah urutan)">
        <template #actions>
            <Button type="button" size="sm" @click="openDialog(NEW)"
                ><Plus class="size-4" />Tambah Field</Button
            >
        </template>
        <SortableRows v-model="fields" :label-of="(field) => field.label">
            <template #row="{ item, index }">
                <p class="text-sm font-medium">{{ item.label }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ typeLabel(item.fieldTypeId) }}
                    <template v-if="item.isRequired && item.requiredSince">
                        · wajib sejak {{ formatDate(item.requiredSince) }}
                    </template>
                </p>
                <RowErrors :messages="rowErrors(index)" />
            </template>
            <template #actions="{ item, index }">
                <FieldRowActions
                    :label="item.label"
                    :is-required="item.isRequired"
                    @edit="openDialog(index)"
                    @remove="remove(index)"
                />
            </template>
        </SortableRows>
        <ul v-if="archived.length > 0" class="divide-y">
            <!-- BR-FE-12 / W83 — field terhapus diarsipkan, read-only -->
            <li
                v-for="field in archived"
                :key="`archived-${field.id}`"
                class="flex flex-wrap items-center justify-between gap-3 py-2 opacity-60"
            >
                <div>
                    <p class="text-sm font-medium">{{ field.label }}</p>
                    <p class="text-xs text-muted-foreground">{{ field.typeName }}</p>
                </div>
                <Badge variant="outline">Diarsipkan</Badge>
            </li>
        </ul>
        <p
            v-if="fields.length === 0 && archived.length === 0"
            class="text-sm text-muted-foreground"
        >
            Belum ada field.
        </p>
        <FormFieldDialog
            v-model:open="dialogOpen"
            :field="editedField"
            :field-type-options="fieldTypeOptions"
            :published="publishedOfEdited"
            @save="store"
        />
        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Hapus field?"
            :description="`${removingLabel} diarsipkan, bukan dihapus permanen. Jawaban lama tetap terbaca di tampilan read-only.`"
            confirm-label="Hapus"
            destructive
            @confirm="confirmRemove"
        />
    </SectionCard>
</template>
