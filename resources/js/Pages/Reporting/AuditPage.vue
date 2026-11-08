<script setup lang="ts">
// Frame: OPERATOR-07-03
import { Head, Link } from '@inertiajs/vue3'
import { Download } from 'lucide-vue-next'
import { computed, ref } from 'vue'

import AppBanner from '@/Components/app/AppBanner.vue'
import FilterToolbar from '@/Components/app/FilterToolbar.vue'
import PageHeader from '@/Components/app/PageHeader.vue'
import PaginationBar from '@/Components/app/PaginationBar.vue'
import AuditEntryDialog from '@/Components/Reporting/AuditEntryDialog.vue'
import AuditTable from '@/Components/Reporting/AuditTable.vue'
import { Button } from '@/Components/ui/button'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select'
import { useFilters } from '@/composables/useFilters'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { AuditEntryRow, AuditFilters, AuditPageProps } from '@/types/reporting'
import type { FilterSelectSpec } from '@/types/submission-page'

const props = defineProps<AuditPageProps>()

const { filters } = useFilters('reporting.audit.index', props.filters)

// Waktu selalu bernilai (bawaan 7 hari, W155), jadi tidak memakai FilterSelect yang punya "semua".
const selects = computed((): FilterSelectSpec<'action' | 'actor'>[] => [
    {
        key: 'action',
        label: 'Tipe Aksi',
        allLabel: 'Semua tipe aksi',
        options: props.actionOptions,
    },
    { key: 'actor', label: 'Pelaku', allLabel: 'Semua pelaku', options: props.actorOptions },
])
const selectRange = (value: unknown) => {
    if (typeof value === 'string') filters.range = value
}
const setFilter = (key: keyof AuditFilters, value: string) => (filters[key] = value)

const viewed = ref<AuditEntryRow | null>(null)
const dialogOpen = ref(false)
const view = (entry: AuditEntryRow) => {
    viewed.value = entry
    dialogOpen.value = true
}
</script>

<template>
    <Head title="Audit Log" />
    <AppLayout>
        <PageHeader
            title="Audit Log"
            subtitle="Catatan seluruh aktivitas sistem — bersifat permanen dan tidak dapat dihapus."
        >
            <template #actions>
                <Button as-child variant="outline">
                    <Link :href="route('reporting.exports.index', { type: 'audit-log' })">
                        <Download class="size-4" /> Export Audit Log
                    </Link>
                </Button>
            </template>
        </PageHeader>
        <!-- BR-RPT-05 — tanpa keterangan, daftar pendek peneliti terbaca sebagai data hilang. -->
        <AppBanner v-if="!seesEverything" tone="info" class="mb-4">
            Daftar ini hanya memuat aktivitas pada pengajuan Anda dan akun Anda sendiri.
        </AppBanner>
        <FilterToolbar
            v-model:search="filters.search"
            :selects="selects"
            :values="filters"
            search-placeholder="Cari subjek / pelaku..."
            search-label="Cari subjek atau pelaku"
            @select="setFilter"
        >
            <Select :model-value="filters.range" @update:model-value="selectRange">
                <SelectTrigger class="w-full sm:w-36" aria-label="Waktu">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in rangeOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </FilterToolbar>
        <AuditTable :rows="entries.data" :sees-everything="seesEverything" @view="view" />
        <PaginationBar :page="entries" />
        <AuditEntryDialog
            v-model:open="dialogOpen"
            :entry="viewed"
            :sees-everything="seesEverything"
        />
    </AppLayout>
</template>
