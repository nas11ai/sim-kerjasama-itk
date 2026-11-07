<script setup lang="ts">
// Frame: ADMIN-08-03
import { Head, Link, router } from '@inertiajs/vue3'
import { Plus } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import FilterToolbar from '@/Components/app/FilterToolbar.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import SchemeTable from '@/Components/Scheme/SchemeTable.vue'
import { Button } from '@/Components/ui/button'
import { useFilters } from '@/composables/useFilters'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { SchemeIndexFilters, SchemeIndexPageProps, SchemeRowData } from '@/types/scheme'
import type { FilterSelectSpec } from '@/types/submission-page'

const props = defineProps<SchemeIndexPageProps>()

const { filters } = useFilters('admin.schemes.index', props.filters)

const filtered = computed(() => Object.values(props.filters).some((value) => value !== ''))

const selects = computed((): FilterSelectSpec<Exclude<keyof SchemeIndexFilters, 'search'>>[] => [
    { key: 'type', label: 'Tipe', allLabel: 'Semua tipe', options: props.typeOptions },
    { key: 'status', label: 'Status', allLabel: 'Semua status', options: props.statusOptions },
])

const target = ref<SchemeRowData | null>(null)
const confirming = ref(false)
const processing = ref(false)

function requestDeactivate(row: SchemeRowData) {
    target.value = row
    confirming.value = true
}

function confirmDeactivate() {
    if (target.value === null) return
    processing.value = true
    router.post(
        route('admin.schemes.deactivate', target.value.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => (confirming.value = false),
            onFinish: () => (processing.value = false),
        }
    )
}

function activate(row: SchemeRowData) {
    router.post(route('admin.schemes.activate', row.id), {}, { preserveScroll: true })
}
</script>

<template>
    <Head title="Skema Penelitian" />
    <AppLayout>
        <PageHeader
            title="Skema Penelitian"
            subtitle="Kelola skema pendanaan beserta aturan dan batasannya."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="route('admin.schemes.create')"
                        ><Plus class="size-4" /> Buat Skema Baru</Link
                    >
                </Button>
            </template>
        </PageHeader>
        <FilterToolbar
            v-model:search="filters.search"
            :selects="selects"
            :values="filters"
            search-placeholder="Cari nama / kode skema..."
            search-label="Cari nama atau kode skema"
            @select="(key, value) => (filters[key] = value)"
        />
        <SchemeTable
            :rows="schemes.data"
            :filtered="filtered"
            @deactivate="requestDeactivate"
            @activate="activate"
        />
        <PaginationBar :page="schemes" />
        <p class="mt-4 text-xs text-muted-foreground">
            Skema yang masih dipakai pengajuan aktif tidak dapat dinonaktifkan. Menonaktifkan skema
            memerlukan konfirmasi.
        </p>
        <!-- BR-SCH-10 — pengajuan lama tetap memakai skemanya -->
        <ConfirmDialog
            v-model:open="confirming"
            title="Nonaktifkan skema?"
            :description="`${target?.name ?? ''} tidak lagi ditawarkan pada pengajuan baru. Pengajuan yang sudah memakainya tidak terpengaruh.`"
            confirm-label="Nonaktifkan Skema"
            destructive
            :processing="processing"
            @confirm="confirmDeactivate"
        />
    </AppLayout>
</template>
