<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import AffectedDraftsNotice from '@/Components/FormEngine/AffectedDraftsNotice.vue'
import FormFieldList from '@/Components/FormEngine/FormFieldList.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { toDefinitionFields, toEditableFields } from '@/lib/formDefinition'
import type { FieldTypeOption, FormGeneralData, FormHeaderData } from '@/types/form-engine'
import type { FilterOption } from '@/types/submission-page'

const props = defineProps<{
    form: FormHeaderData | null
    general: FormGeneralData
    fieldTypeOptions: FieldTypeOption[]
    formTypeOptions: FilterOption[]
}>()

const editable = ref(toEditableFields(props.general.fields))

const definition = useForm({
    title: props.general.title,
    description: props.general.description ?? '',
    form_type_id: props.general.formTypeId === null ? null : String(props.general.formTypeId),
    is_active: props.general.isActive,
    fields: toDefinitionFields(editable.value, props.fieldTypeOptions),
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

// Galat `fields.{i}.*` tampil di baris; galat selain kartu General (mis. field_type_id) di banner.
const GENERAL_KEYS = ['title', 'description', 'form_type_id', 'is_active']
const otherErrors = computed(() =>
    Object.entries(definition.errors)
        .filter(([key]) => !GENERAL_KEYS.includes(key) && !key.startsWith('fields.'))
        .map(([, message]) => message)
)

function save(next: boolean) {
    definition.continue = next
    definition.fields = toDefinitionFields(editable.value, props.fieldTypeOptions)
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
        <AppBanner v-if="otherErrors.length > 0" tone="denial">
            <p v-for="message in otherErrors" :key="message">{{ message }}</p>
        </AppBanner>
        <FormFieldList
            v-model="editable"
            :field-type-options="fieldTypeOptions"
            :archived="general.archivedFields"
            :errors="definition.errors"
        />
        <AppBanner tone="info">
            Menghapus field memerlukan konfirmasi; field yang dihapus diarsipkan, bukan dihilangkan
            permanen. Mengubah field menjadi wajib saat ada draft berjalan akan menawarkan
            pengiriman notifikasi ke peneliti terdampak; draft lama tidak dipaksa mengisi ulang.
        </AppBanner>
        <AffectedDraftsNotice
            v-model="definition.notify_affected"
            :count="general.affectedDrafts"
        />
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
