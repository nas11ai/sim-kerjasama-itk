<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { formatDate } from '@/lib/format'
import type {
    FieldTypeOption,
    FormFieldEditData,
    FormGeneralData,
    FormHeaderData,
} from '@/types/form-engine'
import type { FilterOption } from '@/types/submission-page'

const props = defineProps<{
    form: FormHeaderData | null
    general: FormGeneralData
    fieldTypeOptions: FieldTypeOption[]
    formTypeOptions: FilterOption[]
}>()

// FE-CHANGE-14 — tanpa kunci `options`, opsi field tersimpan tidak berubah (BR-FE-24).
const keptFields = (fields: FormFieldEditData[]) =>
    fields.map((field) => ({
        id: field.id,
        field_type_id: field.fieldTypeId,
        label: field.label,
        is_required: field.isRequired,
    }))

const definition = useForm({
    title: props.general.title,
    description: props.general.description ?? '',
    form_type_id: props.general.formTypeId === null ? null : String(props.general.formTypeId),
    is_active: props.general.isActive,
    fields: keptFields(props.general.fields),
    notify_affected: false,
    continue: false,
})

const statusOptions: FilterOption[] = [
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
]
const status = computed({
    get: () => (definition.is_active ? 'active' : 'inactive'),
    set: (value: string | null) => {
        definition.is_active = value === 'active'
    },
})

const typeLabel = (id: number) =>
    props.fieldTypeOptions.find((option) => option.value === String(id))?.label ?? ''

function save(next: boolean) {
    definition.continue = next
    if (props.form === null) {
        definition.post(route('admin.forms.store'))
        return
    }
    definition.put(route('admin.forms.update', props.form.id))
}
</script>

<template>
    <form class="max-w-3xl space-y-6" @submit.prevent="save(false)">
        <SectionCard title="General">
            <FormField id="form-title" label="Judul Form" required :error="definition.errors.title">
                <Input id="form-title" v-model="definition.title" maxlength="255" />
            </FormField>
            <FormField
                id="form-description"
                label="Deskripsi"
                :error="definition.errors.description"
            >
                <Textarea id="form-description" v-model="definition.description" rows="3" />
            </FormField>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    id="form-type"
                    label="Tipe Form"
                    required
                    :error="definition.errors.form_type_id"
                >
                    <OptionSelect
                        id="form-type"
                        v-model="definition.form_type_id"
                        :options="formTypeOptions"
                    />
                </FormField>
                <FormField
                    id="form-status"
                    label="Status"
                    required
                    :error="definition.errors.is_active"
                >
                    <OptionSelect id="form-status" v-model="status" :options="statusOptions" />
                </FormField>
            </div>
        </SectionCard>
        <SectionCard title="Fields (seret untuk mengubah urutan)">
            <ul class="divide-y">
                <li
                    v-for="field in general.fields"
                    :key="field.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-2"
                >
                    <div>
                        <p class="text-sm font-medium">{{ field.label }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ typeLabel(field.fieldTypeId) }}
                            <template v-if="field.isRequired && field.requiredSince">
                                · wajib sejak {{ formatDate(field.requiredSince) }}
                            </template>
                        </p>
                    </div>
                    <Badge :variant="field.isRequired ? 'default' : 'outline'">{{
                        field.isRequired ? 'Wajib' : 'Opsional'
                    }}</Badge>
                </li>
                <!-- BR-FE-12 / W83 — field terhapus diarsipkan, read-only -->
                <li
                    v-for="field in general.archivedFields"
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
                v-if="general.fields.length === 0 && general.archivedFields.length === 0"
                class="text-sm text-muted-foreground"
            >
                Belum ada field.
            </p>
            <p v-if="definition.errors.fields" class="text-xs text-destructive">
                {{ definition.errors.fields }}
            </p>
        </SectionCard>
        <div class="flex flex-wrap gap-2">
            <Button
                v-if="form !== null"
                type="button"
                variant="outline"
                :disabled="definition.processing"
                @click="save(true)"
                >Lanjut: Access Control</Button
            >
            <Button type="submit" :disabled="definition.processing">Simpan</Button>
        </div>
    </form>
</template>
