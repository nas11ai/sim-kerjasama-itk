<script setup lang="ts">
import { ArrowDown, ArrowUp, GripVertical, Pencil, Plus, Trash2 } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import draggable from 'vuedraggable'

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import FormFieldDialog from '@/Components/FormEngine/FormFieldDialog.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { formatDate } from '@/lib/format'
import { moveField, type EditableField } from '@/lib/formDefinition'
import type { ArchivedFieldData, FieldTypeOption } from '@/types/form-engine'

const props = defineProps<{
    fieldTypeOptions: FieldTypeOption[]
    archived: ArchivedFieldData[]
    errors: Record<string, string>
}>()
const fields = defineModel<EditableField[]>({ required: true })

const NEW = -1
const editing = ref<number | null>(null)
const dialogOpen = ref(false)
const removing = ref<number | null>(null)
const confirmOpen = ref(false)

const editedField = computed(() =>
    editing.value === null ? null : (fields.value[editing.value] ?? null)
)
const removingLabel = computed(() =>
    removing.value === null ? '' : fields.value[removing.value]?.label
)

const typeLabel = (id: number) =>
    props.fieldTypeOptions.find((option) => option.value === String(id))?.label ?? ''
const rowErrors = (index: number) =>
    Object.entries(props.errors)
        .filter(([key]) => key.startsWith(`fields.${index}.`))
        .map(([, message]) => message)

const openDialog = (index: number) => {
    editing.value = index
    dialogOpen.value = true
}
const shift = (index: number, by: number) =>
    (fields.value = moveField(fields.value, index, index + by))
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
        <draggable v-model="fields" tag="ul" item-key="key" handle=".drag-handle" class="divide-y">
            <template #item="{ element, index }">
                <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                    <div class="flex items-center gap-2">
                        <GripVertical
                            class="drag-handle size-4 cursor-grab text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div>
                            <p class="text-sm font-medium">{{ element.label }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ typeLabel(element.fieldTypeId) }}
                                <template v-if="element.isRequired && element.requiredSince">
                                    · wajib sejak {{ formatDate(element.requiredSince) }}
                                </template>
                            </p>
                            <p
                                v-for="message in rowErrors(index)"
                                :key="message"
                                class="text-xs text-destructive"
                            >
                                {{ message }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <Badge :variant="element.isRequired ? 'default' : 'outline'">{{
                            element.isRequired ? 'Wajib' : 'Opsional'
                        }}</Badge>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :disabled="index === 0"
                            :aria-label="`Naik: ${element.label}`"
                            @click="shift(index, -1)"
                            ><ArrowUp class="size-4"
                        /></Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :disabled="index === fields.length - 1"
                            :aria-label="`Turun: ${element.label}`"
                            @click="shift(index, 1)"
                            ><ArrowDown class="size-4"
                        /></Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            :aria-label="`Edit ${element.label}`"
                            @click="openDialog(index)"
                            ><Pencil class="size-4" />Edit</Button
                        >
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            :aria-label="`Hapus ${element.label}`"
                            @click="remove(index)"
                            ><Trash2 class="size-4" />Hapus</Button
                        >
                    </div>
                </li>
            </template>
        </draggable>
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
