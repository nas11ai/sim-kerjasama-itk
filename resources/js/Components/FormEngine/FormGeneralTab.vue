<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import SectionCard from '@/Components/app/SectionCard.vue'
import FormFieldList from '@/Components/FormEngine/FormFieldList.vue'
import FormFieldsEditor from '@/Components/FormEngine/FormFieldsEditor.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Textarea } from '@/Components/ui/textarea'
import { toDefinitionFields, toEditableFields } from '@/lib/formDefinition'
import type { FieldTypeOption, FormGeneralData, FormHeaderData } from '@/types/form-engine'
import type { FilterOption } from '@/types/submission-page'

// Kartu General disimpan langsung. BR-FE-34 — field: mode buat mengirimnya sebagai draf pertama;
// mode sunting menyuntingnya lewat FormFieldsEditor (draf → Terbitkan).
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

// Galat `fields.{i}.*` tampil di baris; galat selain kartu General (mis. BR-SCH-05) di banner.
const GENERAL_KEYS = ['title', 'description', 'form_type_id', 'is_active']
const errors = computed<Record<string, string>>(() => ({ ...definition.errors }))
const otherErrors = computed(() =>
    Object.entries(errors.value)
        .filter(([key]) => !GENERAL_KEYS.includes(key) && !key.startsWith('fields.'))
        .map(([, message]) => message)
)

function save(next: boolean) {
    definition.continue = next
    if (props.form === null) {
        definition
            .transform((data) => ({
                ...data,
                fields: toDefinitionFields(editable.value, props.fieldTypeOptions),
            }))
            .post(route('admin.forms.store'))
        return
    }
    definition.put(route('admin.forms.update', props.form.id), { preserveScroll: true })
}
</script>

<template>
    <div class="space-y-6">
        <form class="max-w-3xl space-y-6" @submit.prevent="save(false)">
            <SectionCard title="Umum">
                <FormField id="form-title" label="Judul Formulir" required :error="errors.title">
                    <Input id="form-title" v-model="definition.title" maxlength="255" />
                </FormField>
                <FormField id="form-description" label="Deskripsi" :error="errors.description">
                    <Textarea id="form-description" v-model="definition.description" rows="3" />
                </FormField>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="form-type"
                        label="Tipe Formulir"
                        required
                        :error="errors.form_type_id"
                    >
                        <OptionSelect
                            id="form-type"
                            v-model="definition.form_type_id"
                            :options="formTypeOptions"
                        />
                    </FormField>
                    <FormField id="form-status" label="Status" required :error="errors.is_active">
                        <OptionSelect id="form-status" v-model="status" :options="statusOptions" />
                    </FormField>
                </div>
            </SectionCard>
            <AppBanner v-if="otherErrors.length > 0" tone="denial">
                <p v-for="message in otherErrors" :key="message">{{ message }}</p>
            </AppBanner>
            <FormFieldList
                v-if="form === null"
                v-model="editable"
                :field-type-options="fieldTypeOptions"
                :archived="[]"
                :errors="errors"
            />
            <div class="flex flex-wrap justify-end gap-2">
                <Button
                    v-if="form !== null"
                    type="button"
                    variant="outline"
                    :disabled="definition.processing"
                    @click="save(true)"
                    >Lanjut: Kontrol Akses</Button
                >
                <Button type="submit" :disabled="definition.processing">{{
                    form === null ? 'Buat Formulir' : 'Simpan'
                }}</Button>
            </div>
        </form>
        <FormFieldsEditor
            v-if="form !== null"
            :key="String(general.hasDraft)"
            :form-id="form.id"
            :general="general"
            :field-type-options="fieldTypeOptions"
        />
    </div>
</template>
