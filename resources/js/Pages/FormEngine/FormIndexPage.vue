<script setup lang="ts">
// Frame: ADMIN-08-05
import { Head, Link } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed } from 'vue'

import FilterToolbar from '@/Components/app/FilterToolbar.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import FormDeleteBlockedDialog from '@/Components/FormEngine/FormDeleteBlockedDialog.vue'
import FormTable from '@/Components/FormEngine/FormTable.vue'
import ToggleDeleteDialogs from '@/Components/FormEngine/ToggleDeleteDialogs.vue'
import { Button } from '@/Components/ui/button'
import { useFilters } from '@/composables/useFilters'
import { useFormActions } from '@/composables/useFormActions'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { FormIndexFilters, FormIndexPageProps } from '@/types/form-engine'
import type { FilterSelectSpec } from '@/types/submission-page'

const props = defineProps<FormIndexPageProps>()

const { filters } = useFilters('admin.forms.index', props.filters)

const filtered = computed(() => Object.values(props.filters).some((value) => value !== ''))

const actions = useFormActions()
const { target, toggling, deleting, blocked, processing } = actions

const selects = computed((): FilterSelectSpec<Exclude<keyof FormIndexFilters, 'search'>>[] => [
    { key: 'type', label: 'Tipe', allLabel: 'Semua tipe', options: props.typeOptions },
    { key: 'status', label: 'Status', allLabel: 'Semua status', options: props.statusOptions },
])
</script>

<template>
    <Head title="Pembuat Formulir" />
    <AppLayout>
        <PageHeader
            title="Pembuat Formulir"
            subtitle="Kelola form pengajuan & monev, susunan field dapat diubah sewaktu-waktu."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="route('admin.forms.create')"
                        ><Plus class="size-4" /> Buat Form Baru</Link
                    >
                </Button>
            </template>
        </PageHeader>
        <FilterToolbar
            v-model:search="filters.search"
            :selects="selects"
            :values="filters"
            search-placeholder="Cari nama form..."
            search-label="Cari nama form"
            @select="(key, value) => (filters[key] = value)"
        />
        <FormTable
            :rows="forms.data"
            :filtered="filtered"
            @toggle-active="actions.requestToggle"
            @delete="actions.requestDelete"
        />
        <PaginationBar :page="forms" />
        <!-- W76 / BR-FE-19 — Form yang pernah dipakai dinonaktifkan, bukan dihapus -->
        <p class="mt-4 text-xs text-muted-foreground">
            Form yang pernah dipakai pengajuan tidak dapat dihapus, nonaktifkan agar berhenti
            menerima pengajuan baru. Menonaktifkan form memerlukan konfirmasi.
        </p>
        <FormDeleteBlockedDialog
            v-model:open="blocked"
            :form="target"
            @deactivate="target && actions.requestToggle(target)"
        />
        <ToggleDeleteDialogs
            v-model:toggling="toggling"
            v-model:deleting="deleting"
            :toggle-copy="actions.toggleCopy.value"
            delete-title="Hapus form?"
            :delete-description="`${target?.title ?? ''} beserta field-nya dihapus permanen.`"
            delete-confirm-label="Hapus Form"
            :destructive-toggle="target?.isActive ?? false"
            :processing="processing"
            @confirm-toggle="actions.confirmToggle"
            @confirm-delete="actions.confirmDelete"
        />
    </AppLayout>
</template>
