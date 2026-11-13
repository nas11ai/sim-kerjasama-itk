<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed, watch } from 'vue'

import ActionDialog from '@/Components/app/ActionDialog.vue'
import AppBanner from '@/Components/app/AppBanner.vue'
import FormField from '@/Components/app/FormField.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import { Input } from '@/Components/ui/input'
import type { FilterOption } from '@/types/submission-page'
import type { OrganizationUnitData } from '@/types/system-config'

export type OrganizationUnitDialogMode = 'root' | 'child' | 'edit'

// BR-IAM-17 — tambah akar, tambah sub-unit, atau sunting nama/tipe/induk satu unit.
const props = defineProps<{
    mode: OrganizationUnitDialogMode
    unit: OrganizationUnitData | null
    typeOptions: FilterOption[]
    parentOptions: FilterOption[]
}>()
const open = defineModel<boolean>('open', { required: true })

const form = useForm<{ name: string; type: string | null; parent_id: string | null }>({
    name: '',
    type: null,
    parent_id: null,
})

const editing = computed(() => (props.mode === 'edit' ? props.unit : null))
const title = computed(() => {
    if (props.mode === 'root') return 'Tambah Unit Induk'
    return props.mode === 'child' ? `Tambah Sub-unit di ${props.unit?.name ?? ''}` : 'Ubah Unit'
})
// Tipe lama di luar katalog tetap terpilih saat menyunting (W117).
const types = computed(() => {
    const unit = editing.value
    return unit === null || props.typeOptions.some((o) => o.value === unit.type)
        ? props.typeOptions
        : [...props.typeOptions, { value: unit.type, label: unit.typeLabel }]
})
// Keturunannya tetap tampil; backend menolaknya dengan pesan BR-IAM-17.
const parents = computed(() =>
    props.parentOptions.filter((o) => o.value !== String(editing.value?.id))
)

const parentOf = (): string | null => {
    if (props.mode === 'root' || props.unit === null) return null
    const id = props.mode === 'child' ? props.unit.id : props.unit.parentId
    return id === null ? null : String(id)
}

watch(
    open,
    (isOpen) => {
        if (!isOpen) return
        form.name = editing.value?.name ?? ''
        form.type = editing.value?.type ?? null
        form.parent_id = parentOf()
        form.clearErrors()
    },
    { immediate: true }
)

const send = () => {
    const request = form.transform((data) => ({
        ...data,
        parent_id: data.parent_id === null ? null : Number(data.parent_id),
    }))
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) }
    if (editing.value === null) {
        request.post(route('admin.organizations.store'), options)
    } else {
        request.put(route('admin.organizations.update', editing.value.id), options)
    }
}
</script>

<template>
    <ActionDialog
        v-model:open="open"
        :title="title"
        :submit-label="mode === 'edit' ? 'Simpan' : 'Tambah Unit'"
        :processing="form.processing"
        :submit-disabled="form.name.trim() === '' || form.type === null"
        @submit="send"
    >
        <FormField id="organization-unit-name" label="Nama Unit" required :error="form.errors.name">
            <template #default="{ describedBy }">
                <Input
                    id="organization-unit-name"
                    v-model="form.name"
                    :aria-describedby="describedBy"
                    :aria-invalid="form.errors.name ? true : undefined"
                />
            </template>
        </FormField>
        <FormField id="organization-unit-type" label="Tipe" required :error="form.errors.type">
            <template #default="{ describedBy }">
                <OptionSelect
                    id="organization-unit-type"
                    v-model="form.type"
                    :options="types"
                    :described-by="describedBy"
                />
            </template>
        </FormField>
        <FormField
            v-if="mode === 'edit'"
            id="organization-unit-parent"
            label="Induk"
            :error="form.errors.parent_id"
        >
            <template #default="{ describedBy }">
                <OptionSelect
                    id="organization-unit-parent"
                    v-model="form.parent_id"
                    :options="parents"
                    placeholder="Tanpa induk"
                    :described-by="describedBy"
                />
            </template>
        </FormField>
        <!-- BR-IAM-14 — W117: induk nonaktif ditolak backend; pesannya tampil di sini. -->
        <AppBanner v-else-if="form.errors.parent_id" tone="denial">
            {{ form.errors.parent_id }}
        </AppBanner>
    </ActionDialog>
</template>
